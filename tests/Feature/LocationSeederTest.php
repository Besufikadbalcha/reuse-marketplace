<?php

use App\Models\Location;
use Database\Seeders\LocationSeeder;

test('the location seeder adds unique active Ethiopian cities', function () {
    $requiredCities = [
        'Addis Ababa',
        'Adama (Nazret)',
        'Dire Dawa',
        'Hawassa',
        'Bahir Dar',
        'Gondar',
        'Mekelle',
        'Jimma',
        'Dessie',
        'Harar',
    ];

    $requiredRegions = [
        'Addis Ababa',
        'Afar',
        'Amhara',
        'Benishangul-Gumuz',
        'Central Ethiopia',
        'Dire Dawa',
        'Gambela',
        'Harari',
        'Oromia',
        'Sidama',
        'Somali',
        'South Ethiopia',
        'Southwest Ethiopia',
        'Tigray',
    ];

    $this->seed(LocationSeeder::class);
    $seededCount = Location::query()->count();

    $this->seed(LocationSeeder::class);

    expect(Location::query()->whereIn('name', $requiredCities)->active()->count())->toBe(count($requiredCities))
        ->and(Location::query()->whereIn('name', $requiredRegions)->active()->count())->toBe(count($requiredRegions))
        ->and(Location::query()->count())->toBe($seededCount)
        ->and(Location::query()->active()->count())->toBe($seededCount);
});

test('the location seeder reactivates a seeded city without duplicating it', function () {
    Location::query()->create(['name' => 'Addis Ababa', 'is_active' => false]);

    $this->seed(LocationSeeder::class);

    $addisAbaba = Location::query()->where('name', 'Addis Ababa')->get();

    expect($addisAbaba)->toHaveCount(1)
        ->and($addisAbaba->first()->is_active)->toBeTrue();
});
