<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

function buyerCheckoutProduct(array $attributes = []): Product
{
    return Product::factory()
        ->for(Category::factory()->create(), 'category')
        ->approved()
        ->create($attributes);
}

function buyerCheckoutPayload(array $overrides = []): array
{
    return array_merge([
        'idempotency_key' => (string) Str::uuid(),
        'fulfillment_method' => Order::FULFILLMENT_PICKUP,
    ], $overrides);
}

test('guests are redirected to login before checkout', function () {
    $product = buyerCheckoutProduct(['title' => 'Guest Checkout Item']);

    $this->get(route('checkout.create', $product))->assertRedirect(route('login'));
    $this->post(route('checkout.store', $product), buyerCheckoutPayload())->assertRedirect(route('login'));

    expect(Order::count())->toBe(0);
});

test('guests can still browse eligible listings', function () {
    $product = buyerCheckoutProduct(['title' => 'Browsable Item']);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee('Browsable Item')
        ->assertSee('Log in to buy now');
});

test('authenticated buyers see a buy now action on eligible listings', function () {
    $buyer = User::factory()->create();
    $product = buyerCheckoutProduct(['title' => 'Buyable Item']);

    $this->actingAs($buyer)
        ->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertViewHas('canPurchase', true)
        ->assertSee('Buy now');
});

test('product owners do not see a buy now action or checkout link on their own listing', function () {
    $seller = User::factory()->create(['name' => 'Balcha']);
    $product = Product::factory()
        ->for($seller, 'seller')
        ->approved()
        ->create([
            'title' => 'Table',
            'slug' => 'table',
            'price_minor' => 250000,
        ]);

    $this->actingAs($seller)
        ->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee('Table')
        ->assertSee('ETB 2,500.00')
        ->assertSee('Balcha')
        ->assertSee(__('Sellers cannot buy their own products.'))
        ->assertDontSee('Buy now')
        ->assertDontSee('Log in to buy now')
        ->assertDontSee(route('checkout.create', $product), false)
        ->assertViewHas('canPurchase', false)
        ->assertViewHas('isOwnProduct', true);
});

test('other authenticated users still see a buy now action on the seller listing', function () {
    $seller = User::factory()->create(['name' => 'Balcha']);
    $buyer = User::factory()->create();
    $product = Product::factory()
        ->for($seller, 'seller')
        ->approved()
        ->create(['title' => 'Table', 'slug' => 'table']);

    $this->actingAs($buyer)
        ->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee('Buy now')
        ->assertSee(route('checkout.create', $product), false)
        ->assertViewHas('canPurchase', true)
        ->assertViewHas('isOwnProduct', false);
});

test('guests retain the login to buy flow on seller listings', function () {
    $seller = User::factory()->create(['name' => 'Balcha']);
    $product = Product::factory()
        ->for($seller, 'seller')
        ->approved()
        ->create(['title' => 'Table', 'slug' => 'table']);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee(__('Log in to buy this item.'))
        ->assertSee('Log in to buy now')
        ->assertSee(route('checkout.create', $product), false)
        ->assertDontSee('Buy now');
});

test('product owners cannot open checkout for their own listing directly', function () {
    $seller = User::factory()->create();
    $product = Product::factory()
        ->for($seller, 'seller')
        ->approved()
        ->create(['title' => 'Table', 'slug' => 'table']);

    $this->actingAs($seller)
        ->get(route('checkout.create', $product))
        ->assertForbidden()
        ->assertSee(__('Sellers cannot buy their own products.'));
});

test('product owners cannot submit an order for their own listing directly', function () {
    $seller = User::factory()->create();
    $product = Product::factory()
        ->for($seller, 'seller')
        ->approved()
        ->create(['title' => 'Table', 'slug' => 'table']);

    $this->actingAs($seller)
        ->post(route('checkout.store', $product), buyerCheckoutPayload())
        ->assertForbidden()
        ->assertSee(__('Sellers cannot buy their own products.'));

    expect(Order::count())->toBe(0);
});

