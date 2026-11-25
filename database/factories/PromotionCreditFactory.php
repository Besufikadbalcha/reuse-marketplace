<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPromotion;
use App\Models\PromotionCredit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromotionCredit>
 */
class PromotionCreditFactory extends Factory
{
    private const SECONDS_PER_DAY = 86400;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $secondsGranted = 3 * self::SECONDS_PER_DAY;

        return [
            'seller_id' => User::factory(),
            'source_promotion_id' => fn (array $attributes): Factory => $this->stoppedSoldPromotion($attributes['seller_id']),
            'source_order_item_id' => fn (array $attributes): Factory => $this->soldOrderItem($attributes['seller_id']),
            'seconds_granted' => $secondsGranted,
            'seconds_remaining' => $secondsGranted,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (PromotionCredit $credit): void {
            $sourcePromotion = $credit->sourcePromotion;
            $sourceOrderItem = $credit->sourceOrderItem;

            if ($sourcePromotion === null || $sourceOrderItem === null) {
                return;
            }

            if ((int) $sourceOrderItem->product_id !== (int) $sourcePromotion->product_id) {
                $sourceOrderItem->product_id = $sourcePromotion->product_id;
                $sourceOrderItem->saveQuietly();
            }
        });
    }

    public function fromEarlySale(): static
    {
        return $this->state(fn (array $attributes): array => [
            'source_promotion_id' => fn (array $resolvedAttributes): Factory => $this->stoppedSoldPromotion($resolvedAttributes['seller_id']),
            'source_order_item_id' => fn (array $resolvedAttributes): Factory => $this->soldOrderItem($resolvedAttributes['seller_id']),
        ]);
    }

    public function partiallyUsed(int $secondsUsed): static
    {
        return $this->state(function (array $attributes) use ($secondsUsed): array {
            $secondsGranted = (int) ($attributes['seconds_granted'] ?? 3 * self::SECONDS_PER_DAY);
            $boundedSecondsUsed = min(max(1, $secondsUsed), $secondsGranted);

            return [
                'seconds_remaining' => $secondsGranted - $boundedSecondsUsed,
            ];
        });
    }

    public function fullyUsed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'seconds_remaining' => 0,
        ]);
    }

    /**
     * @return Factory<ProductPromotion>
     */
    private function stoppedSoldPromotion(mixed $seller): Factory
    {
        return ProductPromotion::factory()
            ->stoppedSold()
            ->state([
                'seller_id' => $seller,
                'product_id' => Product::factory()->approved()->state([
                    'seller_id' => $seller,
                ]),
            ]);
    }

    /**
     * @return Factory<OrderItem>
     */
    private function soldOrderItem(mixed $seller): Factory
    {
        return OrderItem::factory()
            ->completedForSeller()
            ->state([
                'product_id' => Product::factory()->approved()->state([
                    'seller_id' => $seller,
                ]),
            ]);
    }
}
