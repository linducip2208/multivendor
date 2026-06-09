<?php

namespace App\Services\Payment;

use App\Models\Provider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XenditInvoiceAdapter implements PaymentAdapterInterface
{
    public function __construct(protected Provider $provider) {}

    public function createTransaction(array $payload): array
    {
        try {
            $response = Http::withBasicAuth($this->provider->getApiKeyAttribute(), '')
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->provider->base_url . '/v2/invoices', [
                    'external_id' => $payload['order_id'],
                    'amount' => $payload['amount'],
                    'payer_email' => $payload['customer']['email'] ?? '',
                    'description' => $payload['description'] ?? 'Order payment',
                    'success_redirect_url' => $payload['success_url'] ?? '',
                    'failure_redirect_url' => $payload['failure_url'] ?? '',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'redirect_url' => $data['invoice_url'] ?? null,
                    'invoice_id' => $data['id'] ?? null,
                    'raw' => $data,
                ];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('XenditInvoiceAdapter error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getTransactionStatus(string $transactionId): array
    {
        try {
            $response = Http::withBasicAuth($this->provider->getApiKeyAttribute(), '')
                ->get($this->provider->base_url . "/v2/invoices/{$transactionId}");

            return $response->successful()
                ? ['success' => true, 'data' => $response->json()]
                : ['success' => false];
        } catch (\Exception $e) {
            return ['success' => false];
        }
    }

    public function verifyCallback(array $requestData): bool
    {
        $callbackToken = $this->provider->getApiSecretAttribute();
        $incomingToken = $requestData['headers']['x-callback-token'] ?? '';
        return $callbackToken && hash_equals($callbackToken, $incomingToken);
    }

    public function getChannels(): array
    {
        return ['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA', 'QRIS', 'OVO', 'DANA', 'LINKAJA', 'ALFAMART', 'INDOMARET'];
    }
}
