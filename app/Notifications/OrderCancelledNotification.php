<?php

namespace App\Notifications;

use App\Models\Order;

final class OrderCancelledNotification extends MarketplaceNotification
{
    public function __construct(Order $order)
    {
        parent::__construct(
            type: 'order_cancelled',
            translationKey: 'Your order :order was cancelled.',
            params: ['order' => $order->order_number],
            url: route('orders.show', $order),
        );
    }
}
