<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LanguageMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (session()->has('locale')) {
            App::setLocale(session('locale'));
        } elseif ($request->has('lang')) {
            App::setLocale($request->lang);
            session(['locale' => $request->lang]);
        }
        return $next($request);
    }
}
