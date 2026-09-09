<?php

namespace App\Services\Payment;

use App\Models\Provider;
use Illuminate\Support\Facades\Log;

class PaymentGatewayService
{
    protected array $adapters = [];

    public function getAdapter(Provider $provider): ?PaymentAdapterInterface
    {
        $cacheKey = $provider->id;

        if (isset($this->adapters[$cacheKey])) {
            return $this->adapters[$cacheKey];
        }

        $adapter = match ($provider->api_format) {
            'midtrans-snap' => new SnapRedirectAdapter($provider),
            'midtrans-core' => new CoreApiAdapter($provider),
            'xendit-invoice' => new XenditInvoiceAdapter($provider),
            'tripay-closed' => new TripayClosedAdapter($provider),
            'duitku-redirect' => new GenericRedirectAdapter($provider),
            'oyindonesia-api', 'ipaymu-api', 'faspay-api',
            'doku-api', 'esiapay-api' => new GenericApiAdapter($provider),
            default => null,
        };

        if ($adapter) {
            $this->adapters[$cacheKey] = $adapter;
        }

        return $adapter;
    }

    public function createPayment(Provider $provider, array $payload): array
    {
        $adapter = $this->getAdapter($provider);
        if (!$adapter) {
            return ['success' => false, 'message' => "Format {$provider->api_format} tidak didukung."];
        }
        return $adapter->createTransaction($payload);
    }

    public function getActiveGateways(): array
    {
        return Provider::ofType('payment')->active()->orderBy('sort_order')->get()->all();
    }

    public function getChannelsForGateway(Provider $provider): array
    {
        $adapter = $this->getAdapter($provider);
        return $adapter?->getChannels() ?? [];
    }

    public function verifyCallback(Provider $provider, array $data): bool
    {
        $adapter = $this->getAdapter($provider);
        return $adapter?->verifyCallback($data) ?? false;
    }

    /** @return array{external_id:?string,gateway_transaction_id:?string,status:?string} */
    public function normalizeCallback(Provider $provider, array $data): array
    {
        $body = $data['body'] ?? $data;
        $status = strtolower((string) ($body['transaction_status'] ?? $body['status'] ?? ''));

        if (in_array($provider->api_format, ['midtrans-snap', 'midtrans-core'], true)) {
            $status = match (true) {
                $status === 'settlement', $status === 'capture' && ($body['fraud_status'] ?? 'accept') === 'accept' => 'paid',
                in_array($status, ['deny', 'cancel'], true) => 'failed',
                $status === 'expire' => 'expired',
                $status === 'refund', $status === 'partial_refund' => 'refunded',
                default => 'pending',
            };
            return ['external_id' => $body['order_id'] ?? null, 'gateway_transaction_id' => $body['transaction_id'] ?? null, 'status' => $status];
        }

        if ($provider->api_format === 'xendit-invoice') {
            $status = match ($status) {
                'paid', 'settled' => 'paid', 'expired' => 'expired', 'failed' => 'failed', 'refunded' => 'refunded', default => 'pending',
            };
            return ['external_id' => $body['external_id'] ?? null, 'gateway_transaction_id' => $body['id'] ?? $body['invoice_id'] ?? null, 'status' => $status];
        }

        if ($provider->api_format === 'tripay-closed') {
            $status = match ($status) {
                'paid', 'settlement' => 'paid', 'expired' => 'expired', 'failed', 'unpaid' => 'failed', 'refund', 'refunded' => 'refunded', default => 'pending',
            };
            return ['external_id' => $body['merchant_ref'] ?? $body['reference'] ?? null, 'gateway_transaction_id' => $body['reference'] ?? null, 'status' => $status];
        }

        return ['external_id' => null, 'gateway_transaction_id' => null, 'status' => null];
    }
}
