<?php

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Cache\NullStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @return array{0: Product, 1: User, 2: ProductImage, 3: string, 4: string}
 */
function legacyMigrationTestImage(string $contents = 'legacy-image-contents'): array
{
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->approved()->create();
    $oldPath = 'products/'.fake()->uuid().'.jpg';
    $image = ProductImage::factory()->for($product, 'product')->create([
        'path' => $oldPath,
        'sort_order' => 3,
    ]);
    Storage::disk('public')->put($oldPath, $contents);

    return [$product, $seller, $image, $oldPath, $contents];
}

function legacyMigrationTestChecksum(string $disk, string $path): string
{
    return (string) Storage::disk($disk)->checksum($path, ['checksum_algo' => 'sha256']);
}

test('dry run reports planned work without changing files or records', function () {
    Storage::fake('local');
    Storage::fake('public');

    [, , $image, $oldPath] = legacyMigrationTestImage();

    $this->artisan('images:privatize', ['--dry-run' => true])
        ->expectsOutputToContain("Migrate image {$image->id} from {$oldPath}.")
        ->expectsOutputToContain('Dry run complete. No files or records were changed.')
        ->assertSuccessful();

    expect($image->fresh()->path)->toBe($oldPath)
        ->and(DB::table('image_storage_migrations')->exists())->toBeFalse();

    Storage::disk('public')->assertExists($oldPath);
    Storage::disk('local')->assertEmpty();
});

test('a legacy image is copied to private storage and audited without changing its record identity', function () {
    Storage::fake('local');
    Storage::fake('public');

    [$product, $seller, $image, $oldPath, $contents] = legacyMigrationTestImage();
    $imageId = $image->id;
    $sortOrder = $image->sort_order;

    $this->artisan('images:privatize')->assertSuccessful();

    $image->refresh();
    $audit = DB::table('image_storage_migrations')->where('product_image_id', $imageId)->first();
    $checksum = legacyMigrationTestChecksum('public', $oldPath);

    expect($image->id)->toBe($imageId)
        ->and($image->product_id)->toBe($product->id)
        ->and($image->sort_order)->toBe($sortOrder)
        ->and(str_starts_with($image->path, 'seller-products/'.$seller->id.'/legacy-'.$imageId.'-'))->toBeTrue()
        ->and($image->path)->toBe($audit->new_path)
        ->and($audit->old_path)->toBe($oldPath)
        ->and($audit->status)->toBe('migrated')
        ->and($audit->checksum)->toBe($checksum)
        ->and($audit->migrated_at)->not->toBeNull();

    Storage::disk('public')->assertExists($oldPath);
    Storage::disk('local')->assertExists($image->path);
    expect(legacyMigrationTestChecksum('local', $image->path))->toBe($checksum);

    $response = $this->get(route('product-images.show', [$product, $image]));

    $response->assertOk();
    expect($response->baseResponse->getFile()->getPathname())->toBe(Storage::disk('local')->path($image->path));
});

test('a missing source is recorded and can be retried after the file is recovered', function () {
    Storage::fake('local');
    Storage::fake('public');

    [$product, $seller, $image, $oldPath, $contents] = legacyMigrationTestImage();
    Storage::disk('public')->delete($oldPath);

    $this->artisan('images:privatize')->assertSuccessful();

    $audit = DB::table('image_storage_migrations')->where('product_image_id', $image->id)->first();

    expect($image->fresh()->path)->toBe($oldPath)
        ->and($audit->status)->toBe('missing')
        ->and($audit->error)->toContain('missing');

    Storage::disk('public')->put($oldPath, $contents);

    $this->artisan('images:privatize', ['--retry-failed' => true])->assertSuccessful();

    $image->refresh();
    $audit = DB::table('image_storage_migrations')->where('product_image_id', $image->id)->first();

    expect(str_starts_with($image->path, 'seller-products/'.$seller->id.'/legacy-'.$image->id.'-'))->toBeTrue()
        ->and($image->path)->toBe($audit->new_path)
        ->and($audit->status)->toBe('migrated');

    Storage::disk('local')->assertExists($image->path);
});

test('verification detects a changed private file and records a failure', function () {
    Storage::fake('local');
    Storage::fake('public');

    [, , $image, $oldPath] = legacyMigrationTestImage();

    $this->artisan('images:privatize')->assertSuccessful();

    $newPath = $image->fresh()->path;
    Storage::disk('local')->put($newPath, 'tampered-private-contents');

    $this->artisan('images:privatize', ['--verify' => true])->assertFailed();

    $audit = DB::table('image_storage_migrations')->where('product_image_id', $image->id)->first();

    expect($audit->status)->toBe('failed')
        ->and($audit->error)->toContain('checksum');
});

