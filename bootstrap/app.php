<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\RequirePair::class,
            \App\Http\Middleware\LanguageMiddleware::class,
            \App\Http\Middleware\CaptureUtm::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'vendor' => \App\Http\Middleware\VendorMiddleware::class,
            'customer' => \App\Http\Middleware\CustomerMiddleware::class,
            'vendor.api' => \App\Http\Middleware\EnsureVendorApi::class,
            'delivery.api' => \App\Http\Middleware\EnsureDeliveryApi::class,
            'delivery' => \App\Http\Middleware\DeliveryMiddleware::class,
            'language' => \App\Http\Middleware\LanguageMiddleware::class,
            'license.exempt' => \App\Http\Middleware\LicenseExempt::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'tenant' => \App\Http\Middleware\ResolveTenant::class,
            'force.json' => \Illuminate\Http\Middleware\ForceJsonResponse::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/*',
            'webhook/*',
            'checkout/shipping-cost',
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
