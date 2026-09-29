<?php

declare(strict_types=1);

namespace App\Services\Api;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Named limiters for the public API.
 *
 * Keys are per principal when a token is present and per IP otherwise, so one
 * noisy NAT peer cannot exhaust a signed-in integration's budget.
 */
final class RateLimitRegistry
{
    public const GLOBAL = 'api';

    public const AUTH = 'api:auth';

    public const WRITE = 'api:write';

    public const SEARCH = 'api:search';

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        RateLimiter::for(self::GLOBAL, static function (Request $request): Limit {
            return Limit::perMinute(120)->by(self::principalKey($request))->response(
                self::tooMany(),
                self::headers()
            );
        });

        RateLimiter::for(self::AUTH, static function (Request $request): Limit {
            $identity = strtolower((string) $request->input('email')).'|'.self::principalKey($request);

            return Limit::perMinute(10)->by('auth:'.$identity)->response(self::tooMany(), self::headers());
        });

        RateLimiter::for(self::WRITE, static function (Request $request): Limit {
            return Limit::perMinute(60)->by('write:'.self::principalKey($request))->response(
                self::tooMany(),
                self::headers()
            );
        });

        RateLimiter::for(self::SEARCH, static function (Request $request): Limit {
            return Limit::perMinute(60)->by('search:'.self::principalKey($request))->response(
                self::tooMany(),
                self::headers()
            );
        });
    }

    public static function ensure(): void
    {
        if (! RateLimiter::limiter(self::GLOBAL)) {
            self::$registered = false;
        }

        self::register();
    }

    public static function principalKey(Request $request): string
    {
        $user = $request->user();

        if ($user !== null && method_exists($user, 'getAuthIdentifier')) {
            return 'user:'.$user->getAuthIdentifier();
        }

        $apiKey = $request->attributes->get('api_key_id');

        if ($apiKey !== null) {
            return 'key:'.$apiKey;
        }

        return 'ip:'.(string) $request->ip();
    }

    private static function headers(): array
    {
        return ['Content-Type' => 'application/json'];
    }

    private static function tooMany(): \Illuminate\Http\JsonResponse
    {
        return \App\Support\ApiResponse::error(
            ErrorCodes::RATE_LIMITED,
            'Terlalu banyak permintaan. Coba lagi nanti.',
            429
        );
    }
}
