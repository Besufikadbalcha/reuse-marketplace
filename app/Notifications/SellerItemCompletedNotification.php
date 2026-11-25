<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderItem;

final class SellerItemCompletedNotification extends MarketplaceNotification
{
    public function __construct(Order $order, OrderItem $item)
    {
        parent::__construct(
            type: 'item_completed',
            translationKey: 'Item :item in order :order was completed.',
            params: ['item' => $item->product_title, 'order' => $order->order_number],
            url: route('orders.show', $order),
        );
    }
}
