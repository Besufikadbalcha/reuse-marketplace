<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $fulfillment = $request->query('fulfillment');
        $disputed = $request->query('disputed');

        $orders = Order::query()
            ->with(['buyer', 'items.product.seller'])
            ->when(in_array($status, [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true), fn (Builder $q) => $q->where('status', $status))
            ->when(in_array($fulfillment, [Order::FULFILLMENT_PICKUP, Order::FULFILLMENT_CASH_ON_DELIVERY], true), fn (Builder $q) => $q->where('fulfillment_method', $fulfillment))
            ->when($disputed === '1', fn (Builder $q) => $q->whereHas('items', fn (Builder $i) => $i->where('fulfillment_status', OrderItem::FULFILLMENT_DISPUTED)->orWhereNotNull('buyer_disputed_at')))
            ->latest()
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'status' => is_string($status) ? $status : '',
            'fulfillment' => is_string($fulfillment) ? $fulfillment : '',
            'disputed' => $disputed === '1',
        ]);
    }

    public function show(Order $order): View
    {
        $order->load(['buyer', 'items.product.seller', 'items.statusHistories.actor']);

        return view('admin.orders.show', ['order' => $order]);
    }
}
