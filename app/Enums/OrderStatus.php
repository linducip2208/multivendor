<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Authoritative order lifecycle.
 *
 * Backwards compatible with the legacy `orders.order_status` enum
 * (pending / confirmed / processing / shipped / delivered / canceled /
 * returned / failed) while adding the states a real marketplace needs.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case PaymentPending = 'payment_pending';
    case Paid = 'paid';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case CancelRequested = 'cancel_requested';
    case Cancelled = 'cancelled';
    case ReturnRequested = 'return_requested';
    case Returned = 'returned';
    case RefundPending = 'refund_pending';
    case Refunded = 'refunded';
    case Failed = 'failed';

    /** Canonical database value. `canceled` stays the stored spelling for BC. */
    public function stored(): string
    {
        return $this === self::Cancelled ? 'canceled' : $this->value;
    }

    public static function fromStored(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Pending;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::PaymentPending => 'Menunggu Pembayaran',
            self::Paid => 'Sudah Dibayar',
            self::Confirmed => 'Dikonfirmasi',
            self::Processing => 'Diproses',
            self::Packed => 'Dikemas',
            self::Shipped => 'Dikirim',
            self::Delivered => 'Diterima',
            self::Completed => 'Selesai',
            self::CancelRequested => 'Permintaan Pembatalan',
            self::Cancelled => 'Dibatalkan',
            self::ReturnRequested => 'Permintaan Retur',
            self::Returned => 'Dikembalikan',
            self::RefundPending => 'Refund Diproses',
            self::Refunded => 'Dikembalikan Danua',
            self::Failed => 'Gagal',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending, self::PaymentPending, self::CancelRequested, self::ReturnRequested, self::RefundPending => 'warning',
            self::Paid, self::Confirmed, self::Processing, self::Packed => 'info',
            self::Shipped, self::Delivered => 'primary',
            self::Completed => 'success',
            self::Cancelled, self::Failed, self::Returned, self::Refunded => 'secondary',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed, self::Cancelled, self::Returned, self::Refunded, self::Failed,
        ], true);
    }

    /** States where the order occupies stock and may still be cancelled. */
    public function holdsStock(): bool
    {
        return ! in_array($this, [
            self::Cancelled, self::Failed, self::Refunded, self::Returned,
        ], true);
    }

    public function isPaid(): bool
    {
        return in_array($this, [
            self::Paid, self::Confirmed, self::Processing, self::Packed, self::Shipped, self::Delivered, self::Completed, self::ReturnRequested, self::Returned, self::RefundPending, self::Refunded,
        ], true);
    }
}
