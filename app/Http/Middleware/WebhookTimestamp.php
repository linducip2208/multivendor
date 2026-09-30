<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validasi kesegaran timestamp webhook (aditif).
 * Header: X-Timestamp (unix detik). Stale (>300 dtk) -> 400 + dead-letter oleh controller.
 */
class WebhookTimestamp
{
    public const TOLERANCE_SECONDS = 300;

    public function handle(Request $request, Closure $next, int $tolerance = self::TOLERANCE_SECONDS): Response
    {
        $raw = trim((string) $request->header('X-Timestamp', ''));
        if ($raw === '') {
            return $next($request); // provider tanpa timestamp tetap diproses via signature
        }
        if (! ctype_digit($raw) || abs(time() - (int) $raw) > max(1, $tolerance)) {
            return ApiResponse::error(
                'stale_webhook',
                'Webhook kedaluwarsa (timestamp di luar toleransi). / Stale webhook timestamp.',
                400
            );
        }

        return $next($request);
    }

    public static function isFresh(mixed $timestamp, int $tolerance = self::TOLERANCE_SECONDS): bool
    {
        if (! is_numeric($timestamp)) {
            return false;
        }

        return abs(time() - (int) $timestamp) <= max(1, $tolerance);
    }
}
