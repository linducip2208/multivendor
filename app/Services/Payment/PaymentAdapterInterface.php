<?php

declare(strict_types=1);

namespace App\Services\Payment;

interface PaymentAdapterInterface
{
    public function createTransaction(array $payload): array;

    public function getTransactionStatus(string $transactionId): array;

    public function verifyCallback(array $requestData): bool;

    public function getChannels(): array;

    public function refund(string $gatewayRefundId, float $amount, array $options = []): array;

    public function cancel(string $gatewayPaymentId): array;
}
