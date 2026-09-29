<?php

declare(strict_types=1);

namespace App\Services\B2b;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu angsuran termin atas sebuah order B2B.
 *
 * Status tersimpan: `scheduled` | `partial` | `paid` (kosakata selaras
 * dengan `orders.payment_status`: unpaid/paid/partial/refunded).
 * Keterlambatan dihitung (computed), tidak disimpan.
 *
 * Tabel: `b2b_termins`.
 */
class B2bTermin extends Model
{
    protected $table = 'b2b_termins';

    public const SCHEDULED = 'scheduled';

    public const PARTIAL = 'partial';

    public const PAID = 'paid';

    public const LABELS = [
        'scheduled' => 'Dijadwalkan',
        'partial' => 'Dibayar sebagian',
        'paid' => 'Lunas',
    ];

    protected $fillable = [
        'order_id', 'shop_id', 'sequence', 'label', 'amount',
        'paid_amount', 'status', 'due_at', 'paid_at',
        'reminder_sent_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Order::class, 'order_id');
    }

    public function sisa(): float
    {
        return max(0.0, (float) $this->amount - (float) $this->paid_amount);
    }

    public function isLunas(): bool
    {
        return $this->sisa() <= 0.0 || $this->status === self::PAID;
    }

    public function isTerlambat(): bool
    {
        return ! $this->isLunas()
            && $this->due_at !== null
            && $this->due_at->isPast();
    }

    public function labelStatus(): string
    {
        if ($this->isLunas()) {
            return self::LABELS[self::PAID];
        }

        if ($this->isTerlambat()) {
            return 'Terlambat';
        }

        return self::LABELS[$this->status] ?? $this->status;
    }
}
