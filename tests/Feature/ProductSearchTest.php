<?php

use App\Models\Category;
use App\Models\Product;

test('search normalizes extra whitespace before filtering', function () {
    $category = Category::factory()->create(['name' => 'Furniture']);

    Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Leather Sofa']);

    $response = $this->get(route('home', ['q' => "  LEATHER \t SOFA \n"]))->assertOk();

    expect($response['filters']['q'])->toBe('LEATHER SOFA');
    expect($response['products']->pluck('title')->all())->toBe(['Leather Sofa']);
});

test('search ranks title matches above category and description matches', function () {
    $category = Category::factory()->create(['name' => 'Furniture']);
    $matchingCategory = Category::factory()->create(['name' => 'Leather Sofas']);

    $titleMatch = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Leather Sofa Restored', 'description' => 'A sturdy item.']);

    $categoryMatch = Product::factory()
        ->for($matchingCategory, 'category')
        ->approved()
        ->create(['title' => 'Comfortable Seat', 'description' => 'A sturdy item.']);

    $descriptionMatch = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Handmade Chair', 'description' => 'A leather sofa inspired design.']);

    $response = $this->get(route('home', ['q' => 'leather sofa']))->assertOk();

    expect($response['products']->pluck('id')->all())->toBe([
        $titleMatch->id,
        $categoryMatch->id,
        $descriptionMatch->id,
    ]);
});

test('search requires every term to match at least one searchable field', function () {
    $category = Category::factory()->create(['name' => 'Furniture']);

    $matching = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Leather Sofa', 'description' => 'Restored seating.']);

    Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Leather Chair', 'description' => 'Restored seating.']);

    $response = $this->get(route('home', ['q' => 'leather sofa']))->assertOk();

    expect($response['products']->pluck('id')->all())->toBe([$matching->id]);
});

test('price sorting orders products by price', function () {
    $category = Category::factory()->create(['name' => 'Sorting Category']);

    $expensive = Product::factory()->for($category, 'category')->approved()->create(['price_minor' => 30000]);
    $cheapest = Product::factory()->for($category, 'category')->approved()->create(['price_minor' => 10000]);
    $middle = Product::factory()->for($category, 'category')->approved()->create(['price_minor' => 20000]);

    $ascending = $this->get(route('home', ['sort' => 'price_asc']))->assertOk();
    $descending = $this->get(route('home', ['sort' => 'price_desc']))->assertOk();

    expect($ascending['products']->pluck('id')->all())->toBe([$cheapest->id, $middle->id, $expensive->id]);
    expect($descending['products']->pluck('id')->all())->toBe([$expensive->id, $middle->id, $cheapest->id]);
});

test('explicit latest sorting overrides relevance for a search', function () {
    $category = Category::factory()->create(['name' => 'Furniture']);

    $titleMatch = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Leather Sofa Restored']);

    $descriptionMatch = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Handmade Chair', 'description' => 'A leather sofa inspired design.']);

    $response = $this->get(route('home', ['q' => 'leather sofa', 'sort' => 'latest']))->assertOk();

    expect($response['products']->pluck('id')->all())->toBe([$descriptionMatch->id, $titleMatch->id]);
});

test('relevance sorting without a search term falls back to latest', function () {
    $category = Category::factory()->create(['name' => 'Furniture']);

    $oldest = Product::factory()->for($category, 'category')->approved()->create(['title' => 'Oldest Item']);
    $middle = Product::factory()->for($category, 'category')->approved()->create(['title' => 'Middle Item']);
    $newest = Product::factory()->for($category, 'category')->approved()->create(['title' => 'Newest Item']);

    $response = $this->get(route('home', ['sort' => 'relevance']))->assertOk();

    expect($response['products']->pluck('id')->all())->toBe([$newest->id, $middle->id, $oldest->id]);
});

test('invalid sort values are rejected without changing the catalog', function () {
    $product = Product::factory()->approved()->create(['title' => 'Sort Injection Item']);

    $this->get(route('home', ['sort' => 'price_minor; drop table products']))
        ->assertRedirect(route('home'))
        ->assertSessionHasErrors('sort', 'The selected sort is invalid.');

    expect(Product::query()->count())->toBe(1);
    expect($product->fresh()->title)->toBe('Sort Injection Item');
});

test('sort stays in filter removal links but is not shown as an active filter', function () {
    $response = $this->get(route('home', [
        'q' => 'sofa',
        'min_price' => '10',
        'sort' => 'price_asc',
    ]))->assertOk();

    expect($response['clearPriceRangeUrl'])->toContain('sort=price_asc');
    expect($response['clearFiltersUrl'])->not->toContain('sort=');
    expect(collect($response['activeFilters'])->pluck('label')->implode(' '))->not->toContain('Sort');
});

