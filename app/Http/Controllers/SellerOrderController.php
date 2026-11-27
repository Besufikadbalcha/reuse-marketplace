<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemStatusHistory;
use App\Notifications\SellerItemCompletedNotification;
use App\Notifications\SellerItemConfirmedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerOrderController extends Controller
{
    public function index(Request $request): View
    {
        $seller = $request->user();
        $requestedStatus = $request->query('status');
        $status = is_string($requestedStatus) && array_key_exists($requestedStatus, $this->sellerStatuses())
            ? $requestedStatus
            : null;

        $orders = Order::query()
            ->forSeller($seller)
            ->withSellerItems($seller)
            ->when($status, function (Builder $query, string $selectedStatus) use ($seller): void {
                $query->whereHas('items', function (Builder $itemQuery) use ($seller, $selectedStatus): void {
                    $itemQuery->forSeller($seller);
                    $this->applyStatusFilter($itemQuery, $selectedStatus);
                });
            })
            ->latest()
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('seller.orders.index', [
            'orders' => $orders,
            'statuses' => $this->sellerStatuses(),
            'status' => $status,
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        return view('seller.orders.show', [
            'order' => $this->sellerOrder($request, $order),
        ]);
    }

    public function confirm(Request $request, Order $order, OrderItem $orderItem): RedirectResponse
    {
        return $this->transition($request, $order, $orderItem, function (OrderItem $item): array {
            abort_unless($item->canBeConfirmedBySeller(), 409, __('Only pending items can be confirmed.'));

            return [OrderItem::FULFILLMENT_CONFIRMED, 'item_confirmed', __('Item confirmed.'), new SellerItemConfirmedNotification($item->order, $item)];
        });
    }

    public function readyForPickup(Request $request, Order $order, OrderItem $orderItem): RedirectResponse
    {
        return $this->transition($request, $order, $orderItem, function (OrderItem $item): array {
            abort_unless($item->isPickupOrder(), 409, __('This action is not available for delivery orders.'));
            abort_unless($item->fulfillmentStatus() === OrderItem::FULFILLMENT_CONFIRMED, 409, __('Only confirmed pickup items can be marked ready.'));

            return [OrderItem::FULFILLMENT_READY_FOR_PICKUP, 'item_ready_for_pickup', __('Item marked ready for pickup.'), null];
        });
    }

    public function outForDelivery(Request $request, Order $order, OrderItem $orderItem): RedirectResponse
    {
        return $this->transition($request, $order, $orderItem, function (OrderItem $item): array {
            abort_unless($item->isDeliveryOrder(), 409, __('This action is not available for pickup orders.'));
            abort_unless($item->fulfillmentStatus() === OrderItem::FULFILLMENT_CONFIRMED, 409, __('Only confirmed delivery items can be marked out for delivery.'));

            return [OrderItem::FULFILLMENT_OUT_FOR_DELIVERY, 'item_out_for_delivery', __('Item marked out for delivery.'), null];
        });
    }

    public function recordCashReceived(Request $request, Order $order, OrderItem $orderItem): RedirectResponse
    {
        return $this->transition($request, $order, $orderItem, function (OrderItem $item): array {
            abort_unless($item->canSellerRecordCashReceived(), 409, __('Cash can only be recorded after handover is started.'));

            $item->seller_cash_received_at = now();
            $item->seller_cash_received_by = auth()->id();

            return [OrderItem::FULFILLMENT_AWAITING_BUYER_CONFIRMATION, 'item_cash_received', __('Cash receipt recorded. Waiting for buyer confirmation.'), null];
        });
    }

    public function complete(Request $request, Order $order, OrderItem $orderItem): RedirectResponse
    {
        return $this->transition($request, $order, $orderItem, function (OrderItem $item): array {
            abort_unless($item->seller_cash_received_at !== null, 409, __('Cash must be recorded before completion.'));
            abort_unless($item->buyer_confirmed_received_at !== null, 409, __('This item can only be completed after buyer confirmation.'));
            abort_unless(! $item->isDisputed(), 409, __('Disputed items cannot be completed.'));
            abort_unless($item->fulfillmentStatus() === OrderItem::FULFILLMENT_AWAITING_BUYER_CONFIRMATION, 409, __('This item can only be completed after buyer confirmation.'));

            return [OrderItem::FULFILLMENT_COMPLETED, 'item_completed', __('Item marked complete.'), new SellerItemCompletedNotification($item->order, $item)];
        });
    }

    /**
     * @param  callable(OrderItem):array{0:string,1:?string,2:string,3:?object}  $resolver
     */
    private function transition(Request $request, Order $order, OrderItem $orderItem, callable $resolver): RedirectResponse
    {
        $seller = $request->user();
        $result = DB::transaction(function () use ($order, $orderItem, $seller, $resolver): array {
            $item = OrderItem::query()
                ->forSeller($seller)
                ->whereKey($orderItem->id)
                ->where('order_id', $order->id)
                ->whereHas('order', fn (Builder $q): Builder => $q->lockForUpdate())
                ->with('order')
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->order?->status === Order::STATUS_CANCELLED) {
                abort(409, __('Cancelled orders cannot be changed.'));
            }

            [$toStatus, $statusKey, $message, $notification] = $resolver($item);
            $fromStatus = $item->fulfillmentStatus();

            if ($fromStatus === $toStatus) {
                abort(409, __('This item is already in that state.'));
            }

            $item->fulfillment_status = $toStatus;
            $item->save();

            OrderItemStatusHistory::query()->create([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'actor_id' => $seller->id,
                'actor_role' => 'seller',
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'metadata' => [
                    'seller_cash_received_at' => optional($item->seller_cash_received_at)?->toIso8601String(),
                    'buyer_confirmed_received_at' => optional($item->buyer_confirmed_received_at)?->toIso8601String(),
                ],
            ]);

            $item->order->refreshAggregateStatus();

            return [$message, $statusKey, $notification, $item];
        }, attempts: 3);

        [$message, $statusKey, $notification, $item] = $result;
        if ($notification !== null) {
            $order->buyer?->notify($notification);
        }

        $redirect = redirect()->route('seller.orders.show', $order)->with('status', $message);
        if ($statusKey !== null) {
            $redirect->with('status_key', $statusKey);
        }

        return $redirect;
    }

    private function sellerOrder(Request $request, Order $order): Order
    {
        return Order::query()
            ->forSeller($request->user())
            ->withSellerItems($request->user())
            ->whereKey($order->id)
            ->firstOrFail();
    }

    private function applyStatusFilter(Builder $itemQuery, string $selectedStatus): void
    {
        $legacy = OrderItem::mapLegacySellerStatus($selectedStatus);
        $itemQuery->where(function (Builder $query) use ($selectedStatus, $legacy): void {
            $query->where('fulfillment_status', $selectedStatus)
                ->orWhere(function (Builder $legacyQuery) use ($selectedStatus, $legacy): void {
                    $legacyQuery->whereNull('fulfillment_status')->where('seller_status', $legacy === $selectedStatus ? $selectedStatus : $legacy);
                });
        });
    }

    /**
     * @return array<string, string>
     */
    private function sellerStatuses(): array
    {
        return [
            OrderItem::FULFILLMENT_PENDING => __('Pending confirmation'),
            OrderItem::FULFILLMENT_CONFIRMED => __('Confirmed'),
            OrderItem::FULFILLMENT_READY_FOR_PICKUP => __('Ready for pickup'),
            OrderItem::FULFILLMENT_OUT_FOR_DELIVERY => __('Out for delivery'),
            OrderItem::FULFILLMENT_AWAITING_BUYER_CONFIRMATION => __('Cash received - awaiting buyer confirmation'),
            OrderItem::FULFILLMENT_COMPLETED => __('Completed'),
            OrderItem::FULFILLMENT_DISPUTED => __('Disputed'),
        ];
    }
}
