<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['warehouse_id', 'product_id', 'product_variant_id', 'on_hand', 'reserved', 'incoming', 'safety_stock', 'last_counted_at'])]
class ProductStock extends Model
{
    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'reserved' => 'integer',
            'incoming' => 'integer',
            'safety_stock' => 'integer',
            'last_counted_at' => 'datetime',
        ];
    }

    public function available(): int
    {
        return (int) $this->on_hand - (int) $this->reserved;
    }

    // ── Pendalaman ledger states (aditif) ──

    /** State ledger yang didukung (available derivasi; damaged/returned virtual dari movements). */
    public const LEDGER_STATES = ['on_hand', 'reserved', 'available', 'damaged', 'returned', 'incoming'];

    /** Snapshot state lengkap baris ini (tak pernah negatif). */
    public function ledgerSnapshot(int $damaged = 0, int $returned = 0): array
    {
        return \App\Domain\Inventory\InventoryLedger::snapshot([
            'on_hand' => (int) $this->on_hand,
            'reserved' => (int) $this->reserved,
            'incoming' => (int) $this->incoming,
            'damaged' => $damaged,
            'returned' => $returned,
        ]);
    }

    public function scopeAvailable($query, int $min = 1)
    {
        return $query->whereRaw('(on_hand - reserved) >= ?', [$min]);
    }

    public function isLow(): bool
    {
        return $this->available() <= (int) $this->safety_stock;
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
