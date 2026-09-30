<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Payments\CapabilityMatrix;
use App\Payments\FakePaymentGateway;
use App\Payments\GatewayRegistry;
use App\Payments\PaymentDeclinedException;
use App\Payments\PaymentException;
use App\Payments\PaymentRouter;
use App\Payments\UnsupportedCountryException;
use App\Payments\UnsupportedCurrencyException;
use App\Payments\WebhookPipeline;
use App\Payments\WebhookVerificationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentAbstractionTest extends TestCase
{
    use RefreshDatabase;

    private GatewayRegistry $registry;
    private FakePaymentGateway $fake;

    protected function setUp(): void
    {
        parent::setUp();

        FakePaymentGateway::resetReplayGuard();

        $this->fake = new FakePaymentGateway();
        $this->registry = new GatewayRegistry(new CapabilityMatrix());
        $this->registry->register('fake', fn (): FakePaymentGateway => $this->fake, [
            'currencies' => ['IDR', 'USD'],
            'countries' => ['ID', 'US'],
            'methods' => ['virtual_account', 'e_wallet', 'qris', 'credit_card'],
        ]);
    }

    private function router(): PaymentRouter
    {
        return (new PaymentRouter($this->registry))->useGateways('fake');
    }

    public function test_initialize_charge_and_refund(): void
    {
        $router = $this->router();

        $init = $this->fake->initialize(['reference_id' => 'ord-1', 'amount' => 100000]);
        $this->assertSame('ord-1', $init['reference_id']);
        $this->assertArrayHasKey('redirect_url', $init);

        $charged = $router->charge([
            'reference_id' => 'ord-1', 'amount' => 100000,
            'currency' => 'IDR', 'country' => 'ID', 'method' => 'virtual_account',
        ], 'idem-init-1');

        $this->assertSame('paid', $charged['status']);
        $this->assertSame('fake', $charged['gateway']);

        $refund = $this->fake->refund('ord-1', 100000.0);
        $this->assertSame('refunded', $refund['status']);
        $this->assertSame(1, $this->fake->refundCount);
    }

    public function test_webhook_pipeline_processes_valid_signature(): void
    {
        $providerId = $this->makeProvider();
        $signed = $this->fake->signWebhook(['reference_id' => 'ord-web-1', 'status' => 'paid', 'amount' => 50000]);

        $applied = false;
        $record = (new WebhookPipeline($this->registry))->handle(
            $providerId, 'fake', $signed['payload'], $signed['headers'],
            function () use (&$applied): void {
                $applied = true;
            },
            50000.0
        );

        $this->assertTrue($applied);
        $this->assertSame(WebhookPipeline::PROCESSED, $record->processing_result);
        $this->assertNotNull($record->processed_at);
        $this->assertDatabaseHas('payment_webhook_callbacks', [
            'provider_id' => $providerId,
            'processing_result' => WebhookPipeline::PROCESSED,
        ]);
    }

    public function test_webhook_duplicate_event_is_ignored(): void
    {
        $providerId = $this->makeProvider();
        $pipeline = new WebhookPipeline($this->registry);
        $signed = $this->fake->signWebhook(['reference_id' => 'ord-dup-1', 'status' => 'paid', 'amount' => 10000]);

        $first = $pipeline->handle($providerId, 'fake', $signed['payload'], $signed['headers']);
        $this->assertSame(WebhookPipeline::PROCESSED, $first->processing_result);

        // Replay: event id sama → tidak diproses ulang, tidak menambah baris.
        $second = $pipeline->handle($providerId, 'fake', $signed['payload'], $signed['headers']);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, DB::table('payment_webhook_callbacks')
            ->where('provider_id', $providerId)
            ->where('gateway_transaction_id', 'ord-dup-1')
            ->count());
    }

    public function test_webhook_invalid_signature_fails(): void
    {
        $providerId = $this->makeProvider();
        $signed = $this->fake->signWebhook(['reference_id' => 'ord-bad-1', 'status' => 'paid']);
        $signed['headers']['X-Signature'] = 'tampered';

        $this->expectException(WebhookVerificationException::class);
        try {
            (new WebhookPipeline($this->registry))->handle($providerId, 'fake', $signed['payload'], $signed['headers']);
        } finally {
            $this->assertDatabaseHas('payment_webhook_callbacks', [
                'provider_id' => $providerId,
                'processing_result' => WebhookPipeline::FAILED,
            ]);
        }
    }

    public function test_router_idempotency_key_prevents_double_charge(): void
    {
        $router = $this->router();
        $payload = [
            'reference_id' => 'ord-idem-1', 'amount' => 75000,
            'currency' => 'IDR', 'country' => 'ID', 'method' => 'qris',
        ];

        $first = $router->charge($payload, 'key-abc-123');
        $second = $router->charge($payload, 'key-abc-123');

        $this->assertSame($first['reference_id'], $second['reference_id']);
        $this->assertTrue($second['idempotent_replay'] ?? false);
        $this->assertSame(1, $this->fake->chargeCount, 'Gateway hanya boleh ditagih sekali.');
    }

    public function test_router_rejects_unsupported_currency_and_country(): void
    {
        $router = $this->router();

        try {
            $router->charge(['reference_id' => 'x', 'amount' => 10, 'currency' => 'JPY', 'country' => 'ID', 'method' => 'qris'], 'k-jpy');
            $this->fail('Harus melempar UnsupportedCurrencyException.');
        } catch (UnsupportedCurrencyException $e) {
            $this->assertSame('JPY', $e->context['currency']);
            $this->assertNotSame('', $e->messageId);
            $this->assertNotSame('', $e->messageEn);
        }

        try {
            $router->charge(['reference_id' => 'x', 'amount' => 10, 'currency' => 'IDR', 'country' => 'XX', 'method' => 'qris'], 'k-xx');
            $this->fail('Harus melempar UnsupportedCountryException.');
        } catch (UnsupportedCountryException $e) {
            $this->assertSame('XX', $e->context['country']);
        }

        $this->assertSame(0, $this->fake->chargeCount, 'Gateway tidak boleh dipanggil untuk konteks tak didukung.');
    }

    public function test_router_rejects_unsupported_payment_method(): void
    {
        $router = $this->router();

        $this->expectException(PaymentException::class);
        $router->charge(
            ['reference_id' => 'x', 'amount' => 10, 'currency' => 'IDR', 'country' => 'ID', 'method' => 'barter_batu'],
            'k-method'
        );
    }

    public function test_router_falls_back_to_next_gateway_on_decline(): void
    {
        $primary = new FakePaymentGateway(name: 'primary');
        $primary->failNextCharge();
        $secondary = new FakePaymentGateway(name: 'secondary');

        $registry = new GatewayRegistry(new CapabilityMatrix());
        $registry->register('primary', fn () => $primary);
        $registry->register('secondary', fn () => $secondary);
        $router = (new PaymentRouter($registry))->useGateways('primary', 'secondary');

        $result = $router->charge(
            ['reference_id' => 'ord-fb-1', 'amount' => 20000, 'currency' => 'IDR', 'country' => 'ID', 'method' => 'e_wallet'],
            'k-fallback-1'
        );

        $this->assertSame('paid', $result['status']);
        $this->assertSame('secondary', $result['gateway']);
    }

    public function test_router_honours_database_priority_order(): void
    {
        $secondary = new FakePaymentGateway(name: 'secondary');
        $primary = new FakePaymentGateway(name: 'primary');

        $registry = new GatewayRegistry(new CapabilityMatrix());
        $registry->register('primary', fn () => $primary);
        $registry->register('secondary', fn () => $secondary);

        // sort_order kecil = prioritas lebih tinggi.
        DB::table('providers')->insert([
            'name' => 'Secondary Row', 'type' => 'payment', 'api_format' => 'secondary',
            'config' => json_encode(['gateway' => 'secondary']),
            'is_active' => true, 'sort_order' => 20,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('providers')->insert([
            'name' => 'Primary Row', 'type' => 'payment', 'api_format' => 'primary',
            'config' => json_encode(['gateway' => 'primary']),
            'is_active' => true, 'sort_order' => 5,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $router = new PaymentRouter($registry);
        $candidates = $router->candidates(['currency' => 'IDR', 'country' => 'ID', 'method' => 'qris']);

        $this->assertNotEmpty($candidates);
        $this->assertSame('primary', $candidates[0]['name']);

        $charged = $router->charge(
            ['reference_id' => 'ord-prio-1', 'amount' => 1000, 'currency' => 'IDR', 'country' => 'ID', 'method' => 'qris'],
            'k-prio-1'
        );
        $this->assertSame('primary', $charged['gateway']);
    }

    public function test_gateway_registry_discovers_fake_dynamically(): void
    {
        $fresh = new GatewayRegistry();
        $this->assertTrue($fresh->has('fake'), 'FakePaymentGateway harus ditemukan otomatis.');
        $this->assertInstanceOf(FakePaymentGateway::class, $fresh->resolve('fake'));
    }

    public function test_fake_timeout_simulation(): void
    {
        $slow = new FakePaymentGateway(name: 'slow', timeoutAboveAmount: 1000.0);
        $this->expectException(PaymentDeclinedException::class);
        // failCharge path juga dipakai untuk timeout test di bawah via try/catch agar pesan jelas
        try {
            $slow->charge(['reference_id' => 't1', 'amount' => 5000.0]);
            $this->fail('Harus timeout.');
        } catch (\App\Payments\PaymentTimeoutException $e) {
            $this->assertNotSame('', $e->messageId);
            throw new PaymentDeclinedException('timeout ok', ['ok' => true]);
        }
    }

    private function makeProvider(): int
    {
        // Insert langsung via query builder agar tidak bergantung pada mutator Crypt.
        return (int) DB::table('providers')->insertGetId([
            'name' => 'Fake Provider',
            'type' => 'payment',
            'api_format' => 'fake',
            'base_url' => 'https://fake-gateway.test',
            'config' => json_encode(['gateway' => 'fake']),
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
