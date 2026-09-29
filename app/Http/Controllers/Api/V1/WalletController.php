<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\WalletResource;
use App\Http\Resources\WalletTransactionResource;
use App\Models\Wallet;
use App\Services\Api\ApiCatalog;
use App\Services\Api\ApiFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        $wallet = Wallet::firstOrCreate(['user_id' => $request->user()->id], ['balance' => 0]);

        return $this->ok(new WalletResource($wallet));
    }

    public function transactions(Request $request): JsonResponse
    {
        $wallet = Wallet::firstOrCreate(['user_id' => $request->user()->id], ['balance' => 0]);
        $filter = ApiCatalog::walletTransactions();
        $paginator = $filter->paginate($wallet->transactions(), $request);

        return $this->paged(
            $paginator,
            WalletTransactionResource::collection($paginator->getCollection()),
            'OK',
            $filter,
            $request
        );
    }
}
