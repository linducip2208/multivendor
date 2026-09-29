<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks a route as exempt from the licence gate.
 *
 * Gateway callbacks must always reach the application: an unlicensed or
 * temporarily offline install still has to record the money it was paid, and
 * redirecting a webhook to `/__pair` makes the gateway treat the delivery as
 * failed and never retry. The licence check itself is unaffected for the rest
 * of the surface.
 */
class LicenseExempt
{
    public const ATTRIBUTE = 'license_exempt';

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::ATTRIBUTE, true);

        return $next($request);
    }
}
