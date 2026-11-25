<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use Illuminate\Cache\NullStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

#[Signature('images:privatize {--dry-run : Report planned work without writing files or records} {--retry-failed : Retry failed and missing migration records} {--verify : Verify migrated private files} {--quarantine-orphans : Quarantine unreferenced public product images on the local disk}')]
#[Description('Move legacy public product images to private storage with an auditable, recoverable transition.')]
class PrivatizeProductImages extends Command
{
    private const STATUS_PENDING = 'pending';

    private const STATUS_MIGRATED = 'migrated';

    private const STATUS_VERIFIED = 'verified';

    private const STATUS_FAILED = 'failed';

    private const STATUS_MISSING = 'missing';

    private const QUARANTINE_STATUS_PENDING = 'pending';

    private const QUARANTINE_STATUS_VERIFIED = 'verified';

    private const QUARANTINE_STATUS_SOURCE_DELETE_PENDING = 'source-delete-pending';

    private const QUARANTINE_STATUS_QUARANTINED = 'quarantined';

    private const QUARANTINE_STATUS_FAILED = 'failed';

    private const AUDIT_WRITE_ATTEMPTS = 3;

    private int $migrated = 0;

    private int $verified = 0;

    private int $missing = 0;

    private int $failed = 0;

    private int $skipped = 0;

    private int $quarantined = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->migrated = 0;
        $this->verified = 0;
        $this->missing = 0;
        $this->failed = 0;
        $this->skipped = 0;
        $this->quarantined = 0;

        if ($this->option('dry-run')) {
            $this->dryRun();

            return self::SUCCESS;
        }

        if ($this->option('retry-failed')) {
            $this->retryFailed();
        }

        if ($this->option('verify')) {
            $this->verifyMigrations();
        }

        $this->migrateLegacyImages($this->option('retry-failed'));

        if ($this->option('quarantine-orphans')) {
            $this->quarantineOrphans();
        }

        $this->info("Migrated: {$this->migrated}; verified: {$this->verified}; quarantined: {$this->quarantined}; missing: {$this->missing}; skipped: {$this->skipped}; failed: {$this->failed}.");

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function dryRun(): void
    {
        foreach ($this->legacyImages((bool) $this->option('retry-failed')) as $image) {
            if (Storage::disk('public')->exists($image->path)) {
                $this->line("Migrate image {$image->id} from {$image->path}.");
            } else {
                $this->warn("Missing source for image {$image->id} at {$image->path}.");
            }
        }

        if ($this->option('verify')) {
            foreach ($this->migrationRows([self::STATUS_MIGRATED, self::STATUS_VERIFIED]) as $row) {
                $this->line("Verify image {$row->product_image_id} at {$row->new_path}.");
            }
        }

        if ($this->option('quarantine-orphans')) {
            foreach ($this->orphanPublicFiles() as $path) {
                $this->line("Quarantine unreferenced public file {$path}.");
            }
        }

        $this->info('Dry run complete. No files or records were changed.');
    }

    private function retryFailed(): void
    {
        foreach ($this->migrationRows([self::STATUS_FAILED, self::STATUS_MISSING]) as $row) {
            $image = ProductImage::query()->find($row->product_image_id);

            if ($image !== null && $image->isLegacyPublicPath() && $image->product !== null) {
                $this->migrateImage($image);

                continue;
            }

            $this->verifyRow($row);
        }
    }

    private function verifyMigrations(): void
    {
        foreach ($this->migrationRows([self::STATUS_MIGRATED, self::STATUS_VERIFIED]) as $row) {
            $this->verifyRow($row);
        }
    }

    private function migrateLegacyImages(bool $retryFailed): void
    {
        foreach ($this->legacyImages($retryFailed) as $image) {
            $this->migrateImage($image);
        }
    }

