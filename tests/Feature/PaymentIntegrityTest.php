<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\PaymentGroup;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Shop;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CheckoutCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_midtrans_callback_is_idempotent_for_a_payment_group(): void
    {
        [$customer, $vendor, $shop, $category] = $this->commerceActors();
        $provider = Provider::create(['name' => 'Gateway', 'type' => 'payment', 'api_format' => 'midtrans-snap', 'base_url' => 'https://gateway.test', 'api_secret_encrypted' => 'callback-secret', 'is_active' => true]);
        $group = PaymentGroup::create(['payment_number' => PaymentGroup::generateNumber(), 'customer_id' => $customer->id, 'provider_id' => $provider->id, 'grand_total' => 100000, 'status' => 'pending']);
        $order = Order::create(['payment_group_id' => $group->id, 'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id, 'shop_id' => $shop->id, 'total' => 100000, 'payment_method' => 'midtrans', 'payment_status' => 'unpaid', 'order_status' => 'pending']);
        $group->orders()->attach($order->id, ['amount' => 100000]);
        Transaction::create(['transaction_id' => 'TRX-'.$order->order_number, 'payment_group_id' => $group->id, 'order_id' => $order->id, 'customer_id' => $customer->id, 'shop_id' => $shop->id, 'amount' => 100000, 'payment_method' => 'midtrans', 'status' => 'pending']);
        $body = ['order_id' => $group->payment_number, 'status_code' => '200', 'gross_amount' => '100000', 'transaction_status' => 'settlement', 'fraud_status' => 'accept', 'transaction_id' => 'gateway-123'];
        $body['signature_key'] = hash('sha512', $body['order_id'].$body['status_code'].$body['gross_amount'].'callback-secret');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/webhook/payment/'.$provider->id, $body)->assertOk();
        }

        $this->assertDatabaseHas('payment_groups', ['id' => $group->id, 'status' => 'paid']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);
        $this->assertDatabaseHas('transactions', ['order_id' => $order->id, 'status' => 'success']);
        $this->assertDatabaseCount('payment_webhook_callbacks', 1);
        $this->assertSame(0.0, (float) $vendor->wallet->fresh()->balance);
    }

    public function test_calculator_uses_database_price_and_vendor_coupon_only_for_matching_shop(): void
    {
        [$customer, $vendorA, $shopA, $category] = $this->commerceActors();
        $vendorB = User::factory()->create(['role' => 'vendor', 'status' => 'active']);
        Wallet::create(['user_id' => $vendorB->id, 'balance' => 0]);
        $shopB = Shop::create(['vendor_id' => $vendorB->id, 'name' => 'Shop B', 'slug' => 'shop-b', 'status' => 'active']);
        $one = Product::create(['shop_id' => $shopA->id, 'category_id' => $category->id, 'name' => 'Digital A', 'slug' => 'digital-a', 'price' => 100000, 'current_stock' => 4, 'product_type' => 'digital', 'status' => 'approved', 'published' => true]);
        $two = Product::create(['shop_id' => $shopB->id, 'category_id' => $category->id, 'name' => 'Digital B', 'slug' => 'digital-b', 'price' => 100000, 'current_stock' => 4, 'product_type' => 'digital', 'status' => 'approved', 'published' => true]);
        Cart::create(['customer_id' => $customer->id, 'product_id' => $one->id, 'quantity' => 1, 'price' => 1]);
        Cart::create(['customer_id' => $customer->id, 'product_id' => $two->id, 'quantity' => 1, 'price' => 1]);
        Coupon::create(['shop_id' => $shopA->id, 'code' => 'VENDORA', 'coupon_type' => 'fixed', 'discount_value' => 20000, 'status' => true]);

        $quote = app(CheckoutCalculator::class)->calculate($customer, Cart::where('customer_id', $customer->id)->with(['product.shop', 'variant'])->get(), [], 'VENDORA', []);
        $quotes = $quote['shops']->keyBy(fn ($shop) => $shop->shop->id);
        $this->assertSame(200000.0, $quote['subtotal']);
        $this->assertSame(20000.0, (float) $quotes[$shopA->id]->couponDiscount);
        $this->assertSame(0.0, (float) $quotes[$shopB->id]->couponDiscount);
    }

    public function test_wallet_reservation_prevents_double_withdrawal(): void
    {
        $user = User::factory()->create(['role' => 'vendor', 'status' => 'active']);
        $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 1000000]);
        $wallet->reserve(800000, 'Hold one', 'withdraw', 1, 'withdraw:hold:1');
        $this->assertSame(200000.0, (float) $wallet->fresh()->balance);
        $this->expectException(\DomainException::class);
        $wallet->reserve(800000, 'Hold two', 'withdraw', 2, 'withdraw:hold:2');
    }

    private function commerceActors(): array
    {
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        Wallet::create(['user_id' => $customer->id, 'balance' => 0]);
        $vendor = User::factory()->create(['role' => 'vendor', 'status' => 'active']);
        Wallet::create(['user_id' => $vendor->id, 'balance' => 0]);
        $shop = Shop::create(['vendor_id' => $vendor->id, 'name' => 'Shop A', 'slug' => 'shop-a', 'status' => 'active']);
        $category = Category::create(['name' => 'Category', 'slug' => 'category', 'status' => true]);

        return [$customer, $vendor, $shop, $category];
    }
}
