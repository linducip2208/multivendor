<?php

declare(strict_types=1);

namespace App\Services\Vendor;

use App\Models\DeliveryCashCollect;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cash-on-delivery custody.
 *
 * A COD collect row records that a courier is holding the customer's cash. It
 * is *not* a settlement event: the money belongs to the platform until it is
 * reconciled against the vendor's share, which OrderService already performs on
 * delivery. Crediting a wallet from this screen double-counted the order, so
 * this service only ever flips the custody flag and records the actor.
 */
final class VendorCashCollectService
{
    public function __construct(private readonly VendorScope $scope) {}

    /** @return array{collects: \Illuminate\Contracts\Pagination\LengthAwarePaginator, pending: \App\Support\Money, collected: \App\Support\Money, total: int} */
    public function index(string $status = ''): array
    {
        $shopId = $this->scope->shopId();

        $base = DeliveryCashCollect::query()
            ->whereHas('order', fn ($query) => $query->where('shop_id', $shopId));

        $filtered = clone $base;

        if ($status === 'collected') {
            $filtered->where('collected', true);
        } elseif ($status === 'pending') {
            $filtered->where('collected', false);
        }

        $collects = $filtered->with(['order:id,order_number,shop_id,customer_id,total,payment_status,order_status', 'deliveryMan:id,name'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return [
            'collects' => $collects,
            'pending' => Money::of((float) (clone $base)->where('collected', false)->sum('amount')),
            'collected' => Money::of((float) (clone $base)->where('collected', true)->sum('amount')),
            'total' => (int) (clone $base)->count(),
        ];
    }

    public function markCollected(DeliveryCashCollect $collect): DeliveryCashCollect
    {
        return DB::transaction(function () use ($collect): DeliveryCashCollect {
            $locked = DeliveryCashCollect::query()
                ->whereKey($collect->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $order = Order::query()->lockForUpdate()->find($locked->order_id);

            abort_if($order === null || (int) $order->shop_id !== $this->scope->shopId(), 403);

            if ($locked->collected) {
                return $locked;
            }

            $locked->forceFill(['collected' => true, 'collected_at' => now()])->save();

            app(AuditLogger::class)->log('vendor.cod.collected', $locked, [
                'collected' => false,
            ], [
                'collected' => true,
                'order_id' => (int) $order->getKey(),
                'amount' => Money::of($locked->amount)->toDecimal(),
            ], $this->scope->userId());

            return $locked->refresh();
        }, 3);
    }

    /** @return Collection<int, DeliveryCashCollect> */
    public function recent(int $limit = 5): Collection
    {
        return DeliveryCashCollect::query()
            ->whereHas('order', fn ($query) => $query->where('shop_id', $this->scope->shopId()))
            ->with('order:id,order_number')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
