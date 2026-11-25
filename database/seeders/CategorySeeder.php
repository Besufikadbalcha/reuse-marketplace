<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Bicycles', 'slug' => 'bicycles', 'description' => 'Bicycles and accessories.', 'is_active' => true],
            ['name' => 'Mobile Phones', 'slug' => 'mobile-phones', 'description' => 'Phones and mobile accessories.', 'is_active' => true],
            ['name' => 'Furniture', 'slug' => 'furniture', 'description' => 'Home and office furniture.', 'is_active' => true],
            ['name' => 'Books', 'slug' => 'books', 'description' => 'New and second-hand books.', 'is_active' => true],
            ['name' => 'Clothing', 'slug' => 'clothing', 'description' => 'Clothing and footwear.', 'is_active' => true],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
