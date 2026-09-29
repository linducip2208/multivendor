<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Shop;
use App\Models\User;
use App\Models\Wallet;
use App\Services\OrderWorkflowService;
use App\Services\RefundWorkflowService;
use App\Services\Vendor\PosService;
use App\Services\Vendor\VendorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Perdalaman order & POS: slot jadwal pengiriman, split tender + struk
 * digital POS, dan grading QC retur. Self-contained (RefreshDatabase).
 */
class OrderPosTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_adds_order_pos_columns(): void
    {
        foreach ([
            'delivery_slot_date', 'delivery_slot_time', 'delivery_slot_label',
            'slot_scheduled_at', 'receipt_token', 'pos_customer_phone',
            'digital_receipt_sent_at',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('orders', $column), "kolom orders.{$column} hilang");
        }

        $this->assertTrue(Schema::hasTable('pos_payments'));

        foreach (['order_id', 'method', 'amount', 'created_by'] as $column) {
            $this->assertTrue(Schema::hasColumn('pos_payments', $column), "kolom pos_payments.{$column} hilang");
        }

        foreach (['qc_grade', 'qc_note', 'qc_at', 'qc_by', 'stock_restored_qty'] as $column) {
            $this->assertTrue(Schema::hasColumn('order_returns', $column), "kolom order_returns.{$column} hilang");
        }
    }

    public function test_checkout_persists_delivery_slot(): void
    {
        [$customer, , $shop, $category] = $this->commerceActors();
        $product = $this->makeProduct($shop, $category, 'Slot Produk', 'slot-produk', 75000, 10, 'digital');
        Cart::create(['customer_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 1]);
        $provider = $this->makeProvider();
        $address = $this->makeAddress($customer);
        $slotDate = now()->addDay()->toDateString();
        Http::fake(['https://invoice.test/v2/invoices' => Http::response(['id' => 'invoice-slot', 'invoice_url' => 'https://pay.test/invoice-slot'], 200)]);

        $response = $this->actingAs($customer)->post('/checkout', [
            'address_id' => $address->id,
            'shipping_methods' => [$shop->id => []],
            'payment_provider_id' => $provider->id,
            'payment_channel' => [$provider->id => 'BCA'],
            'delivery_slot_date' => $slotDate,
            'delivery_slot_time' => '08:00-11:00',
        ]);
        $response->assertRedirect('https://pay.test/invoice-slot');

        $order = Order::firstOrFail();
        $this->assertSame($slotDate, (string) $order->getAttribute('delivery_slot_date'));
        $this->assertSame('08:00-11:00', (string) $order->getAttribute('delivery_slot_time'));
        $this->assertStringContainsString('Slot pengiriman', (string) $order->note);
        $this->assertNotNull($order->getAttribute('slot_scheduled_at'));
    }

    public function test_checkout_rejects_invalid_slot(): void
    {
        [$customer, , $shop, $category] = $this->commerceActors();
        $product = $this->makeProduct($shop, $category, 'Slot Invalid', 'slot-invalid', 50000, 10, 'digital');
        Cart::create(['customer_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 1]);
        $provider = $this->makeProvider();
        $address = $this->makeAddress($customer);

        $this->actingAs($customer)->post('/checkout', [
            'address_id' => $address->id,
            'shipping_methods' => [$shop->id => []],
            'payment_provider_id' => $provider->id,
            'delivery_slot_date' => now()->subDay()->toDateString(),
            'delivery_slot_time' => '08:00-11:00',
        ])->assertSessionHasErrors('delivery_slot_date');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_workflow_schedule_slot_is_idempotent(): void
    {
        [$customer, , $shop] = $this->commerceActors();
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id,
            'shop_id' => $shop->id, 'total' => 100000, 'payment_method' => 'transfer',
            'payment_status' => 'paid', 'order_status' => 'confirmed',
        ]);
        $workflow = app(OrderWorkflowService::class);
        $date = now()->addDays(2)->toDateString();

        $scheduled = $workflow->scheduleDeliverySlot($order, $date, '14:00-17:00', $customer->id);
        $this->assertSame($date, (string) $scheduled->getAttribute('delivery_slot_date'));
        $this->assertSame($date.', 14:00-17:00', OrderWorkflowService::deliverySlotLabel($scheduled));

        $history = DB::table('order_status_history')->where('order_id', $order->id)->where('status', 'slot_scheduled')->count();
        $this->assertSame(1, $history);

        // Penjadwalan ulang dengan slot sama: idempoten, tanpa history baru.
        $workflow->scheduleDeliverySlot($order->fresh(), $date, '14:00-17:00', $customer->id);
        $this->assertSame(1, DB::table('order_status_history')->where('order_id', $order->id)->where('status', 'slot_scheduled')->count());

        // Di luar jendela H+14 ditolak.
        try {
            $workflow->scheduleDeliverySlot($order->fresh(), now()->addDays(30)->toDateString(), null, $customer->id);
            $this->fail('Slot di luar jendela harus ditolak.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('delivery_slot_date', $e->errors());
        }
    }

    public function test_pos_split_tender_success(): void
    {
        [$customer, $vendor, $shop, $category] = $this->commerceActors();
        $product = $this->makeProduct($shop, $category, 'POS Split', 'pos-split', 50000, 10);
        $service = new PosService($this->vendorScope($vendor));

        $order = $service->sell([
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'customer_name' => 'Walk-in',
            'customer_phone' => '081234567890',
            'payment_method' => 'split',
            'tenders' => [
                ['method' => 'cash', 'amount' => 30000],
                ['method' => 'qris', 'amount' => 70000],
            ],
        ]);

        $this->assertSame('split', (string) $order->payment_method);
        $this->assertSame(100000.0, (float) $order->total);
        $this->assertSame(8, (int) $product->fresh()->current_stock);

        $rows = DB::table('pos_payments')->where('order_id', $order->id)->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(100000.0, (float) $rows->sum('amount'));
        $this->assertNotSame('', (string) $order->getAttribute('receipt_token'));
        $this->assertSame('081234567890', (string) $order->getAttribute('pos_customer_phone'));

        $tenders = $service->tendersFor($order->fresh());
        $this->assertCount(2, $tenders);
    }

    public function test_pos_split_tender_must_match_total(): void
    {
        [, $vendor, $shop, $category] = $this->commerceActors();
        $product = $this->makeProduct($shop, $category, 'POS Selisih', 'pos-selisih', 50000, 10);
        $service = new PosService($this->vendorScope($vendor));

        try {
            $service->sell([
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'payment_method' => 'split',
                'tenders' => [
                    ['method' => 'cash', 'amount' => 50000],
                    ['method' => 'qris', 'amount' => 40000],
                ],
            ]);
            $this->fail('Split tender yang tidak pas harus ditolak.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('tenders', $e->errors());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, (int) $product->fresh()->current_stock);
    }

    public function test_pos_sell_idempotent_on_idempotency_key(): void
    {
        [, $vendor, $shop, $category] = $this->commerceActors();
        $product = $this->makeProduct($shop, $category, 'POS Idem', 'pos-idem', 25000, 10);
        $service = new PosService($this->vendorScope($vendor));

        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'idempotency_key' => 'kasir-1-tap-2-kali',
        ];

        $first = $service->sell($payload);
        $second = $service->sell($payload);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(9, (int) $product->fresh()->current_stock);
    }

    public function test_pos_receipt_url_and_wa_link(): void
    {
        [, $vendor, $shop, $category] = $this->commerceActors();
        $product = $this->makeProduct($shop, $category, 'POS Struk', 'pos-struk', 40000, 10);
        $service = new PosService($this->vendorScope($vendor));

        $plain = $service->sell([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ]);
        $this->assertNull($service->waLink($plain->fresh()));
        $this->assertStringContainsString('token=', $service->receiptUrl($plain->fresh()));

        $withPhone = $service->sell([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'qris',
            'customer_phone' => '081234567890',
        ]);
        $wa = $service->waLink($withPhone->fresh());
        $this->assertNotNull($wa);
        $this->assertStringStartsWith('https://wa.me/6281234567890', (string) $wa);

        // Token salah → verifikasi gagal; token benar/legacy → lolos.
        $this->assertFalse($service->receiptVerifyOk($withPhone->fresh(), 'token-salah'));
        $this->assertTrue($service->receiptVerifyOk($withPhone->fresh(), (string) $withPhone->getAttribute('receipt_token')));

        // markReceiptShared idempoten: stempel pertama dipertahankan.
        $sent = $service->markReceiptShared($withPhone->fresh(), 'wa', $vendor->id);
        $this->assertNotNull($sent->getAttribute('digital_receipt_sent_at'));
        $first = (string) $sent->getAttribute('digital_receipt_sent_at');
        $again = $service->markReceiptShared($withPhone->fresh(), 'wa', $vendor->id);
        $this->assertSame($first, (string) $again->getAttribute('digital_receipt_sent_at'));
    }

    public function test_qc_grade_baik_restores_stock_once(): void
    {
        [$customer, $vendor, $shop, $category] = $this->commerceActors();
        $product = $this->makeProduct($shop, $category, 'QC Baik', 'qc-baik', 60000, 8);
        [$order, $item] = $this->soldItem($customer, $shop, $product, 2);
        $retur = $this->makeReturn($order, $item);
        $refunds = app(RefundWorkflowService::class);

        $graded = $refunds->gradeReturn($retur, 'baik', $vendor->id, 'Kondisi mulus');
        $this->assertSame('baik', (string) $graded->getAttribute('qc_grade'));
        $this->assertSame(2, (int) $graded->getAttribute('stock_restored_qty'));
        $this->assertSame(10, (int) $product->fresh()->current_stock);
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => 'order_return', 'reference_id' => $retur->id, 'type' => 'return', 'quantity' => 2,
        ]);

        // Idempoten: grading ulang yang sama tidak menambah stok lagi.
        $refunds->gradeReturn($retur->fresh(), 'baik', $vendor->id, 'Kondisi mulus');
        $this->assertSame(10, (int) $product->fresh()->current_stock);
        $this->assertSame(1, DB::table('stock_movements')->where('reference_type', 'order_return')->where('reference_id', $retur->id)->count());
    }

    public function test_qc_grade_rusak_and_buang_keep_stock_with_movement(): void
    {
        [$customer, $vendor, $shop, $category] = $this->commerceActors();
        $product = $this->makeProduct($shop, $category, 'QC Rusak', 'qc-rusak', 60000, 8);
        [$order, $item] = $this->soldItem($customer, $shop, $product, 2);
        $refunds = app(RefundWorkflowService::class);

        $rusak = $refunds->gradeReturn($this->makeReturn($order, $item), 'rusak', $vendor->id, 'Layar pecah');
        $this->assertSame('rusak', (string) $rusak->getAttribute('qc_grade'));
        $this->assertSame(0, (int) $rusak->getAttribute('stock_restored_qty'));
        $this->assertSame(8, (int) $product->fresh()->current_stock);

        $buang = $refunds->gradeReturn($this->makeReturn($order, $item), 'buang', $vendor->id, 'Tidak layak jual');
        $this->assertSame('buang', (string) $buang->getAttribute('qc_grade'));
        $this->assertSame(8, (int) $product->fresh()->current_stock);

        $this->assertSame(2, DB::table('stock_movements')->where('reference_type', 'order_return')->where('type', 'adjustment')->count());

        try {
            $refunds->gradeReturn($this->makeReturn($order, $item), 'hilang', $vendor->id);
            $this->fail('Grade QC tak dikenal harus ditolak.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('qc_grade', $e->errors());
        }
    }

    // ── Pembantu ──

    /** @return array{0: User, 1: User, 2: Shop, 3: Category} */
    private function commerceActors(): array
    {
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        Wallet::create(['user_id' => $customer->id, 'balance' => 0]);
        $vendor = User::factory()->create(['role' => 'vendor', 'status' => 'active']);
        Wallet::create(['user_id' => $vendor->id, 'balance' => 0]);
        $shop = Shop::create(['vendor_id' => $vendor->id, 'name' => 'Shop OrderPOS', 'slug' => 'shop-orderpos-'.uniqid(), 'status' => 'active']);
        $category = Category::create(['name' => 'OrderPOS '.uniqid(), 'slug' => 'orderpos-'.uniqid(), 'status' => true]);

        return [$customer, $vendor, $shop, $category];
    }

    private function vendorScope(User $vendor): VendorScope
    {
        $this->actingAs($vendor, 'vendor');

        return new VendorScope();
    }

    private function makeProduct(Shop $shop, Category $category, string $name, string $slug, float $price, int $stock, string $type = 'physical'): Product
    {
        return Product::create([
            'shop_id' => $shop->id, 'category_id' => $category->id, 'name' => $name,
            'slug' => $slug.'-'.uniqid(), 'price' => $price, 'current_stock' => $stock,
            'product_type' => $type, 'status' => 'approved', 'published' => true,
        ]);
    }

    private function makeProvider(): Provider
    {
        return Provider::create([
            'name' => 'Invoice', 'type' => 'payment', 'api_format' => 'xendit-invoice',
            'base_url' => 'https://invoice.test', 'api_key_encrypted' => 'key',
            'api_secret_encrypted' => 'token', 'is_active' => true,
        ]);
    }

    private function makeAddress(User $customer): CustomerAddress
    {
        return CustomerAddress::create([
            'customer_id' => $customer->id, 'label' => 'Rumah', 'receiver_name' => 'Customer',
            'receiver_phone' => '0812345678', 'address' => 'Jalan Test', 'city' => 'Jakarta',
            'province' => 'DKI', 'shipping_destination_id' => '501', 'is_default' => true,
        ]);
    }

    /** @return array{0: Order, 1: OrderItem} */
    private function soldItem(User $customer, Shop $shop, Product $product, int $qty): array
    {
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id,
            'shop_id' => $shop->id, 'total' => $product->price * $qty, 'payment_method' => 'transfer',
            'payment_status' => 'paid', 'order_status' => 'delivered',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => $qty, 'price' => $product->price, 'sub_total' => $product->price * $qty,
        ]);

        return [$order, $item];
    }

    private function makeReturn(Order $order, OrderItem $item): OrderReturn
    {
        return OrderReturn::create([
            'rma_number' => 'RMA-TEST-'.uniqid(), 'order_id' => $order->id,
            'order_item_id' => $item->id, 'reason' => 'rusak',
            'description' => '[rusak] Barang diterima dalam kondisi rusak',
            'status' => 'approved', 'amount' => (string) $item->sub_total,
        ]);
    }
}
