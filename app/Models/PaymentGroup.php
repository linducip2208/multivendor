<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'payment_number', 'customer_id', 'provider_id', 'subtotal', 'tax', 'shipping_cost',
    'discount', 'grand_total', 'status', 'gateway_reference', 'gateway_response', 'paid_at', 'expired_at',
    'last_reconciled_at', 'reconciliation_attempts', 'reconciliation_note', 'refunded_at',
])]
class PaymentGroup extends Model
{
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2', 'tax' => 'decimal:2', 'shipping_cost' => 'decimal:2',
            'discount' => 'decimal:2', 'grand_total' => 'decimal:2', 'gateway_response' => 'array',
            'paid_at' => 'datetime', 'expired_at' => 'datetime',
            'last_reconciled_at' => 'datetime', 'reconciliation_attempts' => 'integer',
            'refunded_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'payment_group_orders')->withPivot('amount')->withTimestamps();
    }

    public function callbacks(): HasMany
    {
        return $this->hasMany(PaymentWebhookCallback::class);
    }

    /** Grup masih boleh dibuatkan ulang pembayaran gateway tanpa order baru. */
    public function isRetryable(): bool
    {
        return in_array((string) $this->status, ['pending', 'failed', 'expired'], true);
    }

    /** Selisih nominal callback vs grand total (untuk dashboard rekonsiliasi). */
    public function hasAmountMismatch(): bool
    {
        return $this->callbacks()
            ->where('processing_result', 'amount_mismatch')
            ->exists();
    }

    public function markReconciled(?string $note = null): void
    {
        $this->forceFill([
            'last_reconciled_at' => now(),
            'reconciliation_attempts' => ((int) $this->reconciliation_attempts) + 1,
            'reconciliation_note' => $note !== null ? mb_substr($note, 0, 500) : $this->reconciliation_note,
        ])->save();
    }

    public static function generateNumber(): string
    {
        do {
            $number = 'PAY-'.now()->format('Ymd').'-'.strtoupper(Str::random(10));
        } while (static::where('payment_number', $number)->exists());

        return $number;
    }
}
