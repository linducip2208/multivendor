<?php

declare(strict_types=1);

namespace App\Payments\Providers;

use App\Payments\PaymentMethod;
use Illuminate\Http\Client\Response;

/**
 * Adapter Adyen Checkout — HTTP-native TANPA SDK.
 *
 * - /payments (create), /payments/{psp}/captures (≈ /capture),
 *   /payments/{psp}/refunds (≈ /refund), /payments/{psp}/cancels (void).
 * - Env test/live via config services.adyen.environment + live_prefix.
 * - Webhook: header HmacSignature (HMAC-SHA256 base64 atas canonicalJson
 *   payload tanpa field hmacSignature, kunci heksadesimal HMAC Adyen).
 * - Status 3DS: ChallengeShopper/IdentifyShopper/RedirectShopper →
 *   requires_action. Adyen bersifat webhook-driven; getStatus best-effort.
 * - Nonaktif bila api_key/merchant_account kosong.
 */
final class AdyenPaymentGateway extends AbstractIntlGateway
{
    public const CODE = 'adyen';

    private const TEST_BASE = 'https://checkout-test.adyen.com/v68';

    /** @var string[] */
    private const CURRENCIES = [
        'USD', 'EUR', 'GBP', 'JPY', 'AUD', 'CAD', 'SGD', 'IDR',
        'MYR', 'THB', 'PHP', 'INR', 'CHF', 'NZD', 'HKD', 'SEK', 'NOK', 'DKK', 'PLN',
    ];

    /** @var string[] */
    private const COUNTRIES = [
        'NL', 'BE', 'DE', 'FR', 'ES', 'IT', 'IE', 'AT', 'PT', 'FI',
        'GB', 'US', 'SG', 'MY', 'ID', 'AU', 'CA', 'JP', 'CH', 'NZ', 'HK', 'AE',
    ];

    /**
     * Jujur: kartu, transfer bank, wallet, direct debit (SEPA).
     * VA/QRIS/retail lokal TIDAK diklaim.
     *
     * @var string[]
     */
    private const METHODS = [
        PaymentMethod::CREDIT_CARD,
        PaymentMethod::BANK_TRANSFER,
        PaymentMethod::E_WALLET,
        PaymentMethod::DIRECT_DEBIT,
    ];

    private string $apiKey;

    private string $merchantAccount;

    private string $environment;

    private string $livePrefix;

    private string $hmacKey;

    /** @param array<string,mixed>|null $config Override; default config('services.adyen') + env. */
    public function __construct(?array $config = null)
    {
        $cfg = $config ?? (array) config('services.adyen', []);
        $this->apiKey = (string) ($cfg['api_key'] ?? env('ADYEN_API_KEY', ''));
        $this->merchantAccount = (string) ($cfg['merchant_account'] ?? env('ADYEN_MERCHANT_ACCOUNT', ''));
        $this->environment = strtolower((string) ($cfg['environment'] ?? env('ADYEN_ENV', 'test')));
        $this->livePrefix = (string) ($cfg['live_prefix'] ?? env('ADYEN_LIVE_PREFIX', ''));
        $this->hmacKey = (string) ($cfg['hmac_key'] ?? env('ADYEN_HMAC_KEY', ''));
        $this->timeoutSeconds = (int) ($cfg['timeout'] ?? 15);
    }

    public function getName(): string
    {
        return self::CODE;
    }

    public function isEnabled(): bool
    {
        return $this->apiKey !== '' && $this->merchantAccount !== '';
    }

    public function base(): string
    {
        if ($this->environment === 'live' && $this->livePrefix !== '') {
            return "https://{$this->livePrefix}-checkout-live.adyenpayments.com/checkout/v68";
        }

        return self::TEST_BASE;
    }

    public function initialize(array $payload): array
    {
        $this->guardContext($payload);
        $this->ensureEnabled('Adyen');

        $result = $this->startPayment($payload, false);
        $code = (string) ($result['resultCode'] ?? '');

        if (in_array($code, ['Refused', 'Error', 'Cancelled'], true)) {
            $this->declined((string) ($payload['reference_id'] ?? ''), ['adyen_result' => $code]);
        }

        $action = is_array($result['action'] ?? null) ? $result['action'] : [];

        return [
            'reference_id' => (string) ($result['pspReference'] ?? ($payload['reference_id'] ?? '')),
            'status' => self::mapResultCode($code),
            'redirect_url' => $action['url'] ?? null,
            'raw' => $result,
        ];
    }

