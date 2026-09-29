<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupBuyParticipant extends Model
{
    protected $fillable = ['group_buy_id', 'customer_id'];

    public function groupBuy(): BelongsTo
    {
        return $this->belongsTo(GroupBuy::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
