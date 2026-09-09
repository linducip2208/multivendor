<?php

namespace App\Services\Payment;

use App\Models\Provider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenericRedirectAdapter implements PaymentAdapterInterface
{
    public function __construct(protected Provider $provider) {}

    public function createTransaction(array $payload): array
    {
        try {
            $response = Http::withHeaders(array_merge(['Content-Type' => 'application/json', 'Accept' => 'application/json'], $this->headers()))->post($this->provider->base_url.'/transaction', ['order_id' => $payload['order_id'], 'amount' => $payload['amount'], 'customer' => $payload['customer'] ?? [], 'items' => $payload['items'] ?? [], 'callback_url' => $payload['callback_url'] ?? '', 'return_url' => $payload['success_url'] ?? '']);
            if ($response->successful()) {
                $data = $response->json();

                return ['success' => true, 'redirect_url' => $data['redirect_url'] ?? $data['payment_url'] ?? null, 'raw' => $data];
            }

            return ['success' => false, 'message' => 'Gateway menolak permintaan pembayaran.'];
        } catch (\Throwable $e) {
            Log::error('Generic redirect payment failed', ['provider_id' => $this->provider->id, 'exception' => $e::class]);

            return ['success' => false, 'message' => 'Gateway tidak dapat dihubungi.'];
        }
    }

    public function getTransactionStatus(string $transactionId): array
    {
        return ['success' => false];
    }

    public function verifyCallback(array $requestData): bool
    {
        return false;
    }

    public function getChannels(): array
    {
        return [];
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->provider->getApiKeyAttribute(), 'X-API-Key' => $this->provider->getApiKeyAttribute()];
    }
}
