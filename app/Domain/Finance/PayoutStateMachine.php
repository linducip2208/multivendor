<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use Illuminate\Validation\ValidationException;

/**
 * State machine payout vendor (withdraw request).
 *
 * pending → approved → processing → completed (uang keluar)
 * pending → rejected | approved → rejected
 * processing → failed → processing (retry) | failed → rejected
 *
 * Pure domain; transisi DB-nya di FinanceAdminService dengan lockForUpdate.
 */
final class PayoutStateMachine
{
    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        'pending' => ['approved', 'rejected'],
        'approved' => ['processing', 'rejected'],
        'processing' => ['completed', 'failed'],
        'failed' => ['processing', 'rejected'],
        'completed' => [],
        'rejected' => [],
    ];

    /** @return list<string> */
    public static function allowedFrom(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    public static function can(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertCan(string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        if (! self::can($from, $to)) {
            $allowed = self::allowedFrom($from);

            throw ValidationException::withMessages([
                'status' => sprintf(
                    'Payout tidak dapat berubah dari "%s" ke "%s".%s',
                    $from, $to,
                    $allowed !== [] ? ' Diizinkan: '.implode(', ', $allowed).'.' : ''
                ),
            ]);
        }
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, ['completed', 'rejected'], true);
    }
}