test('a different buyer can still complete checkout for the seller listing', function () {
    $seller = User::factory()->create(['name' => 'Balcha']);
    $buyer = User::factory()->create();
    $product = Product::factory()
        ->for($seller, 'seller')
        ->approved()
        ->create(['title' => 'Table', 'slug' => 'table', 'price_minor' => 250000]);

    $response = $this->actingAs($buyer)->post(route('checkout.store', $product), buyerCheckoutPayload());
    $order = Order::query()->where('buyer_id', $buyer->id)->firstOrFail();

    $response->assertRedirect(route('orders.show', $order))
        ->assertSessionHas('status', 'Order placed successfully.');

    expect($order->items()->first()->product_id)->toBe($product->id)
        ->and($order->buyer_id)->toBe($buyer->id)
        ->and($product->fresh()->seller_id)->toBe($seller->id);
});

test('checkout page explains cash payment and shows the total in ETB', function () {
    $buyer = User::factory()->create();
    $product = buyerCheckoutProduct(['title' => 'Clear Item', 'price_minor' => 123456]);

    $this->actingAs($buyer)
        ->get(route('checkout.create', $product))
        ->assertOk()
        ->assertViewIs('checkout.create')
        ->assertSee('Both options are paid in cash.')
        ->assertSee('Pay cash when the product is delivered to you.')
        ->assertSee('Pay cash when you collect the item from the seller.')
        ->assertSee('You and the seller arrange the pickup details.')
        ->assertSee('no online payment is needed.')
        ->assertSee('You chose')
        ->assertSee('Total to pay')
        ->assertSee('ETB 1,234.56');
});

test('checkout page shows the selected option after a validation error', function () {
    $buyer = User::factory()->create();
    $product = buyerCheckoutProduct(['price_minor' => 25000]);

    $this->actingAs($buyer)
        ->from(route('checkout.create', $product))
        ->post(route('checkout.store', $product), buyerCheckoutPayload([
            'fulfillment_method' => Order::FULFILLMENT_CASH_ON_DELIVERY,
        ]))
        ->assertRedirect(route('checkout.create', $product))
        ->assertSessionHasErrors('contact_name');

    $this->get(route('checkout.create', $product))
        ->assertOk()
        ->assertSee('You chose')
        ->assertSeeInOrder(['You chose', 'Cash on delivery']);
});

test('pending rejected and inactive category products cannot be checked out', function () {
    $buyer = User::factory()->create();
    $activeCategory = Category::factory()->create();

    $products = [
        Product::factory()->for($activeCategory, 'category')->create(['title' => 'Pending Item']),
        Product::factory()->for($activeCategory, 'category')->rejected()->create(['title' => 'Rejected Item']),
        Product::factory()->for(Category::factory()->inactive()->create(), 'category')->approved()->create(['title' => 'Inactive Item']),
    ];

    $this->actingAs($buyer);

    foreach ($products as $product) {
        $this->get(route('checkout.create', $product))->assertNotFound();
        $this->post(route('checkout.store', $product), buyerCheckoutPayload())->assertNotFound();
    }

    expect(Order::count())->toBe(0);
});

