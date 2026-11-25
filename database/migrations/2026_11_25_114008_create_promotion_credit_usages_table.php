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
        create table "promotion_credit_usages" (
            "id" integer primary key autoincrement not null,
            "credit_id" integer not null,
            "promotion_id" integer not null,
            "seconds_used" integer not null,
            "created_at" datetime,
            "updated_at" datetime,
            constraint "promotion_credit_usages_promotion_unique" unique ("promotion_id"),
            foreign key("credit_id") references "promotion_credits"("id") on delete restrict,
            foreign key("promotion_id") references "product_promotions"("id") on delete restrict,
            constraint "promotion_credit_usages_seconds_used_positive" check ("seconds_used" > 0)
        )
        SQL);

        Schema::table('promotion_credit_usages', function (Blueprint $table): void {
            $table->index(['credit_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_credit_usages');
    }
};
