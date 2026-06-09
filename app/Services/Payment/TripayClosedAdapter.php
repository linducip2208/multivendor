<?php

namespace App\Services\Payment;

use App\Models\Provider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TripayClosedAdapter implements PaymentAdapterInterface
{
    public function __construct(protected Provider $provider) {}

    public function createTransaction(array $payload): array
    {
        $merchantCode = $this->provider->config['merchant_code'] ?? '';
        $apiKey = $this->provider->getApiKeyAttribute();
        $privateKey = $this->provider->getApiSecretAttribute();
        $merchantRef = $payload['order_id'] ?? uniqid();

        $body = [
            'method' => $payload['channel'] ?? 'BRIVA',
            'merchant_ref' => $merchantRef,
            'amount' => (int) $payload['amount'],
            'customer_name' => $payload['customer']['name'] ?? 'Customer',
            'customer_email' => $payload['customer']['email'] ?? '',
            'customer_phone' => $payload['customer']['phone'] ?? '',
            'order_items' => $payload['items'] ?? [],
            'return_url' => $payload['success_url'] ?? '',
            'signature' => hash_hmac('sha256', $merchantCode . $merchantRef . (int) $payload['amount'], $privateKey),
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->provider->base_url . '/transaction/create', $body);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['success'] ?? false) === true) {
                    return [
                        'success' => true,
                        'redirect_url' => $data['data']['checkout_url'] ?? null,
                        'reference' => $data['data']['reference'] ?? null,
                        'raw' => $data['data'],
                    ];
                }
                return ['success' => false, 'message' => $data['message'] ?? 'Unknown error'];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('TripayClosedAdapter error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getTransactionStatus(string $transactionId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->provider->getApiKeyAttribute(),
            ])->get($this->provider->base_url . "/transaction/detail?reference={$transactionId}");

            return $response->successful()
                ? ['success' => true, 'data' => $response->json()]
                : ['success' => false];
        } catch (\Exception $e) {
            return ['success' => false];
        }
    }

    public function verifyCallback(array $requestData): bool
    {
        $privateKey = $this->provider->getApiSecretAttribute();
        $callbackSignature = $requestData['headers']['x-callback-signature'] ?? '';
        $jsonBody = json_encode($requestData['body'] ?? []);
        $signature = hash_hmac('sha256', $jsonBody, $privateKey);
        return hash_equals($signature, $callbackSignature);
    }

    public function getChannels(): array
    {
        return ['BCAVA', 'BNIVA', 'BRIVA', 'MANDIRIVA', 'QRIS', 'GOPAY', 'OVO', 'DANA', 'SHOPEEPAY'];
    }
}
