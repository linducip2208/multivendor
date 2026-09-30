<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Localization\LocaleNegotiator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolusi locale terpusat. Prioritas: user.locale > session > Accept-Language > default.
 *
 * Memakai LocaleNegotiator existing bila tersedia (catatan: negotiator internal
 * berprioritas session > user; di sini user dimenangkan sesuai kontrak dengan
 * memanggil negotiate() secara eksplisit per kandidat terurut).
 *
 * TIDAK didaftarkan ke kernel oleh agen ini — integrator yang mendaftarkan.
 * TIDAK menyentuh cart (session/DB cart tak tersentuh bahasa).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        App::setLocale($locale);

        // Persistensi ringan: simpan pilihan valid ke session agar konsisten antar request.
        try {
            if ($request->hasSession() && $request->session()->get('locale') !== $locale) {
                $explicit = $request->query('lang');
                if (is_string($explicit) && $explicit !== '') {
                    $request->session()->put('locale', $locale);
                }
            }
        } catch (\Throwable) {
            // Session tak tersedia (API stateless) — abaikan.
        }

        // Persistensi preferensi user bila kolom tersedia (aditif, aman bila belum migrasi).
        try {
            $user = $request->user();
            if ($user !== null && Schema::hasColumn('users', 'locale')) {
                $current = $user->getAttribute('locale');
                $explicit = $request->query('lang');
                if (is_string($explicit) && $explicit !== '' && $current !== $locale) {
                    $user->forceFill(['locale' => $locale])->saveQuietly();
                }
            }
        } catch (\Throwable) {
            // Abaikan — locale request tetap jalan.
        }

        $response = $next($request);

        if ($response instanceof Response) {
            $response->headers->set('Content-Language', str_replace('_', '-', $locale));
            $response->headers->set('X-Locale', $locale);
        }

        return $response;
    }

    public function resolve(Request $request): string
    {
        $sessionLocale = null;
        try {
            $sessionLocale = $request->hasSession() ? $request->session()->get('locale') : null;
        } catch (\Throwable) {
            $sessionLocale = null;
        }

        // ?lang= eksplisit dianggap sebagai session intent (divalidasi negotiator).
        $queryLang = $request->query('lang');
        if (is_string($queryLang) && trim($queryLang) !== '') {
            $sessionLocale = trim($queryLang);
        }

        $userLocale = null;
        try {
            $user = $request->user();
            if ($user !== null) {
                $candidate = $user->getAttribute('locale') ?? $user->getAttribute('preferred_locale') ?? null;
                $userLocale = is_string($candidate) ? $candidate : null;
            }
        } catch (\Throwable) {
            $userLocale = null;
        }

        $acceptLanguage = $request->header('Accept-Language');

        try {
            /** @var LocaleNegotiator $negotiator */
            $negotiator = app(LocaleNegotiator::class);
            // Kontrak: user.locale > session > Accept-Language > default.
            // negotiate(session, user, ...) internal-nya session-first, jadi panggil
            // per kandidat terurut agar user menang.
            $available = app(\App\Services\Localization\LanguageService::class)->activeCodes();

            foreach ([$userLocale, is_string($sessionLocale) ? $sessionLocale : null] as $candidate) {
                $matched = $negotiator->match($candidate, $available);
                if ($matched !== null) {
                    return $matched;
                }
            }

            foreach ($negotiator->parseAcceptLanguage((string) $acceptLanguage) as $candidate) {
                $matched = $negotiator->match($candidate, $available);
                if ($matched !== null) {
                    return $matched;
                }
            }

            return $negotiator->negotiate(null, null, null, null);
        } catch (\Throwable) {
            // Fallback tanpa service (mis. tabel languages belum ada).
            if (is_string($userLocale) && $this->looksSupported($userLocale)) {
                return $this->normalize($userLocale);
            }
            if (is_string($sessionLocale) && $this->looksSupported($sessionLocale)) {
                return $this->normalize($sessionLocale);
            }
            $preferred = $request->getPreferredLanguage(['id', 'en']);
            if (is_string($preferred) && $preferred !== '') {
                return $preferred;
            }

            return (string) config('app.locale', 'id');
        }
    }

    private function looksSupported(string $code): bool
    {
        $base = strtolower(explode('-', str_replace('_', '-', trim($code)))[0]);

        return in_array($base, ['id', 'en'], true);
    }

    private function normalize(string $code): string
    {
        $base = strtolower(explode('-', str_replace('_', '-', trim($code)))[0]);

        return $base === 'en' ? 'en' : 'id';
    }
}
