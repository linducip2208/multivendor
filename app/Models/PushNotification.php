<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushNotification extends Model
{
    protected $fillable = ['title', 'description', 'image', 'target_url', 'target_type', 'target_ids', 'sent', 'sent_at'];

    protected function casts(): array
    {
        return ['target_ids' => 'json', 'sent' => 'boolean', 'sent_at' => 'datetime'];
    }
}
