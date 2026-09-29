<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rma_number', 'order_id', 'order_item_id', 'reason', 'description', 'images', 'status', 'amount', 'decided_by', 'decided_at', 'admin_note', 'qc_grade', 'qc_note', 'qc_at', 'qc_by', 'stock_restored_qty'])]
class OrderReturn extends Model
{
    protected function casts(): array
    {
        return [
            'images' => 'array',
            'amount' => 'decimal:2',
            'decided_at' => 'datetime',
            'qc_at' => 'datetime',
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

    /**
     * Grading QC retur (aditif): baik = stok kembali, rusak = ditahan tanpa
     * menambah stok, buang = dimusnahkan tanpa menambah stok. Setiap grade
     * tercatat di stock_movements oleh RefundWorkflowService::gradeReturn().
     */
    public const QC_GRADES = [
        'baik' => 'Baik — kembali ke stok',
        'rusak' => 'Rusak — ditahan, tidak kembali ke stok',
        'buang' => 'Buang — dimusnahkan, tidak kembali ke stok',
    ];

    /** @return array<string,string> */
    public static function qcGradeLabels(): array
    {
        return self::QC_GRADES;
    }

    public static function normalizeQcGrade(?string $grade): string
    {
        $grade = is_string($grade) ? trim(mb_strtolower($grade)) : '';

        return array_key_exists($grade, self::QC_GRADES) ? $grade : '';
    }

    public function isQcDone(): bool
    {
        return ((string) ($this->getAttribute('qc_grade') ?? '')) !== '';
    }

    public function qcGradeLabel(): ?string
    {
        $grade = (string) ($this->getAttribute('qc_grade') ?? '');

        return $grade === '' ? null : (self::QC_GRADES[$grade] ?? $grade);
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
