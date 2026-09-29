<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Shared query-string sanitising for the vendor back-office.
 *
 * Filter values arrive from a request and end up in a WHERE clause and a
 * hidden form field, so they are trimmed, length-capped and matched against a
 * whitelist before use.
 */
final class VendorScopeRequest
{
    public static function search(Request $request, string $key = 'search'): string
    {
        $value = $request->query($key);

        if (! is_string($value)) {
            return '';
        }

        return mb_substr(trim($value), 0, 120);
    }

    public static function stockFilter(Request $request): string
    {
        $value = (string) $request->query('stock', '');

        return in_array($value, ['', 'in', 'low', 'out'], true) ? $value : '';
    }

    public static function movementType(Request $request): string
    {
        $value = (string) $request->query('type', '');

        return in_array($value, ['in', 'out', 'adjustment'], true) ? $value : '';
    }

    public static function enum(Request $request, string $key, array $allowed, string $default = ''): string
    {
        $value = (string) $request->query($key, $default);

        return in_array($value, $allowed, true) ? $value : $default;
    }
}
