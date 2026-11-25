<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderItem;

final class NewOrderNotification extends MarketplaceNotification
{
    public function __construct(Order $order, OrderItem $item)
    {
        parent::__construct(
            type: 'new_order',
            translationKey: 'New order :order received for :item.',
            params: ['order' => $order->order_number, 'item' => $item->product_title],
            url: route('seller.orders.show', $order),
        );
    }
}
