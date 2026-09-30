<?php

declare(strict_types=1);

namespace App\Payments\Providers;

use App\Payments\PaymentException;
use App\Payments\PaymentMethod;
use Illuminate\Http\Client\Response;

/**
 * Adapter PayPal Orders v2 — HTTP-native TANPA SDK.
 *
 * - Env sandbox/production via config services.paypal.mode ('sandbox'|'live').
 * - OAuth client_credentials → Bearer untuk tiap sesi API.
 * - charge: create order (CAPTURE) + /capture; authorize: create (AUTHORIZE)
 *   + /authorize; capture: /v2/payments/authorizations/{id}/capture;
 *   refund: /v2/payments/captures/{id}/refund; void: .../authorizations/{id}/void.
 * - Webhook: verifikasi server-side ke /v1/notifications/verify-webhook-signature
 *   (verification_status === 'SUCCESS').
 * - Nonaktif bila client_id/secret kosong.
 */
final class PayPalPaymentGateway extends AbstractIntlGateway
{
    public const CODE = 'paypal';

    private const SANDBOX_BASE = 'https://api-m.sandbox.paypal.com';

    private const LIVE_BASE = 'https://api-m.paypal.com';

    /** @var string[] */
    private const CURRENCIES = [
        'USD', 'EUR', 'GBP', 'JPY', 'AUD', 'CAD', 'SGD', 'IDR',
        'MYR', 'PHP', 'THB', 'CHF', 'NZD', 'HKD', 'INR',
    ];

    /** @var string[] */
    private const COUNTRIES = [
        'US', 'GB', 'DE', 'FR', 'NL', 'ES', 'IT', 'IE', 'AT', 'BE',
        'SG', 'MY', 'ID', 'AU', 'CA', 'JP', 'CH', 'NZ', 'HK', 'PH', 'TH', 'IN', 'AE',
    ];

    /**
     * Jujur: dompet PayPal, kartu, Pay Later, transfer bank.
     * VA/QRIS/retail lokal TIDAK diklaim.
     *
     * @var string[]
     */
    private const METHODS = [
        PaymentMethod::E_WALLET,
        PaymentMethod::CREDIT_CARD,
        PaymentMethod::PAYLATER,
        PaymentMethod::BANK_TRANSFER,
    ];

    private string $clientId;

    private string $secret;

    private string $mode;

    private string $webhookId;

    /** @param array<string,mixed>|null $config Override; default config('services.paypal') + env. */
    public function __construct(?array $config = null)
    {
        $cfg = $config ?? (array) config('services.paypal', []);
        $this->clientId = (string) ($cfg['client_id'] ?? env('PAYPAL_CLIENT_ID', ''));
        $this->secret = (string) ($cfg['secret'] ?? env('PAYPAL_SECRET', ''));
        $this->mode = strtolower((string) ($cfg['mode'] ?? $cfg['env'] ?? env('PAYPAL_MODE', env('PAYPAL_ENV', 'sandbox'))));
        $this->webhookId = (string) ($cfg['webhook_id'] ?? env('PAYPAL_WEBHOOK_ID', ''));
        $this->timeoutSeconds = (int) ($cfg['timeout'] ?? 15);
    }

    public function getName(): string
    {
        return self::CODE;
    }

    public function isEnabled(): bool
    {
        return $this->clientId !== '' && $this->secret !== '';
    }

    public function base(): string
    {
        return $this->mode === 'live' || $this->mode === 'production' ? self::LIVE_BASE : self::SANDBOX_BASE;
    }

    public function initialize(array $payload): array
    {
        $this->guardContext($payload);
        $this->ensureEnabled('PayPal');

        $order = $this->createOrder($payload, 'CAPTURE');
        $approve = $this->findLink($order, 'approve');

        return [
            'reference_id' => (string) ($order['id'] ?? ($payload['reference_id'] ?? '')),
            'status' => 'pending',
            'redirect_url' => $approve,
            'raw' => $order,
        ];
    }

    public function authorize(array $payload): array
    {
        $this->guardContext($payload);
        $this->ensureEnabled('PayPal');

        $order = $this->createOrder($payload, 'AUTHORIZE');
        $orderId = (string) ($order['id'] ?? '');

        $response = $this->send(
            fn (): Response => $this->api()->post($this->base()."/v2/checkout/orders/{$orderId}/authorize", new \stdClass()),
            $orderId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $orderId, 'PayPal');
        }
        $result = $response->json();
        $authId = (string) ($result['purchase_units'][0]['payments']['authorizations'][0]['id'] ?? $orderId);

