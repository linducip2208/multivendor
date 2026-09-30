<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grup/namespace terjemahan (mis. "common", "validation").
 * Tabel opsional: memperkaya metadata; kunci tetap valid tanpa grup.
 */
class TranslationGroup extends Model
{
    protected $fillable = [
        'slug',
        'description',
    ];

    public function keys(): HasMany
    {
        return $this->hasMany(TranslationKey::class, 'group_id');
    }
}
