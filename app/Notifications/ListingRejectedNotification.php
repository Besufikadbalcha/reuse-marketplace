<?php

namespace App\Notifications;

use App\Models\Product;

final class ListingRejectedNotification extends MarketplaceNotification
{
    public function __construct(Product $product)
    {
        parent::__construct(
            type: 'listing_rejected',
            translationKey: 'Listing :title was rejected.',
            params: ['title' => $product->title, 'reason' => $product->rejection_reason],
            url: route('seller.products.show', $product),
        );
    }
}
