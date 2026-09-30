<?php

declare(strict_types=1);

namespace App\Payments\Providers;

use App\Payments\PaymentException;
use App\Payments\PaymentMethod;
use Illuminate\Http\Client\Response;

/**
 * Adapter Razorpay — HTTP-native TANPA SDK.
 *
 * REST: https://api.razorpay.com/v1 (Basic auth key_id:key_secret).
 * - POST /orders (create, payment_capture 1/0), POST /payments/{id}/capture,
 *   POST /payments/{id}/refund, GET /orders/{id} & GET /payments/{id}.
 * - Signature checkout: HMAC-SHA256 "order_id|payment_id" (key_secret),
 *   header X-Razorpay-Signature. Webhook event: HMAC atas canonicalJson
 *   dengan webhook_secret.
 * - Jujur: void() TIDAK didukung API Razorpay (otorisasi kedaluwarsa otomatis)
 *   → melempar PaymentException BI/EN yang jelas. capture()/refund()
 *   memakai payment id (pay_...), bukan order id.
 * - Fokus India; klaim currency/country dibatasi jujur.
 * - Nonaktif bila key_id/key_secret kosong.
 */
final class RazorpayPaymentGateway extends AbstractIntlGateway
{
    public const CODE = 'razorpay';

    private const BASE = 'https://api.razorpay.com/v1';

    /** @var string[] */
    private const CURRENCIES = ['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD'];

    /** @var string[] */
    private const COUNTRIES = ['IN', 'US', 'GB', 'AE', 'SG'];

    /**
     * Jujur: kartu, transfer/UPI bank, wallet, paylater/EMI (EMI → paylater).
     * QRIS/VA/retail lokal TIDAK diklaim.
     *
     * @var string[]
     */
    private const METHODS = [
        PaymentMethod::CREDIT_CARD,
        PaymentMethod::BANK_TRANSFER,
        PaymentMethod::E_WALLET,
        PaymentMethod::PAYLATER,
    ];

    private string $keyId;

    private string $keySecret;

    private string $webhookSecret;

    /** @param array<string,mixed>|null $config Override; default config('services.razorpay') + env. */
    public function __construct(?array $config = null)
    {
        $cfg = $config ?? (array) config('services.razorpay', []);
        $this->keyId = (string) ($cfg['key_id'] ?? env('RAZORPAY_KEY_ID', ''));
        $this->keySecret = (string) ($cfg['key_secret'] ?? env('RAZORPAY_KEY_SECRET', ''));
        $this->webhookSecret = (string) ($cfg['webhook_secret'] ?? env('RAZORPAY_WEBHOOK_SECRET', ''));
        $this->timeoutSeconds = (int) ($cfg['timeout'] ?? 15);
    }

    public function getName(): string
    {
        return self::CODE;
    }

    public function isEnabled(): bool
    {
        return $this->keyId !== '' && $this->keySecret !== '';
    }

    public function initialize(array $payload): array
    {
        $this->guardContext($payload);
        $this->ensureEnabled('Razorpay');

        $order = $this->createOrder($payload, true);

        return [
            'reference_id' => (string) ($order['id'] ?? ($payload['reference_id'] ?? '')),
            'status' => self::mapOrderStatus((string) ($order['status'] ?? '')),
            'raw' => $order,
        ];
    }

    public function authorize(array $payload): array
    {
        $this->guardContext($payload);
        $this->ensureEnabled('Razorpay');

        $order = $this->createOrder($payload, false);

        return [
            'reference_id' => (string) ($order['id'] ?? ($payload['reference_id'] ?? '')),
            'status' => 'authorized',
            'raw' => $order,
        ];
    }

