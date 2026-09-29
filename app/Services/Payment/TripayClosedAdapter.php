<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Provider;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Throwable;

class TripayClosedAdapter implements PaymentAdapterInterface
{
    private const TIMEOUT = 15;

    private const CONNECT_TIMEOUT = 5;

    public function __construct(protected Provider $provider) {}

    public function createTransaction(array $payload): array
    {
        $merchantRef = (string) ($payload['order_id'] ?? uniqid());
        $amount = Money::of($payload['amount'] ?? 0);
        $merchantCode = $this->merchantCode();

        $body = [
            'method' => $payload['channel'] ?? 'BRIVA',
            'merchant_ref' => $merchantRef,
            'amount' => $amount->toFloat(),
            'customer_name' => $payload['customer']['name'] ?? 'Customer',
            'customer_email' => $payload['customer']['email'] ?? '',
            'customer_phone' => $payload['customer']['phone'] ?? '',
            'order_items' => $payload['items'] ?? [],
            'return_url' => $payload['success_url'] ?? '',
            'callback_url' => $payload['callback_url'] ?? '',
            'signature' => $this->signature($merchantCode, $merchantRef, $amount),
        ];

        return $this->post($this->config('create_path', '/transaction/create'), $body, function (array $data) {
            return [
                'success' => true,
                'redirect_url' => $data['data']['checkout_url'] ?? null,
                'reference' => $data['data']['reference'] ?? null,
                'raw' => $data['data'] ?? $data,
            ];
        });
    }

    public function getTransactionStatus(string $transactionId): array
    {
        $reference = $this->reference($transactionId, []);

        if ($reference === null) {
            return ['success' => false, 'code' => 'unsupported', 'message' => 'Referensi Tripay tidak tersedia.'];
        }

        $response = $this->request(fn () => Http::withHeaders([
            'Authorization' => 'Bearer '.(string) $this->provider->getApiKeyAttribute(),
            'Accept' => 'application/json',
        ])->timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)
            ->get($this->baseUrl().'/transaction/detail?reference='.rawurlencode($reference)));

        if ($response === null) {
            return ['success' => false, 'code' => 'unreachable', 'message' => 'Tripay tidak dapat dihubungi.'];
        }

        if (! $response->successful()) {
            PaymentLog::channel('warning', 'Tripay rejected status request', [
                'provider_id' => $this->provider->id,
                'status' => $response->status(),
            ]);

            return ['success' => false, 'code' => 'gateway_rejected', 'message' => 'Tripay menolak permintaan status.'];
        }

        $data = $response->json();
        $data = is_array($data) ? $data : [];

        if (($data['success'] ?? false) !== true) {
            return ['success' => false, 'code' => 'gateway_rejected', 'message' => 'Referensi transaksi tidak ditemukan.'];
        }

