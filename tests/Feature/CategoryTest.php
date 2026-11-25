<?php

use App\Models\Category;
use App\Models\Product;

test('category lists its products', function () {
    $category = Category::factory()->create();

    Product::factory()->count(2)->for($category)->create();
    Product::factory()->create();

    expect($category->products()->pluck('id')->all())
        ->toHaveCount(2);
});

test('active category scope excludes inactive categories', function () {
    $active = Category::factory()->create();
    Category::factory()->inactive()->create();

    expect(Category::active()->pluck('id')->all())->toBe([$active->id]);
});

test('a public category archive lists only approved products in that active category', function () {
    $category = Category::factory()->create([
        'name' => 'Bicycles',
        'description' => 'Two wheel finds.',
    ]);
    $otherCategory = Category::factory()->create(['name' => 'Furniture']);

    Product::factory()->for($category, 'category')->approved()->create(['title' => 'City Bicycle']);
    Product::factory()->for($category, 'category')->rejected()->create(['title' => 'Rejected Bicycle']);
    Product::factory()->for($otherCategory, 'category')->approved()->create(['title' => 'Oak Desk']);

    $this->get(route('categories.show', $category))
        ->assertOk()
        ->assertViewIs('products.index')
        ->assertSee('City Bicycle')
        ->assertSee('Two wheel finds.')
        ->assertSee('action="'.route('categories.show', $category).'"', false)
        ->assertDontSee('Rejected Bicycle')
        ->assertDontSee('Oak Desk');
});

test('removing a filter on a category archive keeps the category and remaining filters', function () {
    $category = Category::factory()->create(['name' => 'Bicycles']);

    $response = $this->get(route('categories.show', [
        $category,
        'q' => 'commuter',
        'condition' => Product::CONDITION_GOOD,
    ]));

    $response->assertOk()
        ->assertSee('href="'.e(route('categories.show', [$category, 'condition' => Product::CONDITION_GOOD])).'"', false)
        ->assertSee('href="'.e(route('categories.show', [$category, 'q' => 'commuter'])).'"', false);

    expect(collect($response->viewData('activeFilters'))->pluck('url', 'label')->all())->toBe([
        'Category: Bicycles' => route('home', ['q' => 'commuter', 'condition' => Product::CONDITION_GOOD]),
        'Search: commuter' => route('categories.show', [$category, 'condition' => Product::CONDITION_GOOD]),
        'Condition: Good' => route('categories.show', [$category, 'q' => 'commuter']),
    ]);
});

test('an inactive category archive returns 404', function () {
    $category = Category::factory()->inactive()->create();

    $this->get(route('categories.show', $category))->assertNotFound();
});

test('category archive filters stay scoped to the category', function () {
    $category = Category::factory()->create(['name' => 'Bicycles']);

    Product::factory()->for($category, 'category')->approved()->create([
        'title' => 'Road Bike',
        'description' => 'Fast commuter.',
        'condition' => Product::CONDITION_GOOD,
    ]);
    Product::factory()->for($category, 'category')->approved()->create([
        'title' => 'Mountain Bike',
        'condition' => Product::CONDITION_FAIR,
    ]);

    $this->get(route('categories.show', [
        $category,
        'q' => 'commuter',
        'condition' => Product::CONDITION_GOOD,
    ]))
        ->assertOk()
        ->assertSee('Category: Bicycles')
        ->assertSee('Road Bike')
        ->assertDontSee('Mountain Bike');
});
