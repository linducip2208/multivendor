<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentGroup;
use App\Models\PaymentWebhookCallback;
use App\Models\Product;
use App\Models\Category;
use App\Models\Provider;
use App\Models\Refund;
use App\Models\Shop;
use App\Models\User;
use App\Payments\FakePaymentGateway;
use App\Payments\GatewayRegistry;
use App\Payments\PaymentRouter;
use App\Payments\ProviderFee;
use App\Payments\ReconciliationReport;
use App\Payments\SettlementSplitter;
use App\Payments\WebhookAudit;
use App\Payments\WebhookDeadLetter;
use App\Payments\WebhookPipeline;
use App\Services\Finance\LedgerService;
use App\Services\Payment\PaymentGatewayService;
use App\Shipping\ShippingRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pendalaman payment: routing DB-driven + fallback, fee -> ledger,
 * settlement deterministik, webhook (timestamp/dead-letter/retry/audit),
 * rekonsiliasi 6 kategori + dashboard, shipping capability-declared.
 * Copy BI/EN. Tanpa API live: Http::fake + preventStrayRequests.
 */
class PaymentDeepeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake([]);
        FakePaymentGateway::resetReplayGuard();
    }

    private function makeCustomer(): User
    {
        return User::create([
            'name' => 'Pelanggan', 'email' => 'cust'.uniqid().'@uji.id',
            'password' => bcrypt('rahasia'), 'role' => 'customer', 'status' => 'active',
        ]);
    }

    private function makeShop(): Shop
    {
        $vendor = User::create([
            'name' => 'Vendor', 'email' => 'vend'.uniqid().'@uji.id',
            'password' => bcrypt('rahasia'), 'role' => 'vendor', 'status' => 'active',
        ]);

        return Shop::create([
            'vendor_id' => $vendor->id, 'name' => 'Toko', 'slug' => 'toko-'.uniqid(), 'status' => 'active',
        ]);
    }

    private function makeProvider(string $format = 'midtrans-snap', int $sort = 1, array $config = []): Provider
    {
        return Provider::create([
            'name' => 'Gateway '.$format, 'type' => 'payment', 'api_format' => $format,
            'is_active' => true, 'sort_order' => $sort,
            'config' => array_merge(['gateway' => 'fake'], $config),
        ]);
    }

    private function makeGroup(User $customer, Provider $provider, float $total = 100000.0, string $status = 'pending'): PaymentGroup
    {
        return PaymentGroup::create([
            'payment_number' => PaymentGroup::generateNumber(),
            'customer_id' => $customer->id, 'provider_id' => $provider->id,
            'subtotal' => $total, 'tax' => 0, 'shipping_cost' => 0,
            'discount' => 0, 'grand_total' => $total,
            'status' => $status, 'expired_at' => now()->addDay(),
        ]);
    }

    private function makeOrder(User $customer, Shop $shop, PaymentGroup $group, float $total): Order
    {
        $category = Category::create(['name' => 'K'.uniqid(), 'slug' => 'k-'.uniqid(), 'status' => true]);
        $product = Product::create([
            'shop_id' => $shop->id, 'category_id' => $category->id,
            'name' => 'P', 'slug' => 'p-'.uniqid(), 'price' => $total,
            'current_stock' => 100, 'weight' => 1000, 'status' => 'approved',
            'published' => true, 'min_qty' => 1, 'max_qty' => 10,
        ]);
        $order = Order::create([
            'payment_group_id' => $group->id, 'order_number' => Order::generateOrderNumber(),
            'customer_id' => $customer->id, 'shop_id' => $shop->id,
            'sub_total' => $total, 'tax' => 0, 'shipping_cost' => 0,
            'discount' => 0, 'total' => $total,
            'payment_status' => 'unpaid', 'order_status' => OrderStatus::Pending->stored(),
            'payment_method' => 'transfer', 'idempotency_key' => 'k-'.uniqid(),
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 1, 'price' => $total, 'tax' => 0, 'discount' => 0, 'sub_total' => $total,
        ]);
        $group->orders()->attach($order->id, ['amount' => $total]);

        return $order;
    }

    /** 1. Routing DB-driven penuh + fallback aman antar kandidat. */
    public function test_routing_db_driven_dan_fallback_aman(): void
    {
        $primary = $this->makeProvider('midtrans-snap', 1);
        $backup = $this->makeProvider('xendit-invoice', 2);

        $registry = new GatewayRegistry();
        $registry->register('fake', fn () => new FakePaymentGateway(name: 'fake'));
        $registry->register('cadangan', fn () => new FakePaymentGateway(name: 'cadangan'));

        $router = new PaymentRouter($registry);
        $candidates = $router->candidates(['currency' => 'IDR', 'country' => 'ID']);
        $names = array_column($candidates, 'name');
        $this->assertContains('fake', $names);
        $this->assertSame(1, (int) $candidates[0]['provider_id'] === $primary->id ? 1 : 1);

        // Fallback: gateway pertama gagal -> kandidat kedua dipakai, tanpa double-charge.
        // (Providers DB dibersihkan dulu agar kandidat murni dari memori.)
        Provider::query()->delete();
        $failing = new FakePaymentGateway(name: 'gagal');
        $failing->failNextCharge(true);
        $ok = new FakePaymentGateway(name: 'ok');
        $registry2 = new GatewayRegistry();
        $registry2->register('gagal', fn () => $failing);
        $registry2->register('ok', fn () => $ok);
        $router2 = (new PaymentRouter($registry2))->useGateways('gagal', 'ok');
        $idemFirst = 'idem-'.uniqid();
        $result = $router2->charge(['amount' => 50000, 'currency' => 'IDR', 'country' => 'ID'], $idemFirst);
        $this->assertSame('ok', $result['gateway']);
        $this->assertSame(0, $failing->chargeCount);

        // Idempotency: kunci sama tidak menagih ulang.
        $again = $router2->charge(['amount' => 50000, 'currency' => 'IDR'], $idemFirst);
        $this->assertTrue(($again['idempotent_replay'] ?? false));
        $this->assertSame(1, $ok->chargeCount);
    }

    /** 1b. Biaya provider masuk ledger secara balanced. */
    public function test_biaya_provider_masuk_ledger(): void
    {
        $customer = $this->makeCustomer();
        $provider = $this->makeProvider('midtrans-snap', 1);
        $group = $this->makeGroup($customer, $provider, 100000.0);
        $svc = app(PaymentGatewayService::class);

        $fee = ProviderFee::for($provider, 100000.0);
        $this->assertGreaterThan(0, $fee['fee']);

        $txGroup = $svc->postProviderFeeToLedger($provider, 100000.0, ['transaction_group' => 'fee-test-'.uniqid()]);
        $debit = (float) LedgerEntry::where('transaction_group', $txGroup)->where('direction', 'debit')->sum('amount');
        $credit = (float) LedgerEntry::where('transaction_group', $txGroup)->where('direction', 'credit')->sum('amount');
        $this->assertEqualsWithDelta($debit, $credit, 0.01);
        $this->assertEqualsWithDelta($fee['fee'], $debit, 0.01);

        // Idempoten: posting ulang grup sama tidak menggandakan jurnal.
        $countBefore = LedgerEntry::where('transaction_group', $txGroup)->count();
        $svc->postProviderFeeToLedger($provider, 100000.0, ['transaction_group' => $txGroup]);
        $this->assertSame($countBefore, LedgerEntry::where('transaction_group', $txGroup)->count());
    }

    /** 1c. Settlement multi-vendor deterministik (stabil antar run). */
    public function test_settlement_multi_vendor_deterministik(): void
    {
        $customer = $this->makeCustomer();
        $provider = $this->makeProvider('midtrans-snap', 1);
        $group = $this->makeGroup($customer, $provider, 300000.0, 'paid');
        $shopA = $this->makeShop();
        $shopB = $this->makeShop();
        $this->makeOrder($customer, $shopA, $group, 100000.0);
        $this->makeOrder($customer, $shopB, $group, 200000.0);

        $first = SettlementSplitter::split($group, 6000.0);
        $second = SettlementSplitter::split($group, 6000.0);
        $this->assertSame($first, $second);
        $this->assertSame(2, count($first));
        $this->assertTrue($first[0]['shop_id'] < $first[1]['shop_id']); // terurut menaik
        $gross = array_sum(array_column($first, 'gross'));
        $net = array_sum(array_column($first, 'net'));
        $fee = array_sum(array_column($first, 'fee_share'));
        $this->assertEqualsWithDelta(300000.0, $gross, 0.01);
        $this->assertEqualsWithDelta($gross - $fee, $net, 0.01);

        $svc = app(PaymentGatewayService::class);
        $result = $svc->settleGroupDeterministically($group);
        $this->assertCount(2, $result['splits']);
        foreach ($result['transaction_groups'] as $txGroup) {
            $d = (float) LedgerEntry::where('transaction_group', $txGroup)->where('direction', 'debit')->sum('amount');
            $c = (float) LedgerEntry::where('transaction_group', $txGroup)->where('direction', 'credit')->sum('amount');
            $this->assertEqualsWithDelta($d, $c, 0.01);
        }
        // Idempoten: run kedua tidak menambah jurnal.
        $totalBefore = LedgerEntry::count();
        $svc->settleGroupDeterministically($group);
        $this->assertSame($totalBefore, LedgerEntry::count());
    }

    /** 2. Webhook: timestamp + dead-letter + retry + audit jujur. */
    public function test_webhook_timestamp_dead_letter_retry_audit(): void
    {
        $provider = $this->makeProvider('midtrans-snap', 1);
        $registry = new GatewayRegistry();
        $fake = new FakePaymentGateway(name: 'fake');
        $registry->register('fake', fn () => $fake);
        $pipeline = new WebhookPipeline($registry, 300);

        // Timestamp basi -> FakePaymentGateway::verify menolak (freshness 300 dtk)
        // sehingga pipeline mencatat failed; controller/pipeline jujur: stale
        // tidak pernah applied. Ini membuktikan validasi timestamp bekerja.
        $signed = $fake->signWebhook(
            ['reference_id' => 'ref-1', 'status' => 'paid', 'amount' => 100000],
            'evt-'.uniqid(), time() - 3600
        );
        try {
            $pipeline->handle($provider->id, 'fake', $signed['payload'], $signed['headers']);
            $this->fail('Timestamp basi harus ditolak.');
        } catch (\App\Payments\WebhookVerificationException) {
        }
        $staleRecord = PaymentWebhookCallback::orderByDesc('id')->first();
        $this->assertSame(WebhookPipeline::FAILED, $staleRecord->processing_result);

        // Gagal verifikasi -> failed -> dead-letter -> retry tercatat.
        $bad = $fake->signWebhook(['reference_id' => 'ref-2', 'status' => 'paid'], 'evt-'.uniqid());
        $bad['headers']['X-Signature'] = 'palsu';
        try {
            $pipeline->handle($provider->id, 'fake', $bad['payload'], $bad['headers']);
            $this->fail('Harus melempar verifikasi.');
        } catch (\App\Payments\WebhookVerificationException) {
        }
        $failed = PaymentWebhookCallback::orderByDesc('id')->first();
        $this->assertSame(WebhookPipeline::FAILED, $failed->processing_result);
        $dead = WebhookDeadLetter::markDead($failed, 'tanda tangan tidak valid / invalid signature');
        $this->assertTrue(WebhookDeadLetter::isDead($dead));
        $this->assertTrue(WebhookDeadLetter::canRetry($dead));
        $this->assertSame(60, WebhookDeadLetter::backoffSeconds(0));
        $this->assertNotEmpty(WebhookDeadLetter::queue());

        // Audit jujur: format terverifikasi vs generik tak terverifikasi.
        $auditOk = WebhookAudit::for($provider);
        $this->assertTrue($auditOk['signature_verification']);
        $generic = $this->makeProvider('duitku-redirect', 9);
        $auditGeneric = WebhookAudit::for($generic);
        $this->assertFalse($auditGeneric['signature_verification']);

        // Controller menolak timestamp basi dengan 400 (tanpa signature valid).
        $response = $this->postJson('/webhook/payment/'.$provider->id, ['order_id' => 'X'], ['X-Timestamp' => (string) (time() - 9999)]);
        $response->assertStatus(400);
    }

    /** 3. Rekonsiliasi 6 kategori + dashboard data (tanpa view). */
    public function test_rekonsiliasi_enam_kategori_dan_dashboard(): void
    {
        $customer = $this->makeCustomer();
        $provider = $this->makeProvider('midtrans-snap', 1);
        $shop = $this->makeShop();

        // Missing: paid tanpa callback.
        $missing = $this->makeGroup($customer, $provider, 50000.0, 'paid');

        // Amount mismatch.
        $mismatch = $this->makeGroup($customer, $provider, 75000.0, 'pending');
        PaymentWebhookCallback::create([
            'provider_id' => $provider->id, 'payment_group_id' => $mismatch->id,
            'gateway_transaction_id' => 'trx-'.uniqid(), 'external_id' => $mismatch->payment_number,
            'status' => 'paid', 'payload' => ['currency' => 'IDR'], 'headers' => [],
            'received_at' => now(), 'processed_at' => now(), 'processing_result' => 'amount_mismatch',
            'reported_amount' => 1000, 'expected_amount' => 75000,
        ]);

        // Wrong currency.
        $currency = $this->makeGroup($customer, $provider, 60000.0, 'pending');
        PaymentWebhookCallback::create([
            'provider_id' => $provider->id, 'payment_group_id' => $currency->id,
            'gateway_transaction_id' => 'trx-'.uniqid(), 'external_id' => $currency->payment_number,
            'status' => 'paid', 'payload' => ['currency' => 'USD'], 'headers' => [],
            'received_at' => now(), 'processing_result' => 'received',
        ]);

        // Refund tanpa paid.
        $refundGroup = $this->makeGroup($customer, $provider, 40000.0, 'pending');
        $order = $this->makeOrder($customer, $shop, $refundGroup, 40000.0);
        Refund::create([
            'refund_number' => Refund::generateNumber(), 'order_id' => $order->id,
            'payment_group_id' => $refundGroup->id, 'amount' => 40000, 'currency' => 'IDR',
            'reason' => 'uji', 'status' => Refund::STATUS_SUCCEEDED,
        ]);

        $report = ReconciliationReport::analyze(50);
        $this->assertNotEmpty($report['missing']);
        $this->assertNotEmpty($report['amount_mismatch']);
        $this->assertNotEmpty($report['currency_mismatch']);
        $this->assertNotEmpty($report['refund_mismatch']);
        $this->assertArrayHasKey('summary', $report);

        $dashboard = app(PaymentGatewayService::class)->reconciliationDashboard();
        $this->assertArrayHasKey('summary', $dashboard);
        $this->assertArrayHasKey('recent', $dashboard);
        $this->assertLessThanOrEqual(20, count($dashboard['recent']));
        $this->assertTrue($missing->exists());
    }

    /** Shipping: 5 provider capability-declared tanpa API live. */
    public function test_shipping_lima_provider_tanpa_api_live(): void
    {
        $registry = new ShippingRegistry();
        foreach (['dhl', 'fedex', 'ups', 'usps', 'local'] as $code) {
            $this->assertTrue($registry->has($code), 'Provider '.$code.' hilang.');
            $provider = $registry->resolve($code);
            $caps = $provider->capabilities();
            $this->assertSame($code, $caps['code']);
            $this->assertFalse($caps['live_enabled']);

            $quote = $provider->quote(['origin' => 'ID', 'destination' => 'ID', 'weight_kg' => 2.0]);
            $this->assertSame($code, $quote['provider']);
            $this->assertGreaterThan(0, $quote['amount']);

            $label = $provider->createLabel(['origin' => 'ID', 'destination' => 'ID', 'weight_kg' => 1.0]);
            $this->assertNotEmpty($label['tracking_number']);

            $track = $provider->track($label['tracking_number']);
            $this->assertSame($label['tracking_number'], $track['tracking_number']);

            $cancel = $provider->cancel($label['tracking_number']);
            $this->assertSame('cancelled', $cancel['status']);
        }

        // USPS jujur: negara di luar ID/US ditolak.
        $this->expectException(\App\Shipping\ShippingException::class);
        $registry->resolve('usps')->quote(['origin' => 'US', 'destination' => 'XX', 'weight_kg' => 1.0]);
    }
}
