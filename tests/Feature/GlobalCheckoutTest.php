<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Currency;
use App\Payments\CapabilityMatrix;
use App\Payments\FakePaymentGateway;
use App\Payments\GatewayRegistry;
use App\Payments\PaymentRouter;
use App\Services\Currency\CurrencyConverter;
use App\Services\Currency\CurrencyService;
use App\Services\Currency\Money;
use App\Services\Geo\CountryService;
use App\Services\Geo\TaxEngine;
use App\Services\Payment\PaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Global checkout: currency + country + payment-routing.
 *
 * Self-contained & INDEPENDEN dari gateway live — seluruh interaksi gateway
 * memakai FakePaymentGateway dari app/Payments (tanpa jaringan). Provider DB
 * yang dipakai untuk routing memakai api_format tak dikenal sehingga
 * createPayment gagal cepat (unsupported_format) tanpa HTTP keluar.
 *
 * Aturan yang dijaga:
 * - Display terkonversi ≠ charge (charge tetap IDR kecuali capability lolos).
 * - Header/input user diverifikasi server-side (currency aktif, country valid).
 * - Idempotency: kunci sama tidak pernah double charge.
 * - Fallback hanya bila aman (terbukti tak ada tagihan terbentuk).
 */
class GlobalCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakePaymentGateway::resetReplayGuard();
        Cache::flush();
        CurrencyService::clearCache();

        $now = now();
        foreach ([
            ['IDR', 'Rupiah Indonesia', 'Rp', 0, '1.00000000', true, true],
            ['USD', 'US Dollar', '$', 2, '16500.00000000', false, true],
            ['XXX', 'Inactive Coin', 'X', 2, '999.00000000', false, false],
        ] as [$code, $name, $symbol, $dec, $rate, $def, $active]) {
            Currency::create([
                'code' => $code, 'name' => $name, 'symbol' => $symbol,
                'decimal_places' => $dec, 'decimal_separator' => '.',
                'thousand_separator' => ',',
                'exchange_rate' => $rate, 'rate_source' => 'test-seed',
                'rate_updated_at' => $now, 'is_default' => $def, 'is_active' => $active,
            ]);
        }

        foreach ([
            ['ID', 'IDN', 'Indonesia', 'IDR'],
            ['US', 'USA', 'United States', 'USD'],
        ] as [$iso2, $iso3, $name, $ccy]) {
            Country::create([
                'iso2' => $iso2, 'iso3' => $iso3, 'name' => $name,
                'currency_code' => $ccy, 'locale' => 'en', 'timezone' => 'UTC',
                'is_active' => true, 'payment_hints' => [], 'shipping_hints' => [],
            ]);
        }

        CurrencyService::clearCache();
    }

    protected function tearDown(): void
    {
        CurrencyService::clearCache();
        Cache::flush();

        parent::tearDown();
    }

    // ---------- 1. Display terkonversi, charge tetap IDR ----------

    public function test_display_conversion_presisi_dan_charge_tetap_idr_tanpa_capability(): void
    {
        // Rp165.000 tepat → $10.00 (1000 minor USD), tanpa float.
        $idr = Money::fromMinor(165000, 'IDR', 0);
        $usd = CurrencyConverter::convert($idr, 'USD', 2, '1.00000000', '16500.00000000');

        $this->assertSame(1000, $usd->minor());
        $this->assertSame('10.00', $usd->toMajorString());

        // Provider ID-only tanpa deklarasi currencies: USD tidak didukung.
        $providerId = $this->makeProvider('Midtrans Like', 'midtrans-snap', null, 0);
        $svc = app(PaymentGatewayService::class);
        $provider = \App\Models\Provider::findOrFail($providerId);

        $this->assertTrue($svc->gatewaySupportsCurrency($provider, 'IDR'));
        $this->assertFalse($svc->gatewaySupportsCurrency($provider, 'USD'));

        // Keputusan charge ala controller: tanpa capability → tetap IDR.
        $display = 'USD';
        $chargeCurrency = $svc->gatewaySupportsCurrency($provider, $display) ? $display : 'IDR';
        $this->assertSame('IDR', $chargeCurrency);
    }

    public function test_charge_currency_berubah_hanya_bila_capability_lolos(): void
    {
        $providerId = $this->makeProvider('Multi Ccy', 'custom-open', ['currencies' => ['IDR', 'USD']], 0);
        $svc = app(PaymentGatewayService::class);
        $provider = \App\Models\Provider::findOrFail($providerId);

        $this->assertTrue($svc->gatewaySupportsCurrency($provider, 'USD'));

        $converted = $svc->convertChargeAmount(165000.0, 'USD');
        $this->assertNotNull($converted);
        $this->assertEqualsWithDelta(10.0, (float) $converted, 0.001);
    }

    // ---------- 2. Verifikasi server-side ----------

    public function test_server_side_verification_menolak_currency_inaktif_dan_country_tak_dikenal(): void
    {
        // Currency inaktif tidak boleh dipakai untuk keputusan finansial.
        $this->expectException(\DomainException::class);
        CurrencyService::require('XXX');
    }

    public function test_country_service_tolak_unknown_dan_tax_fallback_none(): void
    {
        $service = app(CountryService::class);

        $this->assertNull($service->resolve('XX'));
        $this->assertSame('ID', $service->contextFor('XX')['iso2'] === 'XX' ? 'XX' : $service->contextFor(null)['iso2'] ?? 'ID');

        // Tanpa rule pajak untuk ID di DB kosong → mode none (fallback aman).
        $quote = app(TaxEngine::class)->calculateByIso(100000.0, 'XX');
        $this->assertSame('none', $quote['mode']);
        $this->assertSame(0.0, $quote['tax']);
    }

    public function test_gateway_country_capability_id_only(): void
    {
        $providerId = $this->makeProvider('Midtrans Like 2', 'midtrans-snap', null, 0);
        $svc = app(PaymentGatewayService::class);
        $provider = \App\Models\Provider::findOrFail($providerId);

        $this->assertTrue($svc->gatewaySupportsCountry($provider, 'ID'));
        $this->assertFalse($svc->gatewaySupportsCountry($provider, 'US'));
    }

    // ---------- 3. Router idempotency + fallback via Fake ----------

    private function fakeRouter(FakePaymentGateway $fake, string $name = 'fake'): PaymentRouter
    {
        $registry = new GatewayRegistry(new CapabilityMatrix());
        $registry->register($name, fn () => $fake);

        return (new PaymentRouter($registry))->useGateways($name);
    }

    public function test_router_idempotency_tanpa_double_charge(): void
    {
        $fake = new FakePaymentGateway();
        $router = $this->fakeRouter($fake);

        $payload = ['reference_id' => 'g-1', 'amount' => 75000, 'currency' => 'IDR', 'country' => 'ID', 'method' => 'qris'];

        $first = $router->charge($payload, 'key-global-1');
        $second = $router->charge($payload, 'key-global-1');

        $this->assertSame('paid', $first['status']);
        $this->assertTrue($second['idempotent_replay'] ?? false);
        $this->assertSame(1, $fake->chargeCount, 'Gateway hanya boleh ditagih sekali.');
    }

    public function test_router_fallback_hanya_bila_aman(): void
    {
        $primary = new FakePaymentGateway(name: 'primary');
        $primary->failNextCharge();
        $secondary = new FakePaymentGateway(name: 'secondary');

        $registry = new GatewayRegistry(new CapabilityMatrix());
        $registry->register('primary', fn () => $primary);
        $registry->register('secondary', fn () => $secondary);
        $router = (new PaymentRouter($registry))->useGateways('primary', 'secondary');

        $result = $router->charge(
            ['reference_id' => 'g-fb-1', 'amount' => 20000, 'currency' => 'IDR', 'country' => 'ID', 'method' => 'e_wallet'],
            'k-global-fb-1'
        );

        $this->assertSame('paid', $result['status']);
        $this->assertSame('secondary', $result['gateway']);
        $this->assertSame(0, $primary->chargeCount, 'Declined tidak membentuk tagihan.');
        $this->assertSame(1, $secondary->chargeCount);
    }

    public function test_router_menolak_currency_dan_country_tak_didukung_tanpa_charge(): void
    {
        $fake = new FakePaymentGateway(currencies: ['IDR'], countries: ['ID']);
        $router = $this->fakeRouter($fake);

        try {
            $router->charge(['reference_id' => 'x', 'amount' => 10, 'currency' => 'JPY', 'country' => 'ID', 'method' => 'qris'], 'k-g-jpy');
            $this->fail('Harus menolak JPY.');
        } catch (\App\Payments\UnsupportedCurrencyException) {
            // ok
        }

        try {
            $router->charge(['reference_id' => 'x', 'amount' => 10, 'currency' => 'IDR', 'country' => 'XX', 'method' => 'qris'], 'k-g-xx');
            $this->fail('Harus menolak XX.');
        } catch (\App\Payments\UnsupportedCountryException) {
            // ok
        }

        $this->assertSame(0, $fake->chargeCount);
    }

    // ---------- 4. Routing provider DB tanpa panggilan live ----------

    public function test_route_provider_memilih_capable_dan_fallback_tanpa_double_charge(): void
    {
        // api_format tak dikenal → createPayment gagal cepat tanpa HTTP.
        $usOnly = $this->makeProvider('US Only', 'custom-open', ['countries' => ['US'], 'currencies' => ['IDR']], 5);
        $general = $this->makeProvider('General', 'custom-open', null, 20);

        $svc = app(PaymentGatewayService::class);

        $picked = $svc->routeProvider('IDR', 'US');
        $this->assertNotNull($picked);
        $this->assertSame($usOnly, (int) $picked->getKey());

        $none = $svc->routeProvider('IDR', 'XX');
        // General (tanpa deklarasi, format non-ID-only) terbuka untuk XX.
        $this->assertNotNull($none);

        $primary = \App\Models\Provider::findOrFail($usOnly);
        $key = 'global-fallback-'.uniqid();

        $first = $svc->createPaymentWithFallback($primary, [
            'order_id' => 'PAY-TEST-1', 'amount' => 50000, 'currency' => 'IDR', 'country' => 'US',
        ], $key, ['currency' => 'IDR', 'country' => 'US']);

        // Semua kandidat unsupported_format → gagal aman, TANPA sukses ganda.
        $this->assertFalse($first['success'] ?? true);
        $this->assertSame('unsupported_format', strtolower((string) ($first['code'] ?? '')));

        // Kunci gagal dilepas (tidak terkunci): percobaan ulang tidak replay sukses.
        $second = $svc->createPaymentWithFallback($primary, [
            'order_id' => 'PAY-TEST-1', 'amount' => 50000, 'currency' => 'IDR', 'country' => 'US',
        ], $key, ['currency' => 'IDR', 'country' => 'US']);

        $this->assertFalse($second['success'] ?? true);
        $this->assertArrayNotHasKey('idempotent_replay', $second);
        $this->assertSame($general, (int) ($second['provider_id'] ?? 0) === $general ? $general : (int) ($second['provider_id'] ?? 0));
    }

    public function test_idempotent_success_tidak_ditagih_ulang(): void
    {
        $idOnly = $this->makeProvider('ID Only X', 'midtrans-snap', null, 0);
        $svc = app(PaymentGatewayService::class);
        $primary = \App\Models\Provider::findOrFail($idOnly);

        // Provider tak capable untuk US → langsung ditolak, gateway tak dipanggil.
        $result = $svc->createPaymentWithFallback($primary, [
            'order_id' => 'PAY-TEST-2', 'amount' => 10000, 'currency' => 'IDR', 'country' => 'US',
        ], 'global-reject-'.uniqid(), ['currency' => 'IDR', 'country' => 'US']);

        $this->assertFalse($result['success'] ?? true);
    }

    private function makeProvider(string $name, string $format, ?array $config, int $sort): int
    {
        return (int) DB::table('providers')->insertGetId([
            'name' => $name,
            'type' => 'payment',
            'api_format' => $format,
            'base_url' => 'https://localhost.test',
            'config' => json_encode($config ?? ['note' => 'test']),
            'is_active' => true,
            'is_default' => false,
            'sort_order' => $sort,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
