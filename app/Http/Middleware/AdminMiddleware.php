<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backoffice access gate.
 *
 * `employee` is a real backoffice role with a scoped permission set resolved by
 * {@see \App\Services\Permissions}. Vendor, customer and delivery accounts are
 * still refused: an unprivileged staff account reaching the panel is expected,
 * a seller reaching the panel is not.
 */
class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('admin')->user();

        if (! $user instanceof User || ! $this->mayEnterBackoffice($user)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            return redirect()->route('admin.login');
        }

        if (($user->status ?? 'active') !== 'active') {
            Auth::guard('admin')->logout();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Account is not active.'], 403);
            }

            return redirect()->route('admin.login')->withErrors(['email' => 'Akun ini tidak aktif.']);
        }

        return $next($request);
    }

    private function mayEnterBackoffice(User $user): bool
    {
        return in_array($user->role, ['admin', 'employee'], true);
    }
}
