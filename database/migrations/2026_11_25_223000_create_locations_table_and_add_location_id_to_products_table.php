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
        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildProducts(true);

            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('location_id')
                ->nullable()
                ->index()
                ->constrained('locations')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildProducts(false);
        } else {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropForeign(['location_id']);
                $table->dropColumn('location_id');
            });
        }

        Schema::dropIfExists('locations');
    }

    private function rebuildProducts(bool $withLocation): void
    {
        Schema::withoutForeignKeyConstraints(function () use ($withLocation): void {
            Schema::dropIfExists('products_integrity');

            DB::statement($this->productsTableSql($withLocation));

            $columns = [
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
                'reservation_token',
            ];

            if ($withLocation) {
                $columns[] = 'location_id';
            }

            DB::table('products_integrity')->insertUsing(
                $columns,
                DB::table('products')->select($columns)
            );

            Schema::dropIfExists('products');
            Schema::rename('products_integrity', 'products');

            $this->restoreIndexes($withLocation);
        });
    }

    private function productsTableSql(bool $withLocation): string
    {
        $constraints = [
            'constraint "products_condition_valid" check ("condition" in (\'new\', \'like_new\', \'good\', \'fair\'))',
            'constraint "products_status_valid" check ("status" in (\'pending\', \'approved\', \'rejected\'))',
            'constraint "products_price_minor_positive" check ("price_minor" > 0)',
            'foreign key("seller_id") references "users"("id") on delete restrict',
            'foreign key("category_id") references "categories"("id") on delete restrict',
        ];

        $columns = <<<'SQL'
            "reservation_token" varchar,
        SQL;

        if ($withLocation) {
            $columns .= <<<'SQL'

                        "location_id" integer,
            SQL;
            $constraints[] = 'foreign key("location_id") references "locations"("id") on delete restrict';
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
            {$columns}
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

    private function restoreIndexes(bool $withLocation): void
    {
        Schema::table('products', function (Blueprint $table) use ($withLocation): void {
            $table->unique('slug');
            $table->index('seller_id');
            $table->index(['status', 'created_at']);
            $table->index(['category_id', 'status']);
            $table->unique('reservation_token');

            if ($withLocation) {
                $table->index('location_id');
            }
        });
    }
};
