<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ShippingMethodResource;
use App\Models\Provider;
use App\Models\ShippingMethod;
use App\Services\Shipping\ShippingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends ApiController
{
    public function methods(Request $request): JsonResponse
    {
        $rows = ShippingMethod::where('status', true)->orderBy('cost')->get();

        return $this->ok(ShippingMethodResource::collection($rows)->resolve($request));
    }

    public function providers(Request $request): JsonResponse
    {
        $rows = Provider::ofType('shipping')->active()->orderBy('sort_order')->get();

        return $this->ok($rows->map(fn (Provider $provider): array => [
            'id' => (int) $provider->id,
            'name' => $provider->name,
            'type' => $provider->type,
            'description' => $provider->description ?? null,
            'is_default' => (bool) $provider->is_default,
        ])->all());
    }

    public function rates(Request $request, ShippingService $shipping): JsonResponse
    {
        $data = $request->validate([
            'shop_id' => 'required|integer|exists:shops,id',
            'destination' => 'required|string|max:100',
            'courier' => 'nullable|string|max:50',
            'weight' => 'nullable|integer|min:1|max:100000',
        ]);

        $provider = Provider::ofType('shipping')->active()->first();

        $this->abortUnlessOwned($provider !== null, 'shipping_provider_not_found');

        $rates = $shipping->getShippingRates($provider, [
            'origin' => (string) (\App\Models\SystemSetting::get('shipping_origin') ?? ''),
            'destination' => $data['destination'],
            'courier' => $data['courier'] ?? '',
            'weight' => $data['weight'] ?? 1,
        ]);

        if (! ($rates['success'] ?? false)) {
            return ApiResponse::error('shipping_rate_unavailable', 'Tarif pengiriman tidak dapat diverifikasi.', 422);
        }

        return $this->ok([
            'provider' => ['id' => (int) $provider->id, 'name' => $provider->name],
            'rates' => array_map(static fn (array $rate): array => [
                'courier' => $rate['courier'] ?? null,
                'service' => $rate['service'] ?? null,
                'description' => $rate['description'] ?? null,
                'cost' => ApiResponse::money($rate['cost'] ?? 0),
                'currency' => 'IDR',
                'etd' => $rate['etd'] ?? null,
            ], $rates['rates'] ?? []),
        ]);
    }
}
