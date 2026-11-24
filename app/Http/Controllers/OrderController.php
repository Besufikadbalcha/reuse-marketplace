<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function create(Product $product): View|RedirectResponse
    {
        $product = $this->publiclyVisibleProduct($product);

        if (! Product::query()->availableForPurchase()->whereKey($product->id)->exists()) {
            return redirect()->route('products.show', $product->slug)->with('error', 'This item is no longer available.');
        }

        return view('checkout.create', [
            'product' => $product,
            'idempotencyKey' => (string) Str::uuid(),
            'fulfillmentMethods' => [
                Order::FULFILLMENT_PICKUP => 'Pickup',
                Order::FULFILLMENT_CASH_ON_DELIVERY => 'Cash on delivery',
            ],
            'defaultFulfillmentMethod' => Order::FULFILLMENT_PICKUP,
        ]);
    }

    public function store(StoreOrderRequest $request, Product $product): RedirectResponse
    {
        $buyer = $request->user();
        $validated = $request->safe()->only([
            'idempotency_key',
            'fulfillment_method',
            'contact_name',
            'contact_phone',
            'delivery_address',
        ]);
        $product = $this->publiclyVisibleProduct($product);
        $idempotencyKey = $validated['idempotency_key'];

        $replayedOrder = $this->findReplay($buyer->id, $idempotencyKey, $product->id);

        if ($replayedOrder !== null) {
            return redirect()->route('orders.show', $replayedOrder)->with('status', 'This order was already placed.');
        }

        if (! Product::query()->availableForPurchase()->whereKey($product->id)->exists()) {
            return redirect()->route('products.show', $product->slug)->with('error', 'This item is no longer available.');
        }

        $alreadyPlaced = false;

        try {
            $order = DB::transaction(function () use ($buyer, $product, $validated, $idempotencyKey): Order {
                $replay = $this->findReplay($buyer->id, $idempotencyKey, $product->id);

                if ($replay !== null) {
                    return $replay;
                }

                $availableProduct = Product::query()
                    ->availableForPurchase()
                    ->whereKey($product->id)
                    ->first();

                if ($availableProduct === null) {
                    abort(409, 'This item is no longer available.');
                }

                $orderNumber = $this->generateOrderNumber();

                $reserved = Product::query()
                    ->availableForPurchase()
                    ->whereKey($availableProduct->id)
                    ->where('title', $availableProduct->title)
                    ->where('price_minor', $availableProduct->price_minor)
                    ->update(['reservation_token' => $orderNumber]);

                if ($reserved !== 1) {
                    abort(409, 'This item is no longer available or its details changed.');
                }

                $order = Order::create([
                    'buyer_id' => $buyer->id,
                    'order_number' => $orderNumber,
                    'status' => Order::STATUS_PENDING,
                    'total_amount_minor' => $availableProduct->price_minor,
                    'fulfillment_method' => $validated['fulfillment_method'],
                    'contact_name' => $validated['contact_name'] ?? null,
                    'contact_phone' => $validated['contact_phone'] ?? null,
                    'delivery_address' => $validated['delivery_address'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                ]);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $availableProduct->id,
                    'product_title' => $availableProduct->title,
                    'price_minor' => $availableProduct->price_minor,
                    'quantity' => 1,
                ]);

                return $order;
            }, attempts: 3);
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

        return redirect()->route('orders.show', $order)->with(
            'status',
            $alreadyPlaced ? 'This order was already placed.' : 'Order placed successfully.'
        );
    }

    public function index(Request $request): View
    {
        $orders = Order::query()
            ->whereBelongsTo($request->user(), 'buyer')
            ->with('items.product')
            ->latest()
            ->orderByDesc('id')
            ->paginate(10);

        return view('orders.index', ['orders' => $orders]);
    }

    public function show(Request $request, Order $order): View
    {
        return view('orders.show', ['order' => $this->ownedOrder($request, $order)]);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $ownedOrder = $this->ownedOrder($request, $order);

        DB::transaction(function () use ($ownedOrder): void {
            $updated = Order::query()
                ->whereKey($ownedOrder->id)
                ->where('status', Order::STATUS_PENDING)
                ->update(['status' => Order::STATUS_CANCELLED]);

            if ($updated !== 1) {
                abort(409, 'Only pending orders can be cancelled.');
            }

            Product::query()
                ->where('reservation_token', $ownedOrder->order_number)
                ->update(['reservation_token' => null]);
        });

        return redirect()->route('orders.show', $ownedOrder)->with('status', 'Order cancelled.');
    }

    private function publiclyVisibleProduct(Product $product): Product
    {
        return Product::query()
            ->publiclyVisible()
            ->whereKey($product->id)
            ->firstOrFail();
    }

    private function findReplay(int $buyerId, string $idempotencyKey, int $productId): ?Order
    {
        $order = Order::query()
            ->where('buyer_id', $buyerId)
            ->where('idempotency_key', $idempotencyKey)
            ->with('items')
            ->first();

        if ($order === null) {
            return null;
        }

        if ($order->items->first()?->product_id !== $productId) {
            abort(409, 'That checkout key was already used for another order.');
        }

        return $order;
    }

    private function isIdempotencyConflict(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $sql = (string) $exception->getSql();

        return str_starts_with($sqlState, '23')
            && str_contains($sql, 'orders')
            && str_contains($sql, 'idempotency_key');
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
        return Order::query()
            ->whereBelongsTo($request->user(), 'buyer')
            ->whereKey($order->id)
            ->with('items.product')
            ->firstOrFail();
    }
}