    public function authorize(array $payload): array
    {
        $this->guardContext($payload);
        $this->ensureEnabled('Adyen');

        // Split capture memerlukan konfigurasi manual-capture di akun Adyen;
        // /payments melakukan otorisasi, capture() dipanggil terpisah.
        $result = $this->startPayment($payload, true);
        $code = (string) ($result['resultCode'] ?? '');

        if (in_array($code, ['Refused', 'Error', 'Cancelled'], true)) {
            $this->declined((string) ($payload['reference_id'] ?? ''), ['adyen_result' => $code]);
        }

        return [
            'reference_id' => (string) ($result['pspReference'] ?? ($payload['reference_id'] ?? '')),
            'status' => $code === 'Authorised' ? 'authorized' : self::mapResultCode($code),
            'raw' => $result,
        ];
    }

    public function capture(string $referenceId, array $options = []): array
    {
        $this->ensureEnabled('Adyen');

        $currency = strtoupper((string) ($options['currency'] ?? 'USD'));
        $response = $this->send(
            fn (): Response => $this->client()->post($this->base()."/payments/{$referenceId}/captures", [
                'merchantAccount' => $this->merchantAccount,
                'amount' => [
                    'currency' => $currency,
                    'value' => self::toMinor((float) ($options['amount'] ?? 0), $currency),
                ],
            ]),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'Adyen');
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
        $this->ensureEnabled('Adyen');

        $result = $this->startPayment($payload, false, $idempotencyKey);
        $code = (string) ($result['resultCode'] ?? '');

        if (in_array($code, ['Refused', 'Error', 'Cancelled'], true)) {
            $this->declined((string) ($result['pspReference'] ?? ($payload['reference_id'] ?? '')), ['adyen_result' => $code]);
        }

        $currency = strtoupper((string) ($payload['currency'] ?? 'USD'));

        return [
            'reference_id' => (string) ($result['pspReference'] ?? ($payload['reference_id'] ?? '')),
            'status' => self::mapResultCode($code),
            'amount' => (float) ($payload['amount'] ?? 0),
            'currency' => $currency,
            'raw' => $result,
        ];
    }

    public function refund(string $referenceId, float $amount, array $options = []): array
    {
        $this->ensureEnabled('Adyen');

        $currency = strtoupper((string) ($options['currency'] ?? 'USD'));
        $response = $this->send(
            fn (): Response => $this->client()->post($this->base()."/payments/{$referenceId}/refunds", [
                'merchantAccount' => $this->merchantAccount,
                'amount' => [
                    'currency' => $currency,
                    'value' => self::toMinor($amount, $currency),
                ],
                'reference' => (string) ($options['reference'] ?? "refund-{$referenceId}"),
            ]),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'Adyen');
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
        $this->ensureEnabled('Adyen');

        $response = $this->send(
            fn (): Response => $this->client()->post($this->base()."/payments/{$referenceId}/cancels", [
                'merchantAccount' => $this->merchantAccount,
            ]),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'Adyen');
        }

