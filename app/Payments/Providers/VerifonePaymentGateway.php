<?php

declare(strict_types=1);

namespace App\Payments\Providers;

use App\Payments\PaymentException;
use App\Payments\PaymentMethod;
use Illuminate\Http\Client\Response;

/**
 * Adapter Verifone — HTTP-native TANPA SDK.
 *
 * Kapabilitas JUJUR:
 * - Inti: init (hosted/redirect), return handling, capture, status, webhook HMAC.
 * - refund/void TERGANTUNG produk & konfigurasi akun Verifone (Central vs
 *   Hosted Cart): refund() melempar PaymentException BI/EN yang jelas bila
 *   config supports_refund=false, bukan berpura-pura berhasil.
 * - Klaim sempit dan jujur: kartu + transfer bank, USD/EUR/GBP, pasar
 *   US/EU/UK. VA/QRIS/e-wallet lokal TIDAK diklaim.
 * - base_url dapat dioverride karena endpoint berbeda antar produk Verifone.
 * - Nonaktif bila api_key/merchant_code kosong.
 */
final class VerifonePaymentGateway extends AbstractIntlGateway
{
    public const CODE = 'verifone';

    private const TEST_BASE = 'https://api-test.verifone.com/v1';

    private const LIVE_BASE = 'https://api.verifone.com/v1';

    private const TIMESTAMP_TOLERANCE = 300;

    /** @var string[] */
    private const CURRENCIES = ['USD', 'EUR', 'GBP'];

    /** @var string[] */
    private const COUNTRIES = ['US', 'GB', 'DE', 'FR', 'NL', 'ES', 'IT', 'IE', 'AT', 'BE'];

    /**
     * Jujur: gerbang akuisisi berfokus kartu (+ transfer bank).
     * E-wallet/paylater/VA/QRIS/retail TIDAK diklaim.
     *
     * @var string[]
     */
    private const METHODS = [
        PaymentMethod::CREDIT_CARD,
        PaymentMethod::BANK_TRANSFER,
    ];

    private string $apiKey;

    private string $merchantCode;

    private string $environment;

    private string $baseUrl;

    private string $webhookSecret;

    private bool $supportsRefund;

    /** @param array<string,mixed>|null $config Override; default config('services.verifone') + env. */
    public function __construct(?array $config = null)
    {
        $cfg = $config ?? (array) config('services.verifone', []);
        $this->apiKey = (string) ($cfg['api_key'] ?? env('VERIFONE_API_KEY', ''));
        $this->merchantCode = (string) ($cfg['merchant_code'] ?? $cfg['entity_id'] ?? env('VERIFONE_MERCHANT_CODE', env('VERIFONE_ENTITY_ID', '')));
        $this->environment = strtolower((string) ($cfg['environment'] ?? env('VERIFONE_ENV', 'test')));
        $this->baseUrl = (string) ($cfg['base_url'] ?? '');
        $this->webhookSecret = (string) ($cfg['webhook_secret'] ?? env('VERIFONE_WEBHOOK_SECRET', ''));
        $this->supportsRefund = filter_var($cfg['supports_refund'] ?? env('VERIFONE_SUPPORTS_REFUND', true), FILTER_VALIDATE_BOOLEAN);
        $this->timeoutSeconds = (int) ($cfg['timeout'] ?? 15);
    }

    public function getName(): string
    {
        return self::CODE;
    }

    public function isEnabled(): bool
    {
        return $this->apiKey !== '' && $this->merchantCode !== '';
    }

    public function base(): string
    {
        if ($this->baseUrl !== '') {
            return rtrim($this->baseUrl, '/');
        }

        return $this->environment === 'live' ? self::LIVE_BASE : self::TEST_BASE;
    }

    public function initialize(array $payload): array
    {
        $this->guardContext($payload);
        $this->ensureEnabled('Verifone');

        $reference = (string) ($payload['reference_id'] ?? 'vf-'.bin2hex(random_bytes(4)));
        $currency = strtoupper((string) ($payload['currency'] ?? 'USD'));

        $response = $this->send(
            fn (): Response => $this->client()->post($this->base().'/payments/init', [
                'merchantCode' => $this->merchantCode,
                'reference' => $reference,
                'amount' => [
                    'currency' => $currency,
                    'value' => self::money((float) ($payload['amount'] ?? 0)),
                ],
                'returnUrl' => (string) ($payload['return_url'] ?? $payload['success_url'] ?? ''),
                'webhookUrl' => (string) ($payload['webhook_url'] ?? ''),
                'description' => (string) ($payload['description'] ?? "Pembayaran {$reference}"),
            ]),
            $reference
        );
        if ($response->failed()) {
            $this->failForStatus($response, $reference, 'Verifone');
        }
        $data = $response->json();

        return [
            'reference_id' => (string) ($data['transactionId'] ?? $reference),
            'status' => self::mapStatus((string) ($data['status'] ?? 'INITIATED')),
            'redirect_url' => $data['redirectUrl'] ?? null,
            'raw' => $data,
        ];
    }

    public function authorize(array $payload): array
    {
        $this->guardContext($payload);
        $this->ensureEnabled('Verifone');

        $reference = (string) ($payload['reference_id'] ?? 'vf-'.bin2hex(random_bytes(4)));
        $currency = strtoupper((string) ($payload['currency'] ?? 'USD'));

        $response = $this->send(
            fn (): Response => $this->client()->post($this->base().'/payments/authorize', [
                'merchantCode' => $this->merchantCode,
                'reference' => $reference,
                'amount' => [
                    'currency' => $currency,
                    'value' => self::money((float) ($payload['amount'] ?? 0)),
                ],
            ]),
            $reference
        );
        if ($response->failed()) {
            $this->failForStatus($response, $reference, 'Verifone');
        }
        $data = $response->json();

        return [
            'reference_id' => (string) ($data['transactionId'] ?? $reference),
            'status' => 'authorized',
            'raw' => $data,
        ];
    }

