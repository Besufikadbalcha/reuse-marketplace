<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    private const CITIES = [
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
        'Bishoftu',
        'Debre Markos',
        'Debre Birhan',
        'Asella',
        'Nekemte',
        'Jijiga',
        'Gambela',
        'Batu (Ziway)',
        'Shashemene',
        'Wolaita Sodo',
        'Dilla',
        'Kombolcha',
        'Axum',
        'Lalibela',
        'Debre Tabor',
        'Woldia',
        'Semera',
        'Hosaena',
    ];

    private const REGIONS = [
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

    public function run(): void
    {
        foreach (array_unique([...self::CITIES, ...self::REGIONS]) as $locationName) {
            Location::query()->updateOrCreate(
                ['name' => $locationName],
                ['is_active' => true],
            );
        }
    }
}
