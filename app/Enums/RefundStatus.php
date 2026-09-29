<?php

declare(strict_types=1);

namespace App\Enums;

/** Per-order-item refund lifecycle. */
enum RefundStatus: string
{
    case None = 'none';
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::None => '-',
            self::Requested => 'Diminta',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Refunded => 'Selesai',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::Requested => 'warning',
            self::Approved => 'info',
            self::Rejected => 'danger',
            self::Refunded => 'success',
        };
    }
}
