<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\PaymentGroup;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Shop;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CheckoutCalculator;
use App\Services\OrderWorkflowService;
use App\Services\Payment\PaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperationalExpansionTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_adds_operational_columns(): void
    {
        foreach ([
            'is_dropship', 'dropship_sender_name', 'dropship_sender_store', 'hide_price_in_package',
            'is_preorder', 'preorder_eta', 'preorder_dp_amount', 'preorder_remaining', 'preorder_settled_at',
            'is_gift', 'gift_wrap', 'gift_message', 'gift_fee',
            'cod_otp_hash', 'cod_otp_expires_at', 'cod_otp_attempts', 'cod_otp_verified_at',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('orders', $column), "kolom orders.{$column} hilang");
        }

        foreach (['is_preorder', 'preorder_lead_days', 'preorder_dp_percent'] as $column) {
            $this->assertTrue(Schema::hasColumn('products', $column), "kolom products.{$column} hilang");
        }

        $this->assertTrue(Schema::hasTable('order_repeat_schedules'));
        foreach (['customer_id', 'product_id', 'quantity', 'frequency', 'next_run_at', 'is_active'] as $column) {
            $this->assertTrue(Schema::hasColumn('order_repeat_schedules', $column), "kolom order_repeat_schedules.{$column} hilang");
        }
    }

    public function test_calculator_adds_gift_fee_to_grand_total(): void
    {
        [$customer, , $shop, $category] = $this->commerceActors();
        $product = Product::create([
            'shop_id' => $shop->id, 'category_id' => $category->id, 'name' => 'Digital Gift',
            'slug' => 'digital-gift', 'price' => 100000, 'current_stock' => 5,
            'product_type' => 'digital', 'status' => 'approved', 'published' => true,
        ]);
        Cart::create(['customer_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 1]);

        $plain = app(CheckoutCalculator::class)->calculate(
            $customer, Cart::where('customer_id', $customer->id)->with(['product.shop', 'variant'])->get(), [], null, []
        );
        $this->assertSame(0.0, (float) ($plain['gift_fee'] ?? -1));
        $this->assertSame(100000.0, (float) $plain['grand_total']);

        $gifted = app(CheckoutCalculator::class)->calculate(
            $customer, Cart::where('customer_id', $customer->id)->with(['product.shop', 'variant'])->get(), [], null, [],
            ['gift' => ['wrap' => true, 'fee' => 10000, 'message' => 'Selamat!']]
        );
        $this->assertSame(10000.0, (float) $gifted['gift_fee']);
        $this->assertSame(110000.0, (float) $gifted['grand_total']);
    }

    public function test_calculator_preorder_bills_dp_now_and_defers_remaining(): void
    {
        [$customer, , $shop, $category] = $this->commerceActors();
        $product = Product::create([
            'shop_id' => $shop->id, 'category_id' => $category->id, 'name' => 'Preorder Digital',
            'slug' => 'preorder-digital', 'price' => 100000, 'current_stock' => 5,
            'product_type' => 'digital', 'status' => 'approved', 'published' => true,
            'is_preorder' => true, 'preorder_lead_days' => 14, 'preorder_dp_percent' => 30,
        ]);
        Cart::create(['customer_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 1]);

        $quote = app(CheckoutCalculator::class)->calculate(
            $customer, Cart::where('customer_id', $customer->id)->with(['product.shop', 'variant'])->get(), [], null, []
        );

        $this->assertTrue((bool) ($quote['preorder']['has_preorder'] ?? false));
        $this->assertSame(30000.0, (float) $quote['preorder']['dp_due']);
        $this->assertSame(70000.0, (float) $quote['preorder']['remaining']);
        $this->assertSame(30000.0, (float) $quote['amount_due_now']);
        $this->assertSame(100000.0, (float) $quote['grand_total']);
        $this->assertNotNull($quote['preorder']['eta']);
    }

    public function test_checkout_persists_dropship_gift_and_preorder(): void
    {
        [$customer, , $shop, $category] = $this->commerceActors();
        $product = Product::create([
            'shop_id' => $shop->id, 'category_id' => $category->id, 'name' => 'Preorder Gift',
            'slug' => 'preorder-gift', 'price' => 200000, 'current_stock' => 5,
            'product_type' => 'digital', 'status' => 'approved', 'published' => true,
            'is_preorder' => true, 'preorder_lead_days' => 7, 'preorder_dp_percent' => 50,
        ]);
        Cart::create(['customer_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 1]);
        $provider = Provider::create([
            'name' => 'Invoice', 'type' => 'payment', 'api_format' => 'xendit-invoice',
            'base_url' => 'https://invoice.test', 'api_key_encrypted' => 'key',
            'api_secret_encrypted' => 'token', 'is_active' => true,
        ]);
        $address = CustomerAddress::create([
            'customer_id' => $customer->id, 'label' => 'Rumah', 'receiver_name' => 'Customer',
            'receiver_phone' => '0812345678', 'address' => 'Jalan Test', 'city' => 'Jakarta',
            'province' => 'DKI', 'shipping_destination_id' => '501', 'is_default' => true,
        ]);
        Http::fake(['https://invoice.test/v2/invoices' => Http::response(['id' => 'invoice-9', 'invoice_url' => 'https://pay.test/invoice-9'], 200)]);

        $this->actingAs($customer)->post('/checkout', [
            'address_id' => $address->id,
            'shipping_methods' => [$shop->id => []],
            'payment_provider_id' => $provider->id,
            'payment_channel' => [$provider->id => 'BCA'],
            'dropship_enabled' => true,
            'dropship_sender_name' => 'Budi Dropship',
            'dropship_sender_store' => 'Toko Budi',
            'dropship_hide_price' => true,
            'gift_wrap' => true,
            'gift_message' => 'Selamat ulang tahun!',
        ])->assertRedirect('https://pay.test/invoice-9');

        $order = Order::firstOrFail();
        $this->assertTrue($order->isDropship());
        $this->assertSame('Toko Budi', $order->shippingLabelSender());
        $this->assertTrue($order->shouldHidePrices());
        $this->assertTrue($order->isGift());
        $this->assertSame('Selamat ulang tahun!', $order->giftCardMessage());
        $this->assertTrue((float) $order->gift_fee > 0);
        $this->assertTrue($order->isPreorder());
        $this->assertSame(100000.0, (float) $order->preorder_dp_amount);
        $this->assertSame(100000.0, (float) $order->preorder_remaining);
        $this->assertSame(100000.0, (float) $order->preorderBalanceDue());
        $this->assertFalse($order->hasSettledPreorder());
        $this->assertSame('partial', $order->payment_status);

        // Grup menagih DP + gift fee, bukan total penuh.
        $group = PaymentGroup::firstOrFail();
        $this->assertSame(100000.0 + (float) $order->gift_fee, (float) $group->grand_total);
    }

    public function test_cod_otp_generate_verify_and_rate_limit(): void
    {
        [$customer, , $shop] = $this->commerceActors();
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id,
            'shop_id' => $shop->id, 'total' => 600000, 'payment_method' => 'cod',
            'payment_status' => 'unpaid', 'order_status' => 'pending',
        ]);
        $payments = app(PaymentGatewayService::class);

        $this->assertTrue($payments->codOtpRequired($order));

        $code = $payments->generateCodOtp($order);
        $this->assertSame(6, strlen($code));
        $this->assertTrue($payments->verifyCodOtp($order, $code));
        // Idempoten setelah terverifikasi.
        $this->assertTrue($payments->verifyCodOtp($order, '000000'));

        $code2 = $payments->generateCodOtp($order);
        for ($i = 0; $i < PaymentGatewayService::COD_OTP_MAX_ATTEMPTS; $i++) {
            $this->assertFalse($payments->verifyCodOtp($order, '000000'));
        }
        // Rate-limit: kode benar pun ditolak setelah 5 upaya gagal.
        $this->assertFalse($payments->verifyCodOtp($order, $code2));

        // Kedaluwarsa ditolak.
        $code3 = $payments->generateCodOtp($order);
        $order->forceFill(['cod_otp_expires_at' => now()->subMinute()])->save();
        $this->assertFalse($payments->verifyCodOtp($order, $code3));
    }

    public function test_cod_otp_not_required_below_threshold_or_non_cod(): void
    {
        [$customer, , $shop] = $this->commerceActors();
        $small = Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id,
            'shop_id' => $shop->id, 'total' => 10000, 'payment_method' => 'cod',
            'payment_status' => 'unpaid', 'order_status' => 'pending',
        ]);
        $transfer = Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id,
            'shop_id' => $shop->id, 'total' => 90000000, 'payment_method' => 'transfer',
            'payment_status' => 'unpaid', 'order_status' => 'pending',
        ]);

        $payments = app(PaymentGatewayService::class);
        $this->assertFalse($payments->codOtpRequired($small));
        $this->assertFalse($payments->codOtpRequired($transfer));
    }

    public function test_repeat_schedule_creates_draft_cart_not_order(): void
    {
        [$customer, , $shop, $category] = $this->commerceActors();
        $product = Product::create([
            'shop_id' => $shop->id, 'category_id' => $category->id, 'name' => 'Langganan',
            'slug' => 'langganan', 'price' => 50000, 'current_stock' => 50,
            'status' => 'approved', 'published' => true,
        ]);

        $scheduleId = Cart::scheduleRepeat([
            'customer_id' => $customer->id, 'product_id' => $product->id,
            'quantity' => 2, 'frequency' => 'weekly', 'next_run_at' => now()->subMinute(),
        ]);

        $result = Cart::runDueRepeats();
        $this->assertSame(1, $result['drafts']);
        $this->assertContains($scheduleId, $result['schedules']);
        $this->assertDatabaseHas('carts', ['customer_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 2]);
        $this->assertDatabaseCount('orders', 0);

        // Jadwal maju otomatis: run kedua tidak membuat draf baru.
        $again = Cart::runDueRepeats();
        $this->assertSame(0, $again['drafts']);
        $this->assertDatabaseHas('carts', ['customer_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 2]);

        // Jadwal jatuh tempo lagi: idempoten terhadap cart existing (tambah kuantitas).
        DB::table('order_repeat_schedules')->where('id', $scheduleId)->update(['next_run_at' => now()->subMinute()]);
        $rerun = Cart::runDueRepeats();
        $this->assertSame(1, $rerun['drafts']);
        $this->assertDatabaseHas('carts', ['customer_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 4]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_preorder_ship_blocked_until_settlement(): void
    {
        [$customer, , $shop] = $this->commerceActors();
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id,
            'shop_id' => $shop->id, 'total' => 200000, 'payment_method' => 'transfer',
            'payment_status' => 'partial', 'order_status' => 'pending',
            'is_preorder' => true, 'preorder_dp_amount' => 100000, 'preorder_remaining' => 100000,
        ]);
        $workflow = app(OrderWorkflowService::class);

        $workflow->confirm($order);
        $workflow->process($order);
        $workflow->pack($order);

        try {
            $workflow->ship($order->fresh());
            $this->fail('ship() harus menolak pre-order yang belum lunas.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('preorder', $e->errors());
        }

        $settled = $workflow->recordPreorderSettlement($order->fresh());
        $this->assertSame(0.0, (float) $settled->preorder_remaining);
        $this->assertTrue($settled->fresh()->hasSettledPreorder());

        // Idempoten: pelunasan kedua mengembalikan order apa adanya.
        $workflow->recordPreorderSettlement($order->fresh());

        $shipped = $workflow->ship($order->fresh(), null, 'RESI-1');
        $this->assertSame('shipped', $shipped->order_status);
    }

    private function commerceActors(): array
    {
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        Wallet::create(['user_id' => $customer->id, 'balance' => 0]);
        $vendor = User::factory()->create(['role' => 'vendor', 'status' => 'active']);
        Wallet::create(['user_id' => $vendor->id, 'balance' => 0]);
        $shop = Shop::create(['vendor_id' => $vendor->id, 'name' => 'Shop Ops', 'slug' => 'shop-ops-'.uniqid(), 'status' => 'active']);
        $category = Category::create(['name' => 'Ops '.uniqid(), 'slug' => 'ops-'.uniqid(), 'status' => true]);

        return [$customer, $vendor, $shop, $category];
    }
}
