<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Provider;
use App\Support\Money;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class SnapRedirectAdapter implements PaymentAdapterInterface
{
    private const TIMEOUT = 15;

    private const CONNECT_TIMEOUT = 5;

    public function __construct(protected Provider $provider) {}

    public function createTransaction(array $payload): array
    {
        $amount = Money::of($payload['amount'] ?? 0)->toFloat();
        $customer = $payload['customer'] ?? [];
        $items = $payload['items'] ?? [];

        $body = [
            'transaction_details' => [
                'order_id' => $payload['order_id'] ?? uniqid('ORD-'),
                'gross_amount' => (int) $amount,
            ],
            'customer_details' => $customer,
            'item_details' => $items,
            'enabled_payments' => $payload['channels'] ?? [],
        ];

        if (! empty($payload['callbacks'])) {
            $body['callbacks'] = $payload['callbacks'];
        }

        return $this->post('/transactions', $body, function (array $data) {
            return [
                'success' => true,
                'token' => $data['token'] ?? null,
                'redirect_url' => $data['redirect_url'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'raw' => $data,
            ];
        });
    }

    public function getTransactionStatus(string $transactionId): array
    {
        return $this->get("/transactions/{$transactionId}", function (array $data) {
            return ['success' => true, 'data' => $data];
        });
    }

    public function verifyCallback(array $requestData): bool
    {
        $body = $requestData['body'] ?? $requestData;
        $secret = $this->provider->getApiSecretAttribute();

        if (! $secret) {
            return false;
        }

        $signature = hash('sha512',
            (string) ($body['order_id'] ?? '').
            (string) ($body['status_code'] ?? '').
            (string) ($body['gross_amount'] ?? '').
            $secret
        );

        return hash_equals($signature, (string) ($body['signature_key'] ?? ''));
    }

    public function getChannels(): array
    {
        $config = $this->provider->config ?? [];

        return is_array($config) && is_array($config['channels'] ?? null) ? $config['channels'] : [];
    }

    public function refund(string $gatewayRefundId, float $amount, array $options = []): array
    {
        $reference = $this->reference($gatewayRefundId, $options);

        if ($reference === null) {
            return self::unsupported('Referensi transaksi gateway tidak tersedia untuk refund.');
        }

        return $this->post("/{$reference}/refund", [
            'refund_amount' => Money::of($amount)->toDecimal(),
            'refund_reason' => (string) ($options['reason'] ?? 'Permintaan refund pelanggan'),
        ], function (array $data) {
            return [
                'success' => true,
                'refund_id' => $data['refund_id'] ?? null,
                'status' => $data['refund_status'] ?? 'pending',
                'raw' => $data,
            ];
        });
    }

    public function cancel(string $gatewayPaymentId): array
    {
        $reference = $this->reference($gatewayPaymentId, []);

        if ($reference === null) {
            return self::unsupported('Referensi transaksi gateway tidak tersedia untuk pembatalan.');
        }

        return $this->post("/{$reference}/cancel", [
            'reason' => 'Pembatalan pesanan',
        ], function (array $data) {
            return [
                'success' => true,
                'status' => $data['transaction_status'] ?? 'cancel',
                'raw' => $data,
            ];
        });
    }

    protected function reference(string $identifier, array $options): ?string
    {
        $candidate = trim($identifier);
        if ($candidate === '') {
            $candidate = trim((string) ($options['payment_number'] ?? ''));
        }

        if ($candidate === '' || $this->isSynthetic($candidate)) {
            return null;
        }

        return rawurlencode($candidate);
    }

    protected function isSynthetic(string $reference): bool
    {
        return str_starts_with($reference, 'synthetic:');
    }

    protected function post(string $path, array $body, callable $onSuccess): array
    {
        $response = $this->request(fn () => Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)
            ->withBasicAuth((string) $this->provider->getApiKeyAttribute(), '')
            ->post($this->baseUrl().$path, $body));

        return $this->interpret($response, $onSuccess, 'Midtrans Snap');
    }

    protected function get(string $path, callable $onSuccess): array
    {
        $response = $this->request(fn () => Http::withHeaders(['Accept' => 'application/json'])
            ->timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)
            ->withBasicAuth((string) $this->provider->getApiKeyAttribute(), '')
            ->get($this->baseUrl().$path));

        return $this->interpret($response, $onSuccess, 'Midtrans Snap');
    }

    protected function baseUrl(): string
    {
        return rtrim((string) $this->provider->base_url, '/');
    }

    protected function request(callable $callback): ?Response
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            PaymentLog::channel('error', 'Midtrans Snap request failed', [
                'provider_id' => $this->provider->id,
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    protected function interpret(?Response $response, callable $onSuccess, string $gateway): array
    {
        if ($response === null) {
            return ['success' => false, 'code' => 'unreachable', 'message' => "{$gateway} tidak dapat dihubungi."];
        }

        $data = $response->json();
        $data = is_array($data) ? $data : [];

        if ($response->successful()) {
            return $onSuccess($data);
        }

        PaymentLog::channel('warning', 'Midtrans Snap rejected request', [
            'provider_id' => $this->provider->id,
            'status' => $response->status(),
            'body' => $data,
        ]);

        return [
            'success' => false,
            'code' => 'gateway_rejected',
            'message' => "{$gateway} menolak permintaan.",
            'status' => $response->status(),
        ];
    }

    public static function unsupported(string $message): array
    {
        return ['success' => false, 'code' => 'unsupported', 'message' => $message];
    }
}
