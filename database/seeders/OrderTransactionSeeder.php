<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('=== ORDER & TRANSACTION SEEDER ===');

        $shops = \App\Models\Shop::where('status', 'active')->get();
        $customers = User::where('role', 'customer')->get();
        $deliveryMen = User::where('role', 'delivery')->get();

        if ($customers->isEmpty() || $shops->isEmpty()) {
            $this->command->error('Need customers and shops first!');
            return;
        }

        $statusFlow = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
        $paymentMethods = ['midtrans', 'xendit', 'transfer', 'cod', 'wallet'];

        $this->command->info('Creating 5000 orders...');
        $bar = $this->command->getOutput()->createProgressBar(5000);

        for ($i = 1; $i <= 5000; $i++) {
            $shop = $shops->random();
            $customer = $customers->random();
            $products = Product::where('shop_id', $shop->id)->where('status', 'approved')->inRandomOrder()->take(rand(1, 5))->get();
            if ($products->isEmpty()) { $bar->advance(); continue; }

            $status = $statusFlow[rand(0, 4)];
            $isPaid = !in_array($status, ['pending']) || rand(0, 3) > 0;
            $subTotal = 0;
            $items = [];

            foreach ($products as $p) {
                $qty = rand(1, 5);
                $price = $p->getEffectivePrice();
                $st = $price * $qty;
                $items[] = ['product' => $p, 'qty' => $qty, 'price' => $price, 'st' => $st];
                $subTotal += $st;
            }

            $shipping = rand(0, 3) > 0 ? rand(10000, 50000) : 0;
            $couponDiscount = rand(0, 5) === 0 ? rand(5000, 30000) : 0;
            $total = max(0, $subTotal + $shipping - $couponDiscount);

            $dates = [
                'created_at' => now()->subDays(rand(0, 90))->subHours(rand(0, 23)),
            ];

            $order = Order::create([
                'order_number' => 'ORD-' . now()->subDays(rand(0, 90))->format('Ymd') . '-' . uniqid(),
                'customer_id' => $customer->id,
                'shop_id' => $shop->id,
                'sub_total' => $subTotal,
                'tax' => 0,
                'shipping_cost' => $shipping,
                'coupon_discount' => $couponDiscount,
                'discount' => 0,
                'total' => $total,
                'shipping_method' => ['jne', 'jnt', 'sicepat', 'tiki'][rand(0, 3)],
                'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                'payment_status' => $isPaid ? 'paid' : 'unpaid',
                'order_status' => $status,
                'delivery_man_id' => rand(0, 3) > 0 && $deliveryMen->count() > 0 ? $deliveryMen->random()->id : null,
                'confirmed_at' => in_array($status, ['confirmed', 'processing', 'shipped', 'delivered']) ? $dates['created_at']->copy()->addHours(rand(1, 4)) : null,
                'processing_at' => in_array($status, ['processing', 'shipped', 'delivered']) ? $dates['created_at']->copy()->addHours(rand(5, 12)) : null,
                'shipped_at' => in_array($status, ['shipped', 'delivered']) ? $dates['created_at']->copy()->addHours(rand(13, 48)) : null,
                'delivered_at' => $status === 'delivered' ? $dates['created_at']->copy()->addDays(rand(1, 5)) : null,
                'created_at' => $dates['created_at'],
                'updated_at' => $dates['created_at'],
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['qty'],
                    'price' => $item['price'],
                    'tax' => 0,
                    'discount' => 0,
                    'sub_total' => $item['st'],
                ]);
            }

            // Status history
            $flow = [];
            if (in_array($status, ['confirmed', 'processing', 'shipped', 'delivered'])) $flow[] = ['status' => 'pending', 'at' => $dates['created_at']];
            if (in_array($status, ['confirmed', 'processing', 'shipped', 'delivered'])) $flow[] = ['status' => 'confirmed', 'at' => $dates['created_at']->copy()->addHours(rand(1, 4))];
            if (in_array($status, ['processing', 'shipped', 'delivered'])) $flow[] = ['status' => 'processing', 'at' => $dates['created_at']->copy()->addHours(rand(5, 12))];
            if (in_array($status, ['shipped', 'delivered'])) $flow[] = ['status' => 'shipped', 'at' => $dates['created_at']->copy()->addHours(rand(13, 48))];
            if ($status === 'delivered') $flow[] = ['status' => 'delivered', 'at' => $dates['created_at']->copy()->addDays(rand(1, 5))];

            foreach ($flow as $f) {
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status' => $f['status'],
                    'changed_by' => rand(0, 1) ? $customer->id : 1,
                    'note' => $f['status'] === 'shipped' ? 'Resi: ' . strtoupper(substr(md5(rand()), 0, 12)) : null,
                    'created_at' => $f['at'],
                    'updated_at' => $f['at'],
                ]);
            }

            // Transaction
            if ($isPaid) {
                $commission = $shop->commission_type === 'percentage'
                    ? $subTotal * ($shop->commission_value / 100)
                    : $shop->commission_value;

                Transaction::create([
                    'transaction_id' => 'TRX-' . $order->order_number,
                    'order_id' => $order->id,
                    'customer_id' => $customer->id,
                    'shop_id' => $shop->id,
                    'amount' => $total,
                    'admin_commission' => $commission,
                    'vendor_amount' => $total - $commission,
                    'payment_method' => $order->payment_method,
                    'status' => 'success',
                    'paid_at' => $dates['created_at'],
                    'created_at' => $dates['created_at'],
                    'updated_at' => $dates['created_at'],
                ]);
            }

            $bar->advance();
        }
        $bar->finish();

        // Canceled orders
        $this->command->info("\nCreating 500 canceled orders...");
        $bar2 = $this->command->getOutput()->createProgressBar(500);
        for ($i = 1; $i <= 500; $i++) {
            $shop = $shops->random();
            $customer = $customers->random();
            $product = Product::where('shop_id', $shop->id)->where('status', 'approved')->inRandomOrder()->first();
            if (!$product) { $bar2->advance(); continue; }

            $dates = ['created_at' => now()->subDays(rand(0, 60))];
            $order = Order::create([
                'order_number' => 'ORD-' . now()->subDays(rand(0, 60))->format('Ymd') . '-CL-' . rand(10000, 99999),
                'customer_id' => $customer->id, 'shop_id' => $shop->id,
                'sub_total' => $product->getEffectivePrice(), 'total' => $product->getEffectivePrice(),
                'payment_method' => 'transfer', 'payment_status' => 'unpaid',
                'order_status' => 'canceled', 'cancel_reason' => 'Customer request',
                'canceled_at' => $dates['created_at']->copy()->addHours(rand(1, 24)),
                'created_at' => $dates['created_at'], 'updated_at' => $dates['created_at'],
            ]);
            OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => $product->getEffectivePrice(), 'sub_total' => $product->getEffectivePrice()]);
            OrderStatusHistory::create(['order_id' => $order->id, 'status' => 'pending', 'changed_by' => $customer->id, 'created_at' => $dates['created_at']]);
            OrderStatusHistory::create(['order_id' => $order->id, 'status' => 'canceled', 'changed_by' => $customer->id, 'note' => 'Customer request', 'created_at' => $dates['created_at']->copy()->addHours(rand(1, 24))]);
            $bar2->advance();
        }
        $bar2->finish();

        $this->command->info("\n=== DONE: 5500 orders + transactions ===");
    }
}
