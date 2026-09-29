<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\OrderResource;
use App\Models\DeliveryManEarning;
use App\Models\Order;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourierController extends ApiController
{
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->ok([
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? null,
            'avatar' => $user->avatar ?? null,
            'role' => $user->role,
            'active_orders' => Order::where('delivery_man_id', $user->id)
                ->whereIn('order_status', ['shipped', 'processing', 'confirmed'])
                ->count(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->ok([
            'active_orders' => Order::where('delivery_man_id', $user->id)
                ->whereIn('order_status', ['shipped', 'processing', 'confirmed'])
                ->count(),
            'delivered_orders' => Order::where('delivery_man_id', $user->id)
                ->whereIn('order_status', ['delivered', 'completed'])
                ->count(),
            'earnings_total' => ApiResponse::money(DeliveryManEarning::where('delivery_man_id', $user->id)->sum('amount')),
            'wallet_balance' => ApiResponse::money($user->wallet?->balance ?? 0),
            'currency' => 'IDR',
        ]);
    }

    public function order(Request $request, int $order): JsonResponse
    {
        return $this->ok(new OrderResource($this->owned($request, $order)));
    }

    public function earnings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $paginator = DeliveryManEarning::where('delivery_man_id', $request->user()->id)
            ->with('order:id,order_number,order_status')
            ->latest()
            ->paginate($data['per_page'] ?? 20);

        $rows = $paginator->getCollection()->map(fn (DeliveryManEarning $row): array => [
            'id' => (int) $row->id,
            'order_id' => $row->order_id === null ? null : (int) $row->order_id,
            'order_number' => $row->order?->order_number,
            'amount' => ApiResponse::money($row->amount),
            'description' => $row->description,
            'created_at' => ApiResponse::iso($row->created_at),
        ])->all();

        return $this->ok($rows, 'OK', ApiResponse::metaFrom($paginator, [], null));
    }

    private function owned(Request $request, int $order): Order
    {
        $model = Order::where('delivery_man_id', $request->user()->id)->whereKey($order)->first();

        $this->abortUnlessOwned($model !== null, 'order_not_found');

        return $model->load(['shop', 'items.product', 'shipments', 'statusHistory']);
    }
}
