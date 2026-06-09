<?php

namespace App\Services\Payment;

use App\Models\Provider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CoreApiAdapter implements PaymentAdapterInterface
{
    public function __construct(protected Provider $provider) {}

    public function createTransaction(array $payload): array
    {
        $orderId = $payload['order_id'] ?? uniqid('ORD-');
        $amount = $payload['amount'] ?? 0;
        $bank = $payload['bank'] ?? 'bca';
        $channel = $payload['channel'] ?? 'bank_transfer';

        $body = [
            'payment_type' => $channel,
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $amount,
            ],
            'customer_details' => $payload['customer'] ?? [],
            'item_details' => $payload['items'] ?? [],
        ];

        if ($channel === 'bank_transfer') {
            $body['bank_transfer'] = ['bank' => $bank];
        } elseif ($channel === 'echannel') {
            $body['echannel'] = ['bill_info1' => 'Payment', 'bill_info2' => 'Online'];
        } elseif ($channel === 'gopay') {
            $body['gopay'] = ['enable_callback' => true];
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->withBasicAuth($this->provider->getApiKeyAttribute(), '')
              ->post($this->provider->base_url . '/charge', $body);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'va_number' => $data['va_numbers'][0]['va_number'] ?? $data['permata_va_number'] ?? $data['bill_key'] ?? null,
                    'bank' => $data['va_numbers'][0]['bank'] ?? $data['bank'] ?? null,
                    'transaction_id' => $data['transaction_id'] ?? null,
                    'expiry' => $data['expiry_time'] ?? null,
                    'raw' => $data,
                ];
            }

            Log::error('CoreApiAdapter error', ['response' => $response->body()]);
            return ['success' => false, 'message' => 'Payment error: ' . $response->status()];
        } catch (\Exception $e) {
            Log::error('CoreApiAdapter exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getTransactionStatus(string $transactionId): array
    {
        try {
            $response = Http::withBasicAuth($this->provider->getApiKeyAttribute(), '')
                ->get($this->provider->base_url . "/transactions/{$transactionId}/status");

            return $response->successful()
                ? ['success' => true, 'data' => $response->json()]
                : ['success' => false, 'message' => 'Failed'];
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
        return ['bank_transfer', 'echannel', 'gopay', 'shopeepay', 'qris'];
    }
}