        return [
            'reference_id' => $referenceId,
            'status' => 'voided',
            'raw' => $response->json(),
        ];
    }

    public function verify(array $payload, array $headers): bool
    {
        if ($this->hmacKey === '') {
            return false;
        }

        $signature = self::header($headers, 'HmacSignature') ?? (string) ($payload['hmacSignature'] ?? '');
        if ($signature === '') {
            return false;
        }

        $unsigned = $payload;
        unset($unsigned['hmacSignature']);

        $key = $this->hmacKey;
        $binary = ctype_xdigit($key) && strlen($key) % 2 === 0 && $key !== ''
            ? (string) hex2bin($key)
            : $key;
        $expected = base64_encode(hash_hmac('sha256', self::canonicalJson($unsigned), $binary, true));

        return hash_equals($expected, $signature);
    }

    /**
     * Buat webhook bertanda tangan untuk pengujian / Test helper.
     *
     * @return array{payload:array,headers:array}
     */
    public function signForTest(array $event): array
    {
        $key = $this->hmacKey;
        $binary = ctype_xdigit($key) && strlen($key) % 2 === 0 && $key !== ''
            ? (string) hex2bin($key)
            : $key;
        $signature = base64_encode(hash_hmac('sha256', self::canonicalJson($event), $binary, true));

        return [
            'payload' => $event,
            'headers' => ['HmacSignature' => $signature],
        ];
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        if (! $this->verify($payload, $headers)) {
            $this->webhookFailure('tanda tangan tidak valid / invalid signature');
        }

        $item = $payload['notificationItems'][0]['NotificationRequestItem'] ?? null;
        if (! is_array($item)) {
            return [
                'event_id' => (string) ($payload['event_id'] ?? ''),
                'reference_id' => (string) ($payload['reference_id'] ?? ''),
                'status' => self::mapResultCode((string) ($payload['resultCode'] ?? $payload['status'] ?? '')),
                'amount' => isset($payload['amount']) ? (float) $payload['amount'] : null,
                'raw' => $payload,
            ];
        }

        $eventCode = strtoupper((string) ($item['eventCode'] ?? ''));
        $success = filter_var($item['success'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $status = match ($eventCode) {
            'AUTHORISATION' => $success ? 'paid' : 'failed',
            'CAPTURE' => $success ? 'captured' : 'failed',
            'REFUND' => $success ? 'refunded' : 'failed',
            'CANCEL' => $success ? 'voided' : 'failed',
            'REFUND_REVERSED' => 'failed',
            default => 'pending',
        };

        $amount = null;
        if (isset($item['amount']['value'], $item['amount']['currency'])) {
            $amount = self::fromMinor((int) $item['amount']['value'], (string) $item['amount']['currency']);
        }

        return [
            'event_id' => (string) (($item['pspReference'] ?? '').':'.($item['eventCode'] ?? '')),
            'reference_id' => (string) ($item['merchantReference'] ?? $item['pspReference'] ?? ''),
            'status' => $status,
            'amount' => $amount,
            'raw' => $payload,
        ];
    }

    public function getStatus(string $referenceId): array
    {
        $this->ensureEnabled('Adyen');

        // Best-effort: Adyen bersifat webhook-driven; endpoint ini dipakai
        // bila akun/MID menyediakannya, bila tidak → error jujur dari API.
        $response = $this->send(
            fn (): Response => $this->client()->get($this->base()."/payments/{$referenceId}"),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'Adyen');
        }
        $data = $response->json();

        return [
            'reference_id' => (string) ($data['pspReference'] ?? $referenceId),
            'status' => isset($data['resultCode']) ? self::mapResultCode((string) $data['resultCode']) : 'unknown',
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
        return $this->http()->withHeaders(['X-API-Key' => $this->apiKey]);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function startPayment(array $payload, bool $manualCapture, ?string $idempotencyKey = null): array
    {
        $currency = strtoupper((string) ($payload['currency'] ?? 'USD'));
        $reference = (string) ($payload['reference_id'] ?? 'adyen-'.bin2hex(random_bytes(4)));

        $body = [
            'merchantAccount' => $this->merchantAccount,
            'reference' => $reference,
            'amount' => [
                'currency' => $currency,
                'value' => self::toMinor((float) ($payload['amount'] ?? 0), $currency),
            ],
            'returnUrl' => (string) ($payload['return_url'] ?? $payload['success_url'] ?? ''),
        ];
        if (is_array($payload['payment_method'] ?? null) || is_array($payload['paymentMethod'] ?? null)) {
            $body['paymentMethod'] = $payload['payment_method'] ?? $payload['paymentMethod'];
        }
        if ($manualCapture) {
            // Otorisasi tanpa capture; capture() dipanggil terpisah.
            $body['captureDelayHours'] = 168;
            $body['manualCapture'] = true;
        }

        $client = $this->client();
        if ($idempotencyKey !== null) {
            $client->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        $response = $this->send(fn (): Response => $client->post($this->base().'/payments', $body), $reference);
        if ($response->failed()) {
            $this->failForStatus($response, $reference, 'Adyen');
        }

        /** @var array<string,mixed> */
        return $response->json();
    }

    public static function mapResultCode(string $resultCode): string
    {
        return match ($resultCode) {
            'Authorised' => 'paid',
            'Received' => 'pending',
            'RedirectShopper', 'IdentifyShopper', 'ChallengeShopper', 'PresentToShopper', 'Pending' => 'requires_action',
            'Refused', 'Error' => 'failed',
            'Cancelled' => 'voided',
            'paid' => 'paid',
            'refunded' => 'refunded',
            'failed' => 'failed',
            default => 'pending',
        };
    }
}
