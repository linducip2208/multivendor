<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Cms\LandingTrackingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Simpan utm_source/medium/campaign dari query ke session — hanya bila
 * ada di query (tak menimpa sesi dengan nilai kosong).
 *
 * SENGAJA tidak didaftarkan ke kernel oleh tim ini. Integrator yang wiring:
 * tambahkan ke grup middleware `web` di bootstrap/app.php:
 *
 *   ->withMiddleware(function (Middleware $middleware) {
 *       $middleware->appendToGroup('web', \App\Http\Middleware\CaptureUtm::class);
 *   })
 */
final class CaptureUtm
{
    public function handle(Request $request, Closure $next): Response
    {
        $utm = LandingTrackingService::extract($request->query->all());

        foreach ($utm as $param => $value) {
            // session key ringkas: utm.source / utm.medium / utm.campaign
            $short = match ($param) {
                'utm_source' => 'source',
                'utm_medium' => 'medium',
                'utm_campaign' => 'campaign',
                default => null,
            };

            if ($short !== null) {
                $request->session()->put('utm.'.$short, $value);
            }
        }

        return $next($request);
    }
}
