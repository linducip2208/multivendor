<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyPoint extends Model
{
    protected $fillable = ['customer_id', 'points'];

    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }

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

class LoyaltyTransaction extends Model
{
    protected $fillable = ['customer_id', 'points', 'type', 'description', 'reference_type', 'reference_id'];
    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
}
