<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Models\CouponUsage;
use App\Models\CustomerActivity;
use App\Models\CustomerAddress;
use App\Models\LoyaltyPoint;
use App\Models\Order;
use App\Models\ProductReview;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Wishlist;
use App\Support\Currency;
use Illuminate\Support\Facades\DB;

/**
 * Customer 360.
 *
 * Everything returned is a recorded fact about a purchase history: what was
 * bought, how much was paid, when, with which coupon, and which support thread
 * followed. No inference is drawn about the person.
 */
final class Customer360Service
{
    public const RECENT_ORDERS = 10;
    public const RECENT_ACTIVITY = 25;
    public const RECENT_REVIEWS = 10;

    /**
     * @return array<string, mixed>
     */
    public function profile(int $customerId): array
    {
        $customer = User::query()->findOrFail($customerId);

        $orders = $this->orders($customerId);
        $spend = (float) $orders->sum('total');
        $paidOrders = (int) $this->paidOrdersQuery($customerId)->count();
        $first = (clone $orders)->min('created_at');
        $last = (clone $orders)->max('created_at');

        $wallet = Wallet::query()->where('user_id', $customerId)->first();
        $loyalty = LoyaltyPoint::query()->where('customer_id', $customerId)->first();

        return [
            'id' => (int) $customer->id,
            'name' => (string) $customer->name,
            'email' => (string) $customer->email,
            'phone' => (string) ($customer->phone ?? ''),
            'avatar' => (string) ($customer->avatar ?? ''),
            'status' => (string) ($customer->status ?? ''),
            'role' => (string) $customer->role,
            'referral_code' => (string) ($customer->referral_code ?? ''),
            'registered_at' => (string) ($customer->created_at?->format('Y-m-d H:i') ?? ''),
            'summary' => $this->summary($customerId, $spend, $paidOrders, $first, $last),
            'wallet' => [
                'balance' => (float) ($wallet->balance ?? 0),
                'pending' => (float) ($wallet->pending_balance ?? 0),
            ],
            'loyalty' => [
                'points' => (int) ($loyalty->points ?? 0),
                'formatted' => Currency::number((int) ($loyalty->points ?? 0)),
            ],
            'frequency' => $this->purchaseFrequency($customerId, $first, $paidOrders),
            'orders' => $this->recentOrders($customerId),
            'activity' => $this->activity($customerId),
            'wishlist' => $this->wishlist($customerId),
            'reviews' => $this->reviews($customerId),
            'support' => $this->support($customerId),
            'addresses' => $this->addresses($customerId),
            'coupons' => $this->couponHabits($customerId),
            'segments' => $this->segments($customerId),
            'top_categories' => $this->topCategories($customerId),
            'top_shops' => $this->topShops($customerId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(int $customerId, float $spend, int $paidOrders, mixed $first, mixed $last): array
    {
        $days = 0;
        if ($first !== null && $last !== null) {
            $days = max(1, (int) $first->diffInDays($last) + 1);
        }

        return [
            'ltv' => $spend,
            'ltv_formatted' => Currency::format($spend),
            'order_count' => $paidOrders,
            'aov' => $paidOrders > 0 ? $spend / $paidOrders : 0.0,
            'aov_formatted' => Currency::format($paidOrders > 0 ? $spend / $paidOrders : 0.0),
            'first_order_at' => $first !== null ? (string) $first->format('Y-m-d') : null,
            'last_order_at' => $last !== null ? (string) $last->format('Y-m-d') : null,
            'last_order_human' => $last !== null ? (string) $last->diffForHumans() : '-',
            'active_days' => $days,
            'orders_per_month' => $days > 0 ? round($paidOrders / max(1, $days / 30), 2) : 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseFrequency(int $customerId, mixed $first, int $paidOrders): array
    {
        $days = 0;
        if ($first !== null) {
            $days = max(1, (int) $first->diffInDays(now()) + 1);
        }

        $interval = $paidOrders > 1 ? (int) round($days / ($paidOrders - 1)) : null;

        return [
            'interval_days' => $interval,
            'interval_label' => $interval === null ? '-' : $interval.' hari',
            'per_month' => $paidOrders > 0 ? round($paidOrders / max(1, $days / 30), 2) : 0.0,
            'customer_since' => $first !== null ? (string) $first->format('Y-m-d') : null,
        ];
    }

    private function orders(int $customerId): \Illuminate\Database\Eloquent\Builder
    {
        return Order::query()->where('customer_id', $customerId);
    }

    private function paidOrdersQuery(int $customerId): \Illuminate\Database\Eloquent\Builder
    {
        return Order::query()
            ->where('customer_id', $customerId)
            ->whereIn('payment_status', ['paid', 'partial', 'refunded'])
            ->whereNotIn('order_status', ['canceled', 'failed']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentOrders(int $customerId): array
    {
        return Order::query()
            ->with('shop:id,name')
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->limit(self::RECENT_ORDERS)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => (int) $order->id,
                'order_number' => (string) $order->order_number,
                'shop' => (string) ($order->shop?->name ?? '-'),
                'total' => (float) $order->total,
                'total_formatted' => Currency::format((float) $order->total),
                'order_status' => (string) $order->order_status,
                'payment_status' => (string) $order->payment_status,
                'items' => (int) $order->items()->count(),
                'created_at' => (string) ($order->created_at?->format('Y-m-d H:i') ?? ''),
                'url' => route('admin.orders.show', $order->id),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activity(int $customerId): array
    {
        return CustomerActivity::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->limit(self::RECENT_ACTIVITY)
            ->get()
            ->map(fn (CustomerActivity $activity): array => [
                'id' => (int) $activity->id,
                'type' => (string) $activity->type,
                'description' => (string) ($activity->description ?? ''),
                'icon' => $this->activityIcon((string) $activity->type),
                'at' => (string) ($activity->created_at?->diffForHumans() ?? ''),
                'at_raw' => (string) ($activity->created_at?->format('Y-m-d H:i') ?? ''),
            ])
            ->all();
    }

    private function activityIcon(string $type): string
    {
        return match (true) {
            str_starts_with($type, 'order') => 'shopping-cart',
            str_starts_with($type, 'payment') => 'credit-card',
            str_starts_with($type, 'review') => 'star',
            str_starts_with($type, 'wishlist') => 'heart',
            str_starts_with($type, 'cart') => 'shopping-bag',
            str_starts_with($type, 'login'), str_starts_with($type, 'auth') => 'lock',
            default => 'activity',
        };
    }

    /**
     * @return array{count: int, items: list<array<string, mixed>>}
     */
    private function wishlist(int $customerId): array
    {
        $query = Wishlist::query()->with('product:id,name,slug,price,current_stock,thumbnail');

        try {
            $total = (int) (clone $query)->where('customer_id', $customerId)->count();
            $rows = $query->where('customer_id', $customerId)
                ->orderByDesc('id')
                ->limit(self::RECENT_REVIEWS)
                ->get()
                ->map(fn (Wishlist $item): array => [
                    'id' => (int) $item->id,
                    'name' => (string) ($item->product?->name ?? 'Produk dihapus'),
                    'price' => (float) ($item->product?->price ?? 0),
                    'price_formatted' => Currency::format((float) ($item->product?->price ?? 0)),
                    'stock' => (int) ($item->product?->current_stock ?? 0),
                    'image' => (string) ($item->product?->thumbnail_url ?? ''),
                    'url' => $item->product?->slug !== null
                        ? route('admin.products.show', $item->product_id)
                        : '#',
                ])
                ->all();
        } catch (\Throwable) {
            $total = 0;
            $rows = [];
        }

        return ['count' => $total, 'items' => $rows];
    }

    /**
     * @return array{count: int, average: float, items: list<array<string, mixed>>}
     */
    private function reviews(int $customerId): array
    {
        $query = ProductReview::query()->with('product:id,name,slug');

        try {
            $total = (int) (clone $query)->where('customer_id', $customerId)->count();
            $average = (float) (clone $query)->where('customer_id', $customerId)->avg('rating');
            $rows = $query->where('customer_id', $customerId)
                ->orderByDesc('id')
                ->limit(self::RECENT_REVIEWS)
                ->get()
                ->map(fn (ProductReview $review): array => [
                    'id' => (int) $review->id,
                    'product' => (string) ($review->product?->name ?? 'Produk dihapus'),
                    'rating' => (int) $review->rating,
                    'comment' => \Illuminate\Support\Str::limit((string) ($review->comment ?? ''), 160),
                    'approved' => (bool) $review->status,
                    'at' => (string) ($review->created_at?->format('Y-m-d') ?? ''),
                ])
                ->all();
        } catch (\Throwable) {
            $total = 0;
            $average = 0.0;
            $rows = [];
        }

        return ['count' => $total, 'average' => round($average, 2), 'items' => $rows];
    }

    /**
     * @return array{open: int, total: int, items: list<array<string, mixed>>}
     */
    private function support(int $customerId): array
    {
        try {
            $query = SupportTicket::query()->where('customer_id', $customerId);
            $total = (int) (clone $query)->count();
            $open = (int) (clone $query)->whereNotIn('status', ['closed', 'resolved'])->count();
            $rows = (clone $query)->orderByDesc('created_at')->limit(5)->get()
                ->map(fn (SupportTicket $ticket): array => [
                    'id' => (int) $ticket->id,
                    'subject' => (string) $ticket->subject,
                    'status' => (string) $ticket->status,
                    'priority' => (string) $ticket->priority,
                    'at' => (string) ($ticket->created_at?->format('Y-m-d') ?? ''),
                ])
                ->all();
        } catch (\Throwable) {
            $total = 0;
            $open = 0;
            $rows = [];
        }

        return ['open' => $open, 'total' => $total, 'items' => $rows];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function addresses(int $customerId): array
    {
        try {
            return CustomerAddress::query()
                ->where('customer_id', $customerId)
                ->orderByDesc('is_default')
                ->get()
                ->map(fn (CustomerAddress $address): array => [
                    'id' => (int) $address->id,
                    'label' => (string) ($address->label ?? 'Alamat'),
                    'receiver' => (string) ($address->receiver_name ?? ''),
                    'phone' => (string) ($address->receiver_phone ?? ''),
                    'line' => trim(implode(', ', array_filter([
                        (string) ($address->address ?? ''),
                        (string) ($address->city ?? ''),
                        (string) ($address->province ?? ''),
                        (string) ($address->postal_code ?? ''),
                    ]))),
                    'is_default' => (bool) $address->is_default,
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{used: int, discount: float, codes: list<string>}
     */
    private function couponHabits(int $customerId): array
    {
        try {
            $rows = CouponUsage::query()
                ->where('customer_id', $customerId)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();
        } catch (\Throwable) {
            return ['used' => 0, 'discount' => 0.0, 'codes' => []];
        }

        $codes = $rows->pluck('coupon_id')->filter()->map(fn (CouponUsage $usage): ?string => $usage->coupon?->code)->filter()->unique()->values()->all();

        return [
            'used' => CouponUsage::query()->where('customer_id', $customerId)->count(),
            'discount' => (float) $rows->sum('discount_amount'),
            'codes' => $codes,
        ];
    }

    /**
     * @return list<array{id: int, name: string, slug: string}>
     */
    private function segments(int $customerId): array
    {
        try {
            return DB::table('customer_segment_members')
                ->join('customer_segments', 'customer_segments.id', '=', 'customer_segment_members.customer_segment_id')
                ->where('customer_segment_members.customer_id', $customerId)
                ->get(['customer_segments.id', 'customer_segments.name', 'customer_segments.slug'])
                ->map(fn ($row): array => [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'slug' => (string) $row->slug,
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * RFM read-only dari riwayat pesanan berbayar (tanpa kolom baru).
     *
     * @return array<string, mixed>
     */
    public function rfm(int $customerId): array
    {
        $paid = $this->paidOrdersQuery($customerId)->orderByDesc('created_at')->get(['total', 'created_at']);
        if ($paid->isEmpty()) {
            return ['recency_days' => null, 'frequency' => 0, 'monetary' => 0.0, 'segment' => 'Belum berbelanja', 'score' => '000'];
        }
        $last = $paid->first()->created_at;
        $recency = $last ? max(0, (int) $last->diffInDays(now())) : null;
        $frequency = $paid->count();
        $monetary = (float) $paid->sum('total');
        $r = $recency === null ? 1 : ($recency <= 30 ? 5 : ($recency <= 90 ? 4 : ($recency <= 180 ? 3 : ($recency <= 365 ? 2 : 1))));
        $f = $frequency >= 10 ? 5 : ($frequency >= 5 ? 4 : ($frequency >= 3 ? 3 : ($frequency >= 2 ? 2 : 1)));
        $m = $monetary >= 10000000 ? 5 : ($monetary >= 5000000 ? 4 : ($monetary >= 1000000 ? 3 : ($monetary >= 250000 ? 2 : 1)));
        $segment = match (true) {
            $r >= 4 && $f >= 4 => 'Pelanggan juara',
            $r >= 3 && $f >= 3 => 'Pelanggan setia',
            $r <= 2 && $f >= 3 => 'Perlu perhatian kembali',
            $r <= 2 => 'Berisiko pergi',
            default => 'Pelanggan berkembang',
        };

        return ['recency_days' => $recency, 'frequency' => $frequency, 'monetary' => $monetary,
            'monetary_formatted' => Currency::format($monetary), 'segment' => $segment, 'score' => $r.$f.$m];
    }

    /** Kohort bulanan read-only: pesanan per bulan daftar. */
    public function cohort(int $customerId, int $months = 6): array
    {
        $driver = \DB::getDriverName();
        $monthExpr = $driver === 'sqlite' ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";
        $rows = $this->paidOrdersQuery($customerId)
            ->selectRaw($monthExpr.' as month, COUNT(*) as orders, SUM(total) as spend')
            ->groupBy('month')->orderByDesc('month')->limit(max(1, min(24, $months)))->get();

        return $rows->map(fn ($r) => ['month' => (string) $r->month, 'orders' => (int) $r->orders,
            'spend' => (float) $r->spend, 'spend_formatted' => Currency::format((float) $r->spend)])->all();
    }

    /** Funnel read-only: keranjang -> checkout -> bayar -> ulasan. */
    public function funnel(int $customerId): array
    {
        try {
            $carts = (int) \App\Models\Cart::where('customer_id', $customerId)->count();
        } catch (\Throwable) {
            $carts = 0;
        }
        $orders = (int) $this->orders($customerId)->count();
        $paid = (int) $this->paidOrdersQuery($customerId)->count();
        try {
            $reviews = (int) ProductReview::where('customer_id', $customerId)->count();
        } catch (\Throwable) {
            $reviews = 0;
        }

        return [
            ['stage' => 'Keranjang', 'count' => $carts],
            ['stage' => 'Pesanan dibuat', 'count' => $orders],
            ['stage' => 'Pesanan dibayar', 'count' => $paid],
            ['stage' => 'Ulasan ditulis', 'count' => $reviews],
        ];
    }

    /**
     * @return list<array{name: string, quantity: int, spend: float}>
     */
    private function topCategories(int $customerId): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.customer_id', $customerId)
            ->whereIn('orders.payment_status', ['paid', 'partial', 'refunded'])
            ->whereNotIn('orders.order_status', ['canceled', 'failed'])
            ->whereNotNull('products.category_id')
            ->groupBy('products.category_id', 'categories.name')
            ->selectRaw('products.category_id as category_id, COALESCE(categories.name, ?) as name, SUM(order_items.quantity) as quantity, SUM(order_items.sub_total) as spend', ['#'.$customerId])
            ->orderByDesc('quantity')
            ->limit(5)
            ->get();

        return $rows->map(fn ($row): array => [
            'name' => (string) $row->name,
            'quantity' => (int) $row->quantity,
            'spend' => (float) $row->spend,
            'spend_formatted' => Currency::format((float) $row->spend),
        ])->all();
    }

    /**
     * @return list<array{name: string, quantity: int, spend: float}>
     */
    private function topShops(int $customerId): array
    {
        $rows = DB::table('orders')
            ->join('shops', 'shops.id', '=', 'orders.shop_id')
            ->where('orders.customer_id', $customerId)
            ->whereIn('orders.payment_status', ['paid', 'partial', 'refunded'])
            ->whereNotIn('orders.order_status', ['canceled', 'failed'])
            ->groupBy('orders.shop_id', 'shops.name')
            ->selectRaw('orders.shop_id as shop_id, shops.name, COUNT(*) as orders, SUM(orders.total) as spend')
            ->orderByDesc('spend')
            ->limit(5)
            ->get();

        return $rows->map(fn ($row): array => [
            'name' => (string) $row->name,
            'orders' => (int) $row->orders,
            'spend' => (float) $row->spend,
            'spend_formatted' => Currency::format((float) $row->spend),
        ])->all();
    }
}
