<?php

namespace Database\Seeders;

use App\Models\PromotionPackage;
use Illuminate\Database\Seeder;
use RuntimeException;

class PromotionPackageSeeder extends Seeder
{
    private const PACKAGES = [
        [
            'code' => PromotionPackage::CODE_BASIC,
            'name' => 'Basic Promotion',
            'duration_days' => 3,
            'priority' => PromotionPackage::PRIORITY_BASIC,
            'sort_order' => 1,
        ],
        [
            'code' => PromotionPackage::CODE_STANDARD,
            'name' => 'Standard Promotion',
            'duration_days' => 7,
            'priority' => PromotionPackage::PRIORITY_STANDARD,
            'sort_order' => 2,
        ],
        [
            'code' => PromotionPackage::CODE_PREMIUM,
            'name' => 'Premium Promotion',
            'duration_days' => 14,
            'priority' => PromotionPackage::PRIORITY_PREMIUM,
            'sort_order' => 3,
        ],
    ];

    private const PRICE_CONFIG_KEYS = [
        PromotionPackage::CODE_BASIC => 'marketplace.promotion_package_prices_minor.basic',
        PromotionPackage::CODE_STANDARD => 'marketplace.promotion_package_prices_minor.standard',
        PromotionPackage::CODE_PREMIUM => 'marketplace.promotion_package_prices_minor.premium',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $prices = [];

        foreach (self::PACKAGES as $package) {
            $prices[$package['code']] = $this->resolvePriceMinor($package['code']);
        }

        foreach (self::PACKAGES as $package) {
            $record = PromotionPackage::query()->firstOrCreate(
                ['code' => $package['code']],
                [
                    'name' => $package['name'],
                    'price_minor' => $prices[$package['code']],
                    'duration_days' => $package['duration_days'],
                    'is_active' => true,
                    'sort_order' => $package['sort_order'],
                ],
            );

            if ((int) $record->priority !== $package['priority']) {
                $record->priority = $package['priority'];
                $record->save();
            }
        }
    }

    private function resolvePriceMinor(string $code): int
    {
        $configKey = self::PRICE_CONFIG_KEYS[$code];
        $priceMinor = filter_var(config($configKey), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($priceMinor === false) {
            throw new RuntimeException("Missing or invalid promotion package price configuration [{$configKey}]; expected a positive integer in minor currency units.");
        }

        return $priceMinor;
    }
}