test('pickup orders store server calculated prices and no delivery details', function () {
    $buyer = User::factory()->create();
    $product = buyerCheckoutProduct(['title' => 'Pickup Item', 'price_minor' => 123456]);

    $response = $this->actingAs($buyer)->post(route('checkout.store', $product), buyerCheckoutPayload([
        'fulfillment_method' => Order::FULFILLMENT_PICKUP,
        'contact_name' => 'Unused Name',
        'contact_phone' => '0912345678',
        'delivery_address' => 'Unused address',
    ]));

    $order = Order::query()->where('buyer_id', $buyer->id)->firstOrFail();
    $item = $order->items()->firstOrFail();

    $response->assertRedirect(route('orders.show', $order))
        ->assertSessionHas('status', 'Order placed successfully.');

    expect($order->status)->toBe(Order::STATUS_PENDING)
        ->and($order->total_amount_minor)->toBe(123456)
        ->and($order->contact_name)->toBeNull()
        ->and($order->contact_phone)->toBeNull()
        ->and($order->delivery_address)->toBeNull()
        ->and(preg_match('/^RM-[A-Z0-9]{10}$/', $order->order_number))->toBe(1)
        ->and($item->product_id)->toBe($product->id)
        ->and($item->product_title)->toBe('Pickup Item')
        ->and($item->price_minor)->toBe(123456)
        ->and($item->quantity)->toBe(1)
        ->and($item->seller_status)->toBe(OrderItem::SELLER_STATUS_PENDING)
        ->and($product->fresh()->reservation_token)->toBe($order->order_number);
});

test('cash on delivery orders require and store contact details', function () {
    $buyer = User::factory()->create();
    $product = buyerCheckoutProduct(['title' => 'Delivered Item', 'price_minor' => 45000]);

    $response = $this->actingAs($buyer)->post(route('checkout.store', $product), buyerCheckoutPayload([
        'fulfillment_method' => Order::FULFILLMENT_CASH_ON_DELIVERY,
        'contact_name' => 'Abebe Bekele',
        'contact_phone' => '+251 91 234 5678',
        'delivery_address' => '12 Meskel Square, Addis Ababa',
    ]));

    $order = Order::query()->where('buyer_id', $buyer->id)->firstOrFail();

    $response->assertRedirect(route('orders.show', $order));

    expect($order->fulfillment_method)->toBe(Order::FULFILLMENT_CASH_ON_DELIVERY)
        ->and($order->contact_name)->toBe('Abebe Bekele')
        ->and($order->contact_phone)->toBe('+251 91 234 5678')
        ->and($order->delivery_address)->toBe('12 Meskel Square, Addis Ababa');
});

test('pickup confirmation shows order number total option and payment timing', function () {
    $buyer = User::factory()->create();
    $product = buyerCheckoutProduct(['price_minor' => 123456]);

    $response = $this->actingAs($buyer)->post(route('checkout.store', $product), buyerCheckoutPayload());
    $order = Order::query()->where('buyer_id', $buyer->id)->firstOrFail();

    $response->assertRedirect(route('orders.show', $order))
        ->assertSessionHas('status', 'Order placed successfully.');

    $this->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('Thank you. Your order is placed.')
        ->assertSee($order->order_number)
        ->assertSee('ETB 1,234.56')
        ->assertSee('You chose')
        ->assertSee('Pickup')
        ->assertSee('When you collect the item from the seller.');
});

test('cash on delivery confirmation shows payment due on delivery', function () {
    $buyer = User::factory()->create();
    $product = buyerCheckoutProduct(['price_minor' => 45000]);

    $response = $this->actingAs($buyer)->post(route('checkout.store', $product), buyerCheckoutPayload([
        'fulfillment_method' => Order::FULFILLMENT_CASH_ON_DELIVERY,
        'contact_name' => 'Abebe Bekele',
        'contact_phone' => '+251 91 234 5678',
        'delivery_address' => '12 Meskel Square, Addis Ababa',
    ]));
    $order = Order::query()->where('buyer_id', $buyer->id)->firstOrFail();

    $response->assertRedirect(route('orders.show', $order))
        ->assertSessionHas('status', 'Order placed successfully.');

    $this->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('Thank you. Your order is placed.')
        ->assertSee($order->order_number)
        ->assertSee('ETB 450.00')
        ->assertSee('Cash on delivery')
        ->assertSee('When the product is delivered to you.');
});

