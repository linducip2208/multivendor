<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Order;
use App\Models\PaymentGroup;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['per_page' => 'nullable|integer|min:1|max:50', 'page' => 'nullable|integer|min:1']);

        $paginator = PaymentGroup::where('customer_id', $request->user()->id)
            ->with('provider:id,name,api_format')
            ->withCount('orders')
            ->latest()
            ->paginate($data['per_page'] ?? 20);

        return $this->pagedMeta(
            $paginator,
            $paginator->getCollection()->map(fn (PaymentGroup $group): array => $this->present($group))->all(),
            $request
        );
    }

    public function show(Request $request, int $payment): JsonResponse
    {
        $group = PaymentGroup::where('customer_id', $request->user()->id)->whereKey($payment)->first();

        $this->abortUnlessOwned($group !== null, 'payment_not_found');

        return $this->ok($this->present($group->load('provider:id,name,api_format')->load('orders')));
    }

    public function orders(Request $request, int $payment): JsonResponse
    {
        $group = PaymentGroup::where('customer_id', $request->user()->id)->whereKey($payment)->first();

        $this->abortUnlessOwned($group !== null, 'payment_not_found');

        return $this->ok(
            Order::where('payment_group_id', $group->id)
                ->with(['shop', 'items.product'])
                ->get()
                ->map(fn (Order $order): array => [
                    'id' => (int) $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->order_status,
                    'payment_status' => $order->payment_status,
                    'total' => ApiResponse::money($order->total),
                ])
                ->all()
        );
    }

    private function pagedMeta($paginator, array $rows, Request $request): JsonResponse
    {
        $meta = ApiResponse::metaFrom($paginator, [], null);

        return $this->ok($rows, 'OK', $meta);
    }

    private function present(PaymentGroup $group): array
    {
        return [
            'id' => (int) $group->id,
            'payment_number' => $group->payment_number,
            'status' => $group->status,
            'subtotal' => ApiResponse::money($group->subtotal),
            'tax' => ApiResponse::money($group->tax),
            'shipping_cost' => ApiResponse::money($group->shipping_cost),
            'discount' => ApiResponse::money($group->discount),
            'grand_total' => ApiResponse::money($group->grand_total),
            'currency' => 'IDR',
            'gateway_reference' => $group->gateway_reference ?? null,
            'provider' => $group->relationLoaded('provider') && $group->provider !== null ? [
                'id' => (int) $group->provider->id,
                'name' => $group->provider->name,
                'api_format' => $group->provider->api_format,
            ] : null,
            'orders_count' => $group->orders_count ?? $group->orders->count(),
            'paid_at' => ApiResponse::iso($group->paid_at),
            'expired_at' => ApiResponse::iso($group->expired_at),
            'created_at' => ApiResponse::iso($group->created_at),
        ];
    }
}
