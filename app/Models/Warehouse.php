<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'code', 'address', 'city', 'province', 'postal_code', 'country', 'phone', 'manager_name', 'shop_id', 'is_default', 'is_active', 'allow_pickup', 'pickup_hours', 'pickup_address'])]
class Warehouse extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'allow_pickup' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Gudang yang melayani ambil di toko (click & collect). */
    public function scopePickupable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('allow_pickup', true);
    }

    /** Gudang utama sebagai fallback alokasi otomatis. */
    public function scopeOrderedForAllocation(Builder $query): Builder
    {
        return $query->orderBy('is_default', 'desc')->orderBy('name');
    }

    /** Alamat pengambilan: alamat khusus pickup bila diisi, bila tidak alamat gudang. */
    public function pickupAddressLabel(): string
    {
        $specific = trim((string) ($this->getAttribute('pickup_address') ?? ''));

        if ($specific !== '') {
            return $specific;
        }

        return trim(implode(', ', array_filter([
            (string) ($this->address ?? ''),
            (string) ($this->city ?? ''),
            (string) ($this->province ?? ''),
            (string) ($this->postal_code ?? ''),
        ])));
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
