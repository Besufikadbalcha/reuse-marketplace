<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemStatusHistory;
use App\Models\Product;
use App\Models\ProductPromotion;
use App\Notifications\NewOrderNotification;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\PromotionResumedNotification;
use App\Notifications\PromotionStoppedAfterOrderNotification;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function create(Request $request, Product $product): View|RedirectResponse
    {
        $product = $this->publiclyVisibleProduct($product);
        abort_if($product->isOwnedBy($request->user()), 403, __('Sellers cannot buy their own products.'));

        if (! Product::query()->availableForPurchase()->whereKey($product->id)->exists()) {
            return redirect()->route('products.show', $product->slug)->with('error', __('This item is no longer available.'));
        }

        return view('checkout.create', [
            'product' => $product,
            'idempotencyKey' => (string) Str::uuid(),
            'fulfillmentMethods' => [
                Order::FULFILLMENT_PICKUP => __('Pickup'),
                Order::FULFILLMENT_CASH_ON_DELIVERY => __('Cash on delivery'),
            ],
            'defaultFulfillmentMethod' => Order::FULFILLMENT_PICKUP,
        ]);
    }

    public function store(StoreOrderRequest $request, Product $product): RedirectResponse
    {
        $buyer = $request->user();
        $validated = $request->safe()->only(['idempotency_key', 'fulfillment_method', 'contact_name', 'contact_phone', 'delivery_address']);
        $product = $this->publiclyVisibleProduct($product);
        abort_if($product->isOwnedBy($request->user()), 403, __('Sellers cannot buy their own products.'));

        $idempotencyKey = $validated['idempotency_key'];
        $replayedOrder = $this->findReplay($buyer->id, $idempotencyKey, $product->id);
        if ($replayedOrder !== null) {
            return redirect()->route('orders.show', $replayedOrder)->with('status', __('This order was already placed.'));
        }

        if (! Product::query()->availableForPurchase()->whereKey($product->id)->exists()) {
            return redirect()->route('products.show', $product->slug)->with('error', __('This item is no longer available.'));
        }

        $alreadyPlaced = false;
        $stoppedPromotion = null;

        try {
            $order = DB::transaction(function () use ($buyer, $product, $validated, $idempotencyKey, &$stoppedPromotion): Order {
                $replay = $this->findReplay($buyer->id, $idempotencyKey, $product->id);
                if ($replay !== null) {
                    return $replay;
                }

                $availableProduct = Product::query()
                    ->availableForPurchase()
                    ->whereKey($product->id)
                    ->lockForUpdate()
                    ->first();

                if ($availableProduct === null) {
                    abort(409, __('This item is no longer available.'));
                }

                $orderNumber = $this->generateOrderNumber();
                $reserved = Product::query()
                    ->availableForPurchase()
                    ->whereKey($availableProduct->id)
                    ->where('title', $availableProduct->title)
                    ->where('price_minor', $availableProduct->price_minor)
                    ->update(['reservation_token' => $orderNumber]);

                if ($reserved !== 1) {
                    abort(409, __('This item is no longer available or its details changed.'));
                }

                $order = Order::create([
                    'buyer_id' => $buyer->id,
                    'order_number' => $orderNumber,
                    'status' => Order::STATUS_PENDING,
                    'total_amount_minor' => $availableProduct->price_minor,
                    'fulfillment_method' => $validated['fulfillment_method'],
                    'contact_name' => $validated['fulfillment_method'] === Order::FULFILLMENT_CASH_ON_DELIVERY ? ($validated['contact_name'] ?? null) : null,
                    'contact_phone' => $validated['fulfillment_method'] === Order::FULFILLMENT_CASH_ON_DELIVERY ? ($validated['contact_phone'] ?? null) : null,
                    'delivery_address' => $validated['fulfillment_method'] === Order::FULFILLMENT_CASH_ON_DELIVERY ? ($validated['delivery_address'] ?? null) : null,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $availableProduct->id,
                    'product_title' => $availableProduct->title,
                    'price_minor' => $availableProduct->price_minor,
                    'quantity' => 1,
                    'seller_status' => OrderItem::SELLER_STATUS_PENDING,
                    'fulfillment_status' => OrderItem::FULFILLMENT_PENDING,
                ]);

                OrderItemStatusHistory::create([
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'actor_id' => $buyer->id,
                    'actor_role' => 'buyer',
                    'from_status' => null,
                    'to_status' => OrderItem::FULFILLMENT_PENDING,
                    'note' => __('Order placed.'),
                ]);

                $promotion = ProductPromotion::query()
                    ->forProduct($availableProduct->id)
                    ->active()
                    ->lockForUpdate()
                    ->first();

                if ($promotion !== null) {
                    $promotion->stopForOrderAt((int) $order->id, now());
                    $promotion->save();
                    $stoppedPromotion = $promotion;
                }

                return $order;
            }, attempts: 3);

            $alreadyPlaced = ! $order->wasRecentlyCreated;
        } catch (QueryException $exception) {
            if (! $this->isIdempotencyConflict($exception)) {
                throw $exception;
            }

            $replay = $this->findReplay($buyer->id, $idempotencyKey, $product->id);
            if ($replay === null) {
                throw $exception;
            }
            $order = $replay;
            $alreadyPlaced = true;
        }

        if (! $alreadyPlaced) {
            $buyer->notify(new OrderPlacedNotification($order));
            $order->load('items.product.seller');
            foreach ($order->items as $item) {
                $item->product->seller?->notify(new NewOrderNotification($order, $item));
            }
            if ($stoppedPromotion !== null) {
                $stoppedPromotion->seller?->notify(new PromotionStoppedAfterOrderNotification($stoppedPromotion, $order));
            }
        }

        return redirect()->route('orders.show', $order)
            ->with('status', $alreadyPlaced ? __('This order was already placed.') : __('Order placed successfully.'))
            ->with('status_key', 'order_placed');
    }

    public function index(Request $request): View
    {
        $buyer = $request->user();
        $orders = Order::query()
            ->whereBelongsTo($buyer, 'buyer')
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->with(['items.product', 'items.statusHistories'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(10);

        $emptyMessage = __('You have not placed any orders yet.');
        if ($orders->isEmpty() && Order::query()->whereBelongsTo($buyer, 'buyer')->where('status', Order::STATUS_CANCELLED)->exists()) {
            $emptyMessage = __('No active orders to show. Cancelled orders are hidden from this list.');
        }

        return view('orders.index', ['orders' => $orders, 'emptyMessage' => $emptyMessage]);
    }

    public function show(Request $request, Order $order): View
    {
        $order = $this->ownedOrder($request, $order);
        $order->loadMissing(['items.product.seller', 'items.statusHistories.actor']);

        return view('orders.show', ['order' => $order]);
    }

    public function confirmReceived(Request $request, Order $order, OrderItem $orderItem): RedirectResponse
    {
        $ownedOrder = $this->ownedOrder($request, $order);
        abort_unless($orderItem->order_id === $ownedOrder->id, 404);
        abort_if($ownedOrder->status === Order::STATUS_CANCELLED, 409, __('Cancelled orders cannot be changed.'));

        DB::transaction(function () use ($request, $ownedOrder, $orderItem): void {
            $lockedOrder = Order::query()->whereKey($ownedOrder->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedOrder->status === Order::STATUS_CANCELLED, 409, __('Cancelled orders cannot be changed.'));

            $item = OrderItem::query()->whereKey($orderItem->id)->where('order_id', $ownedOrder->id)->lockForUpdate()->firstOrFail();
            abort_unless($item->canBuyerConfirmReceived(), 409, __('This item is not waiting for your confirmation.'));

            $from = $item->fulfillmentStatus();
            $item->forceFill([
                'buyer_confirmed_received_at' => now(),
                'buyer_disputed_at' => null,
                'buyer_dispute_reason' => null,
            ])->save();

            OrderItemStatusHistory::create([
                'order_id' => $ownedOrder->id,
                'order_item_id' => $item->id,
                'actor_id' => $request->user()->id,
                'actor_role' => 'buyer',
                'from_status' => $from,
                'to_status' => $from,
                'note' => __('Buyer confirmed receipt.'),
            ]);

            $ownedOrder->refreshAggregateStatus();
        }, attempts: 3);

        return redirect()->route('orders.show', $ownedOrder)->with('status', __('Thanks. Your receipt confirmation was recorded.'));
    }

    public function reportProblem(Request $request, Order $order, OrderItem $orderItem): RedirectResponse
    {
        $ownedOrder = $this->ownedOrder($request, $order);
        abort_unless($orderItem->order_id === $ownedOrder->id, 404);
        abort_if($ownedOrder->status === Order::STATUS_CANCELLED, 409, __('Cancelled orders cannot be changed.'));

        $reason = trim((string) $request->string('reason'));
        throw_if($reason === '' || mb_strlen($reason) > 1000, ValidationException::withMessages(['reason' => __('Please describe the problem before submitting.')]));

        DB::transaction(function () use ($request, $ownedOrder, $orderItem, $reason): void {
            $lockedOrder = Order::query()->whereKey($ownedOrder->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedOrder->status === Order::STATUS_CANCELLED, 409, __('Cancelled orders cannot be changed.'));

            $item = OrderItem::query()->whereKey($orderItem->id)->where('order_id', $ownedOrder->id)->lockForUpdate()->firstOrFail();
            abort_unless($item->canBuyerReportProblem(), 409, __('This item cannot be reported right now.'));

            $from = $item->fulfillmentStatus();
            $item->forceFill([
                'fulfillment_status' => OrderItem::FULFILLMENT_DISPUTED,
                'buyer_disputed_at' => now(),
                'buyer_dispute_reason' => $reason,
            ])->save();

            OrderItemStatusHistory::create([
                'order_id' => $ownedOrder->id,
                'order_item_id' => $item->id,
                'actor_id' => $request->user()->id,
                'actor_role' => 'buyer',
                'from_status' => $from,
                'to_status' => OrderItem::FULFILLMENT_DISPUTED,
                'note' => $reason,
            ]);

            $ownedOrder->refreshAggregateStatus();
        }, attempts: 3);

        return redirect()->route('orders.show', $ownedOrder)->with('status', __('Your problem report was submitted. An admin can review it.'));
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $ownedOrder = $this->ownedOrder($request, $order);
        $restoredPromotion = null;

        DB::transaction(function () use ($ownedOrder, &$restoredPromotion, $request): void {
            $ownedOrder->loadMissing('items');
            $orderItem = $ownedOrder->items->first();
            $product = $orderItem === null ? null : Product::query()->whereKey($orderItem->product_id)->lockForUpdate()->first();
            $promotion = $orderItem === null ? null : ProductPromotion::query()->forProduct($orderItem->product_id)->stoppedByOrderId((int) $ownedOrder->id)->lockForUpdate()->first();

            $updated = Order::query()->whereKey($ownedOrder->id)->where('status', Order::STATUS_PENDING)->update(['status' => Order::STATUS_CANCELLED]);
            if ($updated !== 1) {
                abort(409, __('Only pending orders can be cancelled.'));
            }

            Product::query()->where('reservation_token', $ownedOrder->order_number)->update(['reservation_token' => null]);

            if ($orderItem !== null) {
                OrderItemStatusHistory::create([
                    'order_id' => $ownedOrder->id,
                    'order_item_id' => $orderItem->id,
                    'actor_id' => $request->user()->id,
                    'actor_role' => 'buyer',
                    'from_status' => $orderItem->fulfillmentStatus(),
                    'to_status' => $orderItem->fulfillmentStatus(),
                    'note' => __('Order cancelled by buyer.'),
                ]);
            }

            if ($promotion === null || $product === null || $promotion->status !== ProductPromotion::STATUS_STOPPED_SOLD || ! Product::query()->availableForPurchase()->whereKey($product->id)->exists()) {
                return;
            }

            $conflict = ProductPromotion::query()->forProduct($product->id)->open()->whereKeyNot($promotion->id)->exists();
            if ($conflict) {
                return;
            }

            $promotion->restoreAfterOrderAt(now());
            $promotion->save();
            $restoredPromotion = $promotion;
        }, attempts: 3);

        $request->user()->notify(new OrderCancelledNotification($ownedOrder));
        if ($restoredPromotion !== null) {
            $restoredPromotion->seller?->notify(new PromotionResumedNotification($restoredPromotion));
        }

        return redirect()->route('orders.show', $ownedOrder)->with('status', __('Order cancelled.'))->with('status_key', 'order_cancelled');
    }

    private function publiclyVisibleProduct(Product $product): Product
    {
        return Product::query()->publiclyVisible()->whereKey($product->id)->firstOrFail();
    }

    private function findReplay(int $buyerId, string $idempotencyKey, int $productId): ?Order
    {
        $order = Order::query()->where('buyer_id', $buyerId)->where('idempotency_key', $idempotencyKey)->with('items')->first();
        if ($order === null) {
            return null;
        }
        if ($order->items->first()?->product_id !== $productId) {
            abort(409, __('That checkout key was already used for another order.'));
        }

        return $order;
    }

    private function isIdempotencyConflict(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $sql = (string) $exception->getSql();

        return str_starts_with($sqlState, '23') && str_contains($sql, 'orders') && str_contains($sql, 'idempotency_key');
    }

    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'RM-'.Str::upper(Str::random(10));
        } while (Order::query()->where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    private function ownedOrder(Request $request, Order $order): Order
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);

        return $order->loadMissing('items');
    }
}
