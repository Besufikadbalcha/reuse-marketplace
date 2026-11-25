<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderItem;

final class SellerItemConfirmedNotification extends MarketplaceNotification
{
    public function __construct(Order $order, OrderItem $item)
    {
        parent::__construct(
            type: 'item_confirmed',
            translationKey: 'Item :item in order :order was confirmed.',
            params: ['item' => $item->product_title, 'order' => $order->order_number],
            url: route('orders.show', $order),
        );
    }
}
