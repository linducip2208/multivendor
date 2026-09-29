<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\Api\CheckoutApiService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends ApiController
{
    private const RULES = [
        'address_id' => 'nullable|integer',
        'new_label' => 'nullable|string|max:60',
        'new_receiver_name' => 'nullable|required_without:address_id|string|max:255',
        'new_receiver_phone' => 'nullable|required_without:address_id|string|max:20',
        'new_address' => 'nullable|required_without:address_id|string|max:500',
        'new_city' => 'nullable|required_without:address_id|string|max:100',
        'new_province' => 'nullable|required_without:address_id|string|max:100',
        'new_postal_code' => 'nullable|string|max:20',
        'new_shipping_destination_id' => 'nullable|required_without:address_id|string|max:100',
        'shipping_methods' => 'nullable|array',
        'shipping_methods.*.provider_id' => 'nullable|integer',
        'shipping_methods.*.courier' => 'nullable|string|max:50',
        'shipping_methods.*.service' => 'nullable|string|max:100',
        'shipping_methods.*.destination' => 'nullable|string|max:100',
        'coupon_code' => 'nullable|string|max:50',
        'note' => 'nullable|string|max:2000',
        'idempotency_key' => 'nullable|string|max:80',
    ];

    public function preview(Request $request, CheckoutApiService $checkout): JsonResponse
    {
        $data = $request->validate(self::RULES);

        return $this->ok($checkout->preview($request->user(), $data), 'Perhitungan checkout');
    }

    public function store(Request $request, CheckoutApiService $checkout): JsonResponse
    {
        $data = $request->validate(self::RULES + [
            'payment_provider_id' => 'required|integer',
            'payment_channel' => 'nullable|array',
        ]);

        $result = $checkout->place($request->user(), $data);
        $orderId = $result['order']['id'] ?? null;

        if ($orderId !== null) {
            $this->markResource($request, 'order', (int) $orderId);
        }

        if ($result['replayed'] ?? false) {
            return ApiResponse::success($result, 'Pesanan sudah pernah dibuat.', 200)
                ->header('Idempotency-Replayed', 'true');
        }

        return $this->created($result, 'Pesanan dibuat');
    }
}
