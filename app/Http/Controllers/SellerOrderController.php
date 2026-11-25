<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Notifications\SellerItemCompletedNotification;
use App\Notifications\SellerItemConfirmedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
                    $itemQuery->forSeller($seller)
                        ->where('seller_status', $selectedStatus);
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
        return $this->transition(
            $request,
            $order,
            $orderItem,
            OrderItem::SELLER_STATUS_PENDING,
            OrderItem::SELLER_STATUS_CONFIRMED,
            __('Only pending items can be confirmed.'),
            __('Item confirmed.')
        );
    }

    public function complete(Request $request, Order $order, OrderItem $orderItem): RedirectResponse
    {
        return $this->transition(
            $request,
            $order,
            $orderItem,
            OrderItem::SELLER_STATUS_CONFIRMED,
            OrderItem::SELLER_STATUS_COMPLETED,
            __('Only confirmed items can be completed.'),
            __('Item marked complete.')
        );
    }

    private function transition(
        Request $request,
        Order $order,
        OrderItem $orderItem,
        string $fromStatus,
        string $toStatus,
        string $errorMessage,
        string $successMessage
    ): RedirectResponse {
        $item = $this->sellerItem($request, $order, $orderItem);

        $updated = OrderItem::query()
            ->whereKey($item->id)
            ->forSeller($request->user())
            ->where('order_id', $order->id)
            ->where('seller_status', $fromStatus)
            ->whereHas('order', fn (Builder $orderQuery): Builder => $orderQuery
                ->where('status', '!=', Order::STATUS_CANCELLED))
            ->update(['seller_status' => $toStatus]);

        if ($updated !== 1) {
            abort(409, $errorMessage);
        }

        $statusKey = match ($toStatus) {
            OrderItem::SELLER_STATUS_CONFIRMED => 'item_confirmed',
            OrderItem::SELLER_STATUS_COMPLETED => 'item_completed',
            default => null,
        };

        $notification = match ($toStatus) {
            OrderItem::SELLER_STATUS_CONFIRMED => new SellerItemConfirmedNotification($order, $item),
            OrderItem::SELLER_STATUS_COMPLETED => new SellerItemCompletedNotification($order, $item),
            default => null,
        };

        if ($notification !== null) {
            $order->buyer?->notify($notification);
        }

        $redirect = redirect()->route('seller.orders.show', $order)
            ->with('status', $successMessage);

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

    private function sellerItem(Request $request, Order $order, OrderItem $orderItem): OrderItem
    {
        abort_unless($orderItem->order_id === $order->id, 404);

        return OrderItem::query()
            ->forSeller($request->user())
            ->whereKey($orderItem->id)
            ->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    private function sellerStatuses(): array
    {
        return [
            OrderItem::SELLER_STATUS_PENDING => __('Pending'),
            OrderItem::SELLER_STATUS_CONFIRMED => __('Confirmed'),
            OrderItem::SELLER_STATUS_COMPLETED => __('Completed'),
        ];
    }
}
