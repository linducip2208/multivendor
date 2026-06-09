<?php

namespace App\Services\Shipping;

use App\Models\Provider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShippingService
{
    public function getActiveCouriers(): array
    {
        return Provider::ofType('shipping')->active()->orderBy('sort_order')->get()->all();
    }

    public function getShippingRates(Provider $provider, array $params): array
    {
        $origin = $params['origin'] ?? null;
        $destination = $params['destination'] ?? null;
        $weight = $params['weight'] ?? 1000;
        $courier = $params['courier'] ?? null;

        if (!$origin || !$destination) {
            return ['success' => false, 'message' => 'Origin dan destination diperlukan.'];
        }

        return match ($provider->api_format) {
            'rajaongkir-starter', 'rajaongkir-pro' => $this->rajaOngkirRates($provider, $origin, $destination, $weight, $courier),
            'courier-rest' => $this->genericCourierRates($provider, $origin, $destination, $weight, $courier),
            default => ['success' => false, 'message' => 'Format shipping tidak didukung.'],
        };
    }

    protected function rajaOngkirRates(Provider $provider, $origin, $destination, $weight, $courier): array
    {
        try {
            $response = Http::withHeaders([
                'key' => $provider->getApiKeyAttribute(),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post($provider->base_url . '/cost', [
                'origin' => $origin,
                'destination' => $destination,
                'weight' => max(1, ceil($weight / 1000)) * 1000,
                'courier' => $courier ?? 'jne',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $results = $data['rajaongkir']['results'] ?? [];

                $rates = [];
                foreach ($results as $result) {
                    foreach ($result['costs'] ?? [] as $cost) {
                        $rates[] = [
                            'courier' => $result['code'] ?? strtoupper($result['name'] ?? ''),
                            'service' => $cost['service'] ?? '',
                            'description' => $cost['description'] ?? '',
                            'cost' => $cost['cost'][0]['value'] ?? 0,
                            'etd' => $cost['cost'][0]['etd'] ?? '',
                        ];
                    }
                }
                return ['success' => true, 'rates' => $rates];
            }

            return ['success' => false, 'message' => 'Gagal mengambil ongkir.'];
        } catch (\Exception $e) {
            Log::error('RajaOngkir error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function genericCourierRates(Provider $provider, $origin, $destination, $weight, $courier): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $provider->getApiKeyAttribute(),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($provider->base_url . '/rates', [
                'origin' => $origin,
                'destination' => $destination,
                'weight' => $weight,
                'courier' => $courier,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return ['success' => true, 'rates' => $data['rates'] ?? $data['data'] ?? []];
            }
            return ['success' => false, 'message' => 'Gagal mengambil ongkir.'];
        } catch (\Exception $e) {
            Log::error('GenericCourier error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getTracking(Provider $provider, string $trackingNumber, string $courier = null): array
    {
        return match ($provider->api_format) {
            'rajaongkir-starter', 'rajaongkir-pro' => $this->rajaOngkirTracking($provider, $trackingNumber, $courier),
            default => $this->genericTracking($provider, $trackingNumber, $courier),
        };
    }

    protected function rajaOngkirTracking(Provider $provider, string $trackingNumber, ?string $courier): array
    {
        try {
            $response = Http::withHeaders(['key' => $provider->getApiKeyAttribute()])
                ->asForm()->post($provider->base_url . '/waybill', [
                    'waybill' => $trackingNumber,
                    'courier' => $courier ?? 'jne',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return ['success' => true, 'data' => $data['rajaongkir']['result'] ?? []];
            }
            return ['success' => false];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function genericTracking(Provider $provider, string $trackingNumber, ?string $courier): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $provider->getApiKeyAttribute(),
            ])->get($provider->base_url . "/track/{$trackingNumber}");

            return $response->successful()
                ? ['success' => true, 'data' => $response->json()]
                : ['success' => false];
        } catch (\Exception $e) {
            return ['success' => false];
        }
    }
}
