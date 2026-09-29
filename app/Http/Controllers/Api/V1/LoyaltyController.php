<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\LoyaltyResource;
use App\Http\Resources\LoyaltyTransactionResource;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltyTransaction;
use App\Services\Api\ApiCatalog;
use App\Services\Api\ApiFilter;
use App\Services\Api\ErrorCodes;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        $points = LoyaltyPoint::firstOrCreate(
            ['customer_id' => $request->user()->id],
            ['points' => 0]
        );

        return $this->ok(new LoyaltyResource($points->load('transactions')));
    }

    public function transactions(Request $request): JsonResponse
    {
        $filter = ApiCatalog::loyaltyTransactions();
        $paginator = $filter->paginate(
            LoyaltyTransaction::where('customer_id', $request->user()->id),
            $request
        );

        return $this->paged(
            $paginator,
            LoyaltyTransactionResource::collection($paginator->getCollection()),
            'OK',
            $filter,
            $request
        );
    }

    public function redeem(Request $request): JsonResponse
    {
        $data = $request->validate(['points' => 'required|integer|min:1|max:1000000']);
        $points = (int) $data['points'];
        $customer = $request->user();

        $balance = LoyaltyPoint::where('customer_id', $customer->id)->value('points');

        if ($balance === null || (int) $balance < $points) {
            return ApiResponse::error(
                ErrorCodes::INSUFFICIENT_WALLET_BALANCE,
                'Poin loyalty tidak mencukupi.',
                422,
                ['points' => ['Poin yang diminta melebihi saldo Anda.']]
            );
        }

        $redeemed = \App\Models\LoyaltyPoint::redeem($customer, $points);
        $this->markResource($request, 'loyalty_redemption', $points);

        return $this->ok([
            'points' => $points,
            'redeemed_value' => ApiResponse::money($redeemed),
            'remaining_points' => (int) LoyaltyPoint::where('customer_id', $customer->id)->value('points'),
        ], 'Poin berhasil ditukar');
    }
}
