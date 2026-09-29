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

        return $this->ok(array_merge(
            (new LoyaltyResource($points->load(['transactions' => fn ($q) => $q->latest('id')->limit(50)])))->resolve($request),
            [
                'tier' => $points->tier(),
                'expiring' => $points->expiringSoon(),
                'referral' => $points->referralHistory(),
                'leaderboard' => LoyaltyPoint::referralLeaderboard(5),
            ]
        ));
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
        return $this->idempotent($request, function () use ($request): JsonResponse {
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

            $fresh = LoyaltyPoint::where('customer_id', $customer->id)->first();

            return $this->ok([
                'points' => $points,
                'redeemed_value' => ApiResponse::money($redeemed),
                'remaining_points' => (int) LoyaltyPoint::where('customer_id', $customer->id)->value('points'),
                'tier' => $fresh?->tier(),
            ], 'Poin berhasil ditukar');
        });
    }

    /** Daftar misi harian + streak check-in milik pengguna. */
    public function missions(Request $request): JsonResponse
    {
        $misi = app(\App\Services\Loyalitas\MisiHarian::class);
        $user = $request->user();
        $points = LoyaltyPoint::firstOrCreate(['customer_id' => $user->id], ['points' => 0]);

        return $this->ok([
            'missions' => $misi->statusFor($user),
            'streak' => $misi->streak($user),
            'checkin_points_berikutnya' => $misi->checkinPoints($misi->streak($user) + 1),
            'points' => (int) $points->points,
        ]);
    }

    /** Klaim hadiah satu misi harian (idempoten per hari). */
    public function claimMission(Request $request): JsonResponse
    {
        return $this->idempotent($request, function () use ($request): JsonResponse {
            $data = $request->validate([
                'key' => 'required|string|in:login,checkin,review,share,belanja',
            ]);
            $hasil = app(\App\Services\Loyalitas\MisiHarian::class)->claim($request->user(), (string) $data['key']);

            if (! $hasil['ok']) {
                return ApiResponse::error(ErrorCodes::VALIDATION_FAILED, $hasil['pesan'], 422, ['key' => [$hasil['pesan']]]);
            }
            $this->markResource($request, 'loyalty_mission', $data['key'].'-'.now()->toDateString());

            return $this->ok([
                'key' => $data['key'],
                'poin' => $hasil['poin'],
                'remaining_points' => (int) LoyaltyPoint::where('customer_id', $request->user()->id)->value('points'),
            ], $hasil['pesan']);
        });
    }

    /** Check-in harian beruntun (idempoten per hari). */
    public function checkin(Request $request): JsonResponse
    {
        return $this->idempotent($request, function () use ($request): JsonResponse {
            $hasil = app(\App\Services\Loyalitas\MisiHarian::class)->checkin($request->user());

            if (! $hasil['ok']) {
                return ApiResponse::error(ErrorCodes::VALIDATION_FAILED, $hasil['pesan'], 422, ['checkin' => [$hasil['pesan']]]);
            }
            $this->markResource($request, 'loyalty_checkin', now()->toDateString());

            return $this->ok([
                'poin' => $hasil['poin'],
                'streak' => $hasil['streak'],
                'remaining_points' => (int) LoyaltyPoint::where('customer_id', $request->user()->id)->value('points'),
            ], $hasil['pesan']);
        });
    }
}
