<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Domain\Payment\PaymentStatusMachine;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentGroup;
use App\Models\Transaction;

final class PaymentGroupApplier
{
    public function applyWithinLock(
        PaymentGroup $group,
        PaymentStatus $incoming,
        array $payload = [],
        ?string $gatewayReference = null,
        ?string $note = null,
    ): void {
        $now = now();
        $reference = is_string($gatewayReference) && $gatewayReference !== '' ? $gatewayReference : null;

        $group->forceFill(array_filter([
            'status' => $incoming->value,
            'gateway_reference' => $reference ?? $group->gateway_reference,
            'gateway_response' => $payload !== [] ? $payload : $group->gateway_response,
            'paid_at' => $incoming === PaymentStatus::Paid ? ($group->paid_at ?? $now) : $group->paid_at,
            'expired_at' => $incoming === PaymentStatus::Paid
                ? null
                : ($incoming === PaymentStatus::Expired ? ($group->expired_at ?? $now) : $group->expired_at),
        ], static fn (mixed $value): bool => $value !== null))->save();

        $orders = $group->orders()->lockForUpdate()->get();

        foreach ($orders as $order) {
            $this->applyToOrder($order, $group, $incoming, $payload, $note);
        }
    }

    private function applyToOrder(
        Order $order,
        PaymentGroup $group,
        PaymentStatus $incoming,
        array $payload,
        ?string $note,
    ): void {
        $previous = $order->payment_status;

        $order->forceFill([
            'payment_status' => PaymentStatusMachine::orderPaymentStatus($incoming),
        ])->save();

        $now = now();
        $transactionStatus = PaymentStatusMachine::transactionStatus($incoming);

        $transactions = Transaction::where('order_id', $order->id)
            ->where('payment_group_id', $group->id)
            ->lockForUpdate()
            ->get();

        foreach ($transactions as $transaction) {
            $transaction->status = $transactionStatus;

            if ($payload !== []) {
                $transaction->payment_response = $payload;
            }

            if ($incoming === PaymentStatus::Paid && $transaction->paid_at === null) {
                $transaction->paid_at = $now;
            }

            $transaction->save();
        }

        $order->statusHistory()->create([
            'status' => 'payment_'.$incoming->value,
            'note' => $note ?? 'Status pembayaran diperbarui oleh gateway.',
        ]);

        if ($previous !== $order->payment_status) {
            $this->queuePaymentEvent($order, $incoming);
        }
    }

    private function queuePaymentEvent(Order $order, PaymentStatus $incoming): void
    {
        \App\Jobs\SendPaymentNotification::dispatch((int) $order->getKey(), 'payment_'.$incoming->value)->afterCommit();
    }
}
