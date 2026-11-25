<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
        create table "promotion_packages" (
            "id" integer primary key autoincrement not null,
            "code" varchar not null,
            "name" varchar not null,
            "price_minor" integer not null,
            "duration_days" integer not null,
            "priority" integer not null default '1',
            "is_active" integer not null default '1',
            "sort_order" integer not null default '0',
            "created_at" datetime,
            "updated_at" datetime,
            constraint "promotion_packages_code_unique" unique ("code"),
            constraint "promotion_packages_price_minor_positive" check ("price_minor" > 0),
            constraint "promotion_packages_duration_days_positive" check ("duration_days" > 0),
            constraint "promotion_packages_priority_matches_code" check (("code" = 'basic' and "priority" = 1) or ("code" = 'standard' and "priority" = 2) or ("code" = 'premium' and "priority" = 3))
        )
        SQL);

        Schema::table('promotion_packages', function (Blueprint $table): void {
            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_packages');
    }
};
