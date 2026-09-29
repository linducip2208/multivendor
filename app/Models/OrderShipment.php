<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'provider_id', 'courier', 'service', 'tracking_number', 'label_url', 'weight', 'cost', 'status', 'tracking_history', 'shipped_at', 'delivered_at'])]
class OrderShipment extends Model
{
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'cost' => 'decimal:2',
            'tracking_history' => 'array',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    /** Baris label massal: memakai kolom tracking existing. */
    public function labelData(): array
    {
        return [
            'order_id' => (int) $this->order_id,
            'kurir' => (string) ($this->courier ?? '-'),
            'layanan' => (string) ($this->service ?? '-'),
            'resi' => (string) ($this->tracking_number ?? '-'),
            'berat' => (float) ($this->weight ?? 0),
            'biaya' => (float) ($this->cost ?? 0),
        ];
    }

    /** Baris ekspor CSV fulfillment. */
    public function toExportRow(): array
    {
        return [
            $this->order?->order_number ?? ('#'.$this->order_id),
            (string) ($this->courier ?? ''),
            (string) ($this->service ?? ''),
            (string) ($this->tracking_number ?? ''),
            (string) ($this->status ?? ''),
            number_format((float) ($this->cost ?? 0), 2, '.', ''),
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
