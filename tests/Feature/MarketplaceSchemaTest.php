<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('product slugs are unique at the database level', function () {
    $product = Product::factory()->create();

    expect(fn () => DB::table('products')->insert([
        'seller_id' => $product->seller_id,
        'category_id' => $product->category_id,
        'title' => 'Duplicate slug',
        'slug' => $product->slug,
        'description' => 'Duplicate slug value.',
        'price_minor' => 10000,
        'condition' => Product::CONDITION_GOOD,
        'status' => Product::STATUS_PENDING,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('order numbers are unique at the database level', function () {
    $order = Order::factory()->create();

    expect(fn () => DB::table('orders')->insert([
        'buyer_id' => $order->buyer_id,
        'order_number' => $order->order_number,
        'status' => Order::STATUS_PENDING,
        'total_amount_minor' => 10000,
        'fulfillment_method' => Order::FULFILLMENT_PICKUP,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('invalid listing statuses are rejected by the database', function () {
    $seller = User::factory()->create();
    $category = Category::factory()->create();

    expect(fn () => DB::table('products')->insert([
        'seller_id' => $seller->id,
        'category_id' => $category->id,
        'title' => 'Invalid status',
        'slug' => 'invalid-status',
        'description' => 'Status is not allowed.',
        'price_minor' => 10000,
        'condition' => Product::CONDITION_GOOD,
        'status' => 'archived',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('invalid fulfillment methods are rejected by the database', function () {
    $buyer = User::factory()->create();

    expect(fn () => DB::table('orders')->insert([
        'buyer_id' => $buyer->id,
        'order_number' => 'RM-INVALID001',
        'status' => Order::STATUS_PENDING,
        'total_amount_minor' => 10000,
        'fulfillment_method' => 'bank_transfer',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('a product with order items cannot be deleted', function () {
    $item = OrderItem::factory()->create();
    $product = $item->product;

    expect(fn () => $product->delete())->toThrow(QueryException::class);

    $this->assertDatabaseHas('products', ['id' => $product->id]);
});

test('deleting a product removes its images', function () {
    $image = ProductImage::factory()->create();

    $image->product->delete();

    $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
});

test('an order can contain a product only once', function () {
    $item = OrderItem::factory()->create();

    expect(fn () => DB::table('order_items')->insert([
        'order_id' => $item->order_id,
        'product_id' => $item->product_id,
        'product_title' => 'Duplicate item',
        'price_minor' => 10000,
        'quantity' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('product prices must be positive at the database level', function (int $priceMinor) {
    $seller = User::factory()->create();
    $category = Category::factory()->create();

    expect(fn () => DB::table('products')->insert([
        'seller_id' => $seller->id,
        'category_id' => $category->id,
        'title' => 'Invalid price',
        'slug' => 'invalid-product-price',
        'description' => 'Price is not positive.',
        'price_minor' => $priceMinor,
        'condition' => Product::CONDITION_GOOD,
        'status' => Product::STATUS_PENDING,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
})->with([0, -100]);

test('order totals must be positive at the database level', function (int $totalAmountMinor) {
    $buyer = User::factory()->create();

    expect(fn () => DB::table('orders')->insert([
        'buyer_id' => $buyer->id,
        'order_number' => 'RM-INVALID-TOTAL',
        'status' => Order::STATUS_PENDING,
        'total_amount_minor' => $totalAmountMinor,
        'fulfillment_method' => Order::FULFILLMENT_PICKUP,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
})->with([0, -100]);

test('order item prices must be positive at the database level', function (int $priceMinor) {
    $order = Order::factory()->create();
    $product = Product::factory()->create();

    expect(fn () => DB::table('order_items')->insert([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_title' => $product->title,
        'price_minor' => $priceMinor,
        'quantity' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
})->with([0, -100]);

test('the recovery migration adds quarantine columns and indexes to legacy tables', function () {
    Schema::drop('image_orphan_quarantines');
    Schema::create('image_orphan_quarantines', function (Blueprint $table): void {
        $table->id();
        $table->string('original_path', 512);
        $table->string('quarantine_path', 512)->unique();
        $table->string('checksum', 64)->nullable();
        $table->unsignedBigInteger('bytes')->nullable();
        $table->string('reason', 64)->default('unreferenced_public_file');
        $table->timestamp('quarantined_at');
        $table->timestamp('deleted_at')->nullable();
        $table->timestamps();
    });

    $legacyId = DB::table('image_orphan_quarantines')->insertGetId([
        'original_path' => 'products/legacy.jpg',
        'quarantine_path' => 'quarantine/products/legacy.jpg',
        'checksum' => null,
        'bytes' => 10,
        'reason' => 'unreferenced_public_file',
        'quarantined_at' => now()->subDays(31),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_11_23_212853_add_recovery_columns_to_image_orphan_quarantines_table.php');
    $migration->up();
    $migration->up();

    $row = DB::table('image_orphan_quarantines')->where('id', $legacyId)->first();

    expect($row->status)->toBe('pending')
        ->and($row->error)->toBeNull()
        ->and($row->source_delete_intent_at)->toBeNull()
        ->and($row->delete_intent_at)->toBeNull()
        ->and($row->delete_intent_checksum)->toBeNull()
        ->and(Schema::hasIndex('image_orphan_quarantines', 'image_orphan_quarantines_status_index'))->toBeTrue()
        ->and(Schema::hasIndex('image_orphan_quarantines', 'image_orphan_quarantines_status_deleted_at_index'))->toBeTrue()
        ->and(Schema::hasIndex('image_orphan_quarantines', 'image_orphan_quarantines_delete_intent_at_index'))->toBeTrue();
});

test('order item quantities must be positive at the database level', function (int $quantity) {
    $order = Order::factory()->create();
    $product = Product::factory()->create();

    expect(fn () => DB::table('order_items')->insert([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_title' => $product->title,
        'price_minor' => 12500,
        'quantity' => $quantity,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
})->with([0, -1]);
