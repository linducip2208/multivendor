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

    // ── Pendalaman RMA: resolusi + restocking fee + refund proporsional ──

    /** Resolusi RMA: refund / replace / exchange (kolom admin_note existing). */
    public const RESOLUTIONS = [
        'refund' => 'Refund dana',
        'replace' => 'Ganti barang sama',
        'exchange' => 'Tukar barang lain',
    ];

    /** @return array<string,string> */
    public static function resolutionLabels(): array
    {
        return self::RESOLUTIONS;
    }

    public static function normalizeResolution(?string $resolution): string
    {
        $resolution = is_string($resolution) ? trim(mb_strtolower($resolution)) : '';

        return array_key_exists($resolution, self::RESOLUTIONS) ? $resolution : 'refund';
    }

    public function resolution(): string
    {
        $note = (string) ($this->getAttribute('admin_note') ?? '');

        if (preg_match('/\[resolusi:(refund|replace|exchange)\]/', $note, $m)) {
            return $m[1];
        }

        return 'refund';
    }

    /** Biaya restock: % dari amount, dibatasi amount (tak pernah minus). */
    public function restockingFee(float $percent): float
    {
        $percent = max(0.0, min(100.0, $percent));
        $amount = max(0.0, (float) ($this->getAttribute('amount') ?? 0));

        return round($amount * $percent / 100, 2);
    }

    /**
     * Refund proporsional ongkir+pajak per item terhadap subtotal order.
     *
     * @return array{shipping:float, tax:float, items:float, restocking_fee:float, total:float}
     */
    public function proportionalRefund(float $orderSubtotal, float $orderShipping, float $orderTax, float $restockingPercent = 0.0): array
    {
        $orderSubtotal = max(0.0, $orderSubtotal);
        $items = max(0.0, (float) ($this->getAttribute('amount') ?? 0));
        $ratio = $orderSubtotal > 0 ? min(1.0, $items / $orderSubtotal) : 0.0;
        $shipping = round(max(0.0, $orderShipping) * $ratio, 2);
        $tax = round(max(0.0, $orderTax) * $ratio, 2);
        $fee = $this->restockingFee($restockingPercent);

        return [
            'shipping' => $shipping,
            'tax' => $tax,
            'items' => $items,
            'restocking_fee' => $fee,
            'total' => round(max(0.0, $items + $shipping + $tax - $fee), 2),
        ];
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
