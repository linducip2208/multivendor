<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Models\LoyaltyPoint;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Support\Currency;
use Illuminate\Support\Facades\DB;

/**
 * Loyalty programme overview and the customer conversation desk.
 *
 * The inbox keeps internal notes separate from customer-visible replies and
 * tracks per-participant unread counts so an operator can see what is still
 * unanswered.
 */
final class CrmService
{
    /**
     * @return array<string, mixed>
     */
    public function loyaltyOverview(int $perPage = 20, int $page = 1, string $search = ''): array
    {
        $totalMembers = (int) LoyaltyPoint::query()->count();
        $pointsOutstanding = (int) LoyaltyPoint::query()->sum('points');
        $earned = (int) LoyaltyTransaction::query()->where('type', 'earn')->sum('points');
        $redeemed = (int) LoyaltyTransaction::query()->where('type', 'redeem')->sum('points');

        $query = LoyaltyPoint::query()->with('customer:id,name,email');

        if ($search !== '') {
            $query->whereHas('customer', fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
        }

        $perPage = max(5, min(100, $perPage));
        $page = max(1, $page);
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('points')->forPage($page, $perPage)->get()
            ->map(fn (LoyaltyPoint $point): array => [
                'customer_id' => (int) $point->customer_id,
                'name' => (string) ($point->customer?->name ?? 'Pelanggan #'.$point->customer_id),
                'email' => (string) ($point->customer?->email ?? ''),
                'points' => (int) $point->points,
                'points_formatted' => Currency::number((int) $point->points),
                'tier' => $this->tier((int) $point->points),
                'url' => route('admin.customers.360', $point->customer_id),
            ])
            ->all();

        $recent = LoyaltyTransaction::query()
            ->with('customer:id,name')
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn (LoyaltyTransaction $transaction): array => [
                'id' => (int) $transaction->id,
                'customer' => (string) ($transaction->customer?->name ?? '-'),
                'points' => (int) $transaction->points,
                'type' => (string) $transaction->type,
                'description' => (string) ($transaction->description ?? ''),
                'at' => (string) ($transaction->created_at?->format('Y-m-d H:i') ?? ''),
            ])
            ->all();

        return [
            'kpis' => [
                ['label' => 'Peserta', 'value' => $totalMembers, 'icon' => 'users', 'color' => 'primary', 'hint' => 'Pelanggan dengan poinloyalty'],
                ['label' => 'Poin Beredar', 'value' => $pointsOutstanding, 'icon' => 'award', 'color' => 'success', 'hint' => 'Total poin yang belum ditukar'],
                ['label' => 'Poin Diperoleh', 'value' => $earned, 'icon' => 'trending-up', 'color' => 'info', 'hint' => 'Akumulasi poin dari transaksi'],
                ['label' => 'Poin Ditukar', 'value' => $redeemed, 'icon' => 'undo', 'color' => 'warning', 'hint' => 'Poin yang sudah diredeem'],
            ],
            'rows' => $rows,
            'recent' => $recent,
            'tiers' => $this->tierDistribution(),
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @return list<array{name: string, count: int}>
     */
    private function tierDistribution(): array
    {
        $rows = LoyaltyPoint::query()->get(['points']);

        $buckets = ['Perunggu' => 0, 'Perak' => 0, 'Emas' => 0, 'Platinum' => 0];

        foreach ($rows as $row) {
            $buckets[$this->tier((int) $row->points)]++;
        }

        $out = [];
        foreach ($buckets as $name => $count) {
            $out[] = ['name' => $name, 'count' => $count];
        }

        return $out;
    }

    public function tier(int $points): string
    {
        return match (true) {
            $points >= 10000 => 'Platinum',
            $points >= 5000 => 'Emas',
            $points >= 1000 => 'Perak',
            default => 'Perunggu',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function wishlistOverview(int $perPage = 25, int $page = 1, string $search = ''): array
    {
        try {
            $query = DB::table('wishlist')
                ->join('users', 'users.id', '=', 'wishlist.customer_id')
                ->join('products', 'products.id', '=', 'wishlist.product_id')
                ->select('wishlist.id', 'users.name as customer_name', 'users.email as customer_email', 'products.name as product_name', 'products.price', 'products.current_stock', 'wishlist.created_at');
        } catch (\Throwable) {
            return ['rows' => [], 'kpis' => [], 'pagination' => ['total' => 0, 'per_page' => $perPage, 'current_page' => $page, 'last_page' => 1]];
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('users.name', 'like', '%'.$search.'%')
                    ->orWhere('products.name', 'like', '%'.$search.'%');
            });
        }

        $perPage = max(5, min(100, $perPage));
        $page = max(1, $page);
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('wishlist.id')->forPage($page, $perPage)->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'customer' => (string) $row->customer_name,
                'email' => (string) $row->customer_email,
                'product' => (string) $row->product_name,
                'price' => (float) $row->price,
                'price_formatted' => Currency::format((float) $row->price),
                'stock' => (int) $row->current_stock,
                'at' => (string) $row->created_at,
            ])
            ->all();

        $topProducts = (clone $query)
            ->select('products.name as product_name')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('products.name')
            ->orderByDesc('aggregate')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => ['name' => (string) $row->product_name, 'count' => (int) $row->aggregate])
            ->all();

        return [
            'rows' => $rows,
            'kpis' => [
                ['label' => 'Total Wishlist', 'value' => $total, 'icon' => 'heart', 'color' => 'danger', 'hint' => 'Produk yang ditandai pelanggan'],
                ['label' => 'Produk Paling Disukai', 'value' => $topProducts[0]['name'] ?? '-', 'icon' => 'award', 'color' => 'warning', 'hint' => 'Peringkat pertama wishlist'],
                ['label' => 'Jumlah Produk', 'value' => count($topProducts), 'icon' => 'package', 'color' => 'info', 'hint' => 'Produk berbeda dalam 8 teratas'],
            ],
            'top_products' => $topProducts,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function customerList(int $perPage = 20, int $page = 1, string $search = ''): array
    {
        $query = User::query()->where('role', 'customer');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        $perPage = max(5, min(100, $perPage));
        $page = max(1, $page);
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('id')->forPage($page, $perPage)->get()
            ->map(fn (User $customer): array => [
                'id' => (int) $customer->id,
                'name' => (string) $customer->name,
                'email' => (string) $customer->email,
                'phone' => (string) ($customer->phone ?? ''),
                'status' => (string) ($customer->status ?? ''),
                'orders' => (int) $customer->orders()->count(),
                'spend' => (float) $customer->orders()->whereIn('payment_status', ['paid', 'partial', 'refunded'])->sum('total'),
                'joined' => (string) ($customer->created_at?->format('Y-m-d') ?? ''),
                'url' => route('admin.customers.360', $customer->id),
            ])
            ->all();

        return [
            'rows' => $rows,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }
}
