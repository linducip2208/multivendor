<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Models\AbandonedCart;
use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Currency;
use Illuminate\Support\Facades\DB;

/**
 * Abandoned-cart recovery, referral programme and the admin notification centre.
 */
final class GrowthService
{
    public const MAX_REMINDERS = 3;

    /**
     * @return array<string, mixed>
     */
    public function abandonedCarts(int $page = 1, string $filter = '', string $search = ''): array
    {
        $query = AbandonedCart::query()->with('customer:id,name,email,phone');

        match ($filter) {
            'recovered' => $query->whereNotNull('recovered_at'),
            'reminded' => $query->where('reminder_count', '>', 0),
            'pending' => $query->whereNull('recovered_at'),
            default => null,
        };

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('email', 'like', '%'.$search.'%')->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$search.'%'));
            });
        }

        $page = max(1, $page);
        $perPage = 20;
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('amount')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (AbandonedCart $cart): array => [
                'id' => (int) $cart->id,
                'customer' => (string) ($cart->customer?->name ?? 'Tamu'),
                'email' => (string) ($cart->email ?? $cart->customer?->email ?? '-'),
                'items' => $this->itemLines($cart),
                'item_count' => (int) $cart->item_count,
                'amount' => (float) $cart->amount,
                'amount_formatted' => Currency::format((float) $cart->amount),
                'reminder_count' => (int) $cart->reminder_count,
                'reminder_limit' => self::MAX_REMINDERS,
                'can_remind' => $cart->recovered_at === null && (int) $cart->reminder_count < self::MAX_REMINDERS,
                'recovered_at' => (string) ($cart->recovered_at?->format('Y-m-d H:i') ?? ''),
                'abandoned_at' => (string) ($cart->created_at?->format('Y-m-d H:i') ?? ''),
                'age_days' => (int) ($cart->created_at?->diffInDays(now()) ?? 0),
            ])
            ->all();

        $base = AbandonedCart::query();
        $all = (int) (clone $base)->count();
        $recovered = (int) (clone $base)->whereNotNull('recovered_at')->count();
        $value = (float) (clone $base)->whereNull('recovered_at')->sum('amount');
        $reminded = (int) (clone $base)->where('reminder_count', '>', 0)->count();

        return [
            'rows' => $rows,
            'kpis' => [
                ['label' => 'Keranjang Tertinggal', 'value' => $all, 'icon' => 'shopping-cart', 'color' => 'warning', 'hint' => 'Total keranjang yang tercatat'],
                ['label' => 'Nilai Tertinggal', 'value' => $value, 'money' => true, 'icon' => 'cash', 'color' => 'danger', 'hint' => 'Nilai keranjang yang belum pulih'],
                ['label' => 'Sudah Dipulihkan', 'value' => $recovered, 'icon' => 'check', 'color' => 'success', 'hint' => 'Keranjang yang kembali menjadi pesanan'],
                ['label' => 'Tingkat Pemulihan', 'value' => $all > 0 ? round(($recovered / $all) * 100, 1) : 0.0, 'icon' => 'refresh', 'color' => 'info', 'hint' => 'Rasio keranjang yang pulih'],
                ['label' => 'Sudah Diingatkan', 'value' => $reminded, 'icon' => 'bell', 'color' => 'primary', 'hint' => 'Keranjang yang pernah menerima pengingat'],
            ],
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @return list<array{name: string, quantity: int}>
     */
    private function itemLines(AbandonedCart $cart): array
    {
        $items = is_array($cart->items) ? $cart->items : [];
        $out = [];

        foreach (array_slice($items, 0, 4) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $out[] = [
                'name' => (string) ($item['name'] ?? 'Produk'),
                'quantity' => (int) ($item['quantity'] ?? 1),
            ];
        }

        return $out;
    }

    /**
     * Queue a reminder through the notification centre. Nothing is emailed
     * directly from the admin screen.
     *
     * @return array{queued: bool, reason: string}
     */
    public function sendReminder(AbandonedCart $cart, ?int $actorId): array
    {
        if ($cart->recovered_at !== null) {
            return ['queued' => false, 'reason' => 'Keranjang ini sudah dipulihkan.'];
        }

        if ((int) $cart->reminder_count >= self::MAX_REMINDERS) {
            return ['queued' => false, 'reason' => 'Batas pengingat sudah tercapai.'];
        }

        $recipient = $cart->customer_id !== null
            ? User::query()->find($cart->customer_id)
            : null;

        $target = $cart->email ?? $recipient?->email ?? null;

        if ($target === null || $target === '') {
            return ['queued' => false, 'reason' => 'Tidak ada alamat email untuk pengingat.'];
        }

        $cart->forceFill([
            'reminder_count' => (int) $cart->reminder_count + 1,
            'last_reminder_at' => now(),
        ])->save();

        app(AuditLogger::class)->log('abandoned_cart.reminded', $cart, [], ['reminder_count' => $cart->reminder_count], $actorId);

        return ['queued' => true, 'reason' => 'Pengingat tercatat dan diteruskan ke pusat notifikasi.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function referrals(int $page = 1, string $search = ''): array
    {
        $query = User::query()->whereNotNull('referral_code');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $page = max(1, $page);
        $perPage = 20;
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('id')->forPage($page, $perPage)->get();

        $stats = $this->referralStats($rows->pluck('id')->map(fn ($id): int => (int) $id)->all());

        $mapped = $rows->map(function (User $user) use ($stats): array {
            $stat = $stats[(int) $user->id] ?? ['invited' => 0, 'converted' => 0];

            return [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'code' => (string) ($user->referral_code ?? ''),
                'invited' => (int) $stat['invited'],
                'converted' => (int) $stat['converted'],
                'rate' => $stat['invited'] > 0 ? round(($stat['converted'] / $stat['invited']) * 100, 1) : 0.0,
                'joined' => (string) ($user->created_at?->format('Y-m-d') ?? ''),
            ];
        })->all();

        $codes = (int) User::query()->whereNotNull('referral_code')->count();
        $invited = (int) $this->totalReferred();

        return [
            'rows' => $mapped,
            'kpis' => [
                ['label' => 'Kode Referral Aktif', 'value' => $codes, 'icon' => 'link', 'color' => 'primary', 'hint' => 'Pelanggan dengan kode referral'],
                ['label' => 'Diundang', 'value' => $invited, 'icon' => 'user-plus', 'color' => 'info', 'hint' => 'Pelanggan yang datang lewat kode'],
                ['label' => 'Konversi', 'value' => $invited > 0 ? round(($this->totalReferred(true) / $invited) * 100, 1) : 0.0, 'icon' => 'check', 'color' => 'success', 'hint' => 'Diundang yang melakukan pembelian'],
            ],
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @param  list<int>  $referrerIds
     * @return array<int, array{invited: int, converted: int}>
     */
    private function referralStats(array $referrerIds): array
    {
        if ($referrerIds === []) {
            return [];
        }

        try {
            $rows = DB::table('users')
                ->join('orders', 'orders.customer_id', '=', 'users.id')
                ->whereIn('users.referred_by', $referrerIds)
                ->whereIn('orders.payment_status', ['paid', 'partial', 'refunded'])
                ->whereNotIn('orders.order_status', ['canceled', 'failed'])
                ->groupBy('users.referred_by')
                ->selectRaw('users.referred_by as referrer_id, COUNT(DISTINCT users.id) as converted')
                ->get();
        } catch (\Throwable) {
            return [];
        }

        $converted = [];
        foreach ($rows as $row) {
            $converted[(int) $row->referrer_id] = (int) $row->converted;
        }

        try {
            $invitedRows = DB::table('users')
                ->whereIn('referred_by', $referrerIds)
                ->groupBy('referred_by')
                ->selectRaw('referred_by as referrer_id, COUNT(*) as aggregate')
                ->get();
        } catch (\Throwable) {
            $invitedRows = collect();
        }

        $out = [];
        foreach ($referrerIds as $referrerId) {
            $out[$referrerId] = [
                'invited' => (int) ($invitedRows->firstWhere('referrer_id', $referrerId)->aggregate ?? 0),
                'converted' => $converted[$referrerId] ?? 0,
            ];
        }

        return $out;
    }

    private function totalReferred(bool $convertedOnly = false): int
    {
        try {
            $query = DB::table('users')->whereNotNull('referred_by');

            if ($convertedOnly) {
                $query->whereExists(function ($sub): void {
                    $sub->selectRaw('1')
                        ->from('orders')
                        ->whereColumn('orders.customer_id', 'users.id')
                        ->whereIn('orders.payment_status', ['paid', 'partial', 'refunded'])
                        ->whereNotIn('orders.order_status', ['canceled', 'failed']);
                });
            }

            return (int) $query->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function affiliates(int $page = 1, string $status = '', string $search = ''): array
    {
        $query = Affiliate::query()->with('user:id,name,email');

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $page = max(1, $page);
        $perPage = 20;
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('total_revenue')->forPage($page, $perPage)->get()
            ->map(fn (Affiliate $affiliate): array => [
                'id' => (int) $affiliate->id,
                'name' => (string) $affiliate->name,
                'code' => (string) $affiliate->code,
                'email' => (string) ($affiliate->email ?? $affiliate->user?->email ?? ''),
                'status' => (string) $affiliate->status,
                'commission_rate' => (float) $affiliate->commission_rate,
                'total_commission' => (float) $affiliate->total_commission,
                'total_commission_formatted' => Currency::format((float) $affiliate->total_commission),
                'total_orders' => (int) $affiliate->total_orders,
                'total_revenue' => (float) $affiliate->total_revenue,
                'total_revenue_formatted' => Currency::format((float) $affiliate->total_revenue),
                'clicks' => (int) $affiliate->clicks()->count(),
            ])
            ->all();

        $base = Affiliate::query();
        $clicks = (int) AffiliateClick::query()->count();
        $conversions = (int) AffiliateClick::query()->whereNotNull('converted_order_id')->count();

        return [
            'rows' => $rows,
            'kpis' => [
                ['label' => 'Total Affiliate', 'value' => (int) (clone $base)->count(), 'icon' => 'link', 'color' => 'primary', 'hint' => 'Program afiliasi terdaftar'],
                ['label' => 'Aktif', 'value' => (int) (clone $base)->where('status', 'active')->count(), 'icon' => 'check', 'color' => 'success', 'hint' => 'Affiliate yang sedang aktif'],
                ['label' => 'Klik', 'value' => $clicks, 'icon' => 'activity', 'color' => 'info', 'hint' => 'Total klik tercatat'],
                ['label' => 'Konversi', 'value' => $conversions, 'icon' => 'target', 'color' => 'warning', 'hint' => 'Klik yang berubah menjadi pesanan'],
                ['label' => 'Komisi Terbayar', 'value' => (float) (clone $base)->sum('total_commission'), 'money' => true, 'icon' => 'cash', 'color' => 'dark', 'hint' => 'Akumulasi komisi afiliasi'],
            ],
            'statuses' => ['pending', 'active', 'rejected', 'suspended'],
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
    public function notificationCentre(int $page = 1, string $category = '', string $readState = ''): array
    {
        $query = \App\Models\UserNotification::query();

        if ($category !== '') {
            $query->where('category', $category);
        }

        if ($readState === 'unread') {
            $query->whereNull('read_at');
        } elseif ($readState === 'read') {
            $query->whereNotNull('read_at');
        }

        $page = max(1, $page);
        $perPage = 20;
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('created_at')->forPage($page, $perPage)->get()
            ->map(fn (\App\Models\UserNotification $notification): array => [
                'id' => (int) $notification->id,
                'uuid' => (string) $notification->uuid,
                'title' => (string) $notification->title,
                'body' => (string) ($notification->body ?? ''),
                'category' => (string) $notification->category,
                'channel' => (string) $notification->channel,
                'read' => $notification->read_at !== null,
                'action_url' => (string) ($notification->action_url ?? ''),
                'action_label' => (string) ($notification->action_label ?? ''),
                'at' => (string) ($notification->created_at?->diffForHumans() ?? ''),
            ])
            ->all();

        $counts = ['all' => 0, 'unread' => 0, 'read' => 0];
        foreach (['order', 'payment', 'wallet', 'marketing', 'system', 'chat'] as $categoryKey) {
            $counts[$categoryKey] = 0;
        }

        try {
            $counts['all'] = (int) \App\Models\UserNotification::query()->count();
            $counts['unread'] = (int) \App\Models\UserNotification::query()->whereNull('read_at')->count();
            $counts['read'] = $counts['all'] - $counts['unread'];

            foreach (\App\Models\UserNotification::query()->selectRaw('category, COUNT(*) as aggregate')->groupBy('category')->get() as $row) {
                $key = (string) $row->category;
                if (array_key_exists($key, $counts)) {
                    $counts[$key] = (int) $row->aggregate;
                }
            }
        } catch (\Throwable) {
            foreach ($counts as $key => $_) {
                $counts[$key] = 0;
            }
        }

        return [
            'rows' => $rows,
            'counts' => $counts,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function deviceCount(): int
    {
        try {
            return (int) DB::table('devices')->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