    public function capture(string $referenceId, array $options = []): array
    {
        $this->ensureEnabled('Verifone');

        $body = ['merchantCode' => $this->merchantCode, 'transactionId' => $referenceId];
        if (isset($options['amount'], $options['currency'])) {
            $body['amount'] = [
                'currency' => strtoupper((string) $options['currency']),
                'value' => self::money((float) $options['amount']),
            ];
        }

        $response = $this->send(
            fn (): Response => $this->client()->post($this->base().'/payments/capture', $body),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'Verifone');
        }

        return [
            'reference_id' => $referenceId,
            'status' => 'captured',
            'raw' => $response->json(),
        ];
    }

    public function charge(array $payload, ?string $idempotencyKey = null): array
    {
        $this->guardContext($payload);
        $this->ensureEnabled('Verifone');

        // Jujur: alur hosted/redirect; penjualan langsung hanya bila produk
        // mengembalikannya (status SALE/CAPTURED), selebihnya pending.
        $init = $this->initialize($payload);

        $status = $init['status'];
        if (in_array($status, ['failed'], true)) {
            $this->declined($init['reference_id'], ['verifone_status' => $status]);
        }

        return $init + [
            'amount' => (float) ($payload['amount'] ?? 0),
            'currency' => strtoupper((string) ($payload['currency'] ?? 'USD')),
        ];
    }

    public function refund(string $referenceId, float $amount, array $options = []): array
    {
        $this->ensureEnabled('Verifone');

        if (! $this->supportsRefund) {
            throw new PaymentException(
                'Refund tidak didukung oleh konfigurasi Verifone ini. / Refunds are not supported by this Verifone configuration.',
                'Refund tidak didukung oleh konfigurasi Verifone ini.',
                'Refunds are not supported by this Verifone configuration.',
                ['gateway' => self::CODE, 'reference_id' => $referenceId]
            );
        }

        $body = [
            'merchantCode' => $this->merchantCode,
            'transactionId' => $referenceId,
            'amount' => [
                'currency' => strtoupper((string) ($options['currency'] ?? 'USD')),
                'value' => self::money($amount),
            ],
        ];

        $response = $this->send(
            fn (): Response => $this->client()->post($this->base().'/payments/refund', $body),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'Verifone');
        }

        return [
            'reference_id' => $referenceId,
            'status' => 'refunded',
            'amount' => $amount,
            'raw' => $response->json(),
        ];
    }

    public function void(string $referenceId, array $options = []): array
    {
        $this->ensureEnabled('Verifone');

        $response = $this->send(
            fn (): Response => $this->client()->post($this->base().'/payments/void', [
                'merchantCode' => $this->merchantCode,
                'transactionId' => $referenceId,
            ]),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'Verifone');
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

        $signature = self::header($headers, 'X-Verifone-Signature');
        $timestamp = self::header($headers, 'X-Timestamp');
        if ($signature === null || $timestamp === null) {
            return false;
        }
        if (abs(time() - (int) $timestamp) > self::TIMESTAMP_TOLERANCE) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.self::canonicalJson($payload), $this->webhookSecret);

        return hash_equals($expected, $signature);
    }

    /**
     * Buat webhook bertanda tangan untuk pengujian / Test helper.
     *
     * @return array{payload:array,headers:array}
     */
    public function signForTest(array $event, ?int $timestamp = null): array
    {
        $ts = (string) ($timestamp ?? time());

        return [
            'payload' => $event,
            'headers' => [
                'X-Verifone-Signature' => hash_hmac('sha256', $ts.'.'.self::canonicalJson($event), $this->webhookSecret),
                'X-Timestamp' => $ts,
            ],
        ];
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        if (! $this->verify($payload, $headers)) {
            $this->webhookFailure('tanda tangan tidak valid / invalid signature');
        }

        return [
            'event_id' => (string) ($payload['eventId'] ?? $payload['event_id'] ?? ''),
            'reference_id' => (string) ($payload['transactionId'] ?? $payload['reference_id'] ?? ''),
            'status' => self::mapStatus((string) ($payload['status'] ?? '')),
            'amount' => isset($payload['amount']['value'])
                ? (float) $payload['amount']['value']
                : (isset($payload['amount']) && is_numeric($payload['amount']) ? (float) $payload['amount'] : null),
            'raw' => $payload,
        ];
    }

    public function getStatus(string $referenceId): array
    {
        $this->ensureEnabled('Verifone');

        $response = $this->send(
            fn (): Response => $this->client()->get($this->base()."/payments/{$referenceId}"),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'Verifone');
        }
        $data = $response->json();

        return [
            'reference_id' => (string) ($data['transactionId'] ?? $referenceId),
            'status' => self::mapStatus((string) ($data['status'] ?? '')),
            'raw' => $data,
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
        return $this->http()->withToken($this->apiKey);
    }

    private static function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    public static function mapStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'SALE', 'CAPTURED', 'SETTLED', 'PAID' => 'paid',
            'AUTHORIZED' => 'authorized',
            'INITIATED', 'PENDING', 'REDIRECTED' => 'pending',
            'REFUNDED' => 'refunded',
            'VOIDED', 'CANCELLED', 'CANCELED' => 'voided',
            'FAILED', 'DECLINED', 'ERROR' => 'failed',
            default => 'pending',
        };
    }
}
