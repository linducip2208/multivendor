<?php

declare(strict_types=1);

namespace App\Payments\Providers;

use App\Payments\PaymentMethod;
use Illuminate\Http\Client\Response;

/**
 * Adapter Mollie — HTTP-native TANPA SDK.
 *
 * - POST /v2/payments (create, alur redirect), GET /v2/payments/{id} (sync),
 *   POST /v2/payments/{id}/captures, POST /v2/payments/{id}/refunds,
 *   DELETE /v2/payments/{id} (void/cancel).
 * - Mollie berfokus Eropa; klaim currency/country dibatasi jujur ke cakupan EU.
 * - Webhook: Mollie TIDAK menandatangani callback (hanya form id=tr_...);
 *   verify() memastikan id ada, handleWebhook() lalu sinkronisasi server-side
 *   via GET /payments/{id} sebagai verifikasi sebenarnya.
 * - Nonaktif bila api_key kosong.
 */
final class MolliePaymentGateway extends AbstractIntlGateway
{
    public const CODE = 'mollie';

    private const BASE = 'https://api.mollie.com/v2';

    /**
     * Jujur: cakupan inti Mollie (EUR + EU). USD/GBP/CHF/Nordik didukung
     * terbatas; IDR/Asia TIDAK diklaim.
     *
     * @var string[]
     */
    private const CURRENCIES = [
        'EUR', 'USD', 'GBP', 'CHF', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF',
    ];

    /** @var string[] */
    private const COUNTRIES = [
        'NL', 'BE', 'DE', 'FR', 'ES', 'IT', 'AT', 'IE', 'PT',
        'GB', 'PL', 'CZ', 'DK', 'SE', 'NO', 'FI', 'CH',
    ];

    /**
     * Jujur: kartu, transfer bank (iDEAL dkk), direct debit, wallet.
     * QRIS/VA/retail lokal TIDAK diklaim.
     *
     * @var string[]
     */
    private const METHODS = [
        PaymentMethod::CREDIT_CARD,
        PaymentMethod::BANK_TRANSFER,
        PaymentMethod::DIRECT_DEBIT,
        PaymentMethod::E_WALLET,
    ];

    private string $apiKey;

    private string $defaultRedirectUrl;

    private string $defaultWebhookUrl;

    /** @param array<string,mixed>|null $config Override; default config('services.mollie') + env. */
    public function __construct(?array $config = null)
    {
        $cfg = $config ?? (array) config('services.mollie', []);
        $this->apiKey = (string) ($cfg['api_key'] ?? $cfg['key'] ?? env('MOLLIE_API_KEY', env('MOLLIE_KEY', '')));
        $this->defaultRedirectUrl = (string) ($cfg['redirect_url'] ?? env('MOLLIE_REDIRECT_URL', ''));
        $this->defaultWebhookUrl = (string) ($cfg['webhook_url'] ?? env('MOLLIE_WEBHOOK_URL', ''));
        $this->timeoutSeconds = (int) ($cfg['timeout'] ?? 15);
    }

    public function getName(): string
    {
        return self::CODE;
    }

    public function isEnabled(): bool
    {
        return $this->apiKey !== '';
    }

    public function initialize(array $payload): array
    {
        $this->guardContext($payload);
        $this->ensureEnabled('Mollie');

        $payment = $this->createPayment($payload, false);

        return [
            'reference_id' => (string) ($payment['id'] ?? ($payload['reference_id'] ?? '')),
            'status' => self::mapStatus((string) ($payment['status'] ?? '')),
            'redirect_url' => $payment['_links']['checkout']['href'] ?? null,
            'raw' => $payment,
        ];
    }

    public function authorize(array $payload): array
    {
        $this->guardContext($payload);
        $this->ensureEnabled('Mollie');

        // Best-effort: manual capture didukung untuk metode kartu tertentu.
        $payment = $this->createPayment($payload, true);

        return [
            'reference_id' => (string) ($payment['id'] ?? ($payload['reference_id'] ?? '')),
            'status' => self::mapStatus((string) ($payment['status'] ?? '')),
            'redirect_url' => $payment['_links']['checkout']['href'] ?? null,
            'raw' => $payment,
        ];
    }

    public function capture(string $referenceId, array $options = []): array
    {
        $this->ensureEnabled('Mollie');

        $body = [];
        if (isset($options['amount'], $options['currency'])) {
            $body['amount'] = [
                'currency' => strtoupper((string) $options['currency']),
                'value' => self::money((float) $options['amount']),
            ];
        }
        if (isset($options['description'])) {
            $body['description'] = (string) $options['description'];
        }

        $response = $this->send(
            fn (): Response => $this->client()->post(self::BASE."/payments/{$referenceId}/captures", $body),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'Mollie');
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
        $this->ensureEnabled('Mollie');

        // Jujur: Mollie adalah alur redirect; charge() membuat payment dan
        // mengembalikan pending + redirect_url, pelunasan via return/webhook.
        $payment = $this->createPayment($payload, false, $idempotencyKey);
        $status = (string) ($payment['status'] ?? '');

        if (in_array($status, ['failed', 'canceled', 'expired'], true)) {
            $this->declined((string) ($payment['id'] ?? ($payload['reference_id'] ?? '')), ['mollie_status' => $status]);
        }

        $currency = strtoupper((string) ($payment['amount']['currency'] ?? $payload['currency'] ?? 'EUR'));

        return [
            'reference_id' => (string) ($payment['id'] ?? ($payload['reference_id'] ?? '')),
            'status' => self::mapStatus($status),
            'amount' => isset($payment['amount']['value']) ? (float) $payment['amount']['value'] : (float) ($payload['amount'] ?? 0),
            'currency' => $currency,
            'redirect_url' => $payment['_links']['checkout']['href'] ?? null,
            'raw' => $payment,
        ];
    }

