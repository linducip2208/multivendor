<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan API (aditif; middleware existing TIDAK diubah).
 * Daftarkan manual pada grup/api yang membutuhkan karena routes/* tak disentuh:
 *   ->middleware([\App\Http\Middleware\ApiSecurityHeaders::class])
 */
class ApiSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        if (! $response->headers->has('X-Request-Id')) {
            $response->headers->set('X-Request-Id', (string) \Str::uuid());
        }

        return $response;
    }
}
