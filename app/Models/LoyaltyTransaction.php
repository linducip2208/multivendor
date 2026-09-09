<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTransaction extends Model
{
    protected $fillable = ['customer_id', 'points', 'type', 'description', 'reference_type', 'reference_id'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
