<?php

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;

test('facet counts reflect available products for each option', function () {
    $chairs = Category::factory()->create(['name' => 'Chairs']);
    $tables = Category::factory()->create(['name' => 'Tables']);
    $addisAbaba = Location::query()->create(['name' => 'Addis Ababa']);
    $bole = Location::query()->create(['name' => 'Bole']);

    Product::factory()->for($chairs, 'category')->approved()->create([
        'title' => 'Chair One',
        'condition' => Product::CONDITION_GOOD,
        'location_id' => $addisAbaba->id,
    ]);
    Product::factory()->for($chairs, 'category')->approved()->create([
        'title' => 'Chair Two',
        'condition' => Product::CONDITION_LIKE_NEW,
        'location_id' => $addisAbaba->id,
    ]);
    Product::factory()->for($tables, 'category')->approved()->create([
        'title' => 'Table One',
        'condition' => Product::CONDITION_GOOD,
        'location_id' => $bole->id,
    ]);

    $response = $this->get(route('home'))->assertOk();

    expect($response->viewData('categoryCounts')->all())->toBe([
        $chairs->id => 2,
        $tables->id => 1,
    ]);
    expect($response->viewData('conditionCounts')->all())->toBe([
        Product::CONDITION_NEW => 0,
        Product::CONDITION_LIKE_NEW => 1,
        Product::CONDITION_GOOD => 2,
        Product::CONDITION_FAIR => 0,
    ]);
    expect($response->viewData('locationCounts')->all())->toBe([
        $addisAbaba->id => 2,
        $bole->id => 1,
    ]);

    $response
        ->assertSee('>Chairs (2)</option>', false)
        ->assertSee('>Good (2)</option>', false)
        ->assertSee('data-location-name="Addis Ababa"', false);
});

test('facet counts exclude unavailable products', function () {
    $category = Category::factory()->create(['name' => 'Availability Category']);
    $location = Location::query()->create(['name' => 'Sale City']);

    Product::factory()->for($category, 'category')->approved()->create([
        'title' => 'Available Item',
        'condition' => Product::CONDITION_GOOD,
        'location_id' => $location->id,
    ]);

    $cancelledOrderProduct = Product::factory()->for($category, 'category')->approved()->create([
        'title' => 'Cancelled Order Item',
        'condition' => Product::CONDITION_FAIR,
        'location_id' => $location->id,
    ]);
    $cancelledOrder = Order::factory()->for(User::factory()->create(), 'buyer')->create([
        'status' => Order::STATUS_CANCELLED,
    ]);
    OrderItem::factory()->for($cancelledOrder)->create(['product_id' => $cancelledOrderProduct->id]);

    Product::factory()->for($category, 'category')->create([
        'title' => 'Pending Item',
        'condition' => Product::CONDITION_NEW,
        'location_id' => $location->id,
    ]);
    Product::factory()->for($category, 'category')->rejected()->create([
        'title' => 'Rejected Item',
        'condition' => Product::CONDITION_FAIR,
        'location_id' => $location->id,
    ]);

    $reservedProduct = Product::factory()->for($category, 'category')->approved()->create([
        'title' => 'Reserved Item',
        'condition' => Product::CONDITION_GOOD,
        'location_id' => $location->id,
    ]);
    Product::query()->whereKey($reservedProduct->id)->update(['reservation_token' => 'RM-FACET001']);

    $soldProduct = Product::factory()->for($category, 'category')->approved()->create([
        'title' => 'Sold Item',
        'condition' => Product::CONDITION_GOOD,
        'location_id' => $location->id,
    ]);
    $order = Order::factory()->for(User::factory()->create(), 'buyer')->create();
    OrderItem::factory()->for($order)->create(['product_id' => $soldProduct->id]);

    $response = $this->get(route('home'))->assertOk();

    expect($response->viewData('categoryCounts')->get($category->id))->toBe(2);
    expect($response->viewData('conditionCounts')->all())->toBe([
        Product::CONDITION_NEW => 0,
        Product::CONDITION_LIKE_NEW => 0,
        Product::CONDITION_GOOD => 1,
        Product::CONDITION_FAIR => 1,
    ]);
    expect($response->viewData('locationCounts')->get($location->id))->toBe(2);
});

