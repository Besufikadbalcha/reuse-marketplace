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
        create table "promotion_credits" (
            "id" integer primary key autoincrement not null,
            "seller_id" integer not null,
            "source_promotion_id" integer,
            "source_order_item_id" integer,
            "seconds_granted" integer not null,
            "seconds_remaining" integer not null,
            "created_at" datetime,
            "updated_at" datetime,
            constraint "promotion_credits_source_promotion_unique" unique ("source_promotion_id"),
            constraint "promotion_credits_source_order_item_unique" unique ("source_order_item_id"),
            foreign key("seller_id") references "users"("id") on delete restrict,
            foreign key("source_promotion_id") references "product_promotions"("id") on delete restrict,
            foreign key("source_order_item_id") references "order_items"("id") on delete restrict,
            constraint "promotion_credits_seconds_granted_positive" check ("seconds_granted" > 0),
            constraint "promotion_credits_seconds_remaining_valid" check ("seconds_remaining" >= 0 and "seconds_remaining" <= "seconds_granted")
        )
        SQL);

        Schema::table('promotion_credits', function (Blueprint $table): void {
            $table->index(['seller_id', 'seconds_remaining']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_credits');
    }
};
