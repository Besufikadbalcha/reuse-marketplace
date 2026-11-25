<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS_BEFORE_STOPPED_ORDER = [
        'id',
        'seller_id',
        'product_id',
        'package_id',
        'funding_type',
        'status',
        'open_product_id',
        'package_code_snapshot',
        'price_minor_snapshot',
        'duration_days_snapshot',
        'priority_snapshot',
        'starts_at',
        'ends_at',
        'paused_at',
        'remaining_seconds',
        'stopped_at',
    ];

    private const COLUMNS_AFTER_STOPPED_ORDER = [
        'created_at',
        'updated_at',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable(true);

            return;
        }

        Schema::table('product_promotions', function (Blueprint $table): void {
            $table->integer('stopped_by_order_id')->nullable()->after('stopped_at');
            $table->foreign('stopped_by_order_id', 'product_promotions_stopped_by_order_fk')
                ->references('id')
                ->on('orders')
                ->nullOnDelete();
            $table->index('stopped_by_order_id', 'product_promotions_stopped_by_order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable(false);

            return;
        }

        Schema::table('product_promotions', function (Blueprint $table): void {
            $table->dropForeign('product_promotions_stopped_by_order_fk');
            $table->dropIndex('product_promotions_stopped_by_order_index');
            $table->dropColumn('stopped_by_order_id');
        });
    }

    private function rebuildSqliteTable(bool $withStoppedOrder): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            $columns = array_merge(
                self::COLUMNS_BEFORE_STOPPED_ORDER,
                $withStoppedOrder ? ['stopped_by_order_id'] : [],
                self::COLUMNS_AFTER_STOPPED_ORDER,
            );
            $stoppedOrderColumnSql = $withStoppedOrder ? '"stopped_by_order_id" integer,' : '';
            $stoppedOrderForeignKeySql = $withStoppedOrder
                ? 'foreign key("stopped_by_order_id") references "orders"("id") on delete set null,'
                : '';

            DB::statement(<<<SQL
            create table "product_promotions_new" (
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
                {$stoppedOrderColumnSql}
                "created_at" datetime,
                "updated_at" datetime,
                constraint "product_promotions_open_product_unique" unique ("open_product_id"),
                foreign key("seller_id") references "users"("id") on delete restrict,
                foreign key("product_id") references "products"("id") on delete restrict,
                foreign key("package_id") references "promotion_packages"("id") on delete restrict,
                foreign key("open_product_id") references "products"("id") on delete restrict,
                {$stoppedOrderForeignKeySql}
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

            $selectExpressions = [];

            foreach ($columns as $column) {
                $selectExpressions[] = $column === 'stopped_by_order_id'
                    ? 'null'
                    : '"'.$column.'"';
            }

            DB::statement(sprintf(
                'insert into "product_promotions_new" (%s) select %s from "product_promotions"',
                $this->quoteColumns($columns),
                implode(', ', $selectExpressions),
            ));
            DB::statement('drop table "product_promotions"');
            DB::statement('alter table "product_promotions_new" rename to "product_promotions"');

            $this->createSqliteIndexes($withStoppedOrder);
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    private function createSqliteIndexes(bool $withStoppedOrder): void
    {
        DB::statement('create index "product_promotions_seller_id_status_index" on "product_promotions" ("seller_id", "status")');
        DB::statement('create index "product_promotions_product_id_status_index" on "product_promotions" ("product_id", "status")');
        DB::statement('create index "product_promotions_status_ends_at_index" on "product_promotions" ("status", "ends_at")');
        DB::statement('create index "product_promotions_status_priority_snapshot_index" on "product_promotions" ("status", "priority_snapshot")');
        DB::statement('create index "product_promotions_package_id_index" on "product_promotions" ("package_id")');

        if ($withStoppedOrder) {
            DB::statement('create index "product_promotions_stopped_by_order_index" on "product_promotions" ("stopped_by_order_id")');
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function quoteColumns(array $columns): string
    {
        return implode(', ', array_map(
            fn (string $column): string => '"'.$column.'"',
            $columns,
        ));
    }
};
