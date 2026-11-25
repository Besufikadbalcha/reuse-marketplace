<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\ProductPromotion;

final class PromotionStoppedAfterOrderNotification extends MarketplaceNotification
{
    public function __construct(ProductPromotion $promotion, Order $order)
    {
        parent::__construct(
            type: 'promotion_stopped_after_order',
            translationKey: 'Promotion stopped after order :order for :title.',
            params: [
                'title' => $promotion->product?->title ?? __('Promotion request'),
                'order' => $order->order_number,
            ],
            url: route('seller.promotions.show', $promotion),
        );
    }
}
