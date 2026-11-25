<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProductSeeder extends Seeder
{
    private const SEEDED_IMAGE_COUNT = 2;

    private const PLACEHOLDER_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        $seller = User::query()->firstOrCreate(
            ['email' => 'seller@example.com'],
            ['name' => 'Demo Seller', 'password' => Hash::make('password')],
        );

        $categoryIds = Category::query()->whereIn('slug', ['bicycles', 'mobile-phones', 'furniture'])->get()->keyBy('slug');

        $products = [
            ['slug' => 'city-bicycle', 'title' => 'City Bicycle', 'description' => 'A reliable commuter bicycle.', 'price_minor' => 1250000, 'condition' => Product::CONDITION_GOOD, 'category_slug' => 'bicycles'],
            ['slug' => 'smart-phone', 'title' => 'Smart Phone', 'description' => 'A clean phone with a recent battery replacement.', 'price_minor' => 890000, 'condition' => Product::CONDITION_LIKE_NEW, 'category_slug' => 'mobile-phones'],
            ['slug' => 'wooden-desk', 'title' => 'Wooden Desk', 'description' => 'A sturdy desk in good condition.', 'price_minor' => 650000, 'condition' => Product::CONDITION_FAIR, 'category_slug' => 'furniture'],
        ];

        foreach ($products as $product) {
            $record = Product::query()->updateOrCreate(
                ['slug' => $product['slug']],
                [
                    'seller_id' => $seller->id,
                    'category_id' => $categoryIds->get($product['category_slug'])->id,
                    'title' => $product['title'],
                    'description' => $product['description'],
                    'price_minor' => $product['price_minor'],
                    'condition' => $product['condition'],
                    'status' => Product::STATUS_APPROVED,
                    'rejection_reason' => null,
                ],
            );

            $this->seedImages($record);
        }
    }

    private function seedImages(Product $product): void
    {
        $legacyImages = $product->images()
            ->where('path', 'like', 'products/%')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach (range(1, self::SEEDED_IMAGE_COUNT) as $imageNumber) {
            $path = 'seller-products/'.$product->seller_id.'/'.$product->slug.'-'.$imageNumber.'.png';

            Storage::disk('local')->put($path, base64_decode(self::PLACEHOLDER_PNG));

            $image = $product->images()->where('path', $path)->first();

            if ($image === null) {
                $image = $legacyImages->shift();
            }

            if ($image !== null) {
                $image->update([
                    'path' => $path,
                    'sort_order' => $imageNumber - 1,
                ]);

                continue;
            }

            $product->images()->create([
                'path' => $path,
                'sort_order' => $imageNumber - 1,
            ]);
        }
    }
}
