<?php

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;

test('suggestions return compact product data', function () {
    $category = Category::factory()->create(['name' => 'Furniture']);

    $product = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Leather Sofa', 'price_minor' => 15000]);

    $response = $this->getJson(route('search.suggestions', ['q' => 'sofa']))->assertOk();

    expect(array_keys($response->json('data.0')))->toBe(['id', 'title', 'url', 'price', 'category']);
    expect($response->json('data.0.id'))->toBe($product->id);
    expect($response->json('data.0.title'))->toBe('Leather Sofa');
    expect($response->json('data.0.url'))->toBe(route('products.show', $product->slug));
    expect($response->json('data.0.category'))->toBe('Furniture');
});

test('suggestions return an empty data array when nothing matches', function () {
    $this->getJson(route('search.suggestions', ['q' => 'missing']))
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

test('suggestions reject missing and short search terms', function (array $query, string $message) {
    $this->getJson(route('search.suggestions', $query))
        ->assertUnprocessable()
        ->assertInvalid(['q' => $message]);
})->with([
    'missing' => [[], 'The q field is required.'],
    'short' => [['q' => 'a'], 'The q field must be at least 2 characters.'],
]);

test('suggestions apply active filters and available for purchase rules', function () {
    $category = Category::factory()->create(['name' => 'Bicycles']);
    $otherCategory = Category::factory()->create(['name' => 'Furniture']);
    $location = Location::query()->create(['name' => 'Bole']);
    $otherLocation = Location::query()->create(['name' => 'Kazanchis']);

    $visible = Product::factory()
        ->for($category, 'category')
        ->for($location, 'location')
        ->approved()
        ->create([
            'title' => 'City Bicycle',
            'condition' => Product::CONDITION_NEW,
            'price_minor' => 10000,
        ]);

    $pending = Product::factory()
        ->for($category, 'category')
        ->for($location, 'location')
        ->create([
            'title' => 'Pending Bicycle',
            'condition' => Product::CONDITION_NEW,
            'price_minor' => 11000,
        ]);

    $reserved = Product::factory()
        ->for($category, 'category')
        ->for($location, 'location')
        ->approved()
        ->create([
            'title' => 'Reserved Bicycle',
            'condition' => Product::CONDITION_NEW,
            'price_minor' => 12000,
        ]);
    Product::query()->whereKey($reserved->id)->update(['reservation_token' => 'RM-SUGG001']);

    Product::factory()
        ->for($category, 'category')
        ->for($location, 'location')
        ->approved()
        ->create([
            'title' => 'Fair Bicycle',
            'condition' => Product::CONDITION_FAIR,
            'price_minor' => 13000,
        ]);

    Product::factory()
        ->for($category, 'category')
        ->for($location, 'location')
        ->approved()
        ->create([
            'title' => 'Expensive Bicycle',
            'condition' => Product::CONDITION_NEW,
            'price_minor' => 30000,
        ]);

    Product::factory()
        ->for($category, 'category')
        ->for($otherLocation, 'location')
        ->approved()
        ->create([
            'title' => 'Other Location Bicycle',
            'condition' => Product::CONDITION_NEW,
            'price_minor' => 14000,
        ]);

    Product::factory()
        ->for($otherCategory, 'category')
        ->for($location, 'location')
        ->approved()
        ->create([
            'title' => 'Other Category Bicycle',
            'condition' => Product::CONDITION_NEW,
            'price_minor' => 15000,
        ]);

    $response = $this->getJson(route('search.suggestions', [
        'q' => 'bicycle',
        'category' => $category->id,
        'location' => 'Bole',
        'condition' => Product::CONDITION_NEW,
        'min_price' => '10',
        'max_price' => '200',
    ]))->assertOk();

    $response->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visible->id);

    expect($pending->fresh()->status)->toBe(Product::STATUS_PENDING);
});

test('suggestions limit results to six products', function () {
    $category = Category::factory()->create(['name' => 'Bicycles']);

    foreach (range(1, 8) as $index) {
        Product::factory()
            ->for($category, 'category')
            ->approved()
            ->create(['title' => 'Bicycle '.$index]);
    }

    $this->getJson(route('search.suggestions', ['q' => 'bicycle']))
        ->assertOk()
        ->assertJsonCount(6, 'data');
});