test('unreferenced public files are quarantined only when requested', function () {
    Storage::fake('local');
    Storage::fake('public');

    $orphanPath = 'products/orphan.jpg';
    $contents = 'unreferenced-public-contents';
    Storage::disk('public')->put($orphanPath, $contents);
    $checksum = legacyMigrationTestChecksum('public', $orphanPath);

    $this->artisan('images:privatize', [
        '--dry-run' => true,
        '--quarantine-orphans' => true,
    ])
        ->expectsOutputToContain("Quarantine unreferenced public file {$orphanPath}.")
        ->assertSuccessful();

    $this->artisan('images:privatize')->assertSuccessful();

    expect(DB::table('image_orphan_quarantines')->exists())->toBeFalse();
    Storage::disk('public')->assertExists($orphanPath);

    $this->artisan('images:privatize', ['--quarantine-orphans' => true])->assertSuccessful();

    $audit = DB::table('image_orphan_quarantines')->first();

    expect($audit->original_path)->toBe($orphanPath)
        ->and(str_starts_with($audit->quarantine_path, 'quarantine/products/'))->toBeTrue()
        ->and($audit->checksum)->toBe($checksum)
        ->and($audit->bytes)->toBe(strlen($contents))
        ->and($audit->deleted_at)->toBeNull();

    Storage::disk('public')->assertMissing($orphanPath);
    Storage::disk('local')->assertExists($audit->quarantine_path);
    expect(legacyMigrationTestChecksum('local', $audit->quarantine_path))->toBe($checksum);
});

test('quarantining preserves the public source when its deletion fails', function () {
    Storage::fake('local');
    $publicDisk = Storage::fake('public');

    $orphanPath = 'products/delete-failure.jpg';
    $contents = 'unreferenced-public-contents';
    $publicDisk->put($orphanPath, $contents);

    $guardedDisk = Mockery::mock($publicDisk)->makePartial();
    $guardedDisk->shouldReceive('delete')->with($orphanPath)->once()->andReturn(false);
    Storage::set('public', $guardedDisk);

    $this->artisan('images:privatize', ['--quarantine-orphans' => true])
        ->expectsOutputToContain("Unable to delete the public source file {$orphanPath}.")
        ->expectsOutputToContain('quarantined: 0;')
        ->assertFailed();

    $quarantine = DB::table('image_orphan_quarantines')->first();

    expect($quarantine->status)->toBe('failed')
        ->and($quarantine->error)->toContain("Unable to delete the public source file {$orphanPath}.")
        ->and($quarantine->deleted_at)->toBeNull();

    Storage::disk('public')->assertExists($orphanPath);
    Storage::disk('local')->assertExists($quarantine->quarantine_path);
    expect(Storage::disk('local')->get($quarantine->quarantine_path))->toBe($contents);
});

test('quarantining fails closed when atomic cache locks are unavailable', function () {
    Storage::fake('local');
    Storage::fake('public');

    $orphanPath = 'products/no-lock.jpg';
    Storage::disk('public')->put($orphanPath, 'unreferenced-public-contents');
    Cache::shouldReceive('getStore')->once()->andReturn(new NullStore);

    $this->artisan('images:privatize', ['--quarantine-orphans' => true])
        ->expectsOutputToContain('Atomic cache locks are required for orphan quarantine.')
        ->assertFailed();

    Storage::disk('public')->assertExists($orphanPath);
    Storage::disk('local')->assertEmpty();
    expect(DB::table('image_orphan_quarantines')->exists())->toBeFalse();
});

test('quarantining preserves the public source when the initial audit write fails', function () {
    Storage::fake('local');
    Storage::fake('public');

    $orphanPath = 'products/audit-insert-failure.jpg';
    Storage::disk('public')->put($orphanPath, 'unreferenced-public-contents');
    DB::unprepared("CREATE TRIGGER quarantine_insert_failure BEFORE INSERT ON image_orphan_quarantines BEGIN SELECT RAISE(ABORT, 'blocked'); END");

    $this->artisan('images:privatize', ['--quarantine-orphans' => true])
        ->expectsOutputToContain('Unable to record quarantine for orphan file')
        ->assertFailed();

    Storage::disk('public')->assertExists($orphanPath);
    Storage::disk('local')->assertEmpty();
    expect(DB::table('image_orphan_quarantines')->exists())->toBeFalse();
});

