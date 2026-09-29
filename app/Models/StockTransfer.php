<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['transfer_number', 'from_warehouse_id', 'to_warehouse_id', 'status', 'note', 'created_by', 'shipped_at', 'received_at'])]
class StockTransfer extends Model
{
    protected function casts(): array
    {
        return [
            'shipped_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canShip(): bool
    {
        return (string) $this->status === 'draft';
    }

    public function canReceive(): bool
    {
        return (string) $this->status === 'in_transit';
    }

    public function canCancel(): bool
    {
        return in_array((string) $this->status, ['draft', 'in_transit'], true);
    }
}
