<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'refund_number', 'order_id', 'order_item_id', 'payment_group_id', 'provider_id',
    'gateway_refund_id', 'amount', 'currency', 'reason', 'status', 'requested_by_type',
    'requested_by', 'idempotency_key', 'gateway_response', 'failure_reason', 'succeeded_at',
    'attempts', 'last_attempt_at',
])]
class Refund extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'succeeded_at' => 'datetime',
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
        ];
    }

    public function scopeSucceeded(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUCCEEDED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_FAILED]);
    }

    public function scopeRetryable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public static function generateNumber(): string
    {
        return 'RF-'.date('Ymd').'-'.strtoupper(Str::random(10));
    }

    public function isSucceeded(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    public function isRetryable(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function paymentGroup(): BelongsTo
    {
        return $this->belongsTo(PaymentGroup::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
