<?php

declare(strict_types=1);

namespace App\Payments;

class WebhookVerificationException extends PaymentException
{
    public function __construct(string $reason = '', array $context = [], ?\Throwable $previous = null)
    {
        $suffix = $reason !== '' ? " ({$reason})" : '';
        parent::__construct(
            "Verifikasi webhook gagal{$suffix}. / Webhook verification failed{$suffix}.",
            'Verifikasi webhook gagal.',
            'Webhook verification failed.',
            ['reason' => $reason] + $context,
            $previous
        );
    }
}