        return [
            'reference_id' => $authId,
            'status' => 'authorized',
            'raw' => $result,
        ];
    }

    public function capture(string $referenceId, array $options = []): array
    {
        $this->ensureEnabled('PayPal');

        $body = [];
        if (isset($options['amount'], $options['currency'])) {
            $body['amount'] = [
                'currency_code' => strtoupper((string) $options['currency']),
                'value' => self::money((float) $options['amount']),
            ];
        }

        $response = $this->send(
            fn (): Response => $this->api()->post($this->base()."/v2/payments/authorizations/{$referenceId}/capture", $body === [] ? new \stdClass() : $body),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'PayPal');
        }
        $result = $response->json();
        if (in_array(strtoupper((string) ($result['status'] ?? '')), ['DECLINED', 'FAILED'], true)) {
            $this->declined($referenceId, ['paypal_status' => $result['status']]);
        }

        return [
            'reference_id' => (string) ($result['id'] ?? $referenceId),
            'status' => 'captured',
            'raw' => $result,
        ];
    }

    public function charge(array $payload, ?string $idempotencyKey = null): array
    {
        $this->guardContext($payload);
        $this->ensureEnabled('PayPal');

        $order = $this->createOrder($payload, 'CAPTURE', $idempotencyKey);
        $orderId = (string) ($order['id'] ?? ($payload['reference_id'] ?? ''));

        $client = $this->api();
        if ($idempotencyKey !== null) {
            $client->withHeaders(['PayPal-Request-Id' => $idempotencyKey]);
        }
        $response = $this->send(
            fn (): Response => $client->post($this->base()."/v2/checkout/orders/{$orderId}/capture", new \stdClass()),
            $orderId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $orderId, 'PayPal');
        }
        $result = $response->json();
        $capture = $result['purchase_units'][0]['payments']['captures'][0] ?? [];
        $status = strtoupper((string) ($capture['status'] ?? $result['status'] ?? ''));

        if (in_array($status, ['DECLINED', 'FAILED', 'VOIDED'], true)) {
            $this->declined($orderId, ['paypal_status' => $status]);
        }

        $currency = strtoupper((string) ($capture['amount']['currency_code'] ?? $payload['currency'] ?? 'USD'));

        return [
            'reference_id' => (string) ($capture['id'] ?? $orderId),
            'status' => $status === 'COMPLETED' ? 'paid' : 'pending',
            'amount' => isset($capture['amount']['value']) ? (float) $capture['amount']['value'] : (float) ($payload['amount'] ?? 0),
            'currency' => $currency,
            'raw' => $result,
        ];
    }

    public function refund(string $referenceId, float $amount, array $options = []): array
    {
        $this->ensureEnabled('PayPal');

        $body = [];
        if ($amount > 0 && isset($options['currency'])) {
            $body['amount'] = [
                'currency_code' => strtoupper((string) $options['currency']),
                'value' => self::money($amount),
            ];
        }

        $client = $this->api();
        if (isset($options['idempotency_key'])) {
            $client->withHeaders(['PayPal-Request-Id' => (string) $options['idempotency_key']]);
        }
        $response = $this->send(
            fn (): Response => $client->post($this->base()."/v2/payments/captures/{$referenceId}/refund", $body === [] ? new \stdClass() : $body),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'PayPal');
        }
        $result = $response->json();

        return [
            'reference_id' => $referenceId,
            'status' => strtoupper((string) ($result['status'] ?? '')) === 'FAILED' ? 'failed' : 'refunded',
            'amount' => $amount,
            'raw' => $result,
        ];
    }

    public function void(string $referenceId, array $options = []): array
    {
        $this->ensureEnabled('PayPal');

        $response = $this->send(
            fn (): Response => $this->api()->post($this->base()."/v2/payments/authorizations/{$referenceId}/void", new \stdClass()),
            $referenceId
        );
        if ($response->failed() && $response->status() !== 204) {
            $this->failForStatus($response, $referenceId, 'PayPal');
        }

        return [
            'reference_id' => $referenceId,
            'status' => 'voided',
            'raw' => $response->status() === 204 ? [] : $response->json(),
        ];
    }

    public function verify(array $payload, array $headers): bool
    {
        if (! $this->isEnabled() || $this->webhookId === '') {
            return false;
        }

        $transmissionId = self::header($headers, 'PayPal-Transmission-Id');
        $transmissionTime = self::header($headers, 'PayPal-Transmission-Time');
        $transmissionSig = self::header($headers, 'PayPal-Transmission-Sig');
        $certUrl = self::header($headers, 'PayPal-Cert-Url');
        $authAlgo = self::header($headers, 'PayPal-Auth-Algo');

        if ($transmissionId === null || $transmissionTime === null || $transmissionSig === null) {
            return false;
        }

        try {
            $response = $this->send(
                fn (): Response => $this->api()->post($this->base().'/v1/notifications/verify-webhook-signature', [
                    'transmission_id' => $transmissionId,
                    'transmission_time' => $transmissionTime,
                    'cert_url' => $certUrl ?? '',
                    'auth_algo' => $authAlgo ?? '',
                    'transmission_sig' => $transmissionSig,
                    'webhook_id' => $this->webhookId,
                    'webhook_event' => $payload,
                ]),
                (string) ($payload['id'] ?? '')
            );
        } catch (PaymentException) {
            return false;
        }

        if ($response->failed()) {
            return false;
        }

        return strtoupper((string) ($response->json('verification_status') ?? '')) === 'SUCCESS';
    }

    /**
     * Header transmisi untuk pengujian verify().
     *
     * @return array<string,string>
     */
    public function transmissionHeadersForTest(array $overrides = []): array
    {
        return [
            'PayPal-Transmission-Id' => 'trans-test-1',
            'PayPal-Transmission-Time' => gmdate('Y-m-d\TH:i:s\Z'),
            'PayPal-Transmission-Sig' => 'sig-test',
            'PayPal-Cert-Url' => 'https://api.sandbox.paypal.com/certs/test',
            'PayPal-Auth-Algo' => 'SHA256withRSA',
        ] + $overrides;
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        if (! $this->verify($payload, $headers)) {
            $this->webhookFailure('tanda tangan tidak valid / invalid signature');
        }

        $resource = is_array($payload['resource'] ?? null) ? $payload['resource'] : [];

        return [
            'event_id' => (string) ($payload['id'] ?? ''),
            'reference_id' => (string) ($resource['id'] ?? $payload['reference_id'] ?? ''),
            'status' => self::mapResourceStatus((string) ($resource['status'] ?? $payload['event_type'] ?? '')),
            'amount' => isset($resource['amount']['value']) ? (float) $resource['amount']['value'] : (isset($payload['amount']) ? (float) $payload['amount'] : null),
            'raw' => $payload,
        ];
    }

    public function getStatus(string $referenceId): array
    {
        $this->ensureEnabled('PayPal');

        $response = $this->send(
            fn (): Response => $this->api()->get($this->base()."/v2/checkout/orders/{$referenceId}"),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'PayPal');
        }
        $order = $response->json();

        return [
            'reference_id' => (string) ($order['id'] ?? $referenceId),
            'status' => self::mapOrderStatus((string) ($order['status'] ?? '')),
            'raw' => $order,
        ];
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), self::CURRENCIES, true);
    }

    public function supportsCountry(string $country): bool
    {
        return in_array(strtoupper($country), self::COUNTRIES, true);
    }

    public function supportsPaymentMethod(string $method): bool
    {
        return in_array(PaymentMethod::normalize($method), self::METHODS, true);
    }

    /** @return \Illuminate\Http\Client\PendingRequest */
    private function api(): \Illuminate\Http\Client\PendingRequest
    {
        return $this->http()->withToken($this->accessToken());
    }

    private function accessToken(): string
    {
        $response = $this->send(
            fn (): Response => $this->http()->asForm()->withBasicAuth($this->clientId, $this->secret)
                ->post($this->base().'/v1/oauth2/token', ['grant_type' => 'client_credentials']),
            'paypal-oauth'
        );
        if ($response->failed()) {
            $this->failForStatus($response, 'paypal-oauth', 'PayPal');
        }

        $token = (string) ($response->json('access_token') ?? '');
        if ($token === '') {
            throw new PaymentException(
                'PayPal gagal menerbitkan token. / PayPal failed to issue a token.',
                'PayPal gagal menerbitkan token.',
                'PayPal failed to issue a token.',
                ['gateway' => self::CODE]
            );
        }

        return $token;
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function createOrder(array $payload, string $intent, ?string $idempotencyKey = null): array
    {
        $currency = strtoupper((string) ($payload['currency'] ?? 'USD'));
        $reference = (string) ($payload['reference_id'] ?? 'paypal-'.bin2hex(random_bytes(4)));

        $client = $this->api();
        if ($idempotencyKey !== null) {
            $client->withHeaders(['PayPal-Request-Id' => $idempotencyKey]);
        }

        $response = $this->send(
            fn (): Response => $client->post($this->base().'/v2/checkout/orders', [
                'intent' => $intent,
                'purchase_units' => [[
                    'reference_id' => $reference,
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => self::money((float) ($payload['amount'] ?? 0)),
                    ],
                    'description' => (string) ($payload['description'] ?? "Pembayaran {$reference}"),
                ]],
            ]),
            $reference
        );
        if ($response->failed()) {
            $this->failForStatus($response, $reference, 'PayPal');
        }

        /** @var array<string,mixed> */
        return $response->json();
    }

    /** @param array<string,mixed> $order */
    private function findLink(array $order, string $rel): ?string
    {
        foreach ($order['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === $rel) {
                return $link['href'] ?? null;
            }
        }

        return null;
    }

    private static function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    public static function mapOrderStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'COMPLETED' => 'paid',
            'APPROVED' => 'pending',
            'VOIDED' => 'voided',
            'PAYER_ACTION_REQUIRED' => 'requires_action',
            'CREATED', 'SAVED' => 'pending',
            default => 'pending',
        };
    }

    public static function mapResourceStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'COMPLETED' => 'paid',
            'PENDING' => 'pending',
            'REFUNDED', 'REVERSED', 'REFUNDED_MISMATCH' => 'refunded',
            'DENIED', 'FAILED', 'EXPIRED', 'VOIDED' => 'failed',
            'CHECKOUT.ORDER.APPROVED', 'PAYMENT.AUTHORIZATION.CREATED' => 'authorized',
            'PAYMENT.CAPTURE.COMPLETED', 'CHECKOUT.ORDER.COMPLETED' => 'paid',
            'PAYMENT.CAPTURE.REFUNDED' => 'refunded',
            'PAYMENT.CAPTURE.DENIED' => 'failed',
            default => 'pending',
        };
    }
}
