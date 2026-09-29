<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use App\Services\Api\ApiCatalog;
use App\Services\Api\ApiFilter;
use App\Services\Api\ErrorCodes;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends ApiController
{
    public function index(Request $request, ApiFilter $filter): JsonResponse
    {
        $query = Coupon::where('status', true)
            ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', now()))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()));

        $paginator = ApiCatalog::coupons()->paginate($query, $request);

        return $this->paged(
            $paginator,
            CouponResource::collection($paginator->getCollection())->resolve($request),
            'OK',
            $filter,
            $request
        );
    }

    public function validateCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:50',
            'order_total' => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::where('code', strtoupper(trim($data['code'])))->first();

        if ($coupon === null || ! $coupon->isValid($request->user()->id)) {
            return ApiResponse::error(
                ErrorCodes::COUPON_INVALID,
                'Kupon tidak aktif, kedaluwarsa, atau kuotanya habis.',
                422,
                ['code' => ['Kupon tidak dapat digunakan.']]
            );
        }

        $discount = $coupon->calculateDiscount((float) $data['order_total']);

        return $this->ok([
            'coupon' => new CouponResource($coupon),
            'order_total' => ApiResponse::money($data['order_total']),
            'discount' => ApiResponse::money($discount),
            'payable' => ApiResponse::money(max(0, (float) $data['order_total'] - $discount)),
        ], 'Kupon valid');
    }

    public function show(Request $request, string $code): JsonResponse
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();

        $this->abortUnlessOwned($coupon !== null, 'coupon_not_found');

        return $this->ok(new CouponResource($coupon));
    }
}
