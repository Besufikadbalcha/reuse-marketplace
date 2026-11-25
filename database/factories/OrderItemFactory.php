<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_title' => fake()->sentence(3),
            'price_minor' => fake()->numberBetween(100, 100000),
            'quantity' => 1,
            'seller_status' => OrderItem::SELLER_STATUS_PENDING,
        ];
    }

    public function confirmedForSeller(): static
    {
        return $this->state(fn (array $attributes): array => [
            'seller_status' => OrderItem::SELLER_STATUS_CONFIRMED,
        ]);
    }

    public function completedForSeller(): static
    {
        return $this->state(fn (array $attributes): array => [
            'seller_status' => OrderItem::SELLER_STATUS_COMPLETED,
        ]);
    }
}
