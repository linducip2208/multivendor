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
            $response = Http::withHeaders(array_merge([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ], $this->buildAuthHeaders()))
            ->post($this->provider->base_url . '/transaction', $this->buildPayload($payload));

            if ($response->successful()) {
                $data = $response->json();
                return ['success' => true, 'redirect_url' => $data['redirect_url'] ?? $data['payment_url'] ?? null, 'raw' => $data];
            }
            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('GenericRedirectAdapter error', ['error' => $e->getMessage(), 'format' => $this->provider->api_format]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getTransactionStatus(string $transactionId): array
    {
        try {
            $response = Http::withHeaders($this->buildAuthHeaders())
                ->get($this->provider->base_url . "/transaction/{$transactionId}");

            return $response->successful()
                ? ['success' => true, 'data' => $response->json()]
                : ['success' => false];
        } catch (\Exception $e) {
            return ['success' => false];
        }
    }

    public function verifyCallback(array $requestData): bool
    {
        return true;
    }

    public function getChannels(): array
    {
        return [];
    }

    protected function buildAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->provider->getApiKeyAttribute(),
            'X-API-Key' => $this->provider->getApiKeyAttribute(),
        ];
    }

    protected function buildPayload(array $payload): array
    {
        return [
            'order_id' => $payload['order_id'],
            'amount' => $payload['amount'],
            'customer' => $payload['customer'] ?? [],
            'items' => $payload['items'] ?? [],
            'callback_url' => $payload['callback_url'] ?? '',
            'return_url' => $payload['success_url'] ?? '',
        ];
    }
}

class GenericApiAdapter implements PaymentAdapterInterface
{
    public function __construct(protected Provider $provider) {}

    public function createTransaction(array $payload): array
    {
        try {
            $response = Http::withHeaders(array_merge([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->provider->getApiKeyAttribute(),
            ], $this->provider->extra_headers ?? []))
            ->post($this->provider->base_url . '/transaction/create', [
                'order_id' => $payload['order_id'],
                'amount' => $payload['amount'],
                'customer' => $payload['customer'] ?? [],
                'items' => $payload['items'] ?? [],
                'callback_url' => $payload['callback_url'] ?? '',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'redirect_url' => $data['redirect_url'] ?? $data['payment_url'] ?? null,
                    'va_number' => $data['va_number'] ?? null,
                    'raw' => $data,
                ];
            }
            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('GenericApiAdapter error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getTransactionStatus(string $transactionId): array
    {
        return ['success' => false];
    }
    public function verifyCallback(array $requestData): bool { return true; }
    public function getChannels(): array { return []; }
}