test('facet counts keep zero count active options and omit inactive options', function () {
    $emptyCategory = Category::factory()->create(['name' => 'Empty Category']);
    $hiddenCategory = Category::factory()->inactive()->create(['name' => 'Hidden Category']);
    $emptyLocation = Location::query()->create(['name' => 'Empty Location']);
    $hiddenLocation = Location::query()->create(['name' => 'Hidden Location', 'is_active' => false]);

    Product::factory()->for($hiddenCategory, 'category')->approved()->create([
        'title' => 'Hidden Category Item',
        'location_id' => $hiddenLocation->id,
    ]);

    $response = $this->get(route('home'))->assertOk();

    expect($response->viewData('categoryCounts')->all())->toBe([$emptyCategory->id => 0]);
    expect($response->viewData('locationCounts')->all())->toBe([$emptyLocation->id => 0]);

    $response
        ->assertSee('Empty Category (0)', false)
        ->assertSee('data-location-name="Empty Location"', false)
        ->assertDontSee('Hidden Category')
        ->assertDontSee('data-location-name="Hidden Location"', false);
});

test('category facet counts ignore the selected category but retain other filters', function () {
    $alphaCategory = Category::factory()->create(['name' => 'Alpha Category']);
    $betaCategory = Category::factory()->create(['name' => 'Beta Category']);
    $alphaCity = Location::query()->create(['name' => 'Alpha City']);
    $betaCity = Location::query()->create(['name' => 'Beta City']);

    Product::factory()->for($alphaCategory, 'category')->approved()->create([
        'title' => 'Alpha Good Item',
        'condition' => Product::CONDITION_GOOD,
        'location_id' => $alphaCity->id,
    ]);
    Product::factory()->for($betaCategory, 'category')->approved()->create([
        'title' => 'Beta Good Item',
        'condition' => Product::CONDITION_GOOD,
        'location_id' => $betaCity->id,
    ]);
    Product::factory()->for($betaCategory, 'category')->approved()->create([
        'title' => 'Beta Fair Item',
        'condition' => Product::CONDITION_FAIR,
        'location_id' => $betaCity->id,
    ]);

    $response = $this->get(route('home', ['category' => $alphaCategory->id]))->assertOk();

    expect($response->viewData('categoryCounts')->all())->toBe([
        $alphaCategory->id => 1,
        $betaCategory->id => 2,
    ]);
    expect($response->viewData('conditionCounts')->all())->toBe([
        Product::CONDITION_NEW => 0,
        Product::CONDITION_LIKE_NEW => 0,
        Product::CONDITION_GOOD => 1,
        Product::CONDITION_FAIR => 0,
    ]);
    expect($response->viewData('locationCounts')->all())->toBe([
        $alphaCity->id => 1,
        $betaCity->id => 0,
    ]);
});

test('condition facet counts ignore the selected condition but retain other filters', function () {
    $alphaCategory = Category::factory()->create(['name' => 'Alpha Category']);
    $betaCategory = Category::factory()->create(['name' => 'Beta Category']);
    $alphaCity = Location::query()->create(['name' => 'Alpha City']);
    $betaCity = Location::query()->create(['name' => 'Beta City']);

    Product::factory()->for($alphaCategory, 'category')->approved()->create([
        'title' => 'Alpha Good Item',
        'condition' => Product::CONDITION_GOOD,
        'location_id' => $alphaCity->id,
    ]);
    Product::factory()->for($betaCategory, 'category')->approved()->create([
        'title' => 'Beta Good Item',
        'condition' => Product::CONDITION_GOOD,
        'location_id' => $betaCity->id,
    ]);
    Product::factory()->for($betaCategory, 'category')->approved()->create([
        'title' => 'Beta Fair Item',
        'condition' => Product::CONDITION_FAIR,
        'location_id' => $betaCity->id,
    ]);

    $response = $this->get(route('home', ['condition' => Product::CONDITION_GOOD]))->assertOk();

    expect($response->viewData('conditionCounts')->all())->toBe([
        Product::CONDITION_NEW => 0,
        Product::CONDITION_LIKE_NEW => 0,
        Product::CONDITION_GOOD => 2,
        Product::CONDITION_FAIR => 1,
    ]);
    expect($response->viewData('categoryCounts')->all())->toBe([
        $alphaCategory->id => 1,
        $betaCategory->id => 1,
    ]);
    expect($response->viewData('locationCounts')->all())->toBe([
        $alphaCity->id => 1,
        $betaCity->id => 1,
    ]);
});

