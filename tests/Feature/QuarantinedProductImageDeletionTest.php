<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{0: int, 1: string, 2: string}
 */
function quarantinedDeletionTestRecord(string $contents = 'quarantined-contents', int $ageInDays = 31): array
{
    $path = 'quarantine/products/'.fake()->uuid().'.jpg';
    Storage::disk('local')->put($path, $contents);
    $checksum = (string) Storage::disk('local')->checksum($path, ['checksum_algo' => 'sha256']);

    $id = DB::table('image_orphan_quarantines')->insertGetId([
        'original_path' => 'products/'.fake()->uuid().'.jpg',
        'quarantine_path' => $path,
        'checksum' => $checksum,
        'bytes' => strlen($contents),
        'reason' => 'unreferenced_public_file',
        'quarantined_at' => now()->subDays($ageInDays),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$id, $path, $checksum];
}

/**
 * @return array{0: int, 1: string, 2: string}
 */
function quarantinedDeletionIntentRecord(string $contents = 'quarantined-contents', int $ageInDays = 31): array
{
    [$id, $path, $checksum] = quarantinedDeletionTestRecord($contents, $ageInDays);
    DB::table('image_orphan_quarantines')->where('id', $id)->update([
        'delete_intent_at' => now(),
        'delete_intent_checksum' => $checksum,
    ]);

    return [$id, $path, $checksum];
}

test('permanent deletion requires explicit confirmation', function () {
    Storage::fake('local');

    [$id, $path] = quarantinedDeletionTestRecord();

    $this->artisan('images:delete-quarantined', ['--days' => 30])
        ->expectsOutputToContain('Pass --confirm to permanently delete quarantined files.')
        ->assertFailed();

    Storage::disk('local')->assertExists($path);
    expect(DB::table('image_orphan_quarantines')->where('id', $id)->value('deleted_at'))->toBeNull();
});

test('a deletion dry run reports planned deletions without changing files or records', function () {
    Storage::fake('local');

    [$id, $path] = quarantinedDeletionTestRecord();

    $this->artisan('images:delete-quarantined', [
        '--days' => 30,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain("Delete quarantined file {$path}")
        ->assertSuccessful();

    Storage::disk('local')->assertExists($path);
    expect(DB::table('image_orphan_quarantines')->where('id', $id)->value('deleted_at'))->toBeNull();
});

test('confirmed deletion removes an old quarantined file but keeps its audit record', function () {
    Storage::fake('local');

    [$id, $path] = quarantinedDeletionTestRecord();

    $this->artisan('images:delete-quarantined', [
        '--days' => 30,
        '--confirm' => true,
    ])->assertSuccessful();

    Storage::disk('local')->assertMissing($path);

    $quarantine = DB::table('image_orphan_quarantines')->where('id', $id)->first();

    expect($quarantine->deleted_at)->not->toBeNull()
        ->and($quarantine->delete_intent_at)->toBeNull()
        ->and($quarantine->delete_intent_checksum)->toBeNull()
        ->and($quarantine->error)->toBeNull();
});

test('confirmed deletion skips files that are not old enough', function () {
    Storage::fake('local');

    [$id, $path] = quarantinedDeletionTestRecord('recent-contents', 1);

    $this->artisan('images:delete-quarantined', [
        '--days' => 30,
        '--confirm' => true,
    ])->assertSuccessful();

    Storage::disk('local')->assertExists($path);
    expect(DB::table('image_orphan_quarantines')->where('id', $id)->value('deleted_at'))->toBeNull();
});

test('confirmed deletion refuses to delete a quarantined file whose checksum changed', function () {
    Storage::fake('local');

    [$id, $path] = quarantinedDeletionTestRecord();
    Storage::disk('local')->put($path, 'tampered-contents');

    $this->artisan('images:delete-quarantined', [
        '--days' => 30,
        '--confirm' => true,
    ])
        ->expectsOutputToContain('Checksum mismatch')
        ->assertFailed();

    Storage::disk('local')->assertExists($path);

    $quarantine = DB::table('image_orphan_quarantines')->where('id', $id)->first();

    expect($quarantine->deleted_at)->toBeNull()
        ->and($quarantine->error)->toContain('Checksum mismatch');
});

test('confirmed deletion preserves a quarantined file with no stored checksum', function () {
    Storage::fake('local');

    [$id, $path] = quarantinedDeletionTestRecord();
    DB::table('image_orphan_quarantines')->where('id', $id)->update(['checksum' => null]);

    $this->artisan('images:delete-quarantined', [
        '--days' => 30,
        '--confirm' => true,
    ])
        ->expectsOutputToContain('Missing stored checksum')
        ->assertFailed();

    Storage::disk('local')->assertExists($path);

    $quarantine = DB::table('image_orphan_quarantines')->where('id', $id)->first();

    expect($quarantine->deleted_at)->toBeNull()
        ->and($quarantine->error)->toContain('Missing stored checksum');
});

test('confirmed deletion preserves a quarantined file with a malformed stored checksum', function () {
    Storage::fake('local');

    [$id, $path] = quarantinedDeletionTestRecord();
    DB::table('image_orphan_quarantines')->where('id', $id)->update(['checksum' => 'not-a-checksum']);

    $this->artisan('images:delete-quarantined', [
        '--days' => 30,
        '--confirm' => true,
    ])
        ->expectsOutputToContain('Invalid stored checksum')
        ->assertFailed();

    Storage::disk('local')->assertExists($path);

    $quarantine = DB::table('image_orphan_quarantines')->where('id', $id)->first();

    expect($quarantine->deleted_at)->toBeNull()
        ->and($quarantine->error)->toContain('Invalid stored checksum');
});

test('confirmed deletion preserves a quarantined file whose checksum cannot be read', function () {
    Storage::fake('local');

    [$id, $path] = quarantinedDeletionTestRecord();
    $localDisk = Storage::disk('local');

    $guardedDisk = Mockery::mock($localDisk)->makePartial();
    $guardedDisk->shouldReceive('checksum')
        ->with($path, ['checksum_algo' => 'sha256'])
        ->once()
        ->andThrow(new RuntimeException('unreadable'));

    Storage::set('local', $guardedDisk);

    $this->artisan('images:delete-quarantined', [
        '--days' => 30,
        '--confirm' => true,
    ])
        ->expectsOutputToContain('Unable to checksum')
        ->assertFailed();

    Storage::disk('local')->assertExists($path);

    $quarantine = DB::table('image_orphan_quarantines')->where('id', $id)->first();

    expect($quarantine->deleted_at)->toBeNull()
        ->and($quarantine->error)->toContain('Unable to checksum');
});

test('permanent deletion preserves a file when the deletion intent cannot be recorded', function () {
    Storage::fake('local');

    [$id, $path] = quarantinedDeletionTestRecord();
    DB::unprepared("CREATE TRIGGER deletion_intent_failure BEFORE UPDATE OF delete_intent_at ON image_orphan_quarantines WHEN NEW.delete_intent_at IS NOT NULL BEGIN SELECT RAISE(ABORT, 'blocked'); END");

    $this->artisan('images:delete-quarantined', [
        '--days' => 30,
        '--confirm' => true,
    ])
        ->expectsOutputToContain('Unable to record deletion intent')
        ->assertFailed();

    Storage::disk('local')->assertExists($path);

    $quarantine = DB::table('image_orphan_quarantines')->where('id', $id)->first();

    expect($quarantine->deleted_at)->toBeNull()
        ->and($quarantine->delete_intent_at)->toBeNull()
        ->and($quarantine->error)->toContain('Unable to record deletion intent');
});

test('permanent deletion clears the intent and preserves a file whose deletion fails', function () {
    $localDisk = Storage::fake('local');

    [$id, $path] = quarantinedDeletionTestRecord();

    $guardedDisk = Mockery::mock($localDisk)->makePartial();
    $guardedDisk->shouldReceive('delete')->with($path)->once()->andReturn(false);
    Storage::set('local', $guardedDisk);

    $this->artisan('images:delete-quarantined', [
        '--days' => 30,
        '--confirm' => true,
    ])
        ->expectsOutputToContain('Unable to delete quarantined file')
        ->assertFailed();

    Storage::disk('local')->assertExists($path);

    $quarantine = DB::table('image_orphan_quarantines')->where('id', $id)->first();

    expect($quarantine->deleted_at)->toBeNull()
        ->and($quarantine->delete_intent_at)->toBeNull()
        ->and($quarantine->delete_intent_checksum)->toBeNull()
        ->and($quarantine->error)->toContain('Unable to delete quarantined file');
});

test('deletion recovery finalizes a file that is already missing', function () {
    Storage::fake('local');

    [$id, $path] = quarantinedDeletionIntentRecord();
    Storage::disk('local')->delete($path);

    $this->artisan('images:delete-quarantined', [
        '--days' => 30,
        '--confirm' => true,
    ])
        ->expectsOutputToContain('Finalized deletion')
        ->assertSuccessful();

    $quarantine = DB::table('image_orphan_quarantines')->where('id', $id)->first();

    expect($quarantine->deleted_at)->not->toBeNull()
        ->and($quarantine->delete_intent_at)->toBeNull()
        ->and($quarantine->delete_intent_checksum)->toBeNull();
});

test('deletion recovery preserves a file whose checksum changed after the intent was recorded', function () {
    Storage::fake('local');

    [$id, $path] = quarantinedDeletionIntentRecord();
    Storage::disk('local')->put($path, 'tampered-contents');

    $this->artisan('images:delete-quarantined', [
        '--days' => 30,
        '--confirm' => true,
    ])
        ->expectsOutputToContain('during deletion recovery')
        ->assertFailed();

    Storage::disk('local')->assertExists($path);

    $quarantine = DB::table('image_orphan_quarantines')->where('id', $id)->first();

    expect($quarantine->deleted_at)->toBeNull()
        ->and($quarantine->delete_intent_at)->not->toBeNull()
        ->and($quarantine->error)->toContain('during deletion recovery');
});

test('a failed final deletion audit leaves an intent that recovery can finalize', function () {
    Storage::fake('local');

    [$id, $path] = quarantinedDeletionTestRecord();
    DB::unprepared("CREATE TRIGGER deletion_finalize_failure BEFORE UPDATE OF deleted_at ON image_orphan_quarantines WHEN NEW.deleted_at IS NOT NULL BEGIN SELECT RAISE(ABORT, 'blocked'); END");

    $this->artisan('images:delete-quarantined', [
        '--days' => 30,
        '--confirm' => true,
    ])
        ->expectsOutputToContain('Unable to record deletion')
        ->assertFailed();

    Storage::disk('local')->assertMissing($path);

    $quarantine = DB::table('image_orphan_quarantines')->where('id', $id)->first();

    expect($quarantine->deleted_at)->toBeNull()
        ->and($quarantine->delete_intent_at)->not->toBeNull()
        ->and($quarantine->delete_intent_checksum)->not->toBeNull();

    DB::unprepared('DROP TRIGGER deletion_finalize_failure');

    $this->artisan('images:delete-quarantined', [
        '--days' => 30,
        '--confirm' => true,
    ])
        ->expectsOutputToContain('Finalized deletion')
        ->assertSuccessful();

    $quarantine = DB::table('image_orphan_quarantines')->where('id', $id)->first();

    expect($quarantine->deleted_at)->not->toBeNull()
        ->and($quarantine->delete_intent_at)->toBeNull();
});
