<?php

declare(strict_types=1);

namespace App\Payments;

class PaymentTimeoutException extends PaymentException
{
    public function __construct(string $referenceId = '', array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct(
            'Permintaan pembayaran habis waktu. / Payment request timed out.',
            'Permintaan pembayaran habis waktu.',
            'Payment request timed out.',
            ['reference_id' => $referenceId] + $context,
            $previous
        );
    }
}
