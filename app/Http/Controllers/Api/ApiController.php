<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Api\ApiFilter;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    /** Versi lama yangVI diberi header deprecation; kontrak body tidak berubah. */
    protected function deprecatedVersionHeaders(Request $request): array
    {
        $path = $request->path();
        $version = null;
        if (preg_match('#^api/(v[123])(/|$)#', $path, $m)) {
            $version = $m[1];
        }
        if ($version === null) {
            return [];
        }

        return [
            'Deprecation' => 'true',
            'Sunset' => 'Sat, 01 Aug 2026 00:00:00 GMT',
            'Sunset-Link' => '<'.url('/api/v4').'>; rel="successor-version"',
            'X-Api-Version' => $version,
            'X-Api-Deprecated' => 'Versi '.$version.' masih didukung tetapi disarankan pindah ke v4 untuk baca publik.',
        ];
    }

    protected function ok(mixed $data = null, string $message = 'OK', array $meta = [], int $status = 200): JsonResponse
    {
        $response = ApiResponse::success($data, $message, $status, $this->withLocaleMeta($meta));

        return $this->withDeprecation(request(), $response);
    }

    protected function created(mixed $data = null, string $message = 'Created', array $meta = []): JsonResponse
    {
        $response = ApiResponse::created($data, $message, $this->withLocaleMeta($meta));

        return $this->withDeprecation(request(), $response);
    }

    private function withDeprecation(Request $request, JsonResponse $response): JsonResponse
    {
        foreach ($this->deprecatedVersionHeaders($request) as $key => $value) {
            $response->headers->set($key, $value);
        }
        $response->headers->set('X-Request-Id', $response->headers->get('X-Request-Id', (string) \Str::uuid()));
        // Aditif lokalisasi: bahasa terdokumentasi via header tanpa ubah kontrak body.
        $response->headers->set('Content-Language', str_replace('_', '-', $this->resolveLocale($request)));
        $response->headers->set('X-Locale', $this->resolveLocale($request));
        $response->headers->set('X-Currency', $this->resolveCurrency($request));

        return $response;
    }

    /**
     * Resolusi locale API: user.locale > session > Accept-Language > default.
     * Memakai LocaleNegotiator bila terikat; fallback aman bila tabel belum siap.
     */
    protected function resolveLocale(?Request $request = null): string
    {
        $request ??= request();

        try {
            return app(\App\Services\Localization\LocaleNegotiator::class)->negotiateFromRequest($request);
        } catch (\Throwable) {
            $preferred = $request->getPreferredLanguage(['id', 'en']);

            return is_string($preferred) && $preferred !== '' ? $preferred : (string) config('app.locale', 'id');
        }
    }

    /** Resolusi currency API: X-Currency > X-Country > session > default tenant. */
    protected function resolveCurrency(?Request $request = null): string
    {
        $request ??= request();

        try {
            return app(\App\Http\Middleware\NegotiateCurrency::class)->resolveCurrency($request);
        } catch (\Throwable) {
            $header = strtoupper(trim((string) $request->header('X-Currency', '')));

            return preg_match('/^[A-Z]{3}$/', $header) === 1 ? $header : 'IDR';
        }
    }

    protected function resolveCountry(?Request $request = null): ?string
    {
        $request ??= request();
        $header = trim((string) $request->header('X-Country', ''));

        return preg_match('/^[A-Za-z]{2}$/', $header) === 1 ? strtoupper($header) : null;
    }

    /** Daftar bahasa terdokumentasi untuk klien API (aditif, dari LanguageService). */
    protected function supportedLocales(): array
    {
        try {
            $service = app(\App\Services\Localization\LanguageService::class);

            return [
                'available' => $service->activeCodes(),
                'default' => $service->defaultCode(),
                'fallback' => (string) config('app.fallback_locale', $service->defaultCode()),
            ];
        } catch (\Throwable) {
            return ['available' => ['id', 'en'], 'default' => 'id', 'fallback' => 'id'];
        }
    }

    /**
     * Envelope aditif: tambah locale/currency + dokumentasi bahasa ke meta.
     * Kontrak existing (success/data/meta/message/errors/request_id) tidak diubah.
     */
    protected function withLocaleMeta(array $meta = [], ?Request $request = null): array
    {
        $request ??= request();
        $supported = $this->supportedLocales();

        return array_merge($meta, [
            'locale' => $this->resolveLocale($request),
            'currency' => $this->resolveCurrency($request),
            'country' => $this->resolveCountry($request),
            'available_locales' => $supported['available'],
            'fallback_locale' => $supported['fallback'],
        ]);
    }

    /** Error terjemahan dua bahasa (id/en) tanpa mengubah kontrak error. */
    protected function localizedError(string $code, string $messageId, string $messageEn, int $status = 400, array $errors = []): JsonResponse
    {
        $locale = strtolower((string) $this->resolveLocale());
        $message = str_starts_with($locale, 'en') ? $messageEn : $messageId;

        return \App\Support\ApiResponse::error($code, $message, $status, $errors);
    }

    /**
     * Idempotency untuk semua mutasi: kunci = header Idempotency-Key + user + path.
     * Replay dengan payload sama mengembalikan respons asli; payload beda -> 409.
     * Kontrak sukses tidak berubah; hanya menambah keamanan retry.
     */
    protected function idempotent(Request $request, callable $work): JsonResponse
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '') {
            return $work();
        }
        $userId = (int) optional($request->user())->id;
        $cacheKey = 'api:idem:'.$userId.':'.sha1($request->path().'|'.$key);
        $fingerprint = sha1((string) $request->getContent());
        try {
            $stored = \Cache::get($cacheKey);
            if (is_array($stored) && ($stored['fingerprint'] ?? null) === $fingerprint) {
                $replay = new JsonResponse($stored['body'] ?? null, (int) ($stored['status'] ?? 200), []);
                $replay->headers->set('Idempotency-Replayed', 'true');

                return $this->withDeprecation($request, $replay);
            }
            if (is_array($stored)) {
                return ApiResponse::error('idempotency_key_reused', 'Idempotency-Key sudah dipakai dengan payload yang berbeda.', 409);
            }
            /** @var JsonResponse $response */
            $response = $work();
            \Cache::put($cacheKey, [
                'fingerprint' => $fingerprint,
                'body' => $response->getData(true),
                'status' => $response->getStatusCode(),
            ], now()->addHours(24));
            $response->headers->set('Idempotency-Replayed', 'false');

            return $response;
        } catch (\Throwable) {
            return $work();
        }
    }

    protected function paged(
        Paginator $paginator,
        mixed $data,
        string $message,
        ApiFilter $filter,
        Request $request
    ): JsonResponse {
        $meta = ApiResponse::metaFrom($paginator, $filter->resolvedFilters($request), $filter->sortSummary($request));
        $meta['include'] = array_values($filter->resolvedIncludes($request));
        $meta['pagination'] = $filter->wantsCursor($request) ? 'cursor' : 'offset';

        return ApiResponse::success($data, $message, 200, $this->withLocaleMeta($meta, $request));
    }

    protected function cursorPaged(Paginator $paginator, mixed $data, string $message, Request $request): JsonResponse
    {
        return ApiResponse::success($data, $message, 200, $this->withLocaleMeta(ApiResponse::metaFrom($paginator, [], null), $request));
    }

    /**
     * A caller that does not own the record must not be able to tell it apart
     * from a record that never existed, so ownership failures answer 404.
     */
    protected function abortUnlessOwned(bool $owned, string $code = 'resource_not_found'): void
    {
        abort_unless($owned, 404, 'Sumber daya tidak ditemukan.');
    }

    protected function markResource(Request $request, string $type, int|string $id): void
    {
        $request->attributes->set('api_resource_type', $type);
        $request->attributes->set('api_resource_id', $id);
    }
}
