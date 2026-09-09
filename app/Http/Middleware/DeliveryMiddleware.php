<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeliveryMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::guard('delivery')->check() || Auth::guard('delivery')->user()->role !== 'delivery') {
            return $request->expectsJson() ? response()->json(['success' => false, 'message' => 'Akun delivery diperlukan.'], 403) : abort(403);
        }

        return $next($request);
    }
}
