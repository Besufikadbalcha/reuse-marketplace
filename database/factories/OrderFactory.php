<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_id' => User::factory(),
            'order_number' => 'RM-'.fake()->unique()->numerify('########'),
            'status' => Order::STATUS_PENDING,
            'total_amount_minor' => fake()->numberBetween(100, 100000),
            'fulfillment_method' => Order::FULFILLMENT_PICKUP,
            'contact_name' => null,
            'contact_phone' => null,
            'delivery_address' => null,
        ];
    }

    public function cashOnDelivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'fulfillment_method' => Order::FULFILLMENT_CASH_ON_DELIVERY,
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->phoneNumber(),
            'delivery_address' => fake()->address(),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_CONFIRMED,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_COMPLETED,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_CANCELLED,
        ]);
    }
}
