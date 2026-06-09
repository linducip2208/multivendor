<?php

namespace App\Services\Payment;

interface PaymentAdapterInterface
{
    public function createTransaction(array $payload): array;
    public function getTransactionStatus(string $transactionId): array;
    public function verifyCallback(array $requestData): bool;
    public function getChannels(): array;
}
