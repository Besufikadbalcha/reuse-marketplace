<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(ProductSeeder::class);

        $buyer = User::query()->firstOrCreate(
            ['email' => 'buyer@example.com'],
            ['name' => 'Demo Buyer', 'password' => Hash::make('password')],
        );

        $orders = [
            [
                'order_number' => 'RM-DEMO0001',
                'status' => Order::STATUS_CONFIRMED,
                'seller_status' => OrderItem::SELLER_STATUS_COMPLETED,
                'fulfillment_method' => Order::FULFILLMENT_PICKUP,
                'contact_name' => null,
                'contact_phone' => null,
                'delivery_address' => null,
                'items' => ['city-bicycle' => 1, 'smart-phone' => 1],
            ],
            [
                'order_number' => 'RM-DEMO0002',
                'status' => Order::STATUS_PENDING,
                'seller_status' => OrderItem::SELLER_STATUS_PENDING,
                'fulfillment_method' => Order::FULFILLMENT_CASH_ON_DELIVERY,
                'contact_name' => 'Demo Customer',
                'contact_phone' => '+251911000000',
                'delivery_address' => 'Bole, Addis Ababa',
                'items' => ['wooden-desk' => 1],
            ],
        ];

        foreach ($orders as $orderData) {
            $items = $orderData['items'];
            $sellerStatus = $orderData['seller_status'];
            unset($orderData['items'], $orderData['seller_status']);

            $order = Order::query()->updateOrCreate(
                ['order_number' => $orderData['order_number']],
                [...$orderData, 'buyer_id' => $buyer->id, 'total_amount_minor' => 0],
            );

            $orderItems = collect();

            foreach ($items as $slug => $quantity) {
                $product = Product::query()->where('slug', $slug)->firstOrFail();

                $orderItems->push(OrderItem::query()->updateOrCreate(
                    ['order_id' => $order->id, 'product_id' => $product->id],
                    [
                        'product_title' => $product->title,
                        'price_minor' => $product->price_minor,
                        'quantity' => $quantity,
                        'seller_status' => $sellerStatus,
                    ],
                ));
            }

            $order->update([
                'total_amount_minor' => $orderItems->sum(fn (OrderItem $item): int => $item->price_minor * $item->quantity),
            ]);
        }
    }
}
