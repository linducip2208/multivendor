<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Payment lifecycle for both the master payment group and each vendor order.
 *
 * Ordering is intentional: `rank()` gives a monotonic comparison so a replayed
 * or out-of-order gateway callback can never move a payment backwards.
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case Paid = 'paid';
    case Partial = 'partial';
    case Refunded = 'refunded';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Belum Dibayar',
            self::Pending => 'Menunggu Pembayaran',
            self::Paid => 'Lunas',
            self::Partial => 'Sebagian',
            self::Refunded => 'Dikembalikan',
            self::Failed => 'Gagal',
            self::Expired => 'Kedaluwarsa',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Pending, self::Partial => 'warning',
            self::Failed, self::Expired => 'danger',
            self::Refunded => 'secondary',
            self::Unpaid => 'gray',
        };
    }

    /**
     * Monotonic rank. Higher wins. A callback may only move a payment to a
     * strictly greater rank, except to `failed` which is allowed from
     * pending/expired but never from paid/refunded.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Unpaid => 0,
            self::Pending => 1,
            self::Failed => 2,
            self::Expired => 2,
            self::Partial => 3,
            self::Paid => 4,
            self::Refunded => 5,
        };
    }

    /** States that must never be downgraded by a late or forged callback. */
    public function isLocked(): bool
    {
        return in_array($this, [self::Paid, self::Refunded], true);
    }

    public function isSettled(): bool
    {
        return in_array($this, [self::Paid, self::Partial, self::Refunded], true);
    }
}
