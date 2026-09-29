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
        $response = ApiResponse::success($data, $message, $status, $meta);

        return $this->withDeprecation(request(), $response);
    }

    protected function created(mixed $data = null, string $message = 'Created', array $meta = []): JsonResponse
    {
        $response = ApiResponse::created($data, $message, $meta);

        return $this->withDeprecation(request(), $response);
    }

    private function withDeprecation(Request $request, JsonResponse $response): JsonResponse
    {
        foreach ($this->deprecatedVersionHeaders($request) as $key => $value) {
            $response->headers->set($key, $value);
        }
        $response->headers->set('X-Request-Id', $response->headers->get('X-Request-Id', (string) \Str::uuid()));

        return $response;
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

        return ApiResponse::success($data, $message, 200, $meta);
    }

    protected function cursorPaged(Paginator $paginator, mixed $data, string $message, Request $request): JsonResponse
    {
        return ApiResponse::success($data, $message, 200, ApiResponse::metaFrom($paginator, [], null));
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
