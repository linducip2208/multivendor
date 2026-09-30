<?php

declare(strict_types=1);

namespace App\Payments\Providers;

use App\Payments\PaymentMethod;
use Illuminate\Http\Client\Response;

/**
 * Adapter Stripe PaymentIntent — HTTP-native TANPA SDK.
 *
 * REST: https://api.stripe.com/v1 (Basic auth secret key, form-encoded).
 * - create/confirm PaymentIntent, capture, refund, cancel (void), retrieve.
 * - Webhook: header Stripe-Signature "t=...,v1=..." (HMAC-SHA256 atas
 *   "timestamp.canonicalJson(payload)" dengan webhook secret; toleransi 300 dtk).
 *   Catatan jujur: Stripe asli menandatangani RAW body; adapter ini memverifikasi
 *   bentuk kanonisnya karena kontrak menerima array ter-decode.
 * - Status 3DS: requires_action/processing/authorized dipetakan eksplisit.
 * - Nonaktif (isEnabled=false) bila secret kosong.
 */
final class StripePaymentGateway extends AbstractIntlGateway
{
    public const CODE = 'stripe';

    private const BASE = 'https://api.stripe.com/v1';

    private const TIMESTAMP_TOLERANCE = 300;

    /** @var string[] */
    private const CURRENCIES = [
        'USD', 'EUR', 'GBP', 'JPY', 'AUD', 'CAD', 'SGD', 'IDR',
        'MYR', 'THB', 'PHP', 'INR', 'CHF', 'NZD', 'HKD',
    ];

    /** @var string[] */
    private const COUNTRIES = [
        'US', 'GB', 'DE', 'FR', 'NL', 'ES', 'IT', 'IE', 'AT', 'BE',
        'SG', 'MY', 'ID', 'AU', 'CA', 'JP', 'CH', 'NZ', 'HK', 'PH', 'TH', 'IN',
    ];

    /**
     * Jujur: kartu, debit/transfer bank, direct debit, wallet (Apple/Google Pay).
     * Metode lokal (VA/QRIS/retail) TIDAK diklaim.
     *
     * @var string[]
     */
    private const METHODS = [
        PaymentMethod::CREDIT_CARD,
        PaymentMethod::BANK_TRANSFER,
        PaymentMethod::DIRECT_DEBIT,
        PaymentMethod::E_WALLET,
    ];

    private string $secret;

    private string $webhookSecret;

    /** @param array<string,mixed>|null $config Override; default config('services.stripe') + env. */
    public function __construct(?array $config = null)
    {
        $cfg = $config ?? (array) config('services.stripe', []);
        $this->secret = (string) ($cfg['secret'] ?? env('STRIPE_SECRET', ''));
        $this->webhookSecret = (string) ($cfg['webhook_secret'] ?? env('STRIPE_WEBHOOK_SECRET', ''));
        $this->timeoutSeconds = (int) ($cfg['timeout'] ?? 15);
    }

    public function getName(): string
    {
        return self::CODE;
    }

    public function isEnabled(): bool
    {
        return $this->secret !== '';
    }

    public function initialize(array $payload): array
    {
        $this->guardContext($payload);
        $this->ensureEnabled('Stripe');

        $intent = $this->createIntent($payload, false, false);

        return [
            'reference_id' => $intent['id'],
            'status' => self::mapStatus((string) ($intent['status'] ?? '')),
            'raw' => $intent,
        ];
    }

    public function authorize(array $payload): array
    {
        $this->guardContext($payload);
        $this->ensureEnabled('Stripe');

        $intent = $this->createIntent($payload, true, false);

        return [
            'reference_id' => $intent['id'],
            'status' => 'authorized',
            'raw' => $intent,
        ];
    }

    public function capture(string $referenceId, array $options = []): array
    {
        $this->ensureEnabled('Stripe');

        $body = [];
        if (isset($options['amount'], $options['currency'])) {
            $body['amount_to_capture'] = self::toMinor((float) $options['amount'], (string) $options['currency']);
        }

        $response = $this->send(
            fn (): Response => $this->client()->post(self::BASE."/payment_intents/{$referenceId}/capture", $body),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'Stripe');
        }
        $intent = $response->json();

