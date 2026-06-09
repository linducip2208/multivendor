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
}
