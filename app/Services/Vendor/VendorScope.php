<?php

declare(strict_types=1);

namespace App\Services\Vendor;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Single source of truth for "who is the calling vendor".
 *
 * Every vendor controller resolves the shop through this class so a query can
 * never be written without an ownership scope attached to it.
 */
final class VendorScope
{
    public static function user(): User
    {
        $user = Auth::guard('vendor')->user() ?? Auth::user();

        abort_if(! $user instanceof User, 401);

        return $user;
    }

    public static function userId(): int
    {
        return (int) self::user()->getKey();
    }

    public static function shop(): Shop
    {
        $shop = self::user()->shop;

        abort_if($shop === null, 403, 'Toko belum terhubung dengan akun ini.');

        return $shop;
    }

    public static function shopId(): int
    {
        return (int) self::shop()->getKey();
    }

    public static function owns(int $shopId): bool
    {
        return $shopId === self::shopId();
    }

    public static function assertOwned(int $shopId): int
    {
        abort_if($shopId !== self::shopId(), 403);

        return $shopId;
    }

    /** Free-text scrubber used for every persisted vendor supplied string. */
    public static function clean(mixed $value, int $max = 255): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $text = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $value) ?? '');

        return mb_substr($text, 0, $max);
    }

    public static function cleanNullable(mixed $value, int $max = 255): ?string
    {
        $text = self::clean($value, $max);

        return $text === '' ? null : $text;
    }

    /**
     * Bank account numbers are never rendered in full: every caller receives
     * the same mask so a value leaked into a view, an export or a log line
     * still exposes at most the last four digits.
     */
    public static function maskAccount(?string $account): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $account) ?? '';

        if ($digits === '') {
            return $account === null || $account === '' ? null : '****';
        }

        if (strlen($digits) <= 4) {
            return str_repeat('*', strlen($digits));
        }

        return str_repeat('*', max(4, strlen($digits) - 4)).substr($digits, -4);
    }
}