    public function capture(string $referenceId, array $options = []): array
    {
        $this->ensureEnabled('Razorpay');

        $currency = strtoupper((string) ($options['currency'] ?? 'INR'));
        $response = $this->send(
            fn (): Response => $this->client()->post(self::BASE."/payments/{$referenceId}/capture", [
                'amount' => self::toMinor((float) ($options['amount'] ?? 0), $currency),
                'currency' => $currency,
            ]),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'Razorpay');
        }
        $payment = $response->json();

        if (((string) ($payment['status'] ?? '')) === 'failed') {
            $this->declined($referenceId, ['razorpay_status' => 'failed']);
        }

        return [
            'reference_id' => (string) ($payment['id'] ?? $referenceId),
            'status' => 'captured',
            'raw' => $payment,
        ];
    }

    public function charge(array $payload, ?string $idempotencyKey = null): array
    {
        $this->guardContext($payload);
        $this->ensureEnabled('Razorpay');

        // Jujur: order server-side; pelunasan lewat checkout + webhook.
        // payment_capture=1 agar otomatis capture saat dibayar.
        $order = $this->createOrder($payload, true);
        $currency = strtoupper((string) ($payload['currency'] ?? 'INR'));

        return [
            'reference_id' => (string) ($order['id'] ?? ($payload['reference_id'] ?? '')),
            'status' => self::mapOrderStatus((string) ($order['status'] ?? '')),
            'amount' => (float) ($payload['amount'] ?? 0),
            'currency' => $currency,
            'raw' => $order,
        ];
    }

    public function refund(string $referenceId, float $amount, array $options = []): array
    {
        $this->ensureEnabled('Razorpay');

        $currency = strtoupper((string) ($options['currency'] ?? 'INR'));
        $body = [];
        if ($amount > 0) {
            $body['amount'] = self::toMinor($amount, $currency);
        }

        $response = $this->send(
            fn (): Response => $this->client()->post(self::BASE."/payments/{$referenceId}/refund", $body),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'Razorpay');
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
        $this->ensureEnabled('Razorpay');

        // Jujur: Razorpay tidak menyediakan API void; otorisasi yang belum
        // di-capture dilepas otomatis oleh Razorpay.
        throw new PaymentException(
            'Razorpay tidak mendukung void eksplisit; otorisasi kedaluwarsa otomatis. / Razorpay does not support explicit void; authorizations expire automatically.',
            'Razorpay tidak mendukung void eksplisit.',
            'Razorpay does not support explicit void.',
            ['gateway' => self::CODE, 'reference_id' => $referenceId]
        );
    }

    public function verify(array $payload, array $headers): bool
    {
        if ($this->keySecret === '') {
            return false;
        }

        $signature = self::header($headers, 'X-Razorpay-Signature') ?? (string) ($payload['razorpay_signature'] ?? '');
        if ($signature === '') {
            return false;
        }

        // 1) Handler checkout: order_id|payment_id dengan key_secret.
        $orderId = (string) ($payload['razorpay_order_id'] ?? '');
        $paymentId = (string) ($payload['razorpay_payment_id'] ?? '');
        if ($orderId !== '' && $paymentId !== '') {
            $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $this->keySecret);

            return hash_equals($expected, $signature);
        }

        // 2) Event webhook: HMAC atas body dengan webhook_secret.
        if (isset($payload['event']) && $this->webhookSecret !== '') {
            $expected = hash_hmac('sha256', self::canonicalJson($payload), $this->webhookSecret);

            return hash_equals($expected, $signature);
        }

        return false;
    }

    /**
     * Tanda tangani handler checkout untuk pengujian / Test helper.
     *
     * @return array{payload:array,headers:array}
     */
    public function signCheckoutForTest(string $orderId, string $paymentId): array
    {
        return [
            'payload' => [
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $paymentId,
            ],
            'headers' => [
                'X-Razorpay-Signature' => hash_hmac('sha256', $orderId.'|'.$paymentId, $this->keySecret),
            ],
        ];
    }

    /**
     * Tanda tangani event webhook untuk pengujian / Test helper.
     *
     * @return array{payload:array,headers:array}
     */
    public function signEventForTest(array $event): array
    {
        return [
            'payload' => $event,
            'headers' => [
                'X-Razorpay-Signature' => hash_hmac('sha256', self::canonicalJson($event), $this->webhookSecret),
            ],
        ];
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        if (! $this->verify($payload, $headers)) {
            $this->webhookFailure('tanda tangan tidak valid / invalid signature');
        }

        // Handler checkout yang terverifikasi = pembayaran lunas.
        if (isset($payload['razorpay_order_id'], $payload['razorpay_payment_id'])) {
            return [
                'event_id' => (string) $payload['razorpay_payment_id'],
                'reference_id' => (string) $payload['razorpay_order_id'],
                'status' => 'paid',
                'amount' => isset($payload['amount']) ? (float) $payload['amount'] : null,
                'raw' => $payload,
            ];
        }

        $entity = $payload['payload']['payment']['entity'] ?? $payload['payload']['refund']['entity'] ?? [];
        $event = (string) ($payload['event'] ?? '');
        $status = match ($event) {
            'payment.captured', 'order.paid' => 'paid',
            'payment.authorized' => 'authorized',
            'payment.failed' => 'failed',
            'refund.processed', 'refund.created' => 'refunded',
            default => 'pending',
        };

        $amount = null;
        if (isset($entity['amount'], $entity['currency'])) {
            $amount = self::fromMinor((int) $entity['amount'], (string) $entity['currency']);
        }

        return [
            'event_id' => (string) ($payload['id'] ?? ($entity['id'] ?? '')),
            'reference_id' => (string) ($entity['order_id'] ?? $entity['id'] ?? ''),
            'status' => $status,
            'amount' => $amount,
            'raw' => $payload,
        ];
    }

    public function getStatus(string $referenceId): array
    {
        $this->ensureEnabled('Razorpay');

        $path = str_starts_with($referenceId, 'pay_') ? "/payments/{$referenceId}" : "/orders/{$referenceId}";
        $response = $this->send(
            fn (): Response => $this->client()->get(self::BASE.$path),
            $referenceId
        );
        if ($response->failed()) {
            $this->failForStatus($response, $referenceId, 'Razorpay');
        }
        $data = $response->json();
        $isPayment = str_starts_with($referenceId, 'pay_');

        return [
            'reference_id' => (string) ($data['id'] ?? $referenceId),
            'status' => $isPayment
                ? self::mapPaymentStatus((string) ($data['status'] ?? ''))
                : self::mapOrderStatus((string) ($data['status'] ?? '')),
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
        return $this->http()->withBasicAuth($this->keyId, $this->keySecret);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function createOrder(array $payload, bool $autoCapture): array
    {
        $currency = strtoupper((string) ($payload['currency'] ?? 'INR'));
        $reference = (string) ($payload['reference_id'] ?? 'rzp-'.bin2hex(random_bytes(4)));

        $response = $this->send(
            fn (): Response => $this->client()->post(self::BASE.'/orders', [
                'amount' => self::toMinor((float) ($payload['amount'] ?? 0), $currency),
                'currency' => $currency,
                'receipt' => $reference,
                'payment_capture' => $autoCapture ? 1 : 0,
            ]),
            $reference
        );
        if ($response->failed()) {
            $this->failForStatus($response, $reference, 'Razorpay');
        }

        /** @var array<string,mixed> */
        return $response->json();
    }

    public static function mapOrderStatus(string $status): string
    {
        return match (strtolower($status)) {
            'paid' => 'paid',
            'attempted', 'created' => 'pending',
            default => 'pending',
        };
    }

    public static function mapPaymentStatus(string $status): string
    {
        return match (strtolower($status)) {
            'captured' => 'paid',
            'authorized' => 'authorized',
            'created' => 'pending',
            'refunded' => 'refunded',
            'failed' => 'failed',
            default => 'pending',
        };
    }
}