test('cash on delivery orders reject missing or invalid contact details', function () {
    $buyer = User::factory()->create();
    $product = buyerCheckoutProduct();

    $this->actingAs($buyer)
        ->post(route('checkout.store', $product), buyerCheckoutPayload([
            'fulfillment_method' => Order::FULFILLMENT_CASH_ON_DELIVERY,
        ]))
        ->assertSessionHasErrors([
            'contact_name' => 'Enter a contact name for cash on delivery.',
            'contact_phone' => 'Enter a phone number for cash on delivery.',
            'delivery_address' => 'Enter a delivery address for cash on delivery.',
        ]);

    $this->actingAs($buyer)
        ->post(route('checkout.store', $product), buyerCheckoutPayload([
            'fulfillment_method' => Order::FULFILLMENT_CASH_ON_DELIVERY,
            'contact_name' => 'Abebe Bekele',
            'contact_phone' => 'not-a-phone',
            'delivery_address' => '12 Meskel Square',
        ]))
        ->assertSessionHasErrors(['contact_phone' => 'Enter a valid phone number.']);

    expect(Order::count())->toBe(0);
});

test('invalid fulfillment methods and checkout keys are rejected', function () {
    $buyer = User::factory()->create();
    $product = buyerCheckoutProduct();

    $this->actingAs($buyer)
        ->post(route('checkout.store', $product), buyerCheckoutPayload([
            'fulfillment_method' => 'bank_transfer',
        ]))
        ->assertSessionHasErrors(['fulfillment_method' => 'Choose pickup or cash on delivery.']);

    $this->actingAs($buyer)
        ->post(route('checkout.store', $product), buyerCheckoutPayload([
            'idempotency_key' => 'not-a-uuid',
        ]))
        ->assertSessionHasErrors('idempotency_key');

    expect(Order::count())->toBe(0);
});

test('client supplied prices totals and ownership are ignored', function () {
    $buyer = User::factory()->create();
    $otherBuyer = User::factory()->create();
    $product = buyerCheckoutProduct(['title' => 'Original Title', 'price_minor' => 78900]);

    $this->actingAs($buyer)->post(route('checkout.store', $product), buyerCheckoutPayload([
        'buyer_id' => $otherBuyer->id,
        'status' => Order::STATUS_COMPLETED,
        'order_number' => 'RM-FORCED01',
        'total_amount_minor' => 1,
        'price_minor' => 1,
        'product_title' => 'Tampered Title',
    ]));

    $order = Order::query()->where('buyer_id', $buyer->id)->firstOrFail();

    expect($order->status)->toBe(Order::STATUS_PENDING)
        ->and($order->total_amount_minor)->toBe(78900)
        ->and($order->order_number)->not->toBe('RM-FORCED01')
        ->and($order->items()->first()->product_title)->toBe('Original Title')
        ->and($order->items()->first()->price_minor)->toBe(78900);
});

test('order items snapshot the product title and price', function () {
    $buyer = User::factory()->create();
    $product = buyerCheckoutProduct(['title' => 'Snapshot Title', 'price_minor' => 33300]);

    $this->actingAs($buyer)->post(route('checkout.store', $product), buyerCheckoutPayload());

    $order = Order::query()->where('buyer_id', $buyer->id)->firstOrFail();

    $product->update(['title' => 'Changed Title', 'price_minor' => 99900]);

    $item = $order->items()->first();

    expect($item->product_title)->toBe('Snapshot Title')
        ->and($item->price_minor)->toBe(33300)
        ->and($order->fresh()->total_amount_minor)->toBe(33300);
});

test('repeated submissions with the same checkout key do not duplicate orders', function () {
    $buyer = User::factory()->create();
    $product = buyerCheckoutProduct(['price_minor' => 21000]);
    $payload = buyerCheckoutPayload();

    $firstResponse = $this->actingAs($buyer)->post(route('checkout.store', $product), $payload);
    $order = Order::query()->where('buyer_id', $buyer->id)->firstOrFail();
    $secondResponse = $this->actingAs($buyer)->post(route('checkout.store', $product), $payload);

    $firstResponse->assertRedirect(route('orders.show', $order));
    $secondResponse->assertRedirect(route('orders.show', $order))
        ->assertSessionHas('status', 'This order was already placed.');

    $this->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('This order was already placed.')
        ->assertDontSee('Thank you. Your order is placed.');

    expect(Order::count())->toBe(1)
        ->and(OrderItem::count())->toBe(1)
        ->and($product->fresh()->reservation_token)->toBe($order->order_number);
});

