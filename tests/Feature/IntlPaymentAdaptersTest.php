<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Payments\PaymentException;
use App\Payments\PaymentTimeoutException;
use App\Payments\Providers\AdyenPaymentGateway;
use App\Payments\Providers\MolliePaymentGateway;
use App\Payments\Providers\PayPalPaymentGateway;
use App\Payments\Providers\RazorpayPaymentGateway;
use App\Payments\Providers\StripePaymentGateway;
use App\Payments\Providers\VerifonePaymentGateway;
use App\Payments\WebhookVerificationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Adapter pembayaran internasional: charge/refund/webhook-valid/
 * invalid-signature/timeout via Http::fake. TANPA panggilan API live.
 *
 * Catatan: Http::fake() me-merge stub sehingga tiap test memakai SATU
 * fake berupa closure routing (URL + counter), termasuk simulasi timeout
 * dengan melempar ConnectionException pada panggilan ke-N.
 */
final class IntlPaymentAdaptersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.stripe', [
            'secret' => 'sk_test_xxx', 'webhook_secret' => 'whsec_test', 'timeout' => 5,
        ]);
        config()->set('services.paypal', [
            'client_id' => 'cid_test', 'secret' => 'csec_test',
            'mode' => 'sandbox', 'webhook_id' => 'wh_test', 'timeout' => 5,
        ]);
        config()->set('services.adyen', [
            'api_key' => 'adyen_test_key', 'merchant_account' => 'TestMerchant',
            'environment' => 'test', 'hmac_key' => 'testhmackey1234567890testhmackey12', 'timeout' => 5,
        ]);
        config()->set('services.mollie', ['api_key' => 'test_xxx', 'timeout' => 5]);
        config()->set('services.verifone', [
            'api_key' => 'vf_key', 'merchant_code' => 'M123', 'environment' => 'test',
            'webhook_secret' => 'vf_whsec', 'supports_refund' => true, 'timeout' => 5,
        ]);
        config()->set('services.razorpay', [
            'key_id' => 'rzp_test_id', 'key_secret' => 'rzp_test_secret',
            'webhook_secret' => 'rzp_whsec', 'timeout' => 5,
        ]);
    }

    /** @param array<string,mixed>|callable $map */
    private function fakeNoLive(array|callable $map): void
    {
        Http::fake($map);
        Http::preventStrayRequests();
    }

    // ---- Stripe ----

    public function test_stripe_payment_intent_3ds_refund_webhook_timeout(): void
    {
        $gw = new StripePaymentGateway();
        $this->assertTrue($gw->isEnabled());

        $creates = 0;
        $this->fakeNoLive(function (Request $request) use (&$creates) {
            $url = (string) $request->url();
            if (str_contains($url, '/refunds')) {
                return Http::response(['id' => 're_1', 'status' => 'succeeded'], 200);
            }
            if (str_contains($url, '/capture')) {
                return Http::response([
                    'id' => 'pi_test_1', 'status' => 'succeeded',
                    'amount_received' => 50000, 'currency' => 'usd',
                ], 200);
            }
            if ($request->method() === 'GET') {
                return Http::response([
                    'id' => 'pi_test_1', 'status' => 'succeeded',
                    'amount_received' => 50000, 'currency' => 'usd',
                ], 200);
            }
            $creates++;
            if ($creates === 1) {
                return Http::response([
                    'id' => 'pi_test_1', 'status' => 'succeeded',
                    'amount_received' => 50000, 'currency' => 'usd',
                ], 200);
            }
            if ($creates === 2) {
                return Http::response([
                    'id' => 'pi_test_3ds', 'status' => 'requires_action',
                    'amount_received' => 0, 'currency' => 'usd',
                ], 200);
            }

            throw new ConnectionException('Connection timed out');
        });

        // charge sukses
        $charged = $gw->charge([
            'reference_id' => 'ord-s-1', 'amount' => 500.0, 'currency' => 'USD', 'country' => 'US',
        ]);
        $this->assertSame('pi_test_1', $charged['reference_id']);
        $this->assertSame('paid', $charged['status']);
        $this->assertSame(500.0, $charged['amount']);

        // 3DS: requires_action dipertahankan eksplisit
        $threeDs = $gw->charge([
            'reference_id' => 'ord-s-3ds', 'amount' => 100.0, 'currency' => 'USD', 'country' => 'US',
        ]);
        $this->assertSame('requires_action', $threeDs['status']);

        // capture + refund + getStatus
        $this->assertSame('paid', $gw->capture('pi_test_1', ['amount' => 500.0, 'currency' => 'USD'])['status']);
        $this->assertSame('refunded', $gw->refund('pi_test_1', 500.0, ['currency' => 'USD'])['status']);
        $this->assertSame('paid', $gw->getStatus('pi_test_1')['status']);

        // webhook valid
        $signed = $gw->signForTest([
            'id' => 'evt_1', 'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => 'pi_test_1', 'status' => 'succeeded',
                'amount_received' => 50000, 'currency' => 'usd',
            ]],
        ]);
        $event = $gw->handleWebhook($signed['payload'], $signed['headers']);
        $this->assertSame('paid', $event['status']);
        $this->assertSame('pi_test_1', $event['reference_id']);

        // invalid signature
        try {
            $gw->handleWebhook($signed['payload'], ['Stripe-Signature' => 't=123,v1=tampered']);
            $this->fail('Harus melempar WebhookVerificationException.');
        } catch (WebhookVerificationException) {
        }

        // timeout (create ke-3 melempar ConnectionException)
        try {
            $gw->charge(['reference_id' => 'ord-s-t', 'amount' => 10.0, 'currency' => 'USD', 'country' => 'US']);
            $this->fail('Harus melempar PaymentTimeoutException.');
        } catch (PaymentTimeoutException $e) {
            $this->assertNotSame('', $e->messageId);
            $this->assertNotSame('', $e->messageEn);
        }
    }

    // ---- PayPal ----

    public function test_paypal_orders_capture_refund_webhook_timeout(): void
    {
        $gw = new PayPalPaymentGateway();
        $this->assertTrue($gw->isEnabled());

        $creates = 0;
        $verifies = 0;
        $this->fakeNoLive(function (Request $request) use (&$creates, &$verifies) {
            $url = (string) $request->url();
            if (str_contains($url, 'oauth2/token')) {
                return Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200);
            }
            if (str_contains($url, 'verify-webhook-signature')) {
                $verifies++;

                return Http::response(['verification_status' => $verifies === 1 ? 'SUCCESS' : 'FAILURE'], 200);
            }
            if (str_contains($url, '/authorize')) {
                return Http::response(['purchase_units' => [[
                    'payments' => ['authorizations' => [['id' => 'AUTH-1', 'status' => 'CREATED']]],
                ]]], 201);
            }
            if (str_contains($url, '/authorizations/AUTH-1/capture')) {
                return Http::response(['id' => 'CAP-2', 'status' => 'COMPLETED'], 201);
            }
            if (str_contains($url, '/refund')) {
                return Http::response(['id' => 'RFD-1', 'status' => 'COMPLETED'], 201);
            }
            if (str_contains($url, '/void')) {
                return Http::response(null, 204);
            }
            if (str_contains($url, '/v2/checkout/orders/') && str_contains($url, '/capture')) {
                return Http::response(['id' => 'ORDER-1', 'status' => 'COMPLETED', 'purchase_units' => [[
                    'payments' => ['captures' => [[
                        'id' => 'CAP-1', 'status' => 'COMPLETED',
                        'amount' => ['currency_code' => 'USD', 'value' => '250.00'],
                    ]]],
                ]]], 201);
            }
            $creates++;
            if ($creates > 2) {
                throw new ConnectionException('Connection timed out');
            }

            return Http::response(['id' => 'ORDER-'.$creates, 'status' => 'CREATED'], 201);
        });

        // charge: token + create + order capture
        $charged = $gw->charge([
            'reference_id' => 'ord-p-1', 'amount' => 250.0, 'currency' => 'USD', 'country' => 'US',
        ]);
        $this->assertSame('paid', $charged['status']);
        $this->assertSame('CAP-1', $charged['reference_id']);

        // authorize + capture otorisasi + refund + void
        $auth = $gw->authorize(['reference_id' => 'ord-p-2', 'amount' => 50.0, 'currency' => 'USD', 'country' => 'US']);
        $this->assertSame('authorized', $auth['status']);
        $this->assertSame('AUTH-1', $auth['reference_id']);
        $this->assertSame('captured', $gw->capture('AUTH-1')['status']);
        $this->assertSame('refunded', $gw->refund('CAP-2', 50.0, ['currency' => 'USD'])['status']);
        $this->assertSame('voided', $gw->void('AUTH-1')['status']);

        // webhook valid (verify → SUCCESS)
        $payload = ['id' => 'WH-1', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'CAP-1', 'status' => 'COMPLETED', 'amount' => ['value' => '250.00']]];
        $event = $gw->handleWebhook($payload, $gw->transmissionHeadersForTest());
        $this->assertSame('paid', $event['status']);

        // invalid signature (verify → FAILURE)
        try {
            $gw->handleWebhook($payload, $gw->transmissionHeadersForTest());
            $this->fail('Harus melempar WebhookVerificationException.');
        } catch (WebhookVerificationException) {
        }

        // timeout (create ke-3 melempar ConnectionException)
        try {
            $gw->charge(['reference_id' => 'ord-p-t', 'amount' => 10.0, 'currency' => 'USD', 'country' => 'US']);
            $this->fail('Harus melempar PaymentTimeoutException.');
        } catch (PaymentTimeoutException) {
        }
    }

    public function test_paypal_sandbox_and_production_base(): void
    {
        $seen = [];
        $this->fakeNoLive(function (Request $request) use (&$seen) {
            $url = (string) $request->url();
            $seen[] = $url;
            if (str_contains($url, 'oauth2/token')) {
                return Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200);
            }

            return Http::response(['id' => 'ORDER-9', 'status' => 'CREATED'], 200);
        });

        (new PayPalPaymentGateway(['client_id' => 'c', 'secret' => 's', 'mode' => 'sandbox']))->getStatus('ORDER-9');
        $this->assertTrue(
            collect($seen)->contains(fn (string $u): bool => str_contains($u, 'api-m.sandbox.paypal.com')),
            'Sandbox base dipakai.'
        );

        $seen = [];
        (new PayPalPaymentGateway(['client_id' => 'c', 'secret' => 's', 'mode' => 'live']))->getStatus('ORDER-9');
        $this->assertTrue(
            collect($seen)->contains(fn (string $u): bool => str_contains($u, 'api-m.paypal.com')),
            'Production base dipakai.'
        );
    }

    // ---- Adyen ----

    public function test_adyen_payments_capture_refund_hmac_timeout(): void
    {
        $gw = new AdyenPaymentGateway();
        $this->assertTrue($gw->isEnabled());

        $creates = 0;
        $this->fakeNoLive(function (Request $request) use (&$creates) {
            $url = (string) $request->url();
            if (str_contains($url, '/captures')) {
                return Http::response(['response' => '[capture-received]'], 200);
            }
            if (str_contains($url, '/refunds')) {
                return Http::response(['response' => '[refund-received]'], 200);
            }
            if (str_contains($url, '/cancels')) {
                return Http::response(['response' => '[cancel-received]'], 200);
            }
            $creates++;
            if ($creates === 1) {
                return Http::response(['pspReference' => 'PSP-1', 'resultCode' => 'Authorised'], 200);
            }
            if ($creates === 2) {
                return Http::response([
                    'pspReference' => 'PSP-3ds', 'resultCode' => 'ChallengeShopper',
                    'action' => ['type' => 'threeDS2Challenge', 'url' => 'https://adyen.test/3ds'],
                ], 200);
            }

            throw new ConnectionException('Connection timed out');
        });

        $charged = $gw->charge([
            'reference_id' => 'ord-a-1', 'amount' => 99.0, 'currency' => 'EUR', 'country' => 'NL',
        ]);
        $this->assertSame('paid', $charged['status']);
        $this->assertSame('PSP-1', $charged['reference_id']);

        // 3DS challenge → requires_action
        $init = $gw->initialize([
            'reference_id' => 'ord-a-3ds', 'amount' => 10.0, 'currency' => 'EUR', 'country' => 'NL',
        ]);
        $this->assertSame('requires_action', $init['status']);
        $this->assertSame('https://adyen.test/3ds', $init['redirect_url']);

        // capture + refund + void
        $this->assertSame('captured', $gw->capture('PSP-1', ['amount' => 99.0, 'currency' => 'EUR'])['status']);
        $this->assertSame('refunded', $gw->refund('PSP-1', 99.0, ['currency' => 'EUR'])['status']);
        $this->assertSame('voided', $gw->void('PSP-1')['status']);

        // webhook valid (HMAC)
        $webhook = ['notificationItems' => [[
            'NotificationRequestItem' => [
                'eventCode' => 'AUTHORISATION', 'success' => true,
                'pspReference' => 'PSP-1', 'merchantReference' => 'ord-a-1',
                'amount' => ['currency' => 'EUR', 'value' => 9900],
            ],
        ]]];
        $signed = $gw->signForTest($webhook);
        $event = $gw->handleWebhook($signed['payload'], $signed['headers']);
        $this->assertSame('paid', $event['status']);
        $this->assertSame('ord-a-1', $event['reference_id']);
        $this->assertSame(99.0, $event['amount']);

        // invalid signature
        try {
            $gw->handleWebhook($signed['payload'], ['HmacSignature' => 'tampered']);
            $this->fail('Harus melempar WebhookVerificationException.');
        } catch (WebhookVerificationException) {
        }

        // timeout
        try {
            $gw->charge(['reference_id' => 'ord-a-t', 'amount' => 10.0, 'currency' => 'EUR', 'country' => 'NL']);
            $this->fail('Harus melempar PaymentTimeoutException.');
        } catch (PaymentTimeoutException) {
        }
    }

    // ---- Mollie ----

    public function test_mollie_redirect_webhook_sync_refund_timeout(): void
    {
        $gw = new MolliePaymentGateway();
        $this->assertTrue($gw->isEnabled());

        $creates = 0;
        $this->fakeNoLive(function (Request $request) use (&$creates) {
            $url = (string) $request->url();
            if (str_contains($url, '/refunds')) {
                return Http::response(['id' => 're_m1', 'status' => 'pending'], 201);
            }
            if ($request->method() === 'GET') {
                return Http::response([
                    'id' => 'tr_test1', 'status' => 'paid',
                    'amount' => ['currency' => 'EUR', 'value' => '20.00'],
                ], 200);
            }
            $creates++;
            if ($creates > 1) {
                throw new ConnectionException('Connection timed out');
            }

            return Http::response([
                'id' => 'tr_test1', 'status' => 'open',
                'amount' => ['currency' => 'EUR', 'value' => '20.00'],
                '_links' => ['checkout' => ['href' => 'https://mollie.test/checkout/tr_test1']],
            ], 201);
        });

        $init = $gw->initialize([
            'reference_id' => 'ord-m-1', 'amount' => 20.0, 'currency' => 'EUR', 'country' => 'NL',
        ]);
        $this->assertSame('pending', $init['status']);
        $this->assertSame('https://mollie.test/checkout/tr_test1', $init['redirect_url']);

        // refund
        $this->assertSame('refunded', $gw->refund('tr_test1', 20.0, ['currency' => 'EUR'])['status']);

        // webhook valid: id ada → sync GET → paid
        $event = $gw->handleWebhook(['id' => 'tr_test1'], []);
        $this->assertSame('paid', $event['status']);
        $this->assertSame('tr_test1', $event['reference_id']);
        $this->assertSame('paid', $gw->getStatus('tr_test1')['status']);

        // invalid: tanpa id
        try {
            $gw->handleWebhook([], []);
            $this->fail('Harus melempar WebhookVerificationException.');
        } catch (WebhookVerificationException) {
        }

        // timeout
        try {
            $gw->charge(['reference_id' => 'ord-m-t', 'amount' => 10.0, 'currency' => 'EUR', 'country' => 'NL']);
            $this->fail('Harus melempar PaymentTimeoutException.');
        } catch (PaymentTimeoutException) {
        }
    }

    // ---- Verifone ----

    public function test_verifone_init_refund_capability_webhook_timeout(): void
    {
        $gw = new VerifonePaymentGateway();
        $this->assertTrue($gw->isEnabled());

        $inits = 0;
        $this->fakeNoLive(function (Request $request) use (&$inits) {
            $url = (string) $request->url();
            if (str_contains($url, '/refund')) {
                return Http::response(['transactionId' => 'VF-1', 'status' => 'REFUNDED'], 200);
            }
            $inits++;
            if ($inits > 1) {
                throw new ConnectionException('Connection timed out');
            }

            return Http::response([
                'transactionId' => 'VF-1', 'status' => 'INITIATED',
                'redirectUrl' => 'https://verifone.test/pay/VF-1',
            ], 200);
        });

        $init = $gw->initialize([
            'reference_id' => 'ord-v-1', 'amount' => 75.0, 'currency' => 'USD', 'country' => 'US',
        ]);
        $this->assertSame('pending', $init['status']);
        $this->assertSame('https://verifone.test/pay/VF-1', $init['redirect_url']);

        // refund didukung (config default true)
        $this->assertSame('refunded', $gw->refund('VF-1', 75.0, ['currency' => 'USD'])['status']);

        // capability jujur: bila produk tidak mendukung refund → error BI/EN jelas
        $noRefund = new VerifonePaymentGateway([
            'api_key' => 'vf_key', 'merchant_code' => 'M123',
            'environment' => 'test', 'webhook_secret' => 'vf_whsec', 'supports_refund' => false,
        ]);
        try {
            $noRefund->refund('VF-1', 1.0, ['currency' => 'USD']);
            $this->fail('Harus melempar PaymentException kapabilitas.');
        } catch (PaymentException $e) {
            $this->assertNotSame('', $e->messageId);
            $this->assertNotSame('', $e->messageEn);
        }

        // webhook valid
        $signed = $gw->signForTest([
            'eventId' => 'evt-v-1', 'transactionId' => 'VF-1',
            'status' => 'CAPTURED', 'amount' => ['currency' => 'USD', 'value' => '75.00'],
        ]);
        $event = $gw->handleWebhook($signed['payload'], $signed['headers']);
        $this->assertSame('paid', $event['status']);

        // invalid signature
        try {
            $gw->handleWebhook($signed['payload'], ['X-Verifone-Signature' => 'x', 'X-Timestamp' => (string) time()]);
            $this->fail('Harus melempar WebhookVerificationException.');
        } catch (WebhookVerificationException) {
        }

        // timeout
        try {
            $gw->charge(['reference_id' => 'ord-v-t', 'amount' => 10.0, 'currency' => 'USD', 'country' => 'US']);
            $this->fail('Harus melempar PaymentTimeoutException.');
        } catch (PaymentTimeoutException) {
        }
    }

    // ---- Razorpay ----

    public function test_razorpay_order_signature_capture_refund_timeout(): void
    {
        $gw = new RazorpayPaymentGateway();
        $this->assertTrue($gw->isEnabled());

        $orders = 0;
        $this->fakeNoLive(function (Request $request) use (&$orders) {
            $url = (string) $request->url();
            if (str_contains($url, '/capture')) {
                return Http::response(['id' => 'pay_test1', 'status' => 'captured'], 200);
            }
            if (str_contains($url, '/refund')) {
                return Http::response(['id' => 'rfnd_test1', 'status' => 'processed'], 200);
            }
            $orders++;
            if ($orders > 1) {
                throw new ConnectionException('Connection timed out');
            }

            return Http::response([
                'id' => 'order_test1', 'status' => 'created', 'amount' => 500000, 'currency' => 'INR',
            ], 200);
        });

        $init = $gw->initialize([
            'reference_id' => 'ord-r-1', 'amount' => 5000.0, 'currency' => 'INR', 'country' => 'IN',
        ]);
        $this->assertSame('pending', $init['status']);
        $this->assertSame('order_test1', $init['reference_id']);

        // capture + refund (payment id)
        $this->assertSame('captured', $gw->capture('pay_test1', ['amount' => 5000.0, 'currency' => 'INR'])['status']);
        $this->assertSame('refunded', $gw->refund('pay_test1', 5000.0, ['currency' => 'INR'])['status']);

        // void jujur: tidak didukung API Razorpay
        try {
            $gw->void('pay_test1');
            $this->fail('Harus melempar PaymentException void.');
        } catch (PaymentException $e) {
            $this->assertNotSame('', $e->messageId);
            $this->assertNotSame('', $e->messageEn);
        }

        // signature checkout valid
        $signed = $gw->signCheckoutForTest('order_test1', 'pay_test1');
        $this->assertTrue($gw->verify($signed['payload'], $signed['headers']));
        $event = $gw->handleWebhook($signed['payload'], $signed['headers']);
        $this->assertSame('paid', $event['status']);
        $this->assertSame('order_test1', $event['reference_id']);

        // invalid signature
        $this->assertFalse($gw->verify($signed['payload'], ['X-Razorpay-Signature' => 'tampered']));
        try {
            $gw->handleWebhook($signed['payload'], ['X-Razorpay-Signature' => 'tampered']);
            $this->fail('Harus melempar WebhookVerificationException.');
        } catch (WebhookVerificationException) {
        }

        // timeout
        try {
            $gw->charge(['reference_id' => 'ord-r-t', 'amount' => 10.0, 'currency' => 'INR', 'country' => 'IN']);
            $this->fail('Harus melempar PaymentTimeoutException.');
        } catch (PaymentTimeoutException) {
        }
    }

    // ---- Disabled + kapabilitas jujur ----

    public function test_gateways_disabled_without_credentials_and_honest_capabilities(): void
    {
        $empty = [
            new StripePaymentGateway(['secret' => '', 'webhook_secret' => '']),
            new PayPalPaymentGateway(['client_id' => '', 'secret' => '']),
            new AdyenPaymentGateway(['api_key' => '', 'merchant_account' => '']),
            new MolliePaymentGateway(['api_key' => '']),
            new VerifonePaymentGateway(['api_key' => '', 'merchant_code' => '']),
            new RazorpayPaymentGateway(['key_id' => '', 'key_secret' => '']),
        ];
        foreach ($empty as $gw) {
            $this->assertFalse($gw->isEnabled(), $gw->getName().' harus nonaktif tanpa kredensial.');
            try {
                $gw->charge(['reference_id' => 'x', 'amount' => 1.0]);
                $this->fail($gw->getName().' harus menolak saat kredensial kosong.');
            } catch (PaymentException $e) {
                $this->assertNotSame('', $e->messageId);
                $this->assertNotSame('', $e->messageEn);
            }
        }

        // Spot-check jujur (positif + negatif).
        $this->assertTrue((new StripePaymentGateway())->supportsCurrency('USD'));
        $this->assertTrue((new PayPalPaymentGateway())->supportsPaymentMethod('paylater'));
        $this->assertFalse((new PayPalPaymentGateway())->supportsPaymentMethod('qris'));
        $this->assertTrue((new MolliePaymentGateway())->supportsCurrency('EUR'));
        $this->assertFalse((new MolliePaymentGateway())->supportsCurrency('IDR'));
        $this->assertFalse((new MolliePaymentGateway())->supportsCountry('ID'));
        $this->assertTrue((new VerifonePaymentGateway())->supportsPaymentMethod('credit_card'));
        $this->assertFalse((new VerifonePaymentGateway())->supportsPaymentMethod('qris'));
        $this->assertFalse((new VerifonePaymentGateway())->supportsCurrency('IDR'));
        $this->assertTrue((new RazorpayPaymentGateway())->supportsCurrency('INR'));
        $this->assertFalse((new RazorpayPaymentGateway())->supportsCurrency('JPY'));
        $this->assertTrue((new AdyenPaymentGateway())->supportsCountry('NL'));
    }
}
