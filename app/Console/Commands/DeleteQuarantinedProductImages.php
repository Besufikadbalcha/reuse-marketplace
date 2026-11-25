<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('images:delete-quarantined {--days=30 : Only delete files quarantined at least this many days ago} {--dry-run : Report planned deletions without deleting files or records} {--confirm : Required to permanently delete quarantined files}')]
#[Description('Permanently delete old quarantined product image files after explicit confirmation.')]
class DeleteQuarantinedProductImages extends Command
{
    private const AUDIT_WRITE_ATTEMPTS = 3;

    private int $deleted = 0;

    private int $skipped = 0;

    private int $failed = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->deleted = 0;
        $this->skipped = 0;
        $this->failed = 0;

        $days = (int) $this->option('days');

        if ($days < 0) {
            $this->error('The --days option must be zero or greater.');

            return self::FAILURE;
        }

        if (! $this->option('dry-run') && ! $this->option('confirm')) {
            $this->error('Pass --confirm to permanently delete quarantined files.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $recoveredIds = $dryRun ? [] : $this->recoverPendingDeletions();
        $cutoff = now()->subDays($days);
        $query = DB::table('image_orphan_quarantines')
            ->whereNull('deleted_at')
            ->where('quarantined_at', '<=', $cutoff)
            ->orderBy('id');

        if (! $dryRun) {
            $query->whereNull('delete_intent_at');

            if ($recoveredIds !== []) {
                $query->whereNotIn('id', $recoveredIds);
            }
        }

        $rows = $query->get();

        foreach ($rows as $row) {
            if ($dryRun) {
                $this->line("Delete quarantined file {$row->quarantine_path} from image {$row->original_path}.");
                $this->skipped++;

                continue;
            }

            $storedChecksum = $row->checksum;

            if ($storedChecksum === null || $storedChecksum === '') {
                $this->recordFailure($row, "Missing stored checksum for quarantined file {$row->quarantine_path}. Nothing was deleted.");

                continue;
            }

            if (! is_string($storedChecksum) || preg_match('/^[a-f0-9]{64}$/i', $storedChecksum) !== 1) {
                $this->recordFailure($row, "Invalid stored checksum for quarantined file {$row->quarantine_path}. Nothing was deleted.");

                continue;
            }

            $checksum = $this->checksum('local', $row->quarantine_path);

            if ($checksum === false) {
                $this->recordFailure($row, "Unable to checksum quarantined file {$row->quarantine_path}. Nothing was deleted.");

                continue;
            }

            if (! hash_equals(strtolower($storedChecksum), strtolower($checksum))) {
                $this->recordFailure($row, "Checksum mismatch for quarantined file {$row->quarantine_path}. Nothing was deleted.");

                continue;
            }

            if (! $this->setDeleteIntent($row, strtolower($storedChecksum))) {
                $this->recordFailure($row, "Unable to record deletion intent for quarantined file {$row->quarantine_path}. Nothing was deleted.");

                continue;
            }

            $row->delete_intent_checksum = strtolower($storedChecksum);
            $result = $this->deleteQuarantinedFile((string) $row->quarantine_path);

            if (! $result['removed']) {
                $reason = (string) $result['error'];

                if (! $this->clearDeleteIntent($row)) {
                    $reason .= ' The deletion intent was preserved for recovery.';
                }

                $this->recordFailure($row, $reason);

                continue;
            }

            if (! $this->finalizeDeleted($row)) {
                $this->recordFailure($row, "Unable to record deletion for quarantined file {$row->quarantine_path}. The deletion intent was preserved for recovery.");

                continue;
            }

            $this->deleted++;
            $this->info("Deleted quarantined file {$row->quarantine_path}.");
        }

        $this->info("Deleted: {$this->deleted}; skipped: {$this->skipped}; failed: {$this->failed}.");

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, int>
     */
    private function recoverPendingDeletions(): array
    {
        $rows = DB::table('image_orphan_quarantines')
            ->whereNull('deleted_at')
            ->whereNotNull('delete_intent_at')
            ->orderBy('id')
            ->get();

        $processedIds = [];

        foreach ($rows as $row) {
            $processedIds[] = (int) $row->id;
            $this->recoverPendingDeletion($row);
        }

        return $processedIds;
    }

    private function recoverPendingDeletion(object $row): void
    {
        if (! Storage::disk('local')->exists((string) $row->quarantine_path)) {
            if ($this->finalizeDeleted($row)) {
                $this->deleted++;
                $this->info("Finalized deletion for {$row->quarantine_path}.");

                return;
            }

            $this->recordFailure($row, "Unable to finalize deletion for {$row->quarantine_path}. The deletion intent was preserved for recovery.");

            return;
        }

        $intentChecksum = $row->delete_intent_checksum;

        if (! is_string($intentChecksum) || preg_match('/^[a-f0-9]{64}$/i', $intentChecksum) !== 1) {
            $this->recordFailure($row, "Invalid deletion intent checksum for {$row->quarantine_path}. Nothing was deleted.");

            return;
        }

        $checksum = $this->checksum('local', (string) $row->quarantine_path);

        if ($checksum === false) {
            $this->recordFailure($row, "Unable to checksum quarantined file {$row->quarantine_path} during deletion recovery. Nothing was deleted.");

            return;
        }

        if (! hash_equals(strtolower($intentChecksum), strtolower($checksum))) {
            $this->recordFailure($row, "Checksum mismatch for quarantined file {$row->quarantine_path} during deletion recovery. Nothing was deleted.");

            return;
        }

        $result = $this->deleteQuarantinedFile((string) $row->quarantine_path);

        if (! $result['removed']) {
            $reason = (string) $result['error'];

            if (! $this->clearDeleteIntent($row)) {
                $reason .= ' The deletion intent was preserved for recovery.';
            }

            $this->recordFailure($row, $reason);

            return;
        }

        if (! $this->finalizeDeleted($row)) {
            $this->recordFailure($row, "Unable to record deletion for quarantined file {$row->quarantine_path}. The deletion intent was preserved for recovery.");

            return;
        }

        $this->deleted++;
        $this->info("Deleted quarantined file {$row->quarantine_path} during recovery.");
    }

    /**
     * @return array{removed: bool, error: ?string}
     */
    private function deleteQuarantinedFile(string $path): array
    {
        try {
            $deleted = Storage::disk('local')->delete($path);
            $stillExists = Storage::disk('local')->exists($path);
        } catch (Throwable $exception) {
            try {
                $stillExists = Storage::disk('local')->exists($path);
            } catch (Throwable) {
                $stillExists = true;
            }

            if (! $stillExists) {
                return ['removed' => true, 'error' => null];
            }

            return ['removed' => false, 'error' => "Unable to delete quarantined file {$path}: {$exception->getMessage()}"];
        }

        if (! $stillExists || $deleted) {
            return ['removed' => true, 'error' => null];
        }

        return ['removed' => false, 'error' => "Unable to delete quarantined file {$path}."];
    }

    private function setDeleteIntent(object $row, string $checksum): bool
    {
        return $this->updateRow((int) $row->id, [
            'delete_intent_at' => now(),
            'delete_intent_checksum' => $checksum,
            'error' => null,
        ], function (Builder $query): void {
            $query->whereNull('delete_intent_at');
        });
    }

    private function clearDeleteIntent(object $row): bool
    {
        return $this->updateRow((int) $row->id, [
            'delete_intent_at' => null,
            'delete_intent_checksum' => null,
        ], function (Builder $query): void {
            $query->whereNull('deleted_at')->whereNotNull('delete_intent_at');
        });
    }

    private function finalizeDeleted(object $row): bool
    {
        return $this->updateRow((int) $row->id, [
            'deleted_at' => now(),
            'delete_intent_at' => null,
            'delete_intent_checksum' => null,
            'error' => null,
        ], function (Builder $query) use ($row): void {
            $query->whereNull('deleted_at')->where('delete_intent_checksum', $row->delete_intent_checksum);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  callable(Builder): void  $constraints
     */
    private function updateRow(int $id, array $attributes, callable $constraints): bool
    {
        for ($attempt = 1; $attempt <= self::AUDIT_WRITE_ATTEMPTS; $attempt++) {
            try {
                $query = DB::table('image_orphan_quarantines')->where('id', $id);
                $constraints($query);

                $updated = $query->update(array_merge($attributes, ['updated_at' => now()]));

                if ($updated === 1) {
                    return true;
                }

                return false;
            } catch (Throwable) {
                if ($attempt === self::AUDIT_WRITE_ATTEMPTS) {
                    return false;
                }
            }
        }

        return false;
    }

    private function recordFailure(object $row, string $reason): void
    {
        $this->updateRow((int) $row->id, ['error' => $reason], function (Builder $query): void {});

        $this->failed++;
        $this->error($reason);
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
