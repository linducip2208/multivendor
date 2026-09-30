<?php

declare(strict_types=1);

namespace App\Payments;

class PaymentDeclinedException extends PaymentException
{
    public function __construct(string $referenceId = '', array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct(
            'Pembayaran ditolak oleh gateway. / Payment was declined by the gateway.',
            'Pembayaran ditolak oleh gateway.',
            'Payment was declined by the gateway.',
            ['reference_id' => $referenceId] + $context,
            $previous
        );
    }
}
