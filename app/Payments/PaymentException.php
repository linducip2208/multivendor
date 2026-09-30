<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * Basis error pembayaran ternormalisasi dengan pesan BI/EN.
 */
class PaymentException extends \RuntimeException
{
    public string $messageId;
    public string $messageEn;
    public array $context;

    public function __construct(
        string $message,
        string $messageId = 'Pembayaran gagal.',
        string $messageEn = 'Payment failed.',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->messageId = $messageId;
        $this->messageEn = $messageEn;
        $this->context = $context;
    }

    /** @return array{error:string,message_id:string,message_en:string,context:array} */
    public function toArray(): array
    {
        return [
            'error' => static::class,
            'message_id' => $this->messageId,
            'message_en' => $this->messageEn,
            'context' => $this->context,
        ];
    }
}
