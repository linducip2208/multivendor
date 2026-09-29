<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\PaymentGroup;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Shop;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderWorkflowService;
use App\Services\RefundWorkflowService;
use App\Services\Shipping\ShippingService;
use App\Services\Vendor\PosService;
use App\Services\Vendor\VendorFulfillmentService;
use App\Services\Vendor\VendorInventoryService;
use App\Services\Vendor\VendorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Perdalaman fitur transaksi existing (tanpa migrasi baru).
 *
 * Self-contained: seluruh data dibuat di sini, memakai RefreshDatabase
 * seperti tests/Feature/CategoryTilesTest.php.
 */
class TransactionExpansionTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): User
    {
        return User::create([
            'name' => 'Pelanggan Uji', 'email' => 'pelanggan'.uniqid().'@uji.id',
            'password' => bcrypt('rahasia'), 'role' => 'customer', 'status' => 'active',
        ]);
    }

    private function makeVendor(): User
    {
        $vendor = User::create([
            'name' => 'Vendor Uji', 'email' => 'vendor'.uniqid().'@uji.id',
            'password' => bcrypt('rahasia'), 'role' => 'vendor', 'status' => 'active',
        ]);

        Shop::create([
            'vendor_id' => $vendor->id, 'name' => 'Toko Uji', 'slug' => 'toko-uji-'.uniqid(),
            'status' => 'active',
        ]);

        return $vendor->fresh();
    }

    private function makeProduct(Shop $shop, array $over = []): Product
    {
        $category = Category::create(['name' => 'Kat '.uniqid(), 'slug' => 'kat-'.uniqid(), 'status' => true]);

        return Product::create(array_merge([
            'shop_id' => $shop->id, 'category_id' => $category->id,
            'name' => 'Produk Uji', 'slug' => 'produk-'.uniqid(),
            'price' => 50000, 'current_stock' => 100, 'weight' => 1000,
            'status' => 'approved', 'published' => true,
            'min_qty' => 1, 'max_qty' => 10,
        ], $over));
    }

    private function makePaymentProvider(): Provider
    {
        return Provider::create([
            'name' => 'Gateway Uji', 'type' => 'payment',
            'api_format' => 'format-tidak-didukung-uji',
            'is_active' => true, 'sort_order' => 1,
        ]);
    }

    private function makeOrder(User $customer, Shop $shop, Product $product, array $over = []): Order
    {
        $group = PaymentGroup::create(array_merge([
            'payment_number' => PaymentGroup::generateNumber(),
            'customer_id' => $customer->id,
            'provider_id' => $this->makePaymentProvider()->id,
            'subtotal' => 50000, 'tax' => 0, 'shipping_cost' => 0,
            'discount' => 0, 'grand_total' => 50000,
            'status' => 'pending', 'expired_at' => now()->addDay(),
        ], $over['group'] ?? []));

        $order = Order::create(array_merge([
            'payment_group_id' => $group->id,
            'order_number' => Order::generateOrderNumber(),
            'customer_id' => $customer->id, 'shop_id' => $shop->id,
            'sub_total' => 50000, 'tax' => 0, 'shipping_cost' => 0,
            'discount' => 0, 'total' => 50000,
            'payment_status' => 'unpaid', 'order_status' => OrderStatus::Pending->stored(),
            'payment_method' => 'transfer',
            'idempotency_key' => 'uji-'.uniqid(),
        ], $over['order'] ?? []));

        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 1, 'price' => 50000, 'tax' => 0,
            'discount' => 0, 'sub_total' => 50000,
        ]);

        $group->orders()->attach($order->id, ['amount' => $order->total]);

        return $order->fresh(['items', 'paymentGroup']);
    }

    public function test_resume_pembayaran_gagal_tanpa_order_baru(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $product = $this->makeProduct($vendor->shop);
        $order = $this->makeOrder($customer, $vendor->shop, $product, [
            'order' => ['order_status' => 'failed'],
            'group' => ['status' => 'failed'],
        ]);

        $this->assertTrue($order->paymentGroup->isRetryable());
        $this->assertTrue($order->isPaymentRetryable());

        // Idempotency replay: kunci yang sama menemukan order yang sama.
        $replay = Order::where('customer_id', $customer->id)
            ->where('idempotency_key', $order->idempotency_key)
            ->first();

        $this->assertNotNull($replay);
        $this->assertSame($order->id, $replay->id);

        $countBefore = Order::where('customer_id', $customer->id)->count();

        // Gateway tidak didukung -> recreate gagal dengan aman, tanpa order baru.
        $controller = new \App\Http\Controllers\Storefront\CheckoutController;
        $method = new \ReflectionMethod($controller, 'recreateGatewayPayment');
        $method->setAccessible(true);
        $result = $method->invoke($controller, $order->paymentGroup->fresh());

        $this->assertNull($result);
        $this->assertSame($countBefore, Order::where('customer_id', $customer->id)->count());
    }

    public function test_bulk_fulfillment_status_massal(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $product = $this->makeProduct($vendor->shop);

        $a = $this->makeOrder($customer, $vendor->shop, $product, [
            'order' => ['payment_status' => 'paid', 'order_status' => OrderStatus::Paid->stored()],
            'group' => ['status' => 'paid'],
        ]);
        $b = $this->makeOrder($customer, $vendor->shop, $product, [
            'order' => ['payment_status' => 'paid', 'order_status' => OrderStatus::Paid->stored()],
            'group' => ['status' => 'paid'],
        ]);

        $workflow = app(OrderWorkflowService::class);
        $result = $workflow->bulkTransition([$a->id, $b->id, 999999], OrderStatus::Confirmed, $vendor->id, 'Konfirmasi massal');

        $this->assertContains($a->id, $result['ok']);
        $this->assertContains($b->id, $result['ok']);
        $this->assertArrayHasKey(999999, $result['fail']);
        $this->assertSame('confirmed', Order::find($a->id)->order_status);
    }

    public function test_label_massal_dan_ekspor(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $product = $this->makeProduct($vendor->shop);
        $order = $this->makeOrder($customer, $vendor->shop, $product, [
            'order' => ['payment_status' => 'paid', 'order_status' => OrderStatus::Paid->stored()],
            'group' => ['status' => 'paid'],
        ]);

        \App\Models\OrderShipment::create([
            'order_id' => $order->id, 'courier' => 'JNE', 'service' => 'REG',
            'tracking_number' => 'RESI-'.uniqid(), 'weight' => 1000, 'cost' => 12000,
            'status' => 'shipped', 'shipped_at' => now(),
        ]);

        $this->actingAs($vendor, 'vendor');
        $service = new VendorFulfillmentService(new VendorScope);

        $labels = $service->labelsFor([$order->id]);
        $this->assertCount(1, $labels);
        $this->assertArrayHasKey('resi', $labels[0]);

        $export = $service->exportRows([$order->id]);
        $this->assertSame(['Nomor Pesanan', 'Kurir', 'Layanan', 'No. Resi', 'Status', 'Biaya'], $export[0]);
        $this->assertCount(2, $export);
    }

    public function test_retur_per_item_alasan_terstruktur_dan_analitik(): void
    {
        $this->assertArrayHasKey('rusak', RefundWorkflowService::reasonCatalog());
        $this->assertSame('lainnya', RefundWorkflowService::normalizeReason('ngawur'));
        $this->assertSame('rusak', RefundWorkflowService::normalizeReason('RUSAK'));

        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $product = $this->makeProduct($vendor->shop);
        $order = $this->makeOrder($customer, $vendor->shop, $product, [
            'order' => ['payment_status' => 'paid', 'order_status' => OrderStatus::Delivered->stored()],
            'group' => ['status' => 'paid'],
        ]);

        $workflow = app(OrderWorkflowService::class);
        $retur = $workflow->requestReturn($order, $customer->id, 'rusak', $order->items->first()->id);

        $this->assertSame('rusak', $retur->reason);
        $this->assertSame('Barang rusak/cacat', $retur->reasonLabel());

        $analytics = RefundWorkflowService::analyticsForShop($vendor->shop->id);
        $rusak = collect($analytics)->firstWhere('reason', 'rusak');
        $this->assertSame(1, (int) $rusak['total']);
    }

    public function test_rekonsiliasi_auto_match_dan_retry_webhook(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $product = $this->makeProduct($vendor->shop);
        $order = $this->makeOrder($customer, $vendor->shop, $product);
        $group = $order->paymentGroup;

        $gateway = app(\App\Services\Payment\PaymentGatewayService::class);
        $result = $gateway->reconcileGroup($group);

        $this->assertArrayHasKey('matched', $result);
        $group->refresh();
        $this->assertNotNull($group->last_reconciled_at);
        $this->assertSame(1, (int) $group->reconciliation_attempts);
        $this->assertNotNull(Order::find($order->id)->reconciled_at);

        // Callback selisih dapat ditandai untuk diproses ulang.
        $callback = \App\Models\PaymentWebhookCallback::create([
            'provider_id' => $group->provider_id,
            'payment_group_id' => $group->id,
            'gateway_transaction_id' => 'uji-'.uniqid(),
            'external_id' => $group->payment_number,
            'status' => 'pending',
            'payload' => ['uji' => true],
            'received_at' => now(),
            'processing_result' => 'amount_mismatch',
            'expected_amount' => 50000, 'reported_amount' => 40000,
        ]);

        $controller = app(\App\Http\Controllers\Webhook\PaymentWebhookController::class);
        $retry = $controller->retry($callback, $gateway);
        $this->assertContains($retry['code'], [200, 404, 422]);
    }

    public function test_ongkir_volumetrik_asuransi_zona(): void
    {
        $shipping = app(ShippingService::class);

        $this->assertSame(6000, $shipping->volumetricWeight(30, 20, 50));
        $this->assertSame(6000, $shipping->billableWeight(1000, ['length' => 30, 'width' => 20, 'height' => 50]));
        $this->assertSame(1000, $shipping->billableWeight(1000, []));

        $fee = $shipping->insuranceFee(100000);
        $this->assertGreaterThanOrEqual(0.0, $fee);

        DB::table('shipping_methods')->insert([
            'name' => 'Reguler Uji', 'code' => 'UJI-'.uniqid(), 'cost' => 0,
            'status' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $methodId = (int) DB::getPdo()->lastInsertId();
        $zoneId = DB::table('shipping_zones')->insertGetId([
            'name' => 'Zona Uji', 'status' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('shipping_zone_rates')->insert([
            'shipping_zone_id' => $zoneId, 'shipping_method_id' => $methodId,
            'min_order' => 0, 'max_order' => null, 'cost' => 9000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(9000.0, $shipping->zoneTableQuote(50000, (int) $zoneId));
    }

    public function test_pos_diskon_per_item_pajak_shift_dan_retur_stok(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAs($vendor, 'vendor');
        $product = $this->makeProduct($vendor->shop, ['price' => 20000, 'tax' => 10, 'current_stock' => 50]);

        $pos = new PosService(new VendorScope);
        $order = $pos->sell([
            'items' => [[
                'product_id' => $product->id, 'quantity' => 2,
                'discount' => 1000, 'tax_rate' => 10,
            ]],
            'payment_method' => 'cash',
        ]);

        $item = $order->items->first();
        $this->assertSame(1000.0, (float) $item->discount);
        $this->assertGreaterThan(0.0, (float) $item->tax);

        $register = \App\Models\PosRegister::create([
            'pos_outlet_id' => \App\Models\PosOutlet::create([
                'shop_id' => $vendor->shop->id, 'name' => 'Outlet Uji',
                'code' => 'OUT-'.uniqid(), 'is_active' => true,
            ])->id,
            'name' => 'Kasir 1', 'code' => 'REG-'.uniqid(), 'is_active' => true,
        ]);

        $shift = $pos->openShift($register->id, 100000, 'Shift pagi');
        $this->assertSame('open', $shift->status);

        $closed = $pos->closeShift($shift, 100000, 'Tutup shift');
        $this->assertSame('closed', $closed->status);
        $this->assertEquals((float) $closed->counted_cash - (float) $closed->expected_cash, (float) $closed->variance);

        $stockBefore = (int) $product->fresh()->current_stock;
        $pos->returnToStock($item, 1, 'Pelanggan berubah pikiran');
        $this->assertSame($stockBefore + 1, (int) $product->fresh()->current_stock);

        $barcodes = $pos->barcodeRows([$product->id]);
        $this->assertCount(1, $barcodes);
        $this->assertArrayHasKey('barcode', $barcodes[0]);
    }

    public function test_transfer_gudang_approval_opname_dan_laporan_selisih(): void
    {
        $vendor = $this->makeVendor();
        $this->actingAs($vendor, 'vendor');
        $product = $this->makeProduct($vendor->shop, ['current_stock' => 20]);

        $from = Warehouse::create(['name' => 'Gudang A', 'code' => 'GA-'.uniqid(), 'is_active' => true]);
        $to = Warehouse::create(['name' => 'Gudang B', 'code' => 'GB-'.uniqid(), 'is_active' => true]);

        $inventory = new VendorInventoryService(new VendorScope);
        $transfer = $inventory->requestTransfer($from->id, $to->id, [
            ['product_id' => $product->id, 'quantity' => 5],
        ], 'Pemerataan stok');

        $this->assertSame('draft', $transfer->status);
        $this->assertTrue($transfer->canShip());

        $shipped = $inventory->approveTransfer($transfer);
        $this->assertSame('in_transit', $shipped->status);

        $received = $inventory->receiveTransfer($shipped);
        $this->assertSame('received', $received->status);

        $movement = $inventory->opname($product, 25, $to->id, 'Opname mingguan');
        $this->assertSame('opname', $movement->type);
        $this->assertSame(25, (int) $product->fresh()->current_stock);

        $report = $inventory->varianceReport(10);
        $this->assertGreaterThanOrEqual(1, $report->count());
    }

    public function test_keranjang_simpan_nanti_estimasi_dan_abandoned(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $product = $this->makeProduct($vendor->shop);
        $this->actingAs($customer);

        $product2 = $this->makeProduct($vendor->shop);

        $cart = Cart::create([
            'customer_id' => $customer->id, 'product_id' => $product->id,
            'quantity' => 2, 'price' => 50000, 'tax' => 0,
        ]);

        Cart::create([
            'customer_id' => $customer->id, 'product_id' => $product2->id,
            'quantity' => 1, 'price' => 50000, 'tax' => 0,
        ]);

        $this->put(route('cart.update', $cart), ['quantity' => 2, 'action' => 'save_for_later'])
            ->assertRedirect();
        $this->assertContains($cart->id, session('saved_for_later'));

        $this->get(route('cart.index'))->assertOk()->assertSee('Simpan untuk nanti');

        $this->assertDatabaseHas('abandoned_carts', ['customer_id' => $customer->id]);
    }

    public function test_invoice_bernomor_seri_dan_catatan_per_toko(): void
    {
        $customer = $this->makeCustomer();
        $vendor = $this->makeVendor();
        $product = $this->makeProduct($vendor->shop);
        $order = $this->makeOrder($customer, $vendor->shop, $product);

        $this->assertStringStartsWith('INV-', $order->invoiceNumber());
        $this->assertStringContainsString((string) $order->order_number, $order->invoiceNumber());

        $note = Order::formatShopNote('Toko Uji', 'Bungkus kado ya');
        $this->assertStringContainsString('[Toko Toko Uji]', (string) $note);
        $this->assertNull(Order::formatShopNote('Toko Uji', ''));
    }
}