    private function migrateImage(ProductImage $image): void
    {
        $oldPath = $image->path;

        if ($image->product === null) {
            $this->failed++;
            $this->error("Image {$image->id} has no product.");

            return;
        }

        $sellerId = (int) $image->product->seller_id;
        $publicDisk = Storage::disk('public');
        $localDisk = Storage::disk('local');

        if (! $publicDisk->exists($oldPath)) {
            $this->missing++;
            $this->recordAudit($image->id, [
                'product_id' => $image->product_id,
                'seller_id' => $sellerId,
                'old_path' => $oldPath,
                'new_path' => null,
                'status' => self::STATUS_MISSING,
                'checksum' => null,
                'error' => 'Source file is missing from the public disk.',
                'verified_at' => null,
            ]);
            $this->warn("Image {$image->id} source file is missing: {$oldPath}.");

            return;
        }

        $checksum = $this->checksum('public', $oldPath);

        if ($checksum === false) {
            $this->recordFailure($image, $sellerId, 'Unable to calculate a SHA-256 checksum for the source file.');

            return;
        }

        $extension = strtolower(pathinfo($oldPath, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpeg', 'jpg', 'png', 'webp'], true)) {
            $this->recordFailure($image, $sellerId, 'Source file has an unsupported extension.');

            return;
        }

        $newPath = sprintf('seller-products/%d/legacy-%d-%s.%s', $sellerId, $image->id, $checksum, $extension);
        $tempPath = null;
        $createdTarget = false;

        if ($localDisk->exists($newPath)) {
            $targetChecksum = $this->checksum('local', $newPath);

            if ($targetChecksum === false || ! hash_equals($checksum, $targetChecksum)) {
                $this->recordFailure($image, $sellerId, 'Private target path already exists with different contents.', $newPath, $checksum);

                return;
            }
        } else {
            $tempPath = sprintf('seller-products/%d/.legacy-%d-%s.tmp', $sellerId, $image->id, (string) Str::uuid());
            $stream = $publicDisk->readStream($oldPath);

            if (! is_resource($stream)) {
                $this->recordFailure($image, $sellerId, 'Unable to open the source file for reading.', $newPath, $checksum);

                return;
            }

            try {
                abort_unless($localDisk->writeStream($tempPath, $stream), 500);
            } catch (Throwable) {
                fclose($stream);
                $this->recordFailure($image, $sellerId, 'Unable to write the private temporary file.', $newPath, $checksum);

                return;
            }

            fclose($stream);

            $tempChecksum = $this->checksum('local', $tempPath);

            if ($tempChecksum === false || ! hash_equals($checksum, $tempChecksum)) {
                $localDisk->delete($tempPath);
                $this->recordFailure($image, $sellerId, 'Copied file checksum did not match the source checksum.', $newPath, $checksum);

                return;
            }

            if (! $localDisk->move($tempPath, $newPath)) {
                $localDisk->delete($tempPath);
                $this->recordFailure($image, $sellerId, 'Unable to move the verified file into private storage.', $newPath, $checksum);

                return;
            }

            $createdTarget = true;
            $tempPath = null;
        }

        try {
            DB::transaction(function () use ($image, $oldPath, $newPath, $sellerId, $checksum): void {
                $lockedImage = ProductImage::query()->whereKey($image->id)->lockForUpdate()->firstOrFail();

                abort_unless($lockedImage->path === $oldPath, 409, 'Product image path changed during migration.');

                $lockedImage->update(['path' => $newPath]);

                $this->recordAudit($image->id, [
                    'product_id' => $image->product_id,
                    'seller_id' => $sellerId,
                    'old_path' => $oldPath,
                    'new_path' => $newPath,
                    'status' => self::STATUS_MIGRATED,
                    'checksum' => $checksum,
                    'error' => null,
                    'migrated_at' => now(),
                    'verified_at' => null,
                ]);
            });

            $this->migrated++;
            $this->info("Migrated image {$image->id} to {$newPath}.");
        } catch (Throwable $exception) {
            if ($createdTarget && ! ProductImage::query()->where('path', $newPath)->exists()) {
                $localDisk->delete($newPath);
            }

            $this->recordFailure($image, $sellerId, $exception->getMessage(), $newPath, $checksum);
        } finally {
            if ($tempPath !== null && $localDisk->exists($tempPath)) {
                $localDisk->delete($tempPath);
            }
        }
    }

    private function verifyRow(object $row): void
    {
        $image = ProductImage::query()->find($row->product_image_id);

        if ($image === null || $row->new_path === null || $row->checksum === null || $image->path !== $row->new_path) {
            $this->recordAuditForRow($row, [
                'status' => self::STATUS_FAILED,
                'error' => 'Migrated image record no longer matches the private path.',
                'verified_at' => null,
            ]);
            $this->failed++;
            $this->error("Verification failed for image {$row->product_image_id}.");

            return;
        }

        $checksum = $this->checksum('local', $row->new_path);

        if ($checksum === false || ! hash_equals($row->checksum, $checksum)) {
            $this->recordAuditForRow($row, [
                'status' => self::STATUS_FAILED,
                'error' => 'Private file checksum did not match the recorded checksum.',
                'verified_at' => null,
            ]);
            $this->failed++;
            $this->error("Verification failed for image {$row->product_image_id}.");

            return;
        }

        $this->recordAuditForRow($row, [
            'status' => self::STATUS_VERIFIED,
            'error' => null,
            'verified_at' => now(),
        ]);
        $this->verified++;
        $this->info("Verified image {$row->product_image_id}.");
    }

    private function quarantineOrphans(): void
    {
        if (! $this->atomicLocksSupported()) {
            $this->failed++;
            $this->error('Atomic cache locks are required for orphan quarantine.');

            return;
        }

        $this->reconcileQuarantineRows();

        foreach ($this->orphanPublicFiles() as $path) {
            $this->quarantineOrphan($path);
        }
    }

    private function atomicLocksSupported(): bool
    {
        try {
            $store = Cache::getStore();
        } catch (Throwable) {
            return false;
        }

        return $store instanceof LockProvider && ! $store instanceof NullStore;
    }

    /**
     * @param  callable(): bool  $callback
     */
    private function withQuarantineLock(string $path, callable $callback): bool
    {
        try {
            return (bool) Cache::lock('image-quarantine:'.sha1($path), 60)->block(5, $callback);
        } catch (LockTimeoutException) {
            $this->failed++;
            $this->error("Unable to acquire quarantine lock for {$path}.");

            return false;
        } catch (Throwable $exception) {
            $this->failed++;
            $this->error("Unable to acquire quarantine lock for {$path}: {$exception->getMessage()}");

            return false;
        }
    }

    private function reconcileQuarantineRows(): void
    {
        $rows = DB::table('image_orphan_quarantines')
            ->whereNull('deleted_at')
            ->whereIn('status', [
                self::QUARANTINE_STATUS_PENDING,
                self::QUARANTINE_STATUS_VERIFIED,
                self::QUARANTINE_STATUS_SOURCE_DELETE_PENDING,
            ])
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $this->withQuarantineLock((string) $row->original_path, fn (): bool => $this->reconcileQuarantineRow($row));
        }
    }

