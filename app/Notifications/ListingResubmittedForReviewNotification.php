<?php

namespace App\Notifications;

use App\Models\Product;

final class ListingResubmittedForReviewNotification extends MarketplaceNotification
{
    public function __construct(Product $product)
    {
        parent::__construct(
            type: 'listing_resubmitted_for_review',
            translationKey: 'Listing :title was resubmitted for review.',
            params: [
                'title' => $product->title,
                'product_id' => $product->id,
            ],
            url: route('admin.products.show', $product),
        );
    }
}
