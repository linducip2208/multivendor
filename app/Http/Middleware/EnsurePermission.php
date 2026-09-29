<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Permissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level permission gate.
 *
 * Usage: `->middleware('permission:products.edit')`.
 *
 * A super admin always passes. A user with no role assignment inherits the
 * permissions of their `users.role`, which keeps the pre-RBAC installation
 * working unchanged.
 */
class EnsurePermission
{
    public function __construct(private readonly Permissions $permissions) {}

    public function handle(Request $request, Closure $next, string ...$slugs): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        foreach ($slugs as $slug) {
            if ($this->permissions->allows($user, $slug)) {
                return $next($request);
            }
        }

        abort(403, 'Anda tidak memiliki izin untuk tindakan ini.');
    }
}
