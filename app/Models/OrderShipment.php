<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'provider_id', 'courier', 'service', 'tracking_number', 'label_url', 'weight', 'cost', 'status', 'tracking_history', 'shipped_at', 'delivered_at', 'warehouse_id', 'is_pickup', 'pickup_code', 'pickup_verified_at', 'manifest_no', 'manifest_date'])]
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
            'is_pickup' => 'boolean',
            'pickup_verified_at' => 'datetime',
            'manifest_date' => 'date',
        ];
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    /** Pengiriman ambil di toko (click & collect): tanpa kurir, tanpa ongkir. */
    public function isPickup(): bool
    {
        return (bool) ($this->getAttribute('is_pickup') ?? false);
    }

    public function isPickupVerified(): bool
    {
        return $this->getAttribute('pickup_verified_at') !== null;
    }

    /** Gudang yang memenuhi pengiriman ini (kolom logistik lanjutan). */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function scopeManifest(Builder $query, string $manifestNo): Builder
    {
        return $query->where('manifest_no', $manifestNo);
    }

    public function scopePickup(Builder $query): Builder
    {
        return $query->where('is_pickup', true);
    }

    /** Baris rekap manifest: kurir + tanggal + nomor manifest. */
    public function manifestKey(): string
    {
        $courier = strtoupper(trim((string) ($this->courier ?? 'PICKUP')));
        $date = $this->manifest_date?->format('Y-m-d') ?? '-';

        return $courier.'|'.$date.'|'.(string) ($this->manifest_no ?? '-');
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