    private function reconcileQuarantineRow(object $row): bool
    {
        $privateChecksumValid = $this->checksumMatches('local', (string) $row->quarantine_path, $row->checksum);
        $publicExists = $this->publicSourceExists((string) $row->original_path);

        if (! $privateChecksumValid && $row->status === self::QUARANTINE_STATUS_PENDING && $publicExists) {
            if ($this->writeQuarantineFileExclusively((string) $row->original_path, (string) $row->quarantine_path)
                && $this->checksumMatches('local', (string) $row->quarantine_path, $row->checksum)) {
                $privateChecksumValid = true;
            }
        }

        if (! $privateChecksumValid) {
            $error = "Quarantine recovery is uncertain for {$row->original_path}: the private copy is missing or its checksum cannot be verified.";

            if ($row->status === self::QUARANTINE_STATUS_SOURCE_DELETE_PENDING) {
                $this->recordQuarantineUncertainty((int) $row->id, $error);
            } else {
                $this->recordQuarantineFailure((int) $row->id, $error);
            }

            return true;
        }

        if ($row->status === self::QUARANTINE_STATUS_PENDING) {
            if (! $this->updateQuarantineRow((int) $row->id, ['status' => self::QUARANTINE_STATUS_VERIFIED, 'error' => null], self::QUARANTINE_STATUS_PENDING)) {
                $this->reportQuarantineAuditFailure("Unable to record verified quarantine for orphan file {$row->original_path}.");

                return true;
            }

            $row->status = self::QUARANTINE_STATUS_VERIFIED;
        }

        $this->completeQuarantineFromRow($row);

        return true;
    }

