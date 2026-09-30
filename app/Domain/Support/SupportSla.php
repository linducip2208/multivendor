<?php

declare(strict_types=1);

namespace App\Domain\Support;

/**
 * Dukungan 3-arah (customer ↔ vendor ↔ support) + SLA + catatan internal.
 *
 * Tanpa kolom baru: peran dibaca dari tiket (customer_id/vendor_id/
 * assigned_to), catatan internal memakai prefiks "[INTERNAL]" pada
 * support_ticket_replies.message, SLA memakai priority+created_at existing.
 */
final class SupportSla
{
    public const INTERNAL_PREFIX = '[INTERNAL]';

    public const ROLES = ['customer', 'vendor', 'support'];

    /** @return array<string,int> jam SLA per prioritas */
    public static function targets(): array
    {
        return ['low' => 72, 'normal' => 48, 'medium' => 48, 'high' => 8, 'urgent' => 2];
    }

    public static function isInternal(string $message): bool
    {
        return str_starts_with(ltrim($message), self::INTERNAL_PREFIX);
    }

    public static function markInternal(string $message): string
    {
        return self::isInternal($message) ? $message : self::INTERNAL_PREFIX.' '.ltrim($message);
    }

    /** Balasan yang boleh dilihat customer (internal disaring). */
    public static function visibleForCustomer(string $message): ?string
    {
        return self::isInternal($message) ? null : $message;
    }

    /**
     * @return array{role:string|null, due_at:string|null, breached:bool}
     */
    public static function threadState(
        ?int $customerId,
        ?int $vendorId,
        ?int $assignedTo,
        int $senderId,
    ): array {
        $role = match (true) {
            $customerId !== null && $senderId === $customerId => 'customer',
            $vendorId !== null && $senderId === $vendorId => 'vendor',
            $assignedTo !== null && $senderId === $assignedTo => 'support',
            // Admin/CS tanpa assigned_to tetap dihitung support.
            $customerId !== null && $vendorId !== null && $senderId !== $customerId && $senderId !== $vendorId => 'support',
            default => null,
        };

        return ['role' => $role, 'due_at' => null, 'breached' => false];
    }
}
