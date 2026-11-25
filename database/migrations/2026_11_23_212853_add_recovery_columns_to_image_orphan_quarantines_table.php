<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $table = 'image_orphan_quarantines';

        if (! Schema::hasTable($table)) {
            return;
        }

        $hasStatus = Schema::hasColumn($table, 'status');
        $hasError = Schema::hasColumn($table, 'error');
        $hasSourceDeleteIntentAt = Schema::hasColumn($table, 'source_delete_intent_at');
        $hasDeleteIntentAt = Schema::hasColumn($table, 'delete_intent_at');
        $hasDeleteIntentChecksum = Schema::hasColumn($table, 'delete_intent_checksum');
        $hasStatusIndex = Schema::hasIndex($table, 'image_orphan_quarantines_status_index');
        $hasStatusDeletedAtIndex = Schema::hasIndex($table, 'image_orphan_quarantines_status_deleted_at_index');
        $hasDeleteIntentAtIndex = Schema::hasIndex($table, 'image_orphan_quarantines_delete_intent_at_index');

        Schema::table($table, function (Blueprint $blueprint) use ($hasStatus, $hasError, $hasSourceDeleteIntentAt, $hasDeleteIntentAt, $hasDeleteIntentChecksum, $hasStatusIndex, $hasStatusDeletedAtIndex, $hasDeleteIntentAtIndex): void {
            if (! $hasStatus) {
                $blueprint->string('status', 32)->default('pending');
            }

            if (! $hasError) {
                $blueprint->text('error')->nullable();
            }

            if (! $hasSourceDeleteIntentAt) {
                $blueprint->timestamp('source_delete_intent_at')->nullable();
            }

            if (! $hasDeleteIntentAt) {
                $blueprint->timestamp('delete_intent_at')->nullable();
            }

            if (! $hasDeleteIntentChecksum) {
                $blueprint->string('delete_intent_checksum', 64)->nullable();
            }

            if (! $hasStatusIndex) {
                $blueprint->index('status');
            }

            if (! $hasStatusDeletedAtIndex) {
                $blueprint->index(['status', 'deleted_at']);
            }

            if (! $hasDeleteIntentAtIndex) {
                $blueprint->index('delete_intent_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = 'image_orphan_quarantines';

        if (! Schema::hasTable($table)) {
            return;
        }

        $hasStatusIndex = Schema::hasIndex($table, 'image_orphan_quarantines_status_index');
        $hasStatusDeletedAtIndex = Schema::hasIndex($table, 'image_orphan_quarantines_status_deleted_at_index');
        $hasDeleteIntentAtIndex = Schema::hasIndex($table, 'image_orphan_quarantines_delete_intent_at_index');
        $hasStatus = Schema::hasColumn($table, 'status');
        $hasError = Schema::hasColumn($table, 'error');
        $hasSourceDeleteIntentAt = Schema::hasColumn($table, 'source_delete_intent_at');
        $hasDeleteIntentAt = Schema::hasColumn($table, 'delete_intent_at');
        $hasDeleteIntentChecksum = Schema::hasColumn($table, 'delete_intent_checksum');

        Schema::table($table, function (Blueprint $blueprint) use ($hasStatusIndex, $hasStatusDeletedAtIndex, $hasDeleteIntentAtIndex, $hasStatus, $hasError, $hasSourceDeleteIntentAt, $hasDeleteIntentAt, $hasDeleteIntentChecksum): void {
            if ($hasStatusIndex) {
                $blueprint->dropIndex('image_orphan_quarantines_status_index');
            }

            if ($hasStatusDeletedAtIndex) {
                $blueprint->dropIndex('image_orphan_quarantines_status_deleted_at_index');
            }

            if ($hasDeleteIntentAtIndex) {
                $blueprint->dropIndex('image_orphan_quarantines_delete_intent_at_index');
            }

            if ($hasDeleteIntentChecksum) {
                $blueprint->dropColumn('delete_intent_checksum');
            }

            if ($hasDeleteIntentAt) {
                $blueprint->dropColumn('delete_intent_at');
            }

            if ($hasSourceDeleteIntentAt) {
                $blueprint->dropColumn('source_delete_intent_at');
            }

            if ($hasError) {
                $blueprint->dropColumn('error');
            }

            if ($hasStatus) {
                $blueprint->dropColumn('status');
            }
        });
    }
};
