<?php

namespace App\Notifications;

use App\Models\Product;

final class ListingApprovedNotification extends MarketplaceNotification
{
    public function __construct(Product $product)
    {
        parent::__construct(
            type: 'listing_approved',
            translationKey: 'Listing :title was approved.',
            params: ['title' => $product->title],
            url: route('seller.products.show', $product),
        );
    }
}
