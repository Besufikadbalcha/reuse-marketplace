<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPromotion;
use App\Models\PromotionPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPromotion>
 */
class ProductPromotionFactory extends Factory
{
    private const SECONDS_PER_DAY = 86400;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_id' => User::factory(),
            'product_id' => fn (array $attributes): Factory => Product::factory()->approved()->state([
                'seller_id' => $attributes['seller_id'],
            ]),
            'package_id' => PromotionPackage::factory(),
            'funding_type' => ProductPromotion::FUNDING_PAYMENT,
            'status' => ProductPromotion::STATUS_PAYMENT_PENDING,
            'open_product_id' => function (array $attributes): ?int {
                return in_array($attributes['status'], ProductPromotion::OPEN_STATUSES, true)
                    ? (int) $attributes['product_id']
                    : null;
            },
            'package_code_snapshot' => PromotionPackage::CODE_BASIC,
            'price_minor_snapshot' => 1000,
            'duration_days_snapshot' => 3,
            'priority_snapshot' => PromotionPackage::PRIORITY_BASIC,
            'starts_at' => null,
            'ends_at' => null,
            'paused_at' => null,
            'remaining_seconds' => null,
            'stopped_at' => null,
            'stopped_by_order_id' => null,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (ProductPromotion $promotion): void {
            $this->syncPromotion($promotion);
        });
    }

    public function paymentPending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductPromotion::STATUS_PAYMENT_PENDING,
        ]);
    }

    public function paymentRejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductPromotion::STATUS_PAYMENT_REJECTED,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductPromotion::STATUS_ACTIVE,
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductPromotion::STATUS_PAUSED,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductPromotion::STATUS_EXPIRED,
        ]);
    }

    public function stoppedSold(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductPromotion::STATUS_STOPPED_SOLD,
        ]);
    }

    public function stoppedByOrder(Order $order): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductPromotion::STATUS_STOPPED_SOLD,
            'stopped_by_order_id' => $order->id,
        ]);
    }

    public function creditFunded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'funding_type' => ProductPromotion::FUNDING_CREDIT,
        ]);
    }

    private function syncPromotion(ProductPromotion $promotion): void
    {
        $package = PromotionPackage::query()->findOrFail($promotion->package_id);

        $promotion->package_code_snapshot = $package->code;
        $promotion->price_minor_snapshot = $package->price_minor;
        $promotion->duration_days_snapshot = $package->duration_days;
        $promotion->priority_snapshot = $package->priority;
        $promotion->open_product_id = $promotion->isOpen() ? $promotion->product_id : null;

        $this->syncLifecycle($promotion);

        $promotion->saveQuietly();
    }

    private function syncLifecycle(ProductPromotion $promotion): void
    {
        $durationSeconds = $promotion->duration_days_snapshot * self::SECONDS_PER_DAY;

        switch ($promotion->status) {
            case ProductPromotion::STATUS_ACTIVE:
                $promotion->starts_at ??= now()->subMinute();
                $promotion->ends_at ??= now()->addSeconds($durationSeconds);
                $promotion->paused_at = null;
                $promotion->stopped_at = null;
                $promotion->stopped_by_order_id = null;
                $promotion->remaining_seconds = null;

                break;
            case ProductPromotion::STATUS_PAUSED:
                $promotion->starts_at ??= now()->subMinute();
                $promotion->ends_at ??= now()->addSeconds($durationSeconds);
                $promotion->paused_at ??= now();
                $promotion->stopped_at = null;
                $promotion->stopped_by_order_id = null;
                $promotion->remaining_seconds ??= $durationSeconds;

                break;
            case ProductPromotion::STATUS_EXPIRED:
                $promotion->starts_at ??= now()->subDays($promotion->duration_days_snapshot + 1);
                $promotion->ends_at ??= now()->subDay();
                $promotion->paused_at = null;
                $promotion->stopped_at = null;
                $promotion->stopped_by_order_id = null;
                $promotion->remaining_seconds = null;

                break;
            case ProductPromotion::STATUS_STOPPED_SOLD:
                $promotion->starts_at ??= now()->subMinute();
                $promotion->ends_at ??= now()->addSeconds($durationSeconds);
                $promotion->paused_at = null;
                $promotion->stopped_at ??= now();
                $promotion->remaining_seconds ??= $durationSeconds;

                break;
            default:
                $promotion->starts_at = null;
                $promotion->ends_at = null;
                $promotion->paused_at = null;
                $promotion->stopped_at = null;
                $promotion->stopped_by_order_id = null;
                $promotion->remaining_seconds = null;
        }
    }
}
