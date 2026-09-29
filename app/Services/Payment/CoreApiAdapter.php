<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Provider;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Throwable;

class CoreApiAdapter implements PaymentAdapterInterface
{
    private const TIMEOUT = 15;

    private const CONNECT_TIMEOUT = 5;

    public function __construct(protected Provider $provider) {}

    public function createTransaction(array $payload): array
    {
        $amount = Money::of($payload['amount'] ?? 0)->toFloat();
        $channel = $payload['channel'] ?? 'bank_transfer';

        $body = [
            'payment_type' => $channel,
            'transaction_details' => [
                'order_id' => $payload['order_id'] ?? uniqid('ORD-'),
                'gross_amount' => (int) $amount,
            ],
            'customer_details' => $payload['customer'] ?? [],
            'item_details' => $payload['items'] ?? [],
        ];

        if ($channel === 'bank_transfer') {
            $body['bank_transfer'] = ['bank' => $payload['bank'] ?? 'bca'];
        } elseif ($channel === 'echannel') {
            $body['echannel'] = ['bill_info1' => 'Payment', 'bill_info2' => 'Online'];
        } elseif ($channel === 'gopay') {
            $body['gopay'] = ['enable_callback' => true];
        }

        return $this->post('/charge', $body, function (array $data) {
            return [
                'success' => true,
                'va_number' => $data['va_numbers'][0]['va_number'] ?? $data['permata_va_number'] ?? $data['bill_key'] ?? null,
                'bank' => $data['va_numbers'][0]['bank'] ?? $data['bank'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'expiry' => $data['expiry_time'] ?? null,
                'raw' => $data,
            ];
        });
    }

    public function getTransactionStatus(string $transactionId): array
    {
        $reference = $this->reference($transactionId, []);

        if ($reference === null) {
            return ['success' => false, 'code' => 'unsupported', 'message' => 'Referensi transaksi tidak tersedia.'];
        }

        return $this->get("/transactions/{$reference}/status", function (array $data) {
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
        return ['bank_transfer', 'echannel', 'gopay', 'shopeepay', 'qris'];
    }

    public function refund(string $gatewayRefundId, float $amount, array $options = []): array
    {
        $reference = $this->reference($gatewayRefundId, $options);

        if ($reference === null) {
            return SnapRedirectAdapter::unsupported('Referensi transaksi gateway tidak tersedia untuk refund.');
        }

        return $this->post("/transactions/{$reference}/refund", [
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
            return SnapRedirectAdapter::unsupported('Referensi transaksi gateway tidak tersedia untuk pembatalan.');
        }

        return $this->post("/transactions/{$reference}/cancel", [
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

        if ($candidate === '' || str_starts_with($candidate, 'synthetic:')) {
            $candidate = trim((string) ($options['payment_number'] ?? ''));
        }

        if ($candidate === '' || str_starts_with($candidate, 'synthetic:')) {
            return null;
        }

        return rawurlencode($candidate);
    }

    protected function post(string $path, array $body, callable $onSuccess): array
    {
        $response = $this->request(fn () => Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)
            ->withBasicAuth((string) $this->provider->getApiKeyAttribute(), '')
            ->post(rtrim((string) $this->provider->base_url, '/').$path, $body));

        return $this->interpret($response, $onSuccess);
    }

    protected function get(string $path, callable $onSuccess): array
    {
        $response = $this->request(fn () => Http::withHeaders(['Accept' => 'application/json'])
            ->timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT)
            ->withBasicAuth((string) $this->provider->getApiKeyAttribute(), '')
            ->get(rtrim((string) $this->provider->base_url, '/').$path));

        return $this->interpret($response, $onSuccess);
    }

    protected function request(callable $callback): ?\Illuminate\Http\Client\Response
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            PaymentLog::channel('error', 'Midtrans Core request failed', [
                'provider_id' => $this->provider->id,
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    protected function interpret(?\Illuminate\Http\Client\Response $response, callable $onSuccess): array
    {
        if ($response === null) {
            return ['success' => false, 'code' => 'unreachable', 'message' => 'Midtrans Core tidak dapat dihubungi.'];
        }

        $data = $response->json();
        $data = is_array($data) ? $data : [];

        if ($response->successful()) {
            return $onSuccess($data);
        }

        PaymentLog::channel('warning', 'Midtrans Core rejected request', [
            'provider_id' => $this->provider->id,
            'status' => $response->status(),
            'body' => $data,
        ]);

        return [
            'success' => false,
            'code' => 'gateway_rejected',
            'message' => 'Midtrans Core menolak permintaan.',
            'status' => $response->status(),
        ];
    }
}