    public function refund(string $referenceId, float $amount, array $options = []): array
    {
        $this->ensureEnabled('Mollie');

        $body = [];
        if ($amount > 0) {
            $body['amount'] = [
                'currency' => strtoupper((string) ($options['currency'] ?? 'EUR')),
                'value' => self::money($amount),
            ];
        }
        if (isset($options['description'])) {
            $body['description'] = (string) $options['description'];
        }

        $response = $this->send(
            fn (): Response => $this->client()->post(self::BASE."/payments/{$referenceId}/refunds", $body),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'Mollie');
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
        $this->ensureEnabled('Mollie');

        $response = $this->send(
            fn (): Response => $this->client()->delete(self::BASE."/payments/{$referenceId}"),
            $referenceId
        );
        if ($response->failed() && $response->status() !== 204) {
            $this->failForStatus($response, $referenceId, 'Mollie');
        }

        return [
            'reference_id' => $referenceId,
            'status' => 'voided',
            'raw' => $response->status() === 204 ? [] : $response->json(),
        ];
    }

    public function verify(array $payload, array $headers): bool
    {
        // Jujur: Mollie tidak menandatangani webhook; satu-satunya sinyal
        // adalah id payment (tr_...). Verifikasi sebenarnya = GET status
        // server-side di handleWebhook().
        $id = (string) ($payload['id'] ?? '');

        return $id !== '' && str_starts_with($id, 'tr_');
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        if (! $this->verify($payload, $headers)) {
            $this->webhookFailure('id payment tidak ada / missing payment id');
        }

        // Sinkronisasi status mutakhir dari sisi Mollie (anti-spoofing).
        $payment = $this->fetchPayment((string) $payload['id']);

        return [
            'event_id' => (string) ($payload['id'] ?? ''),
            'reference_id' => (string) ($payment['id'] ?? $payload['id'] ?? ''),
            'status' => self::mapStatus((string) ($payment['status'] ?? '')),
            'amount' => isset($payment['amount']['value']) ? (float) $payment['amount']['value'] : null,
            'raw' => $payment,
        ];
    }

    public function getStatus(string $referenceId): array
    {
        $this->ensureEnabled('Mollie');

        $payment = $this->fetchPayment($referenceId);

        return [
            'reference_id' => (string) ($payment['id'] ?? $referenceId),
            'status' => self::mapStatus((string) ($payment['status'] ?? '')),
            'amount' => isset($payment['amount']['value']) ? (float) $payment['amount']['value'] : null,
            'raw' => $payment,
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

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function createPayment(array $payload, bool $manualCapture, ?string $idempotencyKey = null): array
    {
        $currency = strtoupper((string) ($payload['currency'] ?? 'EUR'));
        $reference = (string) ($payload['reference_id'] ?? 'mollie-'.bin2hex(random_bytes(4)));

        $body = [
            'amount' => [
                'currency' => $currency,
                'value' => self::money((float) ($payload['amount'] ?? 0)),
            ],
            'description' => (string) ($payload['description'] ?? "Pembayaran {$reference}"),
            'redirectUrl' => (string) ($payload['redirect_url'] ?? $payload['return_url'] ?? $this->defaultRedirectUrl),
            'metadata' => ['reference_id' => $reference],
        ];
        $webhookUrl = (string) ($payload['webhook_url'] ?? $this->defaultWebhookUrl);
        if ($webhookUrl !== '') {
            $body['webhookUrl'] = $webhookUrl;
        }
        $mollieMethod = self::mapMethod($payload['method'] ?? $payload['payment_method'] ?? null);
        if ($mollieMethod !== null) {
            $body['method'] = $mollieMethod;
        }
        if ($manualCapture) {
            $body['captureMode'] = 'manual';
        }

        $client = $this->client();
        if ($idempotencyKey !== null) {
            $client->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        $response = $this->send(fn (): Response => $client->post(self::BASE.'/payments', $body), $reference);
        if ($response->failed()) {
            $this->failForStatus($response, $reference, 'Mollie');
        }

        /** @var array<string,mixed> */
        return $response->json();
    }

    /** @return array<string,mixed> */
    private function fetchPayment(string $paymentId): array
    {
        $this->ensureEnabled('Mollie');

        $response = $this->send(
            fn (): Response => $this->client()->get(self::BASE."/payments/{$paymentId}"),
            $paymentId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $paymentId, 'Mollie');
        }

        /** @var array<string,mixed> */
        return $response->json();
    }

    private static function mapMethod(mixed $method): ?string
    {
        if ($method === null) {
            return null;
        }

        return match (PaymentMethod::normalize((string) $method)) {
            PaymentMethod::CREDIT_CARD => 'creditcard',
            PaymentMethod::BANK_TRANSFER => 'banktransfer',
            PaymentMethod::DIRECT_DEBIT => 'directdebit',
            default => null,
        };
    }

    private static function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    public static function mapStatus(string $mollieStatus): string
    {
        return match (strtolower($mollieStatus)) {
            'paid' => 'paid',
            'authorized' => 'authorized',
            'open', 'pending' => 'pending',
            'canceled' => 'voided',
            'expired' => 'expired',
            'failed' => 'failed',
            default => 'pending',
        };
    }
}