test('a failed final audit write leaves a recoverable source deletion intent', function () {
    Storage::fake('local');
    Storage::fake('public');

    $orphanPath = 'products/audit-finalize-failure.jpg';
    $contents = 'unreferenced-public-contents';
    Storage::disk('public')->put($orphanPath, $contents);
    DB::unprepared("CREATE TRIGGER quarantine_finalize_failure BEFORE UPDATE OF status ON image_orphan_quarantines WHEN NEW.status = 'quarantined' BEGIN SELECT RAISE(ABORT, 'blocked'); END");

    $this->artisan('images:privatize', ['--quarantine-orphans' => true])
        ->expectsOutputToContain('Unable to record successful quarantine')
        ->assertFailed();

    $quarantine = DB::table('image_orphan_quarantines')->first();

    expect($quarantine->status)->toBe('source-delete-pending')
        ->and($quarantine->source_delete_intent_at)->not->toBeNull()
        ->and($quarantine->deleted_at)->toBeNull();

    Storage::disk('public')->assertMissing($orphanPath);
    Storage::disk('local')->assertExists($quarantine->quarantine_path);

    DB::unprepared('DROP TRIGGER quarantine_finalize_failure');

    $this->artisan('images:privatize', ['--quarantine-orphans' => true])->assertSuccessful();

    $quarantine = DB::table('image_orphan_quarantines')->first();

    expect(DB::table('image_orphan_quarantines')->count())->toBe(1)
        ->and($quarantine->status)->toBe('quarantined')
        ->and($quarantine->error)->toBeNull();

    Storage::disk('local')->assertExists($quarantine->quarantine_path);
    expect(Storage::disk('local')->get($quarantine->quarantine_path))->toBe($contents);
});

test('quarantining never overwrites an existing private file', function () {
    Storage::fake('local');
    Storage::fake('public');

    $orphanPath = 'products/orphan.jpg';
    $contents = 'unreferenced-public-contents';
    Storage::disk('public')->put($orphanPath, $contents);

    $date = now()->format('Ymd');
    $occupiedPath = "quarantine/products/{$date}/taken-uuid-orphan.jpg";
    $replacementPath = "quarantine/products/{$date}/replacement-uuid-orphan.jpg";
    Storage::disk('local')->put($occupiedPath, 'pre-existing-private-contents');
    Str::createUuidsUsingSequence(['taken-uuid', 'replacement-uuid']);

    try {
        $this->artisan('images:privatize', ['--quarantine-orphans' => true])->assertSuccessful();
    } finally {
        Str::createUuidsNormally();
    }

    $quarantine = DB::table('image_orphan_quarantines')->first();

    expect($quarantine->quarantine_path)->toBe($replacementPath)
        ->and($quarantine->status)->toBe('quarantined');

    Storage::disk('public')->assertMissing($orphanPath);
    Storage::disk('local')->assertExists($occupiedPath);
    expect(Storage::disk('local')->get($occupiedPath))->toBe('pre-existing-private-contents');
    Storage::disk('local')->assertExists($replacementPath);
    expect(Storage::disk('local')->get($replacementPath))->toBe($contents);
});

test('pending quarantine records are recovered without losing the public source twice', function () {
    Storage::fake('local');
    Storage::fake('public');

    $orphanPath = 'products/pending.jpg';
    $contents = 'unreferenced-public-contents';
    Storage::disk('public')->put($orphanPath, $contents);
    $quarantinePath = 'quarantine/products/pending.jpg';
    $checksum = legacyMigrationTestChecksum('public', $orphanPath);

    DB::table('image_orphan_quarantines')->insert([
        'original_path' => $orphanPath,
        'quarantine_path' => $quarantinePath,
        'status' => 'pending',
        'checksum' => $checksum,
        'bytes' => strlen($contents),
        'reason' => 'unreferenced_public_file',
        'quarantined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('images:privatize', ['--quarantine-orphans' => true])->assertSuccessful();

    $quarantine = DB::table('image_orphan_quarantines')->first();

    expect(DB::table('image_orphan_quarantines')->count())->toBe(1)
        ->and($quarantine->status)->toBe('quarantined')
        ->and($quarantine->source_delete_intent_at)->not->toBeNull();

    Storage::disk('public')->assertMissing($orphanPath);
    Storage::disk('local')->assertExists($quarantinePath);
    expect(legacyMigrationTestChecksum('local', $quarantinePath))->toBe($checksum);
});

test('migration audit records survive product and image deletion', function () {
    Storage::fake('local');
    Storage::fake('public');

    [$product, , $image, $oldPath] = legacyMigrationTestImage();
    $orphanPath = 'products/orphan.jpg';
    Storage::disk('public')->put($orphanPath, 'unreferenced-public-contents');

    $this->artisan('images:privatize', ['--quarantine-orphans' => true])->assertSuccessful();

    expect(DB::table('image_storage_migrations')->count())->toBe(1)
        ->and(DB::table('image_orphan_quarantines')->count())->toBe(2);

    $product->delete();

    expect(ProductImage::query()->find($image->id))->toBeNull()
        ->and(DB::table('image_storage_migrations')->where('product_image_id', $image->id)->exists())->toBeTrue()
        ->and(DB::table('image_orphan_quarantines')->count())->toBe(2);
});
