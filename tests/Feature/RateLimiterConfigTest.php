<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every `throttle:<name>` referenced by any route must resolve to a
 * registered RateLimiter. A missing limiter silently falls back to the
 * default 60/min budget (or the wrong limiter), weakening brute-force
 * and abuse protection without any visible error.
 *
 * NOTE: throttle middleware splits parameters on commas, so multi-part
 * names must use the colon form (throttle:api:auth), never commas.
 */
class RateLimiterConfigTest extends TestCase
{
    public function test_all_route_throttles_resolve(): void
    {
        $missing = [];
        $commaForm = [];

        foreach (Route::getRoutes() as $route) {
            foreach ((array) $route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'throttle:')) {
                    continue;
                }

                $param = substr($middleware, strlen('throttle:'));

                // Numeric budgets (throttle:10,1) always resolve.
                if (is_numeric($param) || preg_match('/^\d+,\d+$/', $param)) {
                    continue;
                }

                // Comma form never resolves to a custom limiter.
                if (str_contains($param, ',')) {
                    $commaForm[] = ($route->getName() ?: $route->uri()).' => '.$middleware;

                    continue;
                }

                if (RateLimiter::limiter($param) === null) {
                    $missing[] = ($route->getName() ?: $route->uri()).' => '.$middleware;
                }
            }
        }

        $this->assertSame([], $commaForm, 'Comma-form throttle names do not resolve: '.implode('; ', $commaForm));
        $this->assertSame([], $missing, 'Unregistered throttle limiters: '.implode('; ', $missing));
    }
}
