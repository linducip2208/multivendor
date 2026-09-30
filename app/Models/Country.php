<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = [
        'iso2', 'iso3', 'name', 'currency_code', 'locale', 'timezone',
        'is_active', 'payment_hints', 'shipping_hints',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'payment_hints' => 'array',
            'shipping_hints' => 'array',
        ];
    }

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function taxRules(): HasMany
    {
        return $this->hasMany(TaxRule::class);
    }
}