test('location facet counts ignore the selected location but retain other filters', function () {
    $alphaCategory = Category::factory()->create(['name' => 'Alpha Category']);
    $betaCategory = Category::factory()->create(['name' => 'Beta Category']);
    $alphaCity = Location::query()->create(['name' => 'Alpha City']);
    $betaCity = Location::query()->create(['name' => 'Beta City']);

    Product::factory()->for($alphaCategory, 'category')->approved()->create([
        'title' => 'Alpha Good Item',
        'condition' => Product::CONDITION_GOOD,
        'location_id' => $alphaCity->id,
    ]);
    Product::factory()->for($betaCategory, 'category')->approved()->create([
        'title' => 'Beta Fair Item',
        'condition' => Product::CONDITION_FAIR,
        'location_id' => $betaCity->id,
    ]);
    Product::factory()->for($betaCategory, 'category')->approved()->create([
        'title' => 'Beta Good Item',
        'condition' => Product::CONDITION_GOOD,
        'location_id' => $betaCity->id,
    ]);

    $response = $this->get(route('home', ['location' => 'Alpha City']))->assertOk();

    expect($response->viewData('locationCounts')->all())->toBe([
        $alphaCity->id => 1,
        $betaCity->id => 2,
    ]);
    expect($response->viewData('categoryCounts')->all())->toBe([
        $alphaCategory->id => 1,
        $betaCategory->id => 0,
    ]);
    expect($response->viewData('conditionCounts')->all())->toBe([
        Product::CONDITION_NEW => 0,
        Product::CONDITION_LIKE_NEW => 0,
        Product::CONDITION_GOOD => 1,
        Product::CONDITION_FAIR => 0,
    ]);
});

test('search and price filters affect every facet count', function () {
    $seatCategory = Category::factory()->create(['name' => 'Seat Category']);
    $deskCategory = Category::factory()->create(['name' => 'Desk Category']);
    $seatCity = Location::query()->create(['name' => 'Seat City']);
    $deskCity = Location::query()->create(['name' => 'Desk City']);

    Product::factory()->for($seatCategory, 'category')->approved()->create([
        'title' => 'Leather Sofa',
        'condition' => Product::CONDITION_GOOD,
        'location_id' => $seatCity->id,
        'price_minor' => 5000,
    ]);
    Product::factory()->for($deskCategory, 'category')->approved()->create([
        'title' => 'Leather Chair',
        'condition' => Product::CONDITION_FAIR,
        'location_id' => $deskCity->id,
        'price_minor' => 20000,
    ]);
    Product::factory()->for($deskCategory, 'category')->approved()->create([
        'title' => 'Wood Desk',
        'condition' => Product::CONDITION_GOOD,
        'location_id' => $deskCity->id,
        'price_minor' => 5000,
    ]);

    $response = $this->get(route('home', ['q' => 'leather', 'min_price' => '100']))->assertOk();

    expect($response->viewData('categoryCounts')->all())->toBe([
        $deskCategory->id => 1,
        $seatCategory->id => 0,
    ]);
    expect($response->viewData('conditionCounts')->all())->toBe([
        Product::CONDITION_NEW => 0,
        Product::CONDITION_LIKE_NEW => 0,
        Product::CONDITION_GOOD => 0,
        Product::CONDITION_FAIR => 1,
    ]);
    expect($response->viewData('locationCounts')->all())->toBe([
        $deskCity->id => 1,
        $seatCity->id => 0,
    ]);
});

