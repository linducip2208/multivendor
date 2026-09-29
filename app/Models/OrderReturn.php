<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rma_number', 'order_id', 'order_item_id', 'reason', 'description', 'images', 'status', 'amount', 'decided_by', 'decided_at', 'admin_note'])]
class OrderReturn extends Model
{
    protected function casts(): array
    {
        return [
            'images' => 'array',
            'amount' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'requested';
    }

    /** Alasan retur terstruktur (disimpan pada kolom reason existing). */
    public const REASONS = [
        'rusak' => 'Barang rusak/cacat',
        'salah' => 'Salah varian/ukuran',
        'tidak_sesuai' => 'Tidak sesuai deskripsi',
        'kedaluwarsa' => 'Kedl./basi (FMCG)',
        'berubah_pikiran' => 'Berubah pikiran',
        'lainnya' => 'Lainnya',
    ];

    /** @return array<string,string> */
    public static function reasonLabels(): array
    {
        return self::REASONS;
    }

    public static function normalizeReason(?string $reason): string
    {
        $reason = is_string($reason) ? trim(mb_strtolower($reason)) : '';

        return array_key_exists($reason, self::REASONS) ? $reason : 'lainnya';
    }

    public function reasonLabel(): string
    {
        return self::REASONS[(string) $this->reason] ?? self::REASONS['lainnya'];
    }

    /** Analitik alasan retur per toko (group by kolom reason existing). */
    public static function analyticsForShop(int $shopId): array
    {
        $rows = static::query()
            ->join('orders', 'orders.id', '=', 'order_returns.order_id')
            ->where('orders.shop_id', $shopId)
            ->selectRaw('order_returns.reason, COUNT(*) as total, COALESCE(SUM(order_returns.amount),0) as nominal')
            ->groupBy('order_returns.reason')
            ->orderByDesc('total')
            ->get();

        $out = [];

        foreach (self::REASONS as $key => $label) {
            $row = $rows->firstWhere('reason', $key);
            $out[] = ['reason' => $key, 'label' => $label, 'total' => (int) ($row->total ?? 0), 'nominal' => (float) ($row->nominal ?? 0)];
        }

        return $out;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
