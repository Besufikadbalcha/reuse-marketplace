<?php

namespace App\Notifications;

use App\Models\Order;

final class OrderPlacedNotification extends MarketplaceNotification
{
    public function __construct(Order $order)
    {
        parent::__construct(
            type: 'order_placed',
            translationKey: 'Your order :order was placed.',
            params: ['order' => $order->order_number],
            url: route('orders.show', $order),
        );
    }
}