test('category archive scopes condition and location counts and hides category counts', function () {
    $archiveCategory = Category::factory()->create(['name' => 'Archive Category']);
    $otherCategory = Category::factory()->create(['name' => 'Other Category']);
    $archiveCity = Location::query()->create(['name' => 'Archive City']);
    $otherCity = Location::query()->create(['name' => 'Other City']);

    Product::factory()->for($archiveCategory, 'category')->approved()->create([
        'title' => 'Archive Item',
        'condition' => Product::CONDITION_GOOD,
        'location_id' => $archiveCity->id,
    ]);
    Product::factory()->for($otherCategory, 'category')->approved()->create([
        'title' => 'Other Item',
        'condition' => Product::CONDITION_FAIR,
        'location_id' => $otherCity->id,
    ]);

    $archiveUrl = route('categories.show', $archiveCategory).'?category='.$otherCategory->id;

    $response = $this->get($archiveUrl)->assertOk();

    expect($response->viewData('categoryCounts'))->toBeEmpty();
    expect($response->viewData('conditionCounts')->all())->toBe([
        Product::CONDITION_NEW => 0,
        Product::CONDITION_LIKE_NEW => 0,
        Product::CONDITION_GOOD => 1,
        Product::CONDITION_FAIR => 0,
    ]);
    expect($response->viewData('locationCounts')->all())->toBe([
        $archiveCity->id => 1,
        $otherCity->id => 0,
    ]);

    $response->assertDontSee('All categories', false);
});

test('facet counts stay unchanged across sorting and pagination', function () {
    $largeCategory = Category::factory()->create(['name' => 'Large Category']);
    $smallCategory = Category::factory()->create(['name' => 'Small Category']);
    $busyCity = Location::query()->create(['name' => 'Busy City']);
    $quietCity = Location::query()->create(['name' => 'Quiet City']);

    foreach (range(1, 13) as $number) {
        Product::factory()->for($largeCategory, 'category')->approved()->create([
            'title' => "Large Item {$number}",
            'condition' => Product::CONDITION_GOOD,
            'location_id' => $busyCity->id,
        ]);
    }

    foreach (range(1, 2) as $number) {
        Product::factory()->for($smallCategory, 'category')->approved()->create([
            'title' => "Small Item {$number}",
            'condition' => Product::CONDITION_FAIR,
            'location_id' => $quietCity->id,
        ]);
    }

    $firstPage = $this->get(route('home', ['sort' => 'title_asc']))->assertOk();
    $secondPage = $this->get(route('home', ['sort' => 'title_asc', 'page' => 2]))->assertOk();

    foreach (['categoryCounts', 'conditionCounts', 'locationCounts'] as $facet) {
        expect($firstPage->viewData($facet)->all())->toBe($secondPage->viewData($facet)->all());
    }

    expect($firstPage->viewData('categoryCounts')->all())->toBe([
        $largeCategory->id => 13,
        $smallCategory->id => 2,
    ]);
    expect($firstPage->viewData('conditionCounts')->all())->toBe([
        Product::CONDITION_NEW => 0,
        Product::CONDITION_LIKE_NEW => 0,
        Product::CONDITION_GOOD => 13,
        Product::CONDITION_FAIR => 2,
    ]);
    expect($firstPage->viewData('locationCounts')->all())->toBe([
        $busyCity->id => 13,
        $quietCity->id => 2,
    ]);
});

test('combined filters still select the same products while facet counts render', function () {
    $bicycles = Category::factory()->create(['name' => 'Working Bicycles']);
    $desks = Category::factory()->create(['name' => 'Working Desks']);
    $workingCity = Location::query()->create(['name' => 'Working City']);
    $otherCity = Location::query()->create(['name' => 'Other City']);

    $match = Product::factory()->for($bicycles, 'category')->approved()->create([
        'title' => 'Road Bike',
        'condition' => Product::CONDITION_GOOD,
        'location_id' => $workingCity->id,
        'price_minor' => 10000,
    ]);
    Product::factory()->for($desks, 'category')->approved()->create([
        'title' => 'Wood Desk',
        'condition' => Product::CONDITION_FAIR,
        'location_id' => $otherCity->id,
        'price_minor' => 10000,
    ]);

    $response = $this->get(route('home', [
        'q' => 'Road',
        'category' => $bicycles->id,
        'condition' => Product::CONDITION_GOOD,
        'location' => 'Working City',
        'min_price' => '50',
        'max_price' => '200',
    ]))->assertOk();

    expect($response['products']->pluck('id')->all())->toBe([$match->id]);
    expect($response->viewData('categoryCounts')->get($bicycles->id))->toBe(1);
    expect($response->viewData('categoryCounts')->get($desks->id))->toBe(0);

    $response
        ->assertSee('Category: Working Bicycles')
        ->assertSee('data-location-name="Working City"', false)
        ->assertSee('<option value="">'.__('All categories').'</option>', false);
});
