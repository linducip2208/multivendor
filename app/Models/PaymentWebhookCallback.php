<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider_id', 'payment_group_id', 'gateway_transaction_id', 'external_id', 'status',
    'payload', 'headers', 'received_at', 'processed_at', 'processing_result',
])]
class PaymentWebhookCallback extends Model
{
    protected function casts(): array
    {
        return ['payload' => 'array', 'headers' => 'array', 'received_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function paymentGroup(): BelongsTo
    {
        return $this->belongsTo(PaymentGroup::class);
    }
}
