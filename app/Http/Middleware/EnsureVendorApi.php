<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureVendorApi
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || ! $user->isVendor() || ! $user->shop) {
            return response()->json(['success' => false, 'message' => 'Akun vendor dengan toko aktif diperlukan.'], 403);
        }

        return $next($request);
    }
}
