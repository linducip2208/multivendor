<?php

declare(strict_types=1);

namespace App\Services\Vendor;

use App\Models\CustomerActivity;
use App\Models\Order;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Customer 360 for the shop's own buyers.
 *
 * A customer is only ever reachable through an order that belongs to this shop,
 * so a shopper who has never bought from the vendor is invisible here.
 */
final class VendorCustomerService
{
    private const SEGMENTS = [
        'vip' => 'VIP',
        'repeat' => 'Pembeli Berulang',
        'new' => 'Pembeli Baru',
        'at_risk' => 'Perlu Perhatian',
        'dormant' => 'Tidak Aktif',
    ];

    public function __construct(private readonly VendorScope $scope) {}

    public function index(string $search = '', string $segment = ''): array
    {
        $shopId = $this->scope->shopId();

        $base = $this->customersQuery($shopId);

        if ($search !== '') {
            $base->where(fn ($query) => $query
                ->where('users.name', 'like', '%'.$search.'%')
                ->orWhere('users.email', 'like', '%'.$search.'%')
                ->orWhere('users.phone', 'like', '%'.$search.'%'));
        }

        $customers = $base->orderByDesc('total_spend')->orderBy('users.name')
            ->paginate(20)
            ->withQueryString();

        $collection = $customers->getCollection();

        return [
            'customers' => $customers,
            'segments' => self::SEGMENTS,
            'stats' => [
                'total' => (int) (clone $this->customersQuery($shopId))->count(),
                'vip' => $this->countSegment($shopId, 'vip'),
                'repeat' => $this->countSegment($shopId, 'repeat'),
                'new' => $this->countSegment($shopId, 'new'),
            ],
            'selected' => $segment,
        ];
    }

    public function show(int $customerId): array
    {
        $shopId = $this->scope->shopId();

        $customer = User::query()
            ->whereKey($customerId)
            ->whereHas('orders', fn ($query) => $query->where('shop_id', $shopId))
            ->first();

        abort_if($customer === null, 404);

        $orders = Order::query()
            ->where('shop_id', $shopId)
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $products = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.shop_id', $shopId)
            ->where('orders.customer_id', $customerId)
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->selectRaw('order_items.product_id, order_items.product_name, SUM(order_items.quantity) as units, SUM(order_items.sub_total) as spend')
            ->orderByDesc('spend')
            ->limit(8)
            ->get();

        $reviews = ProductReview::query()
            ->where('shop_id', $shopId)
            ->where('customer_id', $customerId)
            ->with('product:id,name')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $activity = CustomerActivity::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        $spend = Money::sum($orders->pluck('sub_total'));
        $lifetime = Order::query()
            ->where('shop_id', $shopId)
            ->where('customer_id', $customerId)
            ->whereIn('order_status', AnalyticsService::revenueOrderStatuses())
            ->whereIn('payment_status', AnalyticsService::paidPaymentStatuses())
            ->count();

        return [
            'customer' => $customer,
            'orders' => $orders,
            'products' => $products,
            'reviews' => $reviews,
            'activity' => $activity,
            'segments' => self::SEGMENTS,
            'stats' => [
                'orders' => $lifetime,
                'spend' => $spend,
                'aov' => $lifetime > 0 ? Money::of($spend->toFloat())->multiply(1 / $lifetime) : Money::zero(),
                'average_rating' => round((float) $reviews->avg('rating'), 2),
                'segment' => $this->segment($lifetime, $orders->max('created_at')),
            ],
        ];
    }

    private function customersQuery(int $shopId)
    {
        return DB::table('users')
            ->join('orders', 'orders.customer_id', '=', 'users.id')
            ->where('orders.shop_id', $shopId)
            ->where('users.role', 'customer')
            ->groupBy('users.id', 'users.name', 'users.email', 'users.phone', 'users.avatar', 'users.created_at')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.phone',
                'users.avatar',
                'users.created_at',
                DB::raw('COUNT(orders.id) as total_orders'),
                DB::raw('COALESCE(SUM(orders.sub_total), 0) as total_spend'),
                DB::raw('MAX(orders.created_at) as last_order_at'),
            ]);
    }

    private function countSegment(int $shopId, string $segment): int
    {
        $rows = $this->customersQuery($shopId)->get();

        $count = 0;

        foreach ($rows as $row) {
            if ($this->segment((int) $row->total_orders, $row->last_order_at) === $segment) {
                $count++;
            }
        }

        return $count;
    }

    private function segment(int $orders, mixed $lastOrderAt): string
    {
        if ($orders >= 10) {
            return 'vip';
        }

        if ($orders >= 3) {
            return 'repeat';
        }

        if ($lastOrderAt === null) {
            return 'new';
        }

        $age = \Carbon\Carbon::parse($lastOrderAt)->diffInDays(now());

        return $age > 90 ? 'dormant' : 'new';
    }

    /** @return Collection<int, Order> */
    public function recentOrders(int $shopId, int $limit = 6): Collection
    {
        return Order::query()
            ->where('shop_id', $shopId)
            ->with('customer:id,name')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