        return [
            'reference_id' => (string) ($intent['id'] ?? $referenceId),
            'status' => self::mapStatus((string) ($intent['status'] ?? '')),
            'raw' => $intent,
        ];
    }

    public function charge(array $payload, ?string $idempotencyKey = null): array
    {
        $this->guardContext($payload);
        $this->ensureEnabled('Stripe');

        $intent = $this->createIntent($payload, false, true, $idempotencyKey);
        $status = (string) ($intent['status'] ?? '');

        if (in_array($status, ['requires_payment_method', 'requires_confirmation', 'canceled'], true)) {
            $this->declined(
                (string) ($intent['id'] ?? ($payload['reference_id'] ?? '')),
                ['stripe_status' => $status]
            );
        }

        $mapped = self::mapStatus($status);
        $currency = strtoupper((string) ($payload['currency'] ?? $intent['currency'] ?? 'USD'));

        return [
            'reference_id' => (string) ($intent['id'] ?? ($payload['reference_id'] ?? '')),
            'status' => $mapped,
            'amount' => isset($intent['amount_received'])
                ? self::fromMinor((int) $intent['amount_received'], $currency)
                : (float) ($payload['amount'] ?? 0),
            'currency' => $currency,
            'raw' => $intent,
        ];
    }

    public function refund(string $referenceId, float $amount, array $options = []): array
    {
        $this->ensureEnabled('Stripe');

        $currency = strtoupper((string) ($options['currency'] ?? 'USD'));
        $body = ['payment_intent' => $referenceId];
        if ($amount > 0) {
            $body['amount'] = self::toMinor($amount, $currency);
        }

        $response = $this->send(
            fn (): Response => $this->client()->post(self::BASE.'/refunds', $body),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'Stripe');
        }
        $refund = $response->json();

        return [
            'reference_id' => $referenceId,
            'status' => ((string) ($refund['status'] ?? '')) === 'failed' ? 'failed' : 'refunded',
            'amount' => $amount,
            'raw' => $refund,
        ];
    }

    public function void(string $referenceId, array $options = []): array
    {
        $this->ensureEnabled('Stripe');

        $response = $this->send(
            fn (): Response => $this->client()->post(self::BASE."/payment_intents/{$referenceId}/cancel", []),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'Stripe');
        }

        return [
            'reference_id' => $referenceId,
            'status' => 'voided',
            'raw' => $response->json(),
        ];
    }

    public function verify(array $payload, array $headers): bool
    {
        if ($this->webhookSecret === '') {
            return false;
        }

        $sigHeader = self::header($headers, 'Stripe-Signature');
        if ($sigHeader === null) {
            return false;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $sigHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1' && is_string($value)) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }
        if (abs(time() - (int) $timestamp) > self::TIMESTAMP_TOLERANCE) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.self::canonicalJson($payload), $this->webhookSecret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Buat webhook bertanda tangan untuk pengujian / Test helper.
     *
     * @return array{payload:array,headers:array}
     */
    public function signForTest(array $event, ?int $timestamp = null): array
    {
        $ts = (string) ($timestamp ?? time());
        $signature = hash_hmac('sha256', $ts.'.'.self::canonicalJson($event), $this->webhookSecret);

        return [
            'payload' => $event,
            'headers' => ['Stripe-Signature' => "t={$ts},v1={$signature}"],
        ];
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        if (! $this->verify($payload, $headers)) {
            $this->webhookFailure('tanda tangan tidak valid / invalid signature');
        }

        $object = is_array($payload['data']['object'] ?? null) ? $payload['data']['object'] : [];
        $referenceId = (string) ($object['id'] ?? $payload['reference_id'] ?? '');
        $status = $object !== []
            ? self::mapStatus((string) ($object['status'] ?? ''))
            : self::mapStatus((string) ($payload['status'] ?? ''));
        $amount = null;
        if (isset($object['amount_received'], $object['currency'])) {
            $amount = self::fromMinor((int) $object['amount_received'], (string) $object['currency']);
        } elseif (isset($payload['amount'])) {
            $amount = (float) $payload['amount'];
        }

        return [
            'event_id' => (string) ($payload['id'] ?? ($object['id'] ?? '')),
            'reference_id' => $referenceId,
            'status' => $status,
            'amount' => $amount,
            'raw' => $payload,
        ];
    }

    public function getStatus(string $referenceId): array
    {
        $this->ensureEnabled('Stripe');

        $response = $this->send(
            fn (): Response => $this->client()->get(self::BASE."/payment_intents/{$referenceId}"),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'Stripe');
        }
        $intent = $response->json();
        $currency = strtoupper((string) ($intent['currency'] ?? 'USD'));

        return [
            'reference_id' => (string) ($intent['id'] ?? $referenceId),
            'status' => self::mapStatus((string) ($intent['status'] ?? '')),
            'amount' => isset($intent['amount_received'])
                ? self::fromMinor((int) $intent['amount_received'], $currency)
                : null,
            'raw' => $intent,
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
    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return $this->http()->asForm()->withBasicAuth($this->secret, '');
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function createIntent(array $payload, bool $manualCapture, bool $confirm, ?string $idempotencyKey = null): array
    {
        $currency = strtoupper((string) ($payload['currency'] ?? 'USD'));
        $reference = (string) ($payload['reference_id'] ?? 'stripe-'.bin2hex(random_bytes(4)));

        $body = [
            'amount' => self::toMinor((float) ($payload['amount'] ?? 0), $currency),
            'currency' => strtolower($currency),
            'capture_method' => $manualCapture ? 'manual' : 'automatic',
            'description' => (string) ($payload['description'] ?? "Pembayaran {$reference}"),
            'metadata[reference_id]' => $reference,
        ];
        if ($confirm) {
            $body['confirm'] = 'true';
        }
        if (isset($payload['return_url'])) {
            $body['return_url'] = (string) $payload['return_url'];
        }

        $client = $this->client();
        if ($idempotencyKey !== null) {
            $client->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        $response = $this->send(fn (): Response => $client->post(self::BASE.'/payment_intents', $body), $reference);
        if ($response->failed()) {
            $this->failForStatus($response, $reference, 'Stripe');
        }

        /** @var array<string,mixed> */
        return $response->json();
    }

    /** Petakan status PaymentIntent ke kanonis (3DS-aware). */
    public static function mapStatus(string $stripeStatus): string
    {
        return match (strtolower($stripeStatus)) {
            'succeeded' => 'paid',
            'requires_capture' => 'authorized',
            'requires_action', 'requires_source_action' => 'requires_action',
            'processing' => 'processing',
            'requires_confirmation' => 'pending',
            'requires_payment_method' => 'failed',
            'canceled' => 'voided',
            'paid' => 'paid',
            'refunded', 'partially_refunded' => 'refunded',
            'failed' => 'failed',
            default => 'pending',
        };
    }
}