test('a checkout key cannot be reused for a different product', function () {
    $buyer = User::factory()->create();
    $firstProduct = buyerCheckoutProduct();
    $secondProduct = buyerCheckoutProduct();
    $key = (string) Str::uuid();

    $this->actingAs($buyer)->post(route('checkout.store', $firstProduct), buyerCheckoutPayload([
        'idempotency_key' => $key,
    ]))->assertRedirect();

    $this->actingAs($buyer)->post(route('checkout.store', $secondProduct), buyerCheckoutPayload([
        'idempotency_key' => $key,
    ]))->assertStatus(409);

    expect(Order::count())->toBe(1)
        ->and($firstProduct->fresh()->reservation_token)->not->toBeNull()
        ->and($secondProduct->fresh()->reservation_token)->toBeNull();
});

test('reserved and previously ordered products cannot be purchased again', function () {
    $buyer = User::factory()->create();
    $reservedProduct = buyerCheckoutProduct(['title' => 'Reserved Item']);
    $orderedProduct = buyerCheckoutProduct(['title' => 'Already Ordered Item']);

    Product::query()->whereKey($reservedProduct->id)->update(['reservation_token' => 'RM-HELD001']);

    $legacyOrder = Order::factory()->for(User::factory()->create(), 'buyer')->create();
    OrderItem::factory()->for($legacyOrder)->create(['product_id' => $orderedProduct->id]);

    $this->actingAs($buyer);

    $this->post(route('checkout.store', $reservedProduct), buyerCheckoutPayload())
        ->assertRedirect(route('products.show', $reservedProduct->slug))
        ->assertSessionHas('error', 'This item is no longer available.');

    $this->post(route('checkout.store', $orderedProduct), buyerCheckoutPayload())
        ->assertRedirect(route('products.show', $orderedProduct->slug))
        ->assertSessionHas('error', 'This item is no longer available.');

    expect(Order::query()->where('buyer_id', $buyer->id)->count())->toBe(0);
});

test('checkout uniqueness constraints are enforced by the database', function () {
    $buyer = User::factory()->create();
    $firstProduct = buyerCheckoutProduct();
    $secondProduct = buyerCheckoutProduct();
    $key = (string) Str::uuid();

    Product::query()->whereKey($firstProduct->id)->update(['reservation_token' => 'RM-SHARED01']);

    expect(fn () => Product::query()->whereKey($secondProduct->id)->update(['reservation_token' => 'RM-SHARED01']))
        ->toThrow(QueryException::class);

    Order::factory()->for($buyer, 'buyer')->create(['idempotency_key' => $key]);

    expect(fn () => Order::factory()->for($buyer, 'buyer')->create(['idempotency_key' => $key]))
        ->toThrow(QueryException::class);

    expect(DB::table('orders')->where('buyer_id', $buyer->id)->where('idempotency_key', $key)->count())->toBe(1);
});

test('checkout placement is rate limited', function () {
    $buyer = User::factory()->create();
    $product = buyerCheckoutProduct();

    $this->actingAs($buyer);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post(route('checkout.store', $product), buyerCheckoutPayload([
            'fulfillment_method' => 'invalid',
        ]))->assertSessionHasErrors('fulfillment_method');
    }

    $this->post(route('checkout.store', $product), buyerCheckoutPayload())->assertStatus(429);

    expect(Order::count())->toBe(0);
});

