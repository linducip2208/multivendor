<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\DateRange;
use App\Services\Analytics\StockAnalyticsService;
use App\Services\Backoffice\StockService;
use App\Services\Backoffice\SystemHealthService;
use App\Services\Vendor\VendorInventoryService;
use App\Services\Vendor\VendorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GeoForecastTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_adds_geo_forecast_columns(): void
    {
        foreach (['latitude', 'longitude', 'service_radius_km'] as $column) {
            $this->assertTrue(Schema::hasColumn('warehouses', $column), "kolom warehouses.{$column} hilang");
        }

        $this->assertTrue(Schema::hasColumn('shops', 'service_radius_km'), 'kolom shops.service_radius_km hilang');

        foreach (['forecast_daily_rate', 'forecast_days_left', 'forecast_stockout_at', 'forecast_run_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('products', $column), "kolom products.{$column} hilang");
        }

        // Kolom existing tidak boleh hilang (backward-compatible).
        foreach (['latitude', 'longitude', 'city', 'province'] as $column) {
            $this->assertTrue(Schema::hasColumn('shops', $column), "kolom existing shops.{$column} hilang");
        }
    }

    public function test_stock_service_forecasts_depletion(): void
    {
        [$product] = $this->forecastActors(stock: 10, sold: 60);

        $forecast = app(StockService::class)->forecastForProduct((int) $product->id, 30);

        $this->assertSame((int) $product->id, $forecast['product_id']);
        $this->assertSame(10, $forecast['stock']);
        $this->assertSame(60, $forecast['sold']);
        $this->assertEqualsWithDelta(2.0, $forecast['daily_rate'], 0.001);
        $this->assertEqualsWithDelta(5.0, $forecast['days_left'], 0.1);
        $this->assertNotNull($forecast['stockout_at']);
        $this->assertSame(50, $forecast['suggested_restock']);
        $this->assertSame('critical', $forecast['state']);
    }

    public function test_stock_analytics_forecast_lists_urgent_first(): void
    {
        [$urgent] = $this->forecastActors(stock: 4, sold: 40);
        $steady = Product::create([
            'shop_id' => $urgent->shop_id, 'category_id' => $urgent->category_id,
            'name' => 'Stok Aman', 'slug' => 'stok-aman-'.uniqid(),
            'price' => 20000, 'current_stock' => 1000,
            'status' => 'approved', 'published' => true,
        ]);
        // Tanpa penjualan: days_left null → selalu di belakang yang kritis.

        $rows = app(StockAnalyticsService::class)->forecastStockouts($this->range(), 10);

        $this->assertNotEmpty($rows);
        $this->assertSame((int) $urgent->id, $rows[0]['id']);
        $this->assertSame('critical', $rows[0]['state']);
        $this->assertArrayHasKey('suggested_restock', $rows[0]);
        $this->assertArrayHasKey('stockout_at', $rows[0]);

        $ids = array_column($rows, 'id');
        $this->assertContains((int) $steady->id, $ids);
    }

    public function test_vendor_inventory_forecast_is_shop_scoped(): void
    {
        [$product] = $this->forecastActors(stock: 10, sold: 60);
        $vendor = $product->shop->vendor;

        $this->actingAs($vendor, 'vendor');
        $rows = app(VendorInventoryService::class)->forecast(30, 10);

        $this->assertNotEmpty($rows);
        $this->assertSame((int) $product->id, $rows[0]['id']);
        $this->assertSame(50, $rows[0]['suggested_restock']);

        $single = app(VendorInventoryService::class)->forecastFor($product->fresh(), 30);
        $this->assertSame((int) $product->id, $single['id']);
        $this->assertEqualsWithDelta(2.0, $single['daily_rate'], 0.001);

        // Toko lain tidak ikut terbaca.
        $other = User::factory()->create(['role' => 'vendor', 'status' => 'active']);
        $otherShop = Shop::create(['vendor_id' => $other->id, 'name' => 'Toko Lain', 'slug' => 'toko-lain-'.uniqid(), 'status' => 'active']);
        $this->actingAs($other, 'vendor');
        $this->assertSame([], app(VendorInventoryService::class)->forecast(30, 10));
        $this->assertSame((int) $otherShop->id, (new VendorScope)->shopId());
    }

    public function test_nearest_shops_prefers_city_then_province(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor', 'status' => 'active']);
        $far = Shop::create(['vendor_id' => $vendor->id, 'name' => 'Toko Jauh', 'slug' => 'toko-jauh-'.uniqid(), 'status' => 'active', 'city' => 'Makassar', 'province' => 'Sulawesi Selatan', 'rating_average' => 5.0]);
        $near = Shop::create(['vendor_id' => $vendor->id, 'name' => 'Toko Dekat', 'slug' => 'toko-dekat-'.uniqid(), 'status' => 'active', 'city' => 'Bandung', 'province' => 'Jawa Barat', 'rating_average' => 3.0]);
        $sameProvince = Shop::create(['vendor_id' => $vendor->id, 'name' => 'Toko Seprovinsi', 'slug' => 'toko-seprovinsi-'.uniqid(), 'status' => 'active', 'city' => 'Bogor', 'province' => 'Jawa Barat', 'rating_average' => 3.0]);

        $ranked = app(StockAnalyticsService::class)->nearestShops('Bandung', 'Jawa Barat', 10);
        $ids = array_column($ranked, 'id');

        $this->assertSame((int) $near->id, $ids[0]);
        $this->assertSame((int) $sameProvince->id, $ids[1]);
        $this->assertContains((int) $far->id, $ids);
        $this->assertSame('Sekota dengan Anda', $ranked[0]['reason']);

        // Fallback existing: tanpa kota/provinsi tetap kembalikan semua toko aktif.
        $fallback = app(StockAnalyticsService::class)->nearestShops(null, null, 10);
        $this->assertCount(3, $fallback);
    }

    public function test_pin_validation_and_map_embed(): void
    {
        // Jakarta (−6.2, 106.8) ke Bandung (−6.9, 107.6) ≈ 116 km.
        $outside = AnalyticsService::validatePinRadius(-6.9175, 107.6191, -6.2, 106.8167, 50);
        $this->assertTrue($outside['valid']);
        $this->assertFalse($outside['within_radius']);
        $this->assertGreaterThan(50, (float) $outside['distance_km']);

        $inside = AnalyticsService::validatePinRadius(-6.21, 106.82, -6.2, 106.8167, 50);
        $this->assertTrue($inside['within_radius']);

        $noPin = AnalyticsService::validatePinRadius(null, null);
        $this->assertFalse($noPin['valid']);

        $noRadius = AnalyticsService::validatePinRadius(-6.2, 106.8167);
        $this->assertTrue($noRadius['valid']);
        $this->assertNull($noRadius['within_radius']);

        $this->assertNull(AnalyticsService::mapEmbedUrl(null, null));
        $url = AnalyticsService::mapEmbedUrl(-6.2, 106.8167);
        $this->assertStringContainsString('openstreetmap.org', (string) $url);
        $this->assertStringContainsString('marker=-6.2', (string) $url);

        // Haversine Jakarta–Bandung dalam toleransi yang wajar.
        $this->assertEqualsWithDelta(116.0, AnalyticsService::haversineKm(-6.2, 106.8167, -6.9175, 107.6191), 8.0);
    }

    public function test_anomaly_alerts_detect_negative_stock_and_notify(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor', 'status' => 'active']);
        $shop = Shop::create(['vendor_id' => $vendor->id, 'name' => 'Toko Anomali', 'slug' => 'toko-anomali-'.uniqid(), 'status' => 'active']);
        $category = Category::create(['name' => 'Anomali '.uniqid(), 'slug' => 'anomali-'.uniqid(), 'status' => true]);
        Product::create([
            'shop_id' => $shop->id, 'category_id' => $category->id,
            'name' => 'Stok Minus', 'slug' => 'stok-minus-'.uniqid(),
            'price' => 10000, 'current_stock' => -3,
            'status' => 'approved', 'published' => true,
        ]);

        $alerts = app(SystemHealthService::class)->anomalyAlerts(7);
        $keys = array_column($alerts, 'key');

        $this->assertContains('negative_stock', $keys);
        $negative = $alerts[array_search('negative_stock', $keys, true)];
        $this->assertSame('danger', $negative['level']);

        User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $sent = app(SystemHealthService::class)->notifyAnomalyAdmins(7);

        $this->assertGreaterThan(0, $sent);
        $this->assertSame($sent, (int) DB::table('notifications')->count());
        $row = DB::table('notifications')->first();
        $this->assertSame('App\\Notifications\\AnomalyAlert', $row->type);
        $this->assertStringContainsString('Stok minus', (string) $row->data);
    }

    public function test_anomaly_alerts_ok_when_clean(): void
    {
        $alerts = app(SystemHealthService::class)->anomalyAlerts(7);

        $this->assertSame('ok', $alerts[0]['key']);
        $this->assertSame('success', $alerts[0]['level']);
        $this->assertSame(0, app(SystemHealthService::class)->notifyAnomalyAdmins(7));
    }

    /**
     * @return array{Product, Product}
     */
    private function forecastActors(int $stock, int $sold): array
    {
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $vendor = User::factory()->create(['role' => 'vendor', 'status' => 'active']);
        $shop = Shop::create(['vendor_id' => $vendor->id, 'name' => 'Toko Forecast', 'slug' => 'toko-forecast-'.uniqid(), 'status' => 'active', 'city' => 'Bandung', 'province' => 'Jawa Barat']);
        $category = Category::create(['name' => 'Forecast '.uniqid(), 'slug' => 'forecast-'.uniqid(), 'status' => true]);

        $urgent = Product::create([
            'shop_id' => $shop->id, 'category_id' => $category->id,
            'name' => 'Produk Kritis', 'slug' => 'produk-kritis-'.uniqid(),
            'price' => 50000, 'current_stock' => $stock,
            'status' => 'approved', 'published' => true,
        ]);
        $calm = Product::create([
            'shop_id' => $shop->id, 'category_id' => $category->id,
            'name' => 'Produk Tenang', 'slug' => 'produk-tenang-'.uniqid(),
            'price' => 30000, 'current_stock' => 900,
            'status' => 'approved', 'published' => true,
        ]);

        $statuses = AnalyticsService::revenueOrderStatuses();
        $paid = AnalyticsService::paidPaymentStatuses();

        $order = Order::create([
            'order_number' => 'ORD-FC-'.uniqid(), 'customer_id' => $customer->id,
            'shop_id' => $shop->id, 'total' => $sold * 50000,
            'payment_method' => 'transfer', 'payment_status' => $paid[0],
            'order_status' => $statuses[0], 'created_at' => now()->subDays(5),
        ]);
        $order->items()->create([
            'product_id' => $urgent->id, 'quantity' => $sold, 'price' => 50000, 'sub_total' => $sold * 50000,
        ]);

        Warehouse::create(['name' => 'Gudang Forecast', 'code' => 'GFC-'.uniqid(), 'city' => 'Bandung', 'is_default' => true, 'is_active' => true]);

        return [$urgent->fresh(), $calm->fresh()];
    }

    private function range(): DateRange
    {
        return DateRange::fromRequest(Request::create('/', 'GET', ['range' => '30d']));
    }
}
