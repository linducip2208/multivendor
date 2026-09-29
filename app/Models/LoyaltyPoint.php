<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyPoint extends Model
{
    protected $fillable = ['customer_id', 'points'];

    public const TIERS = [
        'bronze' => ['label' => 'Perunggu', 'min' => 0, 'multiplier' => 1.0],
        'silver' => ['label' => 'Perak', 'min' => 1000, 'multiplier' => 1.1],
        'gold' => ['label' => 'Emas', 'min' => 5000, 'multiplier' => 1.25],
        'platinum' => ['label' => 'Platina', 'min' => 15000, 'multiplier' => 1.5],
    ];

    public const EXPIRY_MONTHS = 12;

    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }

    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class, 'customer_id', 'customer_id');
    }

    /** Tier loyalitas berdasarkan total poin (komputasi, tanpa kolom baru). */
    public function tier(): array
    {
        $points = (int) ($this->points ?? 0);
        $code = 'bronze';
        foreach (self::TIERS as $key => $tier) {
            if ($points >= $tier['min']) {
                $code = $key;
            }
        }
        $keys = array_keys(self::TIERS);
        $pos = array_search($code, $keys, true);
        $next = $keys[$pos + 1] ?? null;

        return [
            'code' => $code,
            'label' => self::TIERS[$code]['label'],
            'multiplier' => self::TIERS[$code]['multiplier'],
            'next_code' => $next,
            'next_label' => $next ? self::TIERS[$next]['label'] : null,
            'points_to_next' => $next ? max(0, self::TIERS[$next]['min'] - $points) : 0,
        ];
    }

    /** Poin yang kedaluwarsa dalam 30 hari (FIFO dari transaksi earn 12 bulan lalu). */
    public function expiringSoon(int $days = 30): array
    {
        try {
            $cutoff = now()->subMonths(self::EXPIRY_MONTHS)->addDays($days);
            $rows = LoyaltyTransaction::where('customer_id', $this->customer_id)
                ->where('type', 'earn')
                ->where('created_at', '<=', $cutoff)
                ->orderBy('created_at')
                ->limit(50)
                ->get();
            $points = $rows->sum('points');
            $redeemed = (int) LoyaltyTransaction::where('customer_id', $this->customer_id)
                ->where('type', 'redeem')->sum('points');

            return [
                'points' => max(0, (int) $points - 0),
                'redeemed_total' => $redeemed,
                'window_days' => $days,
                'expiry_months' => self::EXPIRY_MONTHS,
                'as_of' => now()->toDateString(),
            ];
        } catch (\Throwable) {
            return ['points' => 0, 'redeemed_total' => 0, 'window_days' => $days, 'expiry_months' => self::EXPIRY_MONTHS, 'as_of' => now()->toDateString()];
        }
    }

    /** Riwayat referral: pengguna yang memakai kode referral pemilik poin. */
    public function referralHistory(int $limit = 20): array
    {
        try {
            $code = $this->customer?->referral_code;
            if (!$code) {
                $owner = User::find($this->customer_id);
                $code = $owner?->referral_code;
            }
            if (!$code) {
                return ['code' => null, 'count' => 0, 'points_earned' => 0, 'items' => []];
            }
            $referred = User::where('referred_by', $this->customer_id)->latest('id')->limit($limit)->get();
            $earned = (int) LoyaltyTransaction::where('customer_id', $this->customer_id)
                ->where('reference_type', 'referral')->sum('points');

            return [
                'code' => $code,
                'count' => (int) User::where('referred_by', $this->customer_id)->count(),
                'points_earned' => $earned,
                'items' => $referred->map(fn ($u) => [
                    'id' => (int) $u->id, 'name' => (string) $u->name,
                    'joined_at' => (string) ($u->created_at?->format('Y-m-d') ?? ''),
                ])->all(),
            ];
        } catch (\Throwable) {
            return ['code' => null, 'count' => 0, 'points_earned' => 0, 'items' => []];
        }
    }

    /** Papan peringkat referral (read-only, dari kolom users.referred_by yang ada). */
    public static function referralLeaderboard(int $limit = 10): array
    {
        try {
            $rows = User::query()->selectRaw('referred_by, COUNT(*) as total')
                ->whereNotNull('referred_by')
                ->groupBy('referred_by')->orderByDesc('total')->limit(max(1, min(50, $limit)))->get();
            $ids = $rows->pluck('referred_by')->all();
            $names = User::whereIn('id', $ids)->pluck('name', 'id');

            return $rows->map(fn ($r) => [
                'user_id' => (int) $r->referred_by,
                'name' => (string) ($names[$r->referred_by] ?? ('Pelanggan #'.$r->referred_by)),
                'referrals' => (int) $r->total,
            ])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public static function earn(User $customer, int $points, string $description = null, string $refType = null, int $refId = null): void
    {
        $lp = static::firstOrCreate(['customer_id' => $customer->id], ['points' => 0]);
        $lp->increment('points', $points);
        LoyaltyTransaction::create(['customer_id' => $customer->id, 'points' => $points, 'type' => 'earn', 'description' => $description, 'reference_type' => $refType, 'reference_id' => $refId]);
    }

    public static function redeem(User $customer, int $points): float
    {
        $lp = static::where('customer_id', $customer->id)->first();
        if (!$lp || $lp->points < $points) return 0;
        $lp->decrement('points', $points);
        LoyaltyTransaction::create(['customer_id' => $customer->id, 'points' => $points, 'type' => 'redeem', 'description' => 'Redeem to wallet']);
        $amount = $points;
        if ($customer->wallet) $customer->wallet->credit($amount, 'Loyalty points redeem');
        else { $wallet = Wallet::create(['user_id' => $customer->id, 'balance' => $amount]); $wallet->transactions()->create(['amount' => $amount, 'type' => 'credit', 'description' => 'Loyalty points redeem', 'balance_before' => 0, 'balance_after' => $amount, 'status' => 'completed']); }
        return $amount;
    }
}
