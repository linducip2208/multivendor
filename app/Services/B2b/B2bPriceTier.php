<?php

declare(strict_types=1);

namespace App\Services\B2b;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris tier grosir: pembelian >= min_qty memakai `price`.
 *
 * Sengaja ditempatkan di namespace B2b agar scope eksklusif terpenuhi
 * (tidak menambah file di app/Models). Tabel: `b2b_price_tiers`.
 */
class B2bPriceTier extends Model
{
    protected $table = 'b2b_price_tiers';

    protected $fillable = [
        'product_id', 'shop_id', 'min_qty', 'price', 'note',
    ];

    protected function casts(): array
    {
        return [
            'min_qty' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id');
    }
}
