<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->rebuildTables(true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->rebuildTables(false);
    }

    private function rebuildTables(bool $withPositiveAmountChecks): void
    {
        Schema::withoutForeignKeyConstraints(function () use ($withPositiveAmountChecks): void {
            $this->dropTemporaryTables();

            DB::statement($this->productsTableSql($withPositiveAmountChecks));
            DB::statement($this->ordersTableSql($withPositiveAmountChecks));
            DB::statement($this->orderItemsTableSql($withPositiveAmountChecks));

            $this->copyTable('products');
            $this->copyTable('orders');
            $this->copyTable('order_items');

            $this->replaceTable('products');
            $this->replaceTable('orders');
            $this->replaceTable('order_items');

            $this->restoreIndexes();
        });
    }

    private function dropTemporaryTables(): void
    {
        foreach (['order_items', 'orders', 'products'] as $table) {
            $temporaryTable = "{$table}_integrity";

            if (! Schema::hasTable($table) && Schema::hasTable($temporaryTable)) {
                Schema::rename($temporaryTable, $table);

                continue;
            }

            Schema::dropIfExists($temporaryTable);
        }
    }

    private function productsTableSql(bool $withPositiveAmountChecks): string
    {
        $constraints = [
            'constraint "products_condition_valid" check ("condition" in (\'new\', \'like_new\', \'good\', \'fair\'))',
            'constraint "products_status_valid" check ("status" in (\'pending\', \'approved\', \'rejected\'))',
            'foreign key("seller_id") references "users"("id") on delete restrict',
            'foreign key("category_id") references "categories"("id") on delete restrict',
        ];

        if ($withPositiveAmountChecks) {
            array_unshift($constraints, 'constraint "products_price_minor_positive" check ("price_minor" > 0)');
        }

        return <<<SQL
        create table "products_integrity" (
            "id" integer primary key autoincrement not null,
            "seller_id" integer not null,
            "category_id" integer not null,
            "title" varchar not null,
            "slug" varchar not null,
            "description" text not null,
            "price_minor" integer not null,
            "condition" varchar not null,
            "status" varchar not null default 'pending',
            "rejection_reason" text,
            "created_at" datetime,
            "updated_at" datetime,
            {$this->constraintsSql($constraints)}
        )
        SQL;
    }

    private function ordersTableSql(bool $withPositiveAmountChecks): string
    {
        $constraints = [
            'constraint "orders_status_valid" check ("status" in (\'pending\', \'confirmed\', \'completed\', \'cancelled\'))',
            'constraint "orders_fulfillment_method_valid" check ("fulfillment_method" in (\'pickup\', \'cash_on_delivery\'))',
            'foreign key("buyer_id") references "users"("id") on delete restrict',
        ];

        if ($withPositiveAmountChecks) {
            array_unshift($constraints, 'constraint "orders_total_amount_minor_positive" check ("total_amount_minor" > 0)');
        }

        return <<<SQL
        create table "orders_integrity" (
            "id" integer primary key autoincrement not null,
            "buyer_id" integer not null,
            "order_number" varchar not null,
            "status" varchar not null default 'pending',
            "total_amount_minor" integer not null,
            "fulfillment_method" varchar not null,
            "contact_name" varchar,
            "contact_phone" varchar,
            "delivery_address" text,
            "created_at" datetime,
            "updated_at" datetime,
            {$this->constraintsSql($constraints)}
        )
        SQL;
    }

    private function orderItemsTableSql(bool $withPositiveAmountChecks): string
    {
        $constraints = [
            'foreign key("order_id") references "orders"("id") on delete cascade',
            'foreign key("product_id") references "products"("id") on delete restrict',
        ];

        if ($withPositiveAmountChecks) {
            array_unshift($constraints, 'constraint "order_items_price_minor_positive" check ("price_minor" > 0)');
            array_unshift($constraints, 'constraint "order_items_quantity_positive" check ("quantity" > 0)');
        }

        return <<<SQL
        create table "order_items_integrity" (
            "id" integer primary key autoincrement not null,
            "order_id" integer not null,
            "product_id" integer not null,
            "product_title" varchar not null,
            "price_minor" integer not null,
            "quantity" integer not null default '1',
            "created_at" datetime,
            "updated_at" datetime,
            {$this->constraintsSql($constraints)}
        )
        SQL;
    }

    /**
     * @param  array<int, string>  $constraints
     */
    private function constraintsSql(array $constraints): string
    {
        return implode(",\n            ", $constraints);
    }

    private function copyTable(string $table): void
    {
        $columns = match ($table) {
            'products' => [
                'id',
                'seller_id',
                'category_id',
                'title',
                'slug',
                'description',
                'price_minor',
                'condition',
                'status',
                'rejection_reason',
                'created_at',
                'updated_at',
            ],
            'orders' => [
                'id',
                'buyer_id',
                'order_number',
                'status',
                'total_amount_minor',
                'fulfillment_method',
                'contact_name',
                'contact_phone',
                'delivery_address',
                'created_at',
                'updated_at',
            ],
            'order_items' => [
                'id',
                'order_id',
                'product_id',
                'product_title',
                'price_minor',
                'quantity',
                'created_at',
                'updated_at',
            ],
        };

        DB::table("{$table}_integrity")->insertUsing(
            $columns,
            DB::table($table)->select($columns)
        );
    }

    private function replaceTable(string $table): void
    {
        Schema::dropIfExists($table);
        Schema::rename("{$table}_integrity", $table);
    }

    private function restoreIndexes(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unique('slug');
            $table->index('seller_id');
            $table->index(['status', 'created_at']);
            $table->index(['category_id', 'status']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unique('order_number');
            $table->index('buyer_id');
            $table->index(['status', 'created_at']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->unique(['order_id', 'product_id']);
            $table->index('product_id');
        });
    }
};