test('sort alone keeps the catalog landing page', function () {
    $response = $this->get(route('home', ['sort' => 'price_asc']))->assertOk();

    expect($response['isLandingPage'])->toBeTrue();
});

test('empty search results offer translated guidance and a clear filters link', function () {
    $category = Category::factory()->create(['name' => 'Furniture']);

    Product::factory()->for($category, 'category')->approved()->create(['title' => 'Leather Sofa']);

    $response = $this->get(route('home', ['q' => 'missing sofa']))->assertOk();

    expect($response['products'])->toHaveCount(0);

    $response
        ->assertSee('No products found')
        ->assertSee('Try fewer words or check the spelling.')
        ->assertSee('Clear filters');
});

test('sort dropdown reflects the effective relevance sort for a search', function () {
    $category = Category::factory()->create(['name' => 'Furniture']);

    $titleMatch = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Leather Sofa', 'description' => 'A sturdy item.']);

    $descriptionMatch = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Handmade Chair', 'description' => 'A leather sofa inspired design.']);

    $response = $this->get(route('home', ['q' => 'leather sofa']))->assertOk();

    expect($response->viewData('filters')['sort'])->toBe('relevance');
    expect($response['products']->pluck('id')->all())->toBe([$titleMatch->id, $descriptionMatch->id]);

    $response->assertSee('<option value="relevance" selected>'.__('Most relevant').'</option>', false);
});

test('sort dropdown reflects the effective latest sort when relevance has no search term', function () {
    $category = Category::factory()->create(['name' => 'Furniture']);

    $older = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Older Item', 'created_at' => now()->subDay()]);

    $newer = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Newer Item', 'created_at' => now()]);

    $response = $this->get(route('home', ['sort' => 'relevance']))->assertOk();

    expect($response['products']->pluck('id')->all())->toBe([$newer->id, $older->id]);

    $response->assertSee('<option value="latest" selected>'.__('Latest').'</option>', false);
});

test('title ASC sorts catalog products alphabetically case-insensitively', function () {
    $category = Category::factory()->create(['name' => 'Furniture']);

    $banana = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'banana chair']);

    $apple = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Apple chair']);

    $cherry = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Cherry chair']);

    $response = $this->get(route('home', ['sort' => 'title_asc']))->assertOk();

    expect($response['products']->pluck('id')->all())->toBe([$apple->id, $banana->id, $cherry->id]);
    expect($response->viewData('filters')['sort'])->toBe('title_asc');

    $response->assertSee('<option value="title_asc" selected>'.__('Title: A to Z').'</option>', false);
});

test('title ASC breaks equal titles by newest creation time then highest id', function () {
    $older = Product::factory()
        ->approved()
        ->create(['title' => 'Same Title', 'created_at' => now()->subDay()]);

    $sameTime = now()->subHour();

    $firstNewer = Product::factory()
        ->approved()
        ->create(['title' => 'same title', 'created_at' => $sameTime]);

    $secondNewer = Product::factory()
        ->approved()
        ->create(['title' => 'SAME TITLE', 'created_at' => $sameTime]);

    $response = $this->get(route('home', ['sort' => 'title_asc']))->assertOk();

    expect($response['products']->pluck('id')->all())->toBe([$secondNewer->id, $firstNewer->id, $older->id]);
});

test('title ASC remains available for searches without changing relevance defaults', function () {
    $category = Category::factory()->create(['name' => 'Furniture']);

    $olderApple = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Apple Sofa', 'created_at' => now()->subDay()]);

    $newerZebra = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Zebra Sofa', 'created_at' => now()]);

    $sorted = $this->get(route('home', ['q' => 'sofa', 'sort' => 'title_asc']))->assertOk();

    expect($sorted['products']->pluck('id')->all())->toBe([$olderApple->id, $newerZebra->id]);

    $default = $this->get(route('home', ['q' => 'sofa']))->assertOk();

    expect($default->viewData('filters')['sort'])->toBe('relevance');
    expect($default['products']->pluck('id')->all())->toBe([$newerZebra->id, $olderApple->id]);
});

test('invalid title sort variants are rejected', function (string $sort) {
    $this->get(route('home', ['sort' => $sort]))
        ->assertRedirect(route('home'))
        ->assertSessionHasErrors('sort', 'The selected sort is invalid.');
})->with([
    'uppercase' => 'TITLE_ASC',
    'descending' => 'title_desc',
    'suffixed' => 'title_asc-extra',
]);