        return ['success' => true, 'data' => $data['data'] ?? []];
    }

    public function verifyCallback(array $requestData): bool
    {
        $privateKey = $this->provider->getApiSecretAttribute();
        $callbackSignature = (string) ($requestData['headers']['x-callback-signature'] ?? '');
        $jsonBody = $requestData['raw'] ?? json_encode($requestData['body'] ?? [], JSON_UNESCAPED_SLASHES);

        if (! $privateKey || $callbackSignature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', (string) $jsonBody, $privateKey), $callbackSignature);
    }

    public function getChannels(): array
    {
        return ['BCAVA', 'BNIVA', 'BRIVA', 'MANDIRIVA', 'QRIS', 'GOPAY', 'OVO', 'DANA', 'SHOPEEPAY'];
    }

    public function refund(string $gatewayRefundId, float $amount, array $options = []): array
    {
        $reference = $this->reference($gatewayRefundId, $options);

        if ($reference === null) {
            return SnapRedirectAdapter::unsupported('Referensi Tripay tidak tersedia untuk refund.');
        }

        $amountMoney = Money::of($amount);
        $merchantCode = $this->merchantCode();
        $merchantRef = (string) ($options['merchant_ref'] ?? $reference);

        $body = [
            'merchant_ref' => $merchantRef,
            'reference' => $reference,
            'amount' => $amountMoney->toFloat(),
            'reason' => (string) ($options['reason'] ?? 'Permintaan refund pelanggan'),
            'signature' => $this->signature($merchantCode, $merchantRef, $amountMoney),
        ];

        return $this->post($this->config('refund_path', '/transaction/refund'), $body, function (array $data) {
            return [
                'success' => true,
                'refund_id' => $data['data']['reference'] ?? $data['reference'] ?? null,
                'status' => strtolower((string) ($data['data']['status'] ?? 'pending')),
                'raw' => $data['data'] ?? $data,
            ];
        });
    }

    public function cancel(string $gatewayPaymentId): array
    {
        $reference = $this->reference($gatewayPaymentId, []);

        if ($reference === null) {
            return SnapRedirectAdapter::unsupported('Referensi Tripay tidak tersedia untuk pembatalan.');
        }

        $response = $this->request(fn () => Http::withHeaders([
            'Authorization' => 'Bearer '.(string) $this->provider->getApiKeyAttribute(),
            'Content-Type' => 'application/json',
        ])->timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)
            ->post($this->baseUrl().$this->config('cancel_path', '/transaction/cancel'), [
                'reference' => $reference,
                'reason' => 'Pembatalan pesanan',
            ]));

        if ($response === null) {
            return ['success' => false, 'code' => 'unreachable', 'message' => 'Tripay tidak dapat dihubungi.'];
        }

        $data = $response->json();
        $data = is_array($data) ? $data : [];

        if ($response->successful() && ($data['success'] ?? false) === true) {
            return ['success' => true, 'status' => 'canceled', 'raw' => $data['data'] ?? $data];
        }

        PaymentLog::channel('warning', 'Tripay rejected cancel request', [
            'provider_id' => $this->provider->id,
            'status' => $response->status(),
            'body' => $data,
        ]);

        return ['success' => false, 'code' => 'gateway_rejected', 'message' => 'Tripay tidak mendukung pembatalan pembayaran ini.'];
    }

    protected function signature(string $merchantCode, string $merchantRef, Money $amount): string
    {
        return hash_hmac('sha256', $merchantCode.$merchantRef.$amount->toFloat(), (string) $this->provider->getApiSecretAttribute());
    }

    protected function merchantCode(): string
    {
        return (string) $this->config('merchant_code', '');
    }

    protected function config(string $key, string $default): string
    {
        $config = $this->provider->config;
        $value = is_array($config) ? ($config[$key] ?? null) : null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
    }

    protected function reference(string $identifier, array $options): ?string
    {
        $candidate = trim($identifier);

        if ($candidate === '' || str_starts_with($candidate, 'synthetic:')) {
            $candidate = trim((string) ($options['payment_number'] ?? ''));
        }

        if ($candidate === '' || str_starts_with($candidate, 'synthetic:')) {
            return null;
        }

        return $candidate;
    }

    protected function post(string $path, array $body, callable $onSuccess): array
    {
        $response = $this->request(fn () => Http::withHeaders([
            'Authorization' => 'Bearer '.(string) $this->provider->getApiKeyAttribute(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)
            ->post($this->baseUrl().$path, $body));

        if ($response === null) {
            return ['success' => false, 'code' => 'unreachable', 'message' => 'Tripay tidak dapat dihubungi.'];
        }

        $data = $response->json();
        $data = is_array($data) ? $data : [];

        if (! $response->successful()) {
            PaymentLog::channel('warning', 'Tripay rejected request', [
                'provider_id' => $this->provider->id,
                'status' => $response->status(),
                'body' => $data,
            ]);

            return ['success' => false, 'code' => 'gateway_rejected', 'message' => 'Tripay menolak permintaan.', 'status' => $response->status()];
        }

        if (($data['success'] ?? false) === true) {
            return $onSuccess($data);
        }

        PaymentLog::channel('warning', 'Tripay returned an error payload', [
            'provider_id' => $this->provider->id,
            'body' => $data,
        ]);

        return ['success' => false, 'code' => 'gateway_rejected', 'message' => 'Tripay menolak permintaan.'];
    }

    protected function baseUrl(): string
    {
        return rtrim((string) $this->provider->base_url, '/');
    }

    protected function request(callable $callback): ?\Illuminate\Http\Client\Response
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            PaymentLog::channel('error', 'Tripay request failed', [
                'provider_id' => $this->provider->id,
                'exception' => $e::class,
            ]);

            return null;
        }
    }
}
