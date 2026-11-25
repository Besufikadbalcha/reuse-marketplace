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
        create table "product_promotions" (
            "id" integer primary key autoincrement not null,
            "seller_id" integer not null,
            "product_id" integer not null,
            "package_id" integer not null,
            "funding_type" varchar not null default 'payment',
            "status" varchar not null default 'payment_pending',
            "open_product_id" integer,
            "package_code_snapshot" varchar not null,
            "price_minor_snapshot" integer not null,
            "duration_days_snapshot" integer not null,
            "priority_snapshot" integer not null,
            "starts_at" datetime,
            "ends_at" datetime,
            "paused_at" datetime,
            "remaining_seconds" integer,
            "stopped_at" datetime,
            "created_at" datetime,
            "updated_at" datetime,
            constraint "product_promotions_open_product_unique" unique ("open_product_id"),
            foreign key("seller_id") references "users"("id") on delete restrict,
            foreign key("product_id") references "products"("id") on delete restrict,
            foreign key("package_id") references "promotion_packages"("id") on delete restrict,
            foreign key("open_product_id") references "products"("id") on delete restrict,
            constraint "product_promotions_funding_type_valid" check ("funding_type" in ('payment', 'credit')),
            constraint "product_promotions_status_valid" check ("status" in ('payment_pending', 'payment_rejected', 'active', 'paused', 'expired', 'stopped_sold')),
            constraint "product_promotions_price_minor_snapshot_positive" check ("price_minor_snapshot" > 0),
            constraint "product_promotions_duration_days_snapshot_positive" check ("duration_days_snapshot" > 0),
            constraint "product_promotions_priority_snapshot_valid" check ("priority_snapshot" between 1 and 3),
            constraint "product_promotions_remaining_seconds_valid" check ("remaining_seconds" is null or "remaining_seconds" >= 0),
            constraint "product_promotions_open_product_valid" check ((("status" in ('payment_pending', 'payment_rejected', 'active', 'paused') and "open_product_id" = "product_id") or ("status" in ('expired', 'stopped_sold') and "open_product_id" is null))),
            constraint "product_promotions_window_valid" check ((("starts_at" is null and "ends_at" is null) or ("starts_at" is not null and "ends_at" is not null and "ends_at" > "starts_at")))
        )
        SQL);

        Schema::table('product_promotions', function (Blueprint $table): void {
            $table->index(['seller_id', 'status']);
            $table->index(['product_id', 'status']);
            $table->index(['status', 'ends_at']);
            $table->index(['status', 'priority_snapshot']);
            $table->index('package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_promotions');
    }
};
