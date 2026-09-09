<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupBuyParticipant extends Model
{
    protected $fillable = ['group_buy_id', 'customer_id'];
}
