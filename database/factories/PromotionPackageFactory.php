<?php

namespace Database\Factories;

use App\Models\PromotionPackage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends Factory<PromotionPackage>
 */
class PromotionPackageFactory extends Factory
{
    /** @var array<string, int> */
    private const DURATION_DAYS = [
        PromotionPackage::CODE_BASIC => 3,
        PromotionPackage::CODE_STANDARD => 7,
        PromotionPackage::CODE_PREMIUM => 14,
    ];

    /** @var array<string, int> */
    private const SORT_ORDERS = [
        PromotionPackage::CODE_BASIC => 1,
        PromotionPackage::CODE_STANDARD => 2,
        PromotionPackage::CODE_PREMIUM => 3,
    ];

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->prependState(new Sequence(
            ['code' => PromotionPackage::CODE_BASIC],
            ['code' => PromotionPackage::CODE_STANDARD],
            ['code' => PromotionPackage::CODE_PREMIUM],
        ));
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => PromotionPackage::CODE_BASIC,
            'name' => fn (array $attributes): string => ucfirst((string) $attributes['code']).' Promotion',
            'price_minor' => fake()->numberBetween(100, 100000),
            'duration_days' => fn (array $attributes): int => self::DURATION_DAYS[$attributes['code']],
            'priority' => fn (array $attributes): int => PromotionPackage::priorityFor((string) $attributes['code']),
            'is_active' => true,
            'sort_order' => fn (array $attributes): int => self::SORT_ORDERS[$attributes['code']],
        ];
    }

    public function basic(): static
    {
        return $this->state(['code' => PromotionPackage::CODE_BASIC]);
    }

    public function standard(): static
    {
        return $this->state(['code' => PromotionPackage::CODE_STANDARD]);
    }

    public function premium(): static
    {
        return $this->state(['code' => PromotionPackage::CODE_PREMIUM]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
