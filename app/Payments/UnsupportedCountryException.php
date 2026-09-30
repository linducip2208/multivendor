<?php

declare(strict_types=1);

namespace App\Payments;

class UnsupportedCountryException extends PaymentException
{
    public function __construct(string $country, string $gateway = '', array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct(
            "Negara {$country} tidak didukung".($gateway !== '' ? " oleh {$gateway}" : '').'. / Country '.$country.' is not supported'.($gateway !== '' ? " by {$gateway}" : '').'.',
            "Negara {$country} tidak didukung.",
            "Country {$country} is not supported.",
            ['country' => $country, 'gateway' => $gateway] + $context,
            $previous
        );
    }
}