test('checkout returns 409 when the price or title changes before reservation', function () {
    $buyer = User::factory()->create();
    $product = buyerCheckoutProduct(['title' => 'Original Title', 'price_minor' => 33300]);
    $productId = $product->id;
    $retrievals = 0;

    $listener = function (Product $product) use (&$retrievals, $productId): void {
        if ($product->id !== $productId) {
            return;
        }

        $retrievals++;

        if ($retrievals === 3) {
            Product::query()->whereKey($productId)->update([
                'title' => 'Changed Title',
                'price_minor' => 99900,
            ]);
        }
    };

    Product::retrieved($listener);

    try {
        $this->actingAs($buyer)
            ->post(route('checkout.store', $product), buyerCheckoutPayload())
            ->assertStatus(409);

        expect(Order::count())->toBe(0)
            ->and(OrderItem::count())->toBe(0)
            ->and($product->fresh()->reservation_token)->toBeNull()
            ->and($product->fresh()->title)->toBe('Original Title')
            ->and($product->fresh()->price_minor)->toBe(33300);
    } finally {
        Event::forget('eloquent.retrieved: '.Product::class, $listener);
    }
});

test('checkout returns 409 when the product becomes unavailable before reservation', function () {
    $buyer = User::factory()->create();
    $product = buyerCheckoutProduct(['title' => 'Reserved Race Item', 'price_minor' => 33300]);
    $productId = $product->id;
    $retrievals = 0;

    $listener = function (Product $product) use (&$retrievals, $productId): void {
        if ($product->id !== $productId) {
            return;
        }

        $retrievals++;

        if ($retrievals === 3) {
            Product::query()->whereKey($productId)->update(['status' => Product::STATUS_PENDING]);
        }
    };

    Product::retrieved($listener);

    try {
        $this->actingAs($buyer)
            ->post(route('checkout.store', $product), buyerCheckoutPayload())
            ->assertStatus(409);

        expect(Order::count())->toBe(0)
            ->and(OrderItem::count())->toBe(0)
            ->and($product->fresh()->reservation_token)->toBeNull()
            ->and($product->fresh()->status)->toBe(Product::STATUS_APPROVED);
    } finally {
        Event::forget('eloquent.retrieved: '.Product::class, $listener);
    }
});

test('unrelated database errors during checkout are not treated as idempotent replays', function () {
    $buyer = User::factory()->create();
    $product = buyerCheckoutProduct();
    $key = (string) Str::uuid();

    $listener = function (): void {
        throw new QueryException(
            'sqlite',
            'insert into order_items (order_id, product_id) values (?, ?)',
            [],
            new RuntimeException('boom'),
        );
    };

    OrderItem::creating($listener);

    try {
        $this->actingAs($buyer)
            ->post(route('checkout.store', $product), buyerCheckoutPayload(['idempotency_key' => $key]))
            ->assertStatus(500);

        expect(Order::count())->toBe(0)
            ->and(OrderItem::count())->toBe(0)
            ->and($product->fresh()->reservation_token)->toBeNull();
    } finally {
        Event::forget('eloquent.creating: '.OrderItem::class, $listener);
    }
});

test('idempotency shaped database errors without a matching replay are rethrown', function () {
    $buyer = User::factory()->create();
    $product = buyerCheckoutProduct();
    $key = (string) Str::uuid();

    $listener = function (): void {
        throw new QueryException(
            'sqlite',
            'insert into orders (buyer_id, idempotency_key) values (?, ?)',
            [],
            new RuntimeException('boom', 23000),
        );
    };

    Order::creating($listener);

    try {
        $this->actingAs($buyer)
            ->post(route('checkout.store', $product), buyerCheckoutPayload(['idempotency_key' => $key]))
            ->assertStatus(500);

        expect(Order::count())->toBe(0)
            ->and(OrderItem::count())->toBe(0)
            ->and($product->fresh()->reservation_token)->toBeNull();
    } finally {
        Event::forget('eloquent.creating: '.Order::class, $listener);
    }
});
