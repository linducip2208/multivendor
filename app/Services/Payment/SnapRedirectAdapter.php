<?php

namespace App\Services\Payment;

use App\Models\Provider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SnapRedirectAdapter implements PaymentAdapterInterface
{
    public function __construct(protected Provider $provider) {}

    public function createTransaction(array $payload): array
    {
        $orderId = $payload['order_id'] ?? uniqid('ORD-');
        $amount = $payload['amount'] ?? 0;
        $customer = $payload['customer'] ?? [];
        $items = $payload['items'] ?? [];

        $body = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $amount,
            ],
            'customer_details' => $customer,
            'item_details' => $items,
            'enabled_payments' => $payload['channels'] ?? [],
        ];

        if (!empty($payload['callbacks'])) {
            $body['callbacks'] = $payload['callbacks'];
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->withBasicAuth($this->provider->getApiKeyAttribute(), '')
              ->post($this->provider->base_url . '/transactions', $body);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'token' => $data['token'] ?? null,
                    'redirect_url' => $data['redirect_url'] ?? null,
                    'raw' => $data,
                ];
            }

            Log::error('SnapRedirectAdapter error', ['response' => $response->body()]);
            return ['success' => false, 'message' => 'Payment gateway error: ' . $response->status()];
        } catch (\Exception $e) {
            Log::error('SnapRedirectAdapter exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getTransactionStatus(string $transactionId): array
    {
        try {
            $response = Http::withBasicAuth($this->provider->getApiKeyAttribute(), '')
                ->get($this->provider->base_url . "/transactions/{$transactionId}");

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }
            return ['success' => false, 'message' => 'Failed to get status'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function verifyCallback(array $requestData): bool
    {
        $signatureKey = hash('sha512',
            $requestData['order_id'] .
            $requestData['status_code'] .
            ($requestData['gross_amount'] ?? '') .
            $this->provider->getApiSecretAttribute()
        );

        return ($signatureKey === ($requestData['signature_key'] ?? ''));
    }

    public function getChannels(): array
    {
        $config = $this->provider->config ?? [];
        return $config['channels'] ?? [];
    }
}
