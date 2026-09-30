<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Geo\CountryService;
use App\Support\Currency;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Negosiasi currency + country untuk API & storefront.
 *
 * Prioritas currency: X-Currency > X-Country (via CountryService) > session > default tenant.
 * TIDAK didaftarkan ke kernel oleh agen ini — integrator yang mendaftarkan.
 * Hanya request attributes + header respons; tidak mengubah kontrak body.
 */
class NegotiateCurrency
{
    public function handle(Request $request, Closure $next): Response
    {
        $country = $this->resolveCountry($request);
        $currency = $this->resolveCurrency($request, $country);

        $request->attributes->set('currency', $currency);
        $request->attributes->set('country', $country);
        if ($country !== null) {
            $request->attributes->set('country_iso2', $country);
        }

        $response = $next($request);

        if ($response instanceof Response) {
            $response->headers->set('X-Currency', $currency);
            if ($country !== null) {
                $response->headers->set('X-Country', $country);
            }
        }

        return $response;
    }

    public function resolveCountry(Request $request): ?string
    {
        $header = trim((string) $request->header('X-Country', ''));
        if (preg_match('/^[A-Za-z]{2}$/', $header) === 1) {
            return strtoupper($header);
        }

        try {
            if ($request->hasSession()) {
                $session = $request->session()->get('country');
                if (is_string($session) && preg_match('/^[A-Za-z]{2}$/', trim($session)) === 1) {
                    return strtoupper(trim($session));
                }
            }
        } catch (\Throwable) {
            // Abaikan.
        }

        return null;
    }

    public function resolveCurrency(Request $request, ?string $country = null): string
    {
        $header = strtoupper(trim((string) $request->header('X-Currency', '')));
        if (preg_match('/^[A-Z]{3}$/', $header) === 1) {
            return $header;
        }

        $country ??= $this->resolveCountry($request);

        if ($country !== null) {
            try {
                $currency = app(CountryService::class)->currencyFor($country);
                if (preg_match('/^[A-Z]{3}$/', strtoupper(trim($currency))) === 1) {
                    return strtoupper(trim($currency));
                }
            } catch (\Throwable) {
                // Lanjut ke fallback.
            }
        }

        try {
            if ($request->hasSession()) {
                $session = $request->session()->get('currency');
                if (is_string($session) && preg_match('/^[A-Za-z]{3}$/', trim($session)) === 1) {
                    return strtoupper(trim($session));
                }
            }
        } catch (\Throwable) {
            // Abaikan.
        }

        try {
            $code = (string) (Currency::config()['code'] ?? 'IDR');

            return preg_match('/^[A-Z]{3}$/', strtoupper(trim($code))) === 1 ? strtoupper(trim($code)) : 'IDR';
        } catch (\Throwable) {
            return 'IDR';
        }
    }
}