    private function quarantineOrphan(string $path): bool
    {
        if (! $this->isOrphanPublicFile($path)) {
            return true;
        }

        $checksum = $this->checksum('public', $path);

        if ($checksum === false) {
            $this->failed++;
            $this->error("Unable to checksum orphan file {$path}.");

            return true;
        }

        return $this->withQuarantineLock($path, function () use ($path, $checksum): bool {
            if (! $this->isOrphanPublicFile($path)) {
                return true;
            }

            $quarantinePath = $this->generateQuarantinePath($path);

            if ($quarantinePath === false) {
                $this->failed++;
                $this->error("Unable to choose a quarantine path for orphan file {$path}.");

                return true;
            }

            try {
                $bytes = Storage::disk('public')->size($path);
            } catch (Throwable) {
                $bytes = null;
            }

            $quarantineId = $this->insertQuarantineRow([
                'original_path' => $path,
                'quarantine_path' => $quarantinePath,
                'status' => self::QUARANTINE_STATUS_PENDING,
                'checksum' => $checksum,
                'error' => null,
                'bytes' => $bytes,
                'reason' => 'unreferenced_public_file',
                'quarantined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($quarantineId === false) {
                $this->failed++;
                $this->error("Unable to record quarantine for orphan file {$path}.");

                return true;
            }

            if (! $this->writeQuarantineFileExclusively($path, $quarantinePath)) {
                $this->recordQuarantineFailure($quarantineId, "Unable to quarantine orphan file {$path}.");

                return true;
            }

            if (! $this->checksumMatches('local', $quarantinePath, $checksum)) {
                Storage::disk('local')->delete($quarantinePath);
                $this->recordQuarantineFailure($quarantineId, "Quarantine checksum failed for orphan file {$path}.");

                return true;
            }

            if (! $this->updateQuarantineRow($quarantineId, ['status' => self::QUARANTINE_STATUS_VERIFIED, 'error' => null], self::QUARANTINE_STATUS_PENDING)) {
                $this->reportQuarantineAuditFailure("Unable to record verified quarantine for orphan file {$path}.");

                return true;
            }

            $this->completeQuarantineFromRow((object) [
                'id' => $quarantineId,
                'original_path' => $path,
                'quarantine_path' => $quarantinePath,
                'checksum' => $checksum,
                'source_delete_intent_at' => null,
                'status' => self::QUARANTINE_STATUS_VERIFIED,
            ]);

            return true;
        });
    }

    private function completeQuarantineFromRow(object $row): void
    {
        if (! $this->checksumMatches('local', (string) $row->quarantine_path, $row->checksum)) {
            if ($row->status === self::QUARANTINE_STATUS_SOURCE_DELETE_PENDING) {
                $this->recordQuarantineUncertainty((int) $row->id, "Quarantine recovery is uncertain for {$row->original_path}: the private copy checksum cannot be verified.");
            } else {
                $this->recordQuarantineFailure((int) $row->id, "Quarantine checksum failed for orphan file {$row->original_path}.");
            }

            return;
        }

        if (($row->source_delete_intent_at ?? null) === null) {
            if (! $this->updateQuarantineRow((int) $row->id, [
                'status' => self::QUARANTINE_STATUS_SOURCE_DELETE_PENDING,
                'source_delete_intent_at' => now(),
                'error' => null,
            ], self::QUARANTINE_STATUS_VERIFIED)) {
                $this->reportQuarantineAuditFailure("Unable to record source deletion intent for orphan file {$row->original_path}.");

                return;
            }

            $row->status = self::QUARANTINE_STATUS_SOURCE_DELETE_PENDING;
        }

        $result = $this->removePublicSource((string) $row->original_path);

        if (! $result['removed']) {
            $this->recordQuarantineFailure((int) $row->id, (string) $result['error'], self::QUARANTINE_STATUS_SOURCE_DELETE_PENDING);

            return;
        }

        if (! $this->updateQuarantineRow((int) $row->id, ['status' => self::QUARANTINE_STATUS_QUARANTINED, 'error' => null], self::QUARANTINE_STATUS_SOURCE_DELETE_PENDING)) {
            $this->reportQuarantineAuditFailure("Unable to record successful quarantine for orphan file {$row->original_path}.");

            return;
        }

        $this->quarantined++;
        $this->info("Quarantined orphan file {$row->original_path} to {$row->quarantine_path}.");
    }

    /**
     * @return array{removed: bool, error: ?string}
     */
    private function removePublicSource(string $path): array
    {
        try {
            $deleted = Storage::disk('public')->delete($path);
            $stillExists = Storage::disk('public')->exists($path);
        } catch (Throwable $exception) {
            try {
                $stillExists = Storage::disk('public')->exists($path);
            } catch (Throwable) {
                $stillExists = true;
            }

            if (! $stillExists) {
                return ['removed' => true, 'error' => null];
            }

            return ['removed' => false, 'error' => "Unable to delete the public source file {$path}: {$exception->getMessage()}"];
        }

        if (! $stillExists || $deleted) {
            return ['removed' => true, 'error' => null];
        }

        return ['removed' => false, 'error' => "Unable to delete the public source file {$path}."];
    }

    private function publicSourceExists(string $path): bool
    {
        try {
            return Storage::disk('public')->exists($path);
        } catch (Throwable) {
            return true;
        }
    }

    private function isOrphanPublicFile(string $path): bool
    {
        if (! $this->publicSourceExists($path) || ProductImage::query()->where('path', $path)->exists()) {
            return false;
        }

        return ! DB::table('image_orphan_quarantines')
            ->where('original_path', $path)
            ->whereNull('deleted_at')
            ->where(fn ($query) => $query->where('status', '!=', self::QUARANTINE_STATUS_FAILED)->orWhereNull('status'))
            ->exists();
    }

    private function generateQuarantinePath(string $path): string|false
    {
        $localDisk = Storage::disk('local');

        try {
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $candidate = 'quarantine/products/'.now()->format('Ymd').'/'.(string) Str::uuid().'-'.basename($path);

                if (! $localDisk->exists($candidate)) {
                    return $candidate;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    private function writeQuarantineFileExclusively(string $sourcePath, string $quarantinePath): bool
    {
        $absolutePath = null;
        $sourceStream = null;
        $destination = null;
        $created = false;
        $completed = false;

        try {
            $absolutePath = Storage::disk('local')->path($quarantinePath);
            (new Filesystem)->ensureDirectoryExists(dirname($absolutePath));
            $sourceStream = Storage::disk('public')->readStream($sourcePath);

            if (! is_resource($sourceStream)) {
                return false;
            }

            $destination = @fopen($absolutePath, 'xb');

            if ($destination === false) {
                return false;
            }

            $created = true;

            while (! feof($sourceStream)) {
                $chunk = fread($sourceStream, 8192);

                if ($chunk === false || ($chunk !== '' && fwrite($destination, $chunk) === false)) {
                    throw new RuntimeException('Unable to write the quarantine file.');
                }
            }

            $completed = true;

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            if (is_resource($sourceStream)) {
                fclose($sourceStream);
            }

            if (is_resource($destination)) {
                fclose($destination);
            }

            if ($created && ! $completed && $absolutePath !== null && file_exists($absolutePath)) {
                @unlink($absolutePath);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertQuarantineRow(array $attributes): int|false
    {
        for ($attempt = 1; $attempt <= self::AUDIT_WRITE_ATTEMPTS; $attempt++) {
            try {
                return (int) DB::table('image_orphan_quarantines')->insertGetId($attributes);
            } catch (Throwable) {
                if ($attempt === self::AUDIT_WRITE_ATTEMPTS) {
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateQuarantineRow(int $id, array $attributes, ?string $expectedStatus = null): bool
    {
        for ($attempt = 1; $attempt <= self::AUDIT_WRITE_ATTEMPTS; $attempt++) {
            try {
                $query = DB::table('image_orphan_quarantines')->where('id', $id);

                if ($expectedStatus !== null) {
                    $query->where('status', $expectedStatus);
                }

                $updated = $query->update(array_merge($attributes, ['updated_at' => now()]));

                if ($updated === 1) {
                    return true;
                }

                if ($updated === 0) {
                    return false;
                }
            } catch (Throwable) {
                if ($attempt === self::AUDIT_WRITE_ATTEMPTS) {
                    return false;
                }
            }
        }

        return false;
    }

    private function recordQuarantineFailure(int $id, string $error, ?string $expectedStatus = null): void
    {
        $this->updateQuarantineRow($id, [
            'status' => self::QUARANTINE_STATUS_FAILED,
            'error' => $error,
        ], $expectedStatus);

        $this->failed++;
        $this->error($error);
    }

    private function recordQuarantineUncertainty(int $id, string $error): void
    {
        $this->updateQuarantineRow($id, ['error' => $error]);

        $this->failed++;
        $this->error($error);
    }

    private function reportQuarantineAuditFailure(string $message): void
    {
        $this->failed++;
        $this->error($message);
    }

    private function checksumMatches(string $disk, string $path, mixed $checksum): bool
    {
        if (! is_string($checksum) || preg_match('/^[a-f0-9]{64}$/i', $checksum) !== 1) {
            return false;
        }

        $actualChecksum = $this->checksum($disk, $path);

        return $actualChecksum !== false && hash_equals(strtolower($checksum), strtolower($actualChecksum));
    }

    private function legacyImages(bool $retryFailed): Collection
    {
        $statuses = $this->auditStatuses();

        return ProductImage::query()
            ->where('path', 'like', 'products/%')
            ->with('product')
            ->get()
            ->filter(function (ProductImage $image) use ($statuses, $retryFailed): bool {
                if (! $image->isLegacyPublicPath() || $image->product === null) {
                    return false;
                }

                $status = $statuses[$image->id] ?? null;

                if (in_array($status, [self::STATUS_MIGRATED, self::STATUS_VERIFIED], true)) {
                    return false;
                }

                return $retryFailed
                    ? in_array($status, [self::STATUS_FAILED, self::STATUS_MISSING], true)
                    : ($status === null || $status === self::STATUS_PENDING);
            });
    }

    private function orphanPublicFiles(): Collection
    {
        $referencedPaths = ProductImage::query()->pluck('path')->filter()->all();
        $activeQuarantines = DB::table('image_orphan_quarantines')
            ->whereNull('deleted_at')
            ->where(fn ($query) => $query->where('status', '!=', self::QUARANTINE_STATUS_FAILED)->orWhereNull('status'))
            ->pluck('original_path')
            ->all();

        return collect(Storage::disk('public')->files('products'))
            ->reject(fn (string $path): bool => in_array($path, $referencedPaths, true) || in_array($path, $activeQuarantines, true))
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function auditStatuses(): array
    {
        /** @var array<int, string> $statuses */
        $statuses = DB::table('image_storage_migrations')
            ->get()
            ->keyBy('product_image_id')
            ->map(fn (object $row): string => (string) $row->status)
            ->all();

        return $statuses;
    }

    /**
     * @return Collection<int, object>
     */
    private function migrationRows(array $statuses): Collection
    {
        return DB::table('image_storage_migrations')
            ->whereIn('status', $statuses)
            ->orderBy('product_image_id')
            ->get();
    }

    private function recordFailure(ProductImage $image, int $sellerId, string $error, ?string $newPath = null, ?string $checksum = null): void
    {
        $this->failed++;
        $this->recordAudit($image->id, [
            'product_id' => $image->product_id,
            'seller_id' => $sellerId,
            'old_path' => $image->path,
            'new_path' => $newPath,
            'status' => self::STATUS_FAILED,
            'checksum' => $checksum,
            'error' => $error,
            'verified_at' => null,
        ]);
        $this->error("Image {$image->id}: {$error}");
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recordAudit(int $imageId, array $attributes): void
    {
        $existing = DB::table('image_storage_migrations')->where('product_image_id', $imageId)->first();
        $data = array_merge([
            'product_image_id' => $imageId,
            'attempts' => ((int) ($existing->attempts ?? 0)) + 1,
            'last_attempt_at' => now(),
            'updated_at' => now(),
        ], $attributes);

        if ($existing === null) {
            $data['created_at'] = now();
        }

        DB::table('image_storage_migrations')->updateOrInsert(
            ['product_image_id' => $imageId],
            $data
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recordAuditForRow(object $row, array $attributes): void
    {
        DB::table('image_storage_migrations')
            ->where('id', $row->id)
            ->update(array_merge([
                'attempts' => ((int) $row->attempts) + 1,
                'last_attempt_at' => now(),
                'updated_at' => now(),
            ], $attributes));
    }

    private function checksum(string $disk, string $path): string|false
    {
        try {
            $checksum = Storage::disk($disk)->checksum($path, ['checksum_algo' => 'sha256']);
        } catch (Throwable) {
            return false;
        }

        return is_string($checksum) && preg_match('/^[a-f0-9]{64}$/i', $checksum) === 1 ? $checksum : false;
    }
}
