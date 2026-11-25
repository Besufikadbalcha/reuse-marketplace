<?php

namespace App\Notifications;

use App\Models\ProductPromotion;

final class PromotionResumedNotification extends MarketplaceNotification
{
    public function __construct(ProductPromotion $promotion)
    {
        parent::__construct(
            type: 'promotion_resumed',
            translationKey: 'Promotion resumed for :title.',
            params: [
                'title' => $promotion->product?->title ?? __('Promotion request'),
            ],
            url: route('seller.promotions.show', $promotion),
        );
    }
}
