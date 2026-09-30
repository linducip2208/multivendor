<?php

declare(strict_types=1);

namespace App\Shipping;

/** Pengecualian pengiriman BI/EN / Shipping exception. */
class ShippingException extends \RuntimeException
{
    public function __construct(string $messageId, string $messageEn, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($messageId.' / '.$messageEn, 0, $previous);
        $this->messageId = $messageId;
        $this->messageEn = $messageEn;
        $this->context = $context;
    }

    public string $messageId;

    public string $messageEn;

    public array $context;
}
