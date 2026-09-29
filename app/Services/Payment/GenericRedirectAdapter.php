<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Provider;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Throwable;

class GenericRedirectAdapter implements PaymentAdapterInterface
{
    public function __construct(protected Provider $provider) {}

    public function createTransaction(array $payload): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(15)->connectTimeout(5)
                ->post(rtrim((string) $this->provider->base_url, '/').'/transaction', [
                    'order_id' => $payload['order_id'] ?? null,
                    'amount' => Money::of($payload['amount'] ?? 0)->toFloat(),
                    'customer' => $payload['customer'] ?? [],
                    'items' => $payload['items'] ?? [],
                    'callback_url' => $payload['callback_url'] ?? '',
                    'return_url' => $payload['success_url'] ?? '',
                ]);

            if ($response->successful()) {
                $data = is_array($response->json()) ? $response->json() : [];

                return [
                    'success' => true,
                    'redirect_url' => $data['redirect_url'] ?? $data['payment_url'] ?? null,
                    'raw' => $data,
                ];
            }

            PaymentLog::channel('warning', 'Generic redirect gateway rejected create', [
                'provider_id' => $this->provider->id,
                'status' => $response->status(),
            ]);

            return ['success' => false, 'code' => 'gateway_rejected', 'message' => 'Gateway menolak permintaan pembayaran.'];
        } catch (Throwable $e) {
            PaymentLog::channel('error', 'Generic redirect gateway unreachable', [
                'provider_id' => $this->provider->id,
                'exception' => $e::class,
            ]);

            return ['success' => false, 'code' => 'unreachable', 'message' => 'Gateway tidak dapat dihubungi.'];
        }
    }

    public function getTransactionStatus(string $transactionId): array
    {
        return ['success' => false, 'code' => 'unsupported', 'message' => 'Status transaksi tidak dapat diverifikasi.'];
    }

    public function verifyCallback(array $requestData): bool
    {
        return false;
    }

    public function getChannels(): array
    {
        return [];
    }

    public function refund(string $gatewayRefundId, float $amount, array $options = []): array
    {
        return SnapRedirectAdapter::unsupported('Gateway ini belum mendukung eksekusi refund.');
    }

    public function cancel(string $gatewayPaymentId): array
    {
        return SnapRedirectAdapter::unsupported('Gateway ini belum mendukung pembatalan pembayaran.');
    }

    private function headers(): array
    {
        $key = (string) $this->provider->getApiKeyAttribute();

        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$key,
            'X-API-Key' => $key,
        ];
    }
}
