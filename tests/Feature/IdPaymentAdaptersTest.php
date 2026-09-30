<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Payments\CapabilityMatrix;
use App\Payments\FakePaymentGateway;
use App\Payments\GatewayRegistry;
use App\Payments\PaymentException;
use App\Payments\PaymentGatewayInterface;
use App\Payments\PaymentMethod;
use App\Payments\ProviderCatalog;
use App\Payments\Providers\IpaymuGateway;
use App\Payments\Providers\MidtransGateway;
use App\Payments\Providers\TripayGateway;
use App\Payments\Providers\XenditGateway;
use App\Payments\UnsupportedCountryException;
use App\Payments\UnsupportedCurrencyException;
use App\Payments\WebhookVerificationException;
use App\Services\Payment\PaymentAdapterInterface;
use Tests\TestCase;

class IdPaymentAdaptersTest extends TestCase
{
    private GatewayRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        FakePaymentGateway::resetReplayGuard();
        $this->registry = ProviderCatalog::registerAll(new GatewayRegistry(new CapabilityMatrix()));
    }

    public function test_catalog_metadata_lengkap_tanpa_kredensial(): void
    {
        $all = ProviderCatalog::all();

        $this->assertCount(4, $all);
        $this->assertSame(['midtrans', 'xendit', 'tripay', 'ipaymu'], ProviderCatalog::codes());

        $required = ['code', 'name', 'api_format', 'countries', 'currencies', 'methods',
            'min_amount', 'max_amount', 'fee_percent', 'fee_flat', 'priority', 'test_mode_supported'];

        foreach ($all as $entry) {
            foreach ($required as $key) {
                $this->assertArrayHasKey($key, $entry, "Katalog {$entry['code']} wajib punya {$key}.");
            }
            $this->assertSame(['ID'], $entry['countries']);
            $this->assertSame(['IDR'], $entry['currencies']);
            $this->assertNotEmpty($entry['methods']);
            foreach ($entry['methods'] as $method) {
                $this->assertSame($method, PaymentMethod::normalize($method), 'Metode katalog harus bentuk baku.');
            }
            $this->assertLessThan($entry['max_amount'], $entry['min_amount']);
            $this->assertTrue($entry['test_mode_supported']);
        }

        $priorities = array_column($all, 'priority');
        $sorted = $priorities;
        sort($sorted);
        $this->assertSame($sorted, $priorities, 'Katalog terurut prioritas menaik.');
        $this->assertSame(10, ProviderCatalog::find('midtrans')['priority']);

        $dump = json_encode($all) ?: '';
        foreach (['secret', 'api_key', 'api_secret', 'password', 'token'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $dump, 'Katalog TANPA kredensial.');
        }
    }

    public function test_registry_discovery_dan_resolve_keempat_gateway(): void
    {
        foreach (ProviderCatalog::codes() as $code) {
            $this->assertTrue($this->registry->has($code), "Registry harus mengenal {$code}.");
            $this->assertInstanceOf(PaymentGatewayInterface::class, $this->registry->resolve($code));
        }

        $this->assertInstanceOf(XenditGateway::class, $this->registry->resolve('xendit'));
        $this->assertInstanceOf(MidtransGateway::class, $this->registry->resolve('midtrans'));
        $this->assertInstanceOf(TripayGateway::class, $this->registry->resolve('tripay'));
        $this->assertInstanceOf(IpaymuGateway::class, $this->registry->resolve('ipaymu'));
    }

    public function test_kapabilitas_jujur_idr_id_saja(): void
    {
        foreach (ProviderCatalog::codes() as $code) {
            $gateway = $this->registry->resolve($code);
            $this->assertTrue($gateway->supportsCurrency('IDR'));
            $this->assertTrue($gateway->supportsCurrency('idr'), 'Pengecekan currency case-insensitive.');
            $this->assertFalse($gateway->supportsCurrency('USD'), "{$code} JANGAN klaim USD.");
            $this->assertTrue($gateway->supportsCountry('ID'));
            $this->assertFalse($gateway->supportsCountry('US'), "{$code} JANGAN klaim US.");
            $this->assertFalse($gateway->supportsCountry('MY'));
        }

        $xendit = $this->registry->resolve('xendit');
        $this->assertTrue($xendit->supportsPaymentMethod('virtual_account'));
        $this->assertTrue($xendit->supportsPaymentMethod('QRIS'));
        $this->assertTrue($xendit->supportsPaymentMethod('GoPay'));
        $this->assertTrue($xendit->supportsPaymentMethod('retail_outlet'));
        $this->assertFalse($xendit->supportsPaymentMethod('credit_card'), 'Xendit JANGAN klaim CC.');

        $midtrans = $this->registry->resolve('midtrans');
        $this->assertTrue($midtrans->supportsPaymentMethod('credit_card'));
        $this->assertTrue($midtrans->supportsPaymentMethod('bank_transfer'));
        $this->assertTrue($midtrans->supportsPaymentMethod('qris'));
        $this->assertFalse($midtrans->supportsPaymentMethod('paylater'), 'Midtrans JANGAN klaim paylater.');

        $tripay = $this->registry->resolve('tripay');
        $this->assertTrue($tripay->supportsPaymentMethod('virtual_account'));
        $this->assertTrue($tripay->supportsPaymentMethod('e_wallet'));
        $this->assertFalse($tripay->supportsPaymentMethod('credit_card'), 'Tripay JANGAN klaim CC.');
        $this->assertFalse($tripay->supportsPaymentMethod('retail_outlet'), 'Tripay JANGAN klaim retail.');

        $ipaymu = $this->registry->resolve('ipaymu');
        $this->assertTrue($ipaymu->supportsPaymentMethod('bank_transfer'));
        $this->assertTrue($ipaymu->supportsPaymentMethod('retail_outlet'));
    }

    public function test_charge_dan_refund_via_stub_tanpa_api_live(): void
    {
        foreach (ProviderCatalog::codes() as $code) {
            $gateway = $this->registry->resolve($code);

            $init = $gateway->initialize(['reference_id' => "id-{$code}-1", 'amount' => 15000]);
            $this->assertSame("id-{$code}-1", $init['reference_id']);
            $this->assertSame('pending', $init['status']);

            $charged = $gateway->charge([
                'reference_id' => "id-{$code}-1",
                'amount' => 15000.0,
                'currency' => 'IDR',
                'country' => 'ID',
                'method' => 'virtual_account',
            ], "idem-{$code}-1");
            $this->assertSame('paid', $charged['status'], "Charge stub {$code} harus paid tanpa API live.");

            $refunded = $gateway->refund("id-{$code}-1", 15000.0);
            $this->assertSame('refunded', $refunded['status']);

            $status = $gateway->getStatus("id-{$code}-1");
            $this->assertSame("id-{$code}-1", $status['reference_id']);
        }
    }

    public function test_charge_menolak_currency_dan_country_dengan_pesan_bi_en(): void
    {
        $xendit = $this->registry->resolve('xendit');

        try {
            $xendit->charge(['reference_id' => 'x', 'amount' => 10, 'currency' => 'USD']);
            $this->fail('Harus melempar UnsupportedCurrencyException.');
        } catch (UnsupportedCurrencyException $e) {
            $this->assertSame('USD', $e->context['currency']);
            $this->assertNotSame('', $e->messageId);
            $this->assertNotSame('', $e->messageEn);
        }

        try {
            $xendit->charge(['reference_id' => 'x', 'amount' => 10, 'currency' => 'IDR', 'country' => 'MY']);
            $this->fail('Harus melempar UnsupportedCountryException.');
        } catch (UnsupportedCountryException $e) {
            $this->assertSame('MY', $e->context['country']);
            $this->assertNotSame('', $e->messageId);
            $this->assertNotSame('', $e->messageEn);
        }

        try {
            $xendit->charge(['reference_id' => 'x', 'amount' => 10, 'currency' => 'IDR', 'method' => 'barter_batu']);
            $this->fail('Harus melempar PaymentException untuk metode tak didukung.');
        } catch (PaymentException $e) {
            $this->assertNotSame('', $e->messageId);
            $this->assertNotSame('', $e->messageEn);
        }
    }

    public function test_authorize_capture_dan_webhook_bogus_gagal_dwibahasa(): void
    {
        $tripay = $this->registry->resolve('tripay');

        try {
            $tripay->authorize(['reference_id' => 'a', 'amount' => 1000]);
            $this->fail('Authorize Tripay harus ditolak jujur.');
        } catch (PaymentException $e) {
            $this->assertNotSame('', $e->messageId);
            $this->assertNotSame('', $e->messageEn);
        }

        try {
            $tripay->capture('a');
            $this->fail('Capture Tripay harus ditolak jujur.');
        } catch (PaymentException $e) {
            $this->assertNotSame('', $e->messageId);
            $this->assertNotSame('', $e->messageEn);
        }

        $this->assertFalse($tripay->verify(['reference_id' => 'a'], ['X-Signature' => 'bogus']));

        try {
            $tripay->handleWebhook(['reference_id' => 'a'], ['X-Signature' => 'bogus']);
            $this->fail('Webhook bogus harus ditolak.');
        } catch (WebhookVerificationException $e) {
            $this->assertNotSame('', $e->getMessage());
        }
    }

    public function test_webhook_stub_roundtrip_memakai_stub_yang_sama(): void
    {
        $stub = new FakePaymentGateway(
            name: 'xendit',
            currencies: ['IDR'],
            countries: ['ID'],
            methods: ['virtual_account', 'qris', 'e_wallet', 'retail_outlet']
        );
        $gateway = new XenditGateway(stub: $stub);

        $signed = $stub->signWebhook(['reference_id' => 'web-1', 'status' => 'paid', 'amount' => 20000]);

        $this->assertTrue($gateway->verify($signed['payload'], $signed['headers']));

        $event = $gateway->handleWebhook($signed['payload'], $signed['headers']);
        $this->assertSame('web-1', $event['reference_id']);
        $this->assertSame('paid', $event['status']);
    }

    public function test_ipaymu_live_unsupported_tanpa_panggilan_jaringan(): void
    {
        $deadAdapter = new class() implements PaymentAdapterInterface
        {
            public function createTransaction(array $payload): array
            {
                return ['success' => true, 'redirect_url' => 'https://ipaymu.test/pay/x', 'raw' => []];
            }

            public function getTransactionStatus(string $transactionId): array
            {
                return ['success' => false, 'code' => 'unsupported', 'message' => 'Status transaksi tidak dapat diverifikasi.'];
            }

            public function verifyCallback(array $requestData): bool
            {
                return false;
            }

            public function getChannels(): array
            {
                return [];
            }

            public function refund(string $gatewayRefundId, float $amount, array $options = []): array
            {
                return ['success' => false, 'code' => 'unsupported', 'message' => 'Gateway ini belum mendukung eksekusi refund.'];
            }

            public function cancel(string $gatewayPaymentId): array
            {
                return ['success' => false, 'code' => 'unsupported', 'message' => 'Gateway ini belum mendukung pembatalan pembayaran.'];
            }
        };

        $gateway = new IpaymuGateway(adapter: $deadAdapter);

        // initialize/charge didelegasikan ke adaptor existing (mock, tanpa jaringan).
        $init = $gateway->initialize(['reference_id' => 'ipm-1', 'amount' => 5000]);
        $this->assertSame('pending', $init['status']);

        foreach ([
            fn () => $gateway->refund('ipm-1', 5000.0),
            fn () => $gateway->void('ipm-1'),
            fn () => $gateway->getStatus('ipm-1'),
            fn () => $gateway->handleWebhook(['reference_id' => 'ipm-1'], []),
        ] as $call) {
            try {
                $call();
                $this->fail('Operasi iPaymu yang tak didukung adaptor harus melempar.');
            } catch (PaymentException $e) {
                $this->assertNotSame('', $e->messageId, 'Pesan BI wajib ada.');
                $this->assertNotSame('', $e->messageEn, 'Pesan EN wajib ada.');
            }
        }
    }
}
