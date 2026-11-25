<?php

return [

    'currency' => env('MARKETPLACE_CURRENCY', 'ETB'),

    'admin_email' => env('MARKETPLACE_ADMIN_EMAIL'),

    'allow_legacy_public_images' => env('MARKETPLACE_ALLOW_LEGACY_PUBLIC_IMAGES', false),

    /*
     * Promotion package prices in minor currency units (for example, cents).
     */
    'promotion_package_prices_minor' => [
        'basic' => 50000,
        'standard' => 100000,
        'premium' => 150000,
    ],

];
