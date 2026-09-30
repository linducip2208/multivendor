<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Mata uang untuk multi-currency engine.
 *
 * Kurs: exchange_rate = IDR per 1 major unit (string desimal, tanpa float).
 */
#[Fillable([
    'code',
    'name',
    'symbol',
    'decimal_places',
    'decimal_separator',
    'thousand_separator',
    'exchange_rate',
    'rate_source',
    'rate_updated_at',
    'is_default',
    'is_active',
])]
class Currency extends Model
{
    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'exchange_rate' => 'decimal:8',
            'rate_updated_at' => 'datetime',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Normalisasi kode ke uppercase saat di-set. */
    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    /** @return \Illuminate\Database\Eloquent\Builder<static> */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<static> */
    public function scopeOrdered($query)
    {
        return $query->orderBy('is_default', 'desc')->orderBy('code');
    }

    /** Faktor minor-units: 10^decimal_places (int, tanpa float). */
    public function minorFactor(): int
    {
        return (int) (10 ** max(0, (int) $this->decimal_places));
    }
}
