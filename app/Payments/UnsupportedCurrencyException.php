<?php

declare(strict_types=1);

namespace App\Payments;

class UnsupportedCurrencyException extends PaymentException
{
    public function __construct(string $currency, string $gateway = '', array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct(
            "Mata uang {$currency} tidak didukung".($gateway !== '' ? " oleh {$gateway}" : '').'. / Currency '.$currency.' is not supported'.($gateway !== '' ? " by {$gateway}" : '').'.',
            "Mata uang {$currency} tidak didukung.",
            "Currency {$currency} is not supported.",
            ['currency' => $currency, 'gateway' => $gateway] + $context,
            $previous
        );
    }
}
