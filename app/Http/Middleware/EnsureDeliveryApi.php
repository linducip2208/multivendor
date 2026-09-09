<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureDeliveryApi
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() || $request->user()->role !== 'delivery') {
            return response()->json(['success' => false, 'message' => 'Akun delivery diperlukan.'], 403);
        }

        return $next($request);
    }
}
