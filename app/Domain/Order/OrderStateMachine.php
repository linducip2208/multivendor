<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for the order lifecycle.
 *
 * Controllers, the API, the vendor backoffice, POS and the webhook pipeline all
 * funnel through {@see OrderStateMachine::assertCanTransition()} so an invalid
 * transition is impossible regardless of entry point.
 */
final class OrderStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'pending' => ['payment_pending', 'paid', 'confirmed', 'cancel_requested', 'canceled', 'failed'],
        'payment_pending' => ['paid', 'canceled', 'failed', 'expired'],
        'paid' => ['processing', 'cancel_requested', 'confirmed'],
        'confirmed' => ['processing', 'packed', 'cancel_requested', 'canceled'],
        'processing' => ['packed', 'shipped', 'cancel_requested', 'canceled'],
        'packed' => ['shipped', 'processing', 'canceled'],
        'shipped' => ['delivered', 'return_requested'],
        'delivered' => ['completed', 'return_requested'],
        'completed' => ['return_requested'],
        'cancel_requested' => ['canceled', 'confirmed', 'paid', 'processing'],
        'canceled' => [],
        'return_requested' => ['returned', 'delivered', 'refund_pending'],
        'returned' => ['refund_pending', 'completed'],
        'refund_pending' => ['refunded', 'returned'],
        'refunded' => [],
        'failed' => ['pending', 'payment_pending'],
        'expired' => ['pending', 'payment_pending'],
    ];

    /** States that require the order to be paid before they may be entered. */
    private const REQUIRES_PAYMENT = [
        'confirmed', 'processing', 'packed', 'shipped', 'delivered', 'completed', 'returned', 'refund_pending', 'refunded',
    ];

    /** @return list<OrderStatus> */
    public static function allowedFrom(OrderStatus|string $from): array
    {
        $key = $from instanceof OrderStatus ? $from->stored() : (string) $from;

        return array_map(
            fn (string $s) => OrderStatus::fromStored($s),
            self::TRANSITIONS[$key] ?? []
        );
    }

    public static function canTransition(OrderStatus|string $from, OrderStatus|string $to): bool
    {
        $toKey = $to instanceof OrderStatus ? $to->stored() : (string) $to;

        return in_array($toKey, self::TRANSITIONS[$from instanceof OrderStatus ? $from->stored() : (string) $from] ?? [], true);
    }

    /**
     * @param  string|null  $paymentStatus  Current `orders.payment_status`
     *
     * @throws ValidationException when the transition is not permitted
     */
    public static function assertCanTransition(
        OrderStatus|string $from,
        OrderStatus|string $to,
        ?string $paymentStatus = null,
        bool $allowUnpaidException = false,
    ): void {
        $fromKey = $from instanceof OrderStatus ? $from->stored() : (string) $from;
        $toKey = $to instanceof OrderStatus ? $to->stored() : (string) $to;

        if ($fromKey === $toKey) {
            return;
        }

        if (! self::canTransition($fromKey, $toKey)) {
            $allowed = self::allowedFrom($fromKey);
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'Transisi status "%s" ke "%s" tidak diizinkan.',
                    OrderStatus::fromStored($fromKey)->label(),
                    OrderStatus::fromStored($toKey)->label(),
                ).($allowed !== [] ? ' Diizinkan: '.implode(', ', array_map(fn (OrderStatus $s) => $s->label(), $allowed)).'.' : ''),
            ]);
        }

        if (! $allowUnpaidException
            && in_array($toKey, self::REQUIRES_PAYMENT, true)
            && ! in_array((string) $paymentStatus, [PaymentStatus::Paid->value, PaymentStatus::Partial->value, PaymentStatus::Refunded->value], true)) {
            throw ValidationException::withMessages(['payment' => 'Pesanan belum dibayar.']);
        }
    }

    /**
     * Order of side effects executed after a transition commits.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function effectsFor(string $to): array
    {
        return match ($to) {
            'canceled' => [['service', 'restore_stock']],
            'delivered', 'completed' => [['service', 'settle_delivered']],
            'confirmed' => [['notify', 'order_confirmed']],
            'packed' => [['notify', 'order_packed']],
            'shipped' => [['notify', 'order_shipped'], ['event', 'OrderShipped']],
            'delivered' => [['event', 'OrderDelivered']],
            'completed' => [['event', 'OrderCompleted']],
            'canceled' => [['event', 'OrderCancelled']],
            'refunded' => [['event', 'RefundCompleted']],
            'returned' => [['event', 'ReturnCompleted']],
            default => [],
        };
    }

    public static function isTerminal(string $status): bool
    {
        return OrderStatus::fromStored($status)->isTerminal();
    }

    /** Cancel is only allowed while the order still holds stock. */
    public static function canCancel(OrderStatus|string $from): bool
    {
        return self::canTransition($from, OrderStatus::Cancelled);
    }
}
