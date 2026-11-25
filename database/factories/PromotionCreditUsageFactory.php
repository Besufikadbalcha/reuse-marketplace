<?php

namespace Database\Factories;

use App\Models\ProductPromotion;
use App\Models\PromotionCredit;
use App\Models\PromotionCreditUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromotionCreditUsage>
 */
class PromotionCreditUsageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'credit_id' => PromotionCredit::factory(),
            'promotion_id' => ProductPromotion::factory()->active(),
            'seconds_used' => 86400,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (PromotionCreditUsage $usage): void {
            $credit = $usage->credit;
            $promotion = $usage->promotion;

            if ($credit === null || $promotion === null) {
                return;
            }

            $sourcePromotion = $credit->sourcePromotion;
            $productId = $sourcePromotion?->product_id;

            if ((int) $promotion->seller_id !== (int) $credit->seller_id || ($productId !== null && (int) $promotion->product_id !== (int) $productId)) {
                $promotion->seller_id = $credit->seller_id;

                if ($productId !== null) {
                    $promotion->product_id = $productId;
                    $promotion->open_product_id = $promotion->isOpen() ? $productId : null;
                }

                $promotion->saveQuietly();
            }
        });
    }

    public function usingSeconds(int $seconds): static
    {
        return $this->state(fn (array $attributes): array => [
            'seconds_used' => max(1, $seconds),
        ]);
    }

    public function forCredit(PromotionCredit $credit): static
    {
        return $this->for($credit, 'credit');
    }

    public function forPromotion(ProductPromotion $promotion): static
    {
        return $this->for($promotion, 'promotion');
    }
}
