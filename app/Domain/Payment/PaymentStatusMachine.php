<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Enums\PaymentStatus;

/**
 * Monotonic payment lifecycle.
 *
 * A gateway callback is untrusted, replayed and frequently out of order. Every
 * path that can move a payment (webhook, reconciliation poller, expiry job) must
 * funnel through {@see PaymentStatusMachine::decide()} so a late `expire` can
 * never demote a group that is already `paid`, and `paid` / `refunded` are never
 * left again.
 */
final class PaymentStatusMachine
{
    public const RESULT_APPLIED = 'processed';

    public const RESULT_UNCHANGED = 'ignored_unchanged';

    public const RESULT_DOWNGRADE = 'ignored_downgrade';

    public const RESULT_UNKNOWN = 'ignored_unknown';

    public static function resolve(mixed $status): ?PaymentStatus
    {
        if ($status instanceof PaymentStatus) {
            return $status;
        }

        if (! is_string($status)) {
            return null;
        }

        $status = trim($status);

        return $status === '' ? null : PaymentStatus::tryFrom($status);
    }

    public static function isTerminal(mixed $status): bool
    {
        return self::resolve($status)?->isLocked() ?? false;
    }

    public static function canApply(mixed $current, mixed $incoming): bool
    {
        return self::decide($current, $incoming)->shouldApply();
    }

    public static function decide(mixed $current, mixed $incoming): PaymentTransitionDecision
    {
        $target = self::resolve($incoming);
        $source = self::resolve($current);

        if ($target === null) {
            return PaymentTransitionDecision::of($source, PaymentStatus::Pending, false, self::RESULT_UNKNOWN);
        }

        if ($source === null) {
            return PaymentTransitionDecision::of(null, $target, true, self::RESULT_APPLIED);
        }

        if ($source === $target) {
            return PaymentTransitionDecision::of($source, $target, false, self::RESULT_UNCHANGED);
        }

        if ($target->rank() <= $source->rank()) {
            return PaymentTransitionDecision::of($source, $target, false, self::RESULT_DOWNGRADE);
        }

        return PaymentTransitionDecision::of($source, $target, true, self::RESULT_APPLIED);
    }

    public static function orderPaymentStatus(mixed $incoming): string
    {
        $status = self::resolve($incoming);

        return $status?->isSettled() === true ? $status->value : PaymentStatus::Unpaid->value;
    }

    public static function transactionStatus(mixed $incoming): string
    {
        return match (self::resolve($incoming)) {
            PaymentStatus::Paid, PaymentStatus::Partial => 'success',
            PaymentStatus::Refunded => 'refunded',
            PaymentStatus::Failed => 'failed',
            PaymentStatus::Expired => 'expired',
            default => 'pending',
        };
    }

    public static function requiresGatewayReference(mixed $status): bool
    {
        return in_array(self::resolve($status), [PaymentStatus::Paid, PaymentStatus::Refunded], true);
    }
}
