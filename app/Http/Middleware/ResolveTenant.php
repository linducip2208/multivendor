<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant from the request host.
 *
 * Single-tenant installations (the default) resolve to `null` and every query
 * stays unscoped, so nothing changes for a normal deployment. A white-label
 * install with a `tenants` table binds the tenant to the container and the
 * request; `TenantContext` then exposes it to the application.
 *
 * A host that matches no tenant never silently falls through to another
 * tenant's data: it resolves to null and the caller decides whether that is a
 * 404.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $tenant = $this->resolve($host);

        app()->instance(TenantContext::class, new TenantContext($tenant));
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }

    public function resolve(string $host): ?Tenant
    {
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.test') || str_ends_with($host, '.local')) {
            return null;
        }

        try {
            return Cache::remember('tenant:host:'.$host, 300, function () use ($host) {
                return Tenant::query()
                    ->where('is_active', true)
                    ->where(function ($q) use ($host) {
                        $q->where('domain', $host)
                            ->orWhereJsonContains('domains', $host)
                            ->orWhereJsonContains('domains', $host.':443')
                            ->orWhereJsonContains('domains', 'www.'.$host);
                    })
                    ->first();
            });
        } catch (\Throwable) {
            return null;
        }
    }
}
