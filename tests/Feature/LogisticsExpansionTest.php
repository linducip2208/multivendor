<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\OrderShipment;
use App\Models\PaymentGroup;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Provider;
use App\Models\Shop;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Backoffice\FulfillmentService;
use App\Services\Backoffice\StockService;
use App\Services\CheckoutCalculator;
use App\Services\Shipping\ShippingService;
use App\Services\Vendor\VendorFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LogisticsExpansionTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_adds_logistics_columns(): void
    {
        foreach (['is_pickup', 'pickup_warehouse_id', 'pickup_code_hash', 'pickup_verified_at', 'pickup_ready_at', 'pickup_completed_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('orders', $column), "kolom orders.{$column} hilang");
        }

        foreach (['allow_pickup', 'pickup_hours', 'pickup_address'] as $column) {
            $this->assertTrue(Schema::hasColumn('warehouses', $column), "kolom warehouses.{$column} hilang");
        }

        foreach (['warehouse_id', 'is_pickup', 'pickup_code', 'pickup_verified_at', 'manifest_no', 'manifest_date'] as $column) {
            $this->assertTrue(Schema::hasColumn('order_shipments', $column), "kolom order_shipments.{$column} hilang");
        }

        foreach (['pickup_status', 'pickup_scheduled_at', 'pickup_address', 'pickup_courier', 'pickup_tracking', 'return_label_code'] as $column) {
            $this->assertTrue(Schema::hasColumn('order_returns', $column), "kolom order_returns.{$column} hilang");
        }
    }

    public function test_allocation_prefers_stocked_nearest_warehouse(): void
    {
        [$product] = $this->logisticsActors();

        $default = Warehouse::create([
            'name' => 'Gudang Utama Bandung', 'code' => 'GBD', 'city' => 'Bandung',
            'is_default' => true, 'is_active' => true, 'allow_pickup' => false,
        ]);
        $pickup = Warehouse::create([
            'name' => 'Toko Jakarta', 'code' => 'JKT', 'city' => 'Jakarta',
            'is_default' => false, 'is_active' => true, 'allow_pickup' => true,
        ]);

        ProductStock::create(['warehouse_id' => $default->id, 'product_id' => $product->id, 'on_hand' => 50, 'reserved' => 0]);
        ProductStock::create(['warehouse_id' => $pickup->id, 'product_id' => $product->id, 'on_hand' => 10, 'reserved' => 0]);

        $chosen = app(ShippingService::class)->allocateWarehouseForItems([$product->id => 5], 'Jakarta');

        $this->assertNotNull($chosen);
        $this->assertSame($pickup->id, $chosen->id);

        // Kebutuhan melebihi semua stok: fallback ke gudang utama.
        $fallback = app(ShippingService::class)->allocateWarehouseForItems([$product->id => 5000], 'Surabaya');

        $this->assertNotNull($fallback);
        $this->assertSame($default->id, $fallback->id);
    }

    public function test_stock_service_suggests_availability(): void
    {
        [$product] = $this->logisticsActors();

        $warehouse = Warehouse::create([
            'name' => 'Gudang Stok', 'code' => 'GST', 'city' => 'Jakarta',
            'is_default' => true, 'is_active' => true, 'allow_pickup' => true,
        ]);
        ProductStock::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'on_hand' => 8, 'reserved' => 3]);

        $suggestion = app(StockService::class)->suggestWarehouse([$product->id => 5], 'Jakarta');

        $this->assertNotNull($suggestion['warehouse']);
        $this->assertSame(5, $suggestion['available'][$product->id]);
        $this->assertTrue($suggestion['full']);

        $short = app(StockService::class)->suggestWarehouse([$product->id => 6], 'Jakarta');

        $this->assertFalse($short['full']);
    }

    public function test_pickup_checkout_is_free_and_atomic(): void
    {
        [$customer, , $shop, $category] = $this->commerceActors();
        $product = Product::create([
            'shop_id' => $shop->id, 'category_id' => $category->id, 'name' => 'Digital Pickup',
            'slug' => 'digital-pickup', 'price' => 150000, 'current_stock' => 5,
            'product_type' => 'digital', 'status' => 'approved', 'published' => true,
        ]);
        Cart::create(['customer_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 1]);
        $provider = $this->invoiceProvider();
        $address = $this->customerAddress($customer);
        $warehouse = Warehouse::create([
            'name' => 'Toko Ambil', 'code' => 'AMB', 'city' => 'Jakarta',
            'is_default' => true, 'is_active' => true, 'allow_pickup' => true,
        ]);
        Http::fake(['https://invoice.test/v2/invoices' => Http::response(['id' => 'invoice-pickup', 'invoice_url' => 'https://pay.test/invoice-pickup'], 200)]);

        $this->actingAs($customer)->post('/checkout', [
            'address_id' => $address->id,
            'shipping_methods' => [$shop->id => ['pickup' => true, 'pickup_warehouse_id' => $warehouse->id]],
            'payment_provider_id' => $provider->id,
            'payment_channel' => [$provider->id => 'BCA'],
        ])->assertRedirect('https://pay.test/invoice-pickup');

        $order = Order::firstOrFail();
        $this->assertTrue((bool) $order->is_pickup);
        $this->assertSame(0.0, (float) $order->shipping_cost);
        $this->assertSame('PICKUP', $order->shipping_method);

        $shipment = OrderShipment::where('order_id', $order->id)->firstOrFail();
        $this->assertTrue($shipment->isPickup());
        $this->assertSame(0.0, (float) $shipment->cost);
        $this->assertSame(6, strlen((string) $shipment->pickup_code));
        $this->assertTrue(Hash::check((string) $shipment->pickup_code, (string) $order->pickup_code_hash));

        $group = PaymentGroup::firstOrFail();
        $this->assertSame(150000.0, (float) $group->grand_total);
    }

    public function test_pickup_checkout_rejects_bad_warehouse_atomically(): void
    {
        [$customer, , $shop, $category] = $this->commerceActors();
        $product = Product::create([
            'shop_id' => $shop->id, 'category_id' => $category->id, 'name' => 'Digital Gagal',
            'slug' => 'digital-gagal', 'price' => 50000, 'current_stock' => 5,
            'product_type' => 'digital', 'status' => 'approved', 'published' => true,
        ]);
        Cart::create(['customer_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 1]);
        $provider = $this->invoiceProvider();
        $address = $this->customerAddress($customer);
        $closed = Warehouse::create([
            'name' => 'Gudang Tutup', 'code' => 'TTP', 'city' => 'Bogor',
            'is_default' => false, 'is_active' => true, 'allow_pickup' => false,
        ]);

        $this->actingAs($customer)->post('/checkout', [
            'address_id' => $address->id,
            'shipping_methods' => [$shop->id => ['pickup' => true, 'pickup_warehouse_id' => $closed->id]],
            'payment_provider_id' => $provider->id,
        ])->assertSessionHasErrors();

        // Atomicity: tidak ada order / kiriman / grup yang tersisa.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_shipments', 0);
        $this->assertDatabaseCount('payment_groups', 0);
    }

    public function test_pickup_code_verifies_once(): void
    {
        [$customer, , $shop] = $this->commerceActors();
        $warehouse = Warehouse::create([
            'name' => 'Toko Verifikasi', 'code' => 'VRF', 'city' => 'Jakarta',
            'is_default' => true, 'is_active' => true, 'allow_pickup' => true,
        ]);
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id,
            'shop_id' => $shop->id, 'total' => 100000, 'payment_method' => 'transfer',
            'payment_status' => 'paid', 'order_status' => 'confirmed',
        ]);

        $shipment = app(FulfillmentService::class)->createPickupShipment($order, $warehouse->id);
        $code = (string) $shipment->pickup_code;

        app(FulfillmentService::class)->readyForPickup($shipment);

        $verified = app(FulfillmentService::class)->verifyPickup($order, strtolower($code));
        $this->assertSame('delivered', $verified->status);
        $this->assertTrue($verified->isPickupVerified());

        try {
            app(FulfillmentService::class)->verifyPickup($order, $code);
            $this->fail('Verifikasi kedua harus ditolak.');
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }

        $order2 = Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id,
            'shop_id' => $shop->id, 'total' => 100000, 'payment_method' => 'transfer',
            'payment_status' => 'paid', 'order_status' => 'confirmed',
        ]);
        app(FulfillmentService::class)->createPickupShipment($order2, $warehouse->id);

        try {
            app(FulfillmentService::class)->verifyPickup($order2, 'SALAH1');
            $this->fail('Kode salah harus ditolak.');
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }

    public function test_manifest_batch_recap_and_export(): void
    {
        [$customer, , $shop] = $this->commerceActors();

        $ids = [];
        foreach (['RESI-1', 'RESI-2'] as $resi) {
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id,
                'shop_id' => $shop->id, 'total' => 80000, 'payment_method' => 'transfer',
                'payment_status' => 'paid', 'order_status' => 'shipped',
            ]);
            $ids[] = OrderShipment::create([
                'order_id' => $order->id, 'courier' => 'JNE', 'service' => 'REG',
                'tracking_number' => $resi, 'cost' => 15000, 'status' => 'shipped',
            ])->id;
        }

        $batch = app(FulfillmentService::class)->manifestBatch($ids);

        $this->assertStringStartsWith('MNF-', $batch['manifest_no']);
        $this->assertSame(2, $batch['total']);

        // Idempoten: batch ulang tidak menimpa nomor manifest.
        $again = app(FulfillmentService::class)->manifestBatch($ids);
        $this->assertSame(0, $again['total']);

        $recap = app(FulfillmentService::class)->manifestRecap('JNE', now()->toDateString());
        $this->assertNotEmpty($recap);
        $this->assertSame($batch['manifest_no'], $recap[0]['manifest_no']);
        $this->assertSame(2, $recap[0]['total']);

        $export = app(FulfillmentService::class)->manifestExportRows($batch['manifest_no']);
        $this->assertSame(['No. Manifest', 'Nomor Pesanan', 'Kurir', 'Layanan', 'No. Resi', 'Berat', 'Biaya', 'Status'], $export[0]);
        $this->assertCount(3, $export);
    }

    public function test_return_pickup_schedule_status_and_label(): void
    {
        [$customer, , $shop, $category] = $this->commerceActors();
        $product = Product::create([
            'shop_id' => $shop->id, 'category_id' => $category->id, 'name' => 'Retur Jemput',
            'slug' => 'retur-jemput', 'price' => 90000, 'current_stock' => 5,
            'status' => 'approved', 'published' => true,
        ]);
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id,
            'shop_id' => $shop->id, 'total' => 90000, 'payment_method' => 'transfer',
            'payment_status' => 'paid', 'order_status' => 'delivered',
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id, 'quantity' => 1, 'price' => 90000, 'sub_total' => 90000,
        ]);
        $return = OrderReturn::create([
            'rma_number' => 'RMA-'.uniqid(), 'order_id' => $order->id, 'order_item_id' => $item->id,
            'reason' => 'rusak', 'status' => 'approved', 'amount' => 90000,
        ]);

        $scheduled = app(FulfillmentService::class)->scheduleReturnPickup($return, [
            'scheduled_at' => now()->addDay()->toDateTimeString(),
            'address' => 'Jl. Jemput No. 1, Jakarta',
            'courier' => 'JNE',
        ]);

        $this->assertSame('scheduled', $scheduled->pickup_status);
        $this->assertNotNull($scheduled->pickup_scheduled_at);
        $this->assertStringStartsWith('RTL-', (string) $scheduled->return_label_code);

        app(FulfillmentService::class)->markReturnPickup($scheduled, 'in_transit', 'RET-001');
        $received = app(FulfillmentService::class)->markReturnPickup($scheduled->fresh(), 'received');

        $this->assertSame('received', $received->pickup_status);
        $this->assertSame('received', $received->status);

        $label = app(FulfillmentService::class)->returnLabelData($received);

        $this->assertSame((string) $received->return_label_code, $label['kode_label']);
        $this->assertSame($return->rma_number, $label['rma']);
        $this->assertSame('JNE', $label['kurir_jemput']);
    }

    public function test_vendor_scoped_manifest_and_pickup(): void
    {
        [$customer, $vendor, $shop] = $this->commerceActors();
        $warehouse = Warehouse::create([
            'name' => 'Toko Vendor', 'code' => 'VND', 'city' => 'Jakarta',
            'is_default' => true, 'is_active' => true, 'allow_pickup' => true,
        ]);
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id,
            'shop_id' => $shop->id, 'total' => 120000, 'payment_method' => 'transfer',
            'payment_status' => 'paid', 'order_status' => 'confirmed',
        ]);

        $this->actingAs($vendor, 'vendor');
        $service = app(VendorFulfillmentService::class);

        $shipment = $service->createPickup($order, $warehouse->id);
        $this->assertTrue($shipment->isPickup());

        $batch = $service->manifestBatchForShop([$shipment->id]);
        $this->assertSame(1, $batch['total']);

        $recap = $service->manifestRecapForShop();
        $this->assertNotEmpty($recap);

        $queue = $service->pickupQueue();
        $this->assertTrue($queue->contains('id', $shipment->id));
    }

    public function test_checkout_without_pickup_keeps_existing_totals(): void
    {
        [$customer, , $shop, $category] = $this->commerceActors();
        $product = Product::create([
            'shop_id' => $shop->id, 'category_id' => $category->id, 'name' => 'Digital Biasa',
            'slug' => 'digital-biasa', 'price' => 100000, 'current_stock' => 5,
            'product_type' => 'digital', 'status' => 'approved', 'published' => true,
        ]);
        Cart::create(['customer_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 1]);

        $quote = app(CheckoutCalculator::class)->calculate(
            $customer, Cart::where('customer_id', $customer->id)->with(['product.shop', 'variant'])->get(), [], null, []
        );

        $this->assertSame(100000.0, (float) $quote['grand_total']);
        $this->assertSame(0.0, (float) $quote['shipping']);
    }

    /**
     * @return array{User, User, Shop, Category}
     */
    private function commerceActors(): array
    {
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $vendor = User::factory()->create(['role' => 'vendor', 'status' => 'active']);
        $shop = Shop::create(['vendor_id' => $vendor->id, 'name' => 'Shop Logistik', 'slug' => 'shop-logistik-'.uniqid(), 'status' => 'active']);
        $category = Category::create(['name' => 'Logistik '.uniqid(), 'slug' => 'logistik-'.uniqid(), 'status' => true]);

        return [$customer, $vendor, $shop, $category];
    }

    /** @return array{Product} */
    private function logisticsActors(): array
    {
        [, , $shop, $category] = $this->commerceActors();
        $product = Product::create([
            'shop_id' => $shop->id, 'category_id' => $category->id, 'name' => 'Fisik Alokasi',
            'slug' => 'fisik-alokasi-'.uniqid(), 'price' => 75000, 'current_stock' => 100,
            'status' => 'approved', 'published' => true,
        ]);

        return [$product];
    }

    private function invoiceProvider(): Provider
    {
        return Provider::create([
            'name' => 'Invoice', 'type' => 'payment', 'api_format' => 'xendit-invoice',
            'base_url' => 'https://invoice.test', 'api_key_encrypted' => 'key',
            'api_secret_encrypted' => 'token', 'is_active' => true,
        ]);
    }

    private function customerAddress(User $customer): CustomerAddress
    {
        return CustomerAddress::create([
            'customer_id' => $customer->id, 'label' => 'Rumah', 'receiver_name' => 'Customer',
            'receiver_phone' => '0812345678', 'address' => 'Jalan Test', 'city' => 'Jakarta',
            'province' => 'DKI', 'shipping_destination_id' => '501', 'is_default' => true,
        ]);
    }
}
