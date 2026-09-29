<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Api\ApiPrincipal;
use App\Services\Api\ErrorCodes;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Replay protection for every mutating endpoint.
 *
 * The first call with a given `Idempotency-Key` stores the fingerprint and the
 * response. A retry of the same key plus the same payload replays that stored
 * response byte for byte, so a client that times out mid-request never charges
 * or orders twice. The same key with a different payload is a 409.
 */
class ApiIdempotency
{
    public const HEADER = 'Idempotency-Key';

    private const REPLAY_HEADER = 'Idempotency-Replayed';

    private const TTL_HOURS = 24;

    private const STALE_SECONDS = 600;

    private const MUTATING = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), self::MUTATING, true)) {
            return $next($request);
        }

        $key = trim((string) $request->header(self::HEADER, ''));

        if ($key === '' || strlen($key) > 128 || preg_match('/^[A-Za-z0-9._:\-]{8,128}$/', $key) !== 1) {
            return $next($request);
        }

        $principal = $request->attributes->get('api_principal');
        $principalType = $principal instanceof ApiPrincipal ? $principal->credential : 'anonymous';
        $principalId = $principal instanceof ApiPrincipal ? $principal->key() : 'ip:'.(string) $request->ip();
        $fingerprint = $this->fingerprint($request);
        $now = now();

        try {
            DB::table('api_idempotency_keys')->insert([
                'principal_type' => $principalType,
                'principal_id' => substr(hash('sha256', $principalId), 0, 64),
                'idempotency_key' => $key,
                'request_fingerprint' => $fingerprint,
                'method' => $request->method(),
                'endpoint' => substr('/'.$request->path(), 0, 191),
                'state' => 'pending',
                'locked_at' => $now,
                'expires_at' => $now->copy()->addHours(self::TTL_HOURS),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (QueryException $e) {
            return $this->resolveExisting($request, $principalType, $principalId, $key, $fingerprint, $e);
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->release($principalType, $principalId, $key);

            throw $e;
        }

        $this->store($principalType, $principalId, $key, $request, $response);

        return $response;
    }

    private function resolveExisting(
        Request $request,
        string $principalType,
        string $principalId,
        string $key,
        string $fingerprint,
        QueryException $duplicate
    ): Response {
        $row = $this->find($principalType, $principalId, $key);

        if ($row === null) {
            Log::warning('api.idempotency.duplicate_without_row', ['key' => $key, 'error' => $duplicate->getMessage()]);

            return ApiResponse::error(
                ErrorCodes::CONFLICT,
                'Permintaan idempoten sedang diproses.',
                409
            );
        }

        if (! hash_equals((string) $row->request_fingerprint, $fingerprint)) {
            return ApiResponse::error(
                ErrorCodes::IDEMPOTENCY_KEY_REUSED,
                'Idempotency-Key sudah dipakai dengan payload yang berbeda.',
                409,
                ['idempotency_key' => $key]
            );
        }

        if ($row->state === 'completed' && $row->response_body !== null) {
            $replay = new JsonResponse(
                json_decode((string) $row->response_body, true) ?? [],
                (int) ($row->response_status ?? 200),
                is_array($row->response_headers) ? $row->response_headers : [],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            $replay->headers->set(self::REPLAY_HEADER, 'true');

            return $replay;
        }

        $lockedAt = $row->locked_at === null ? null : (int) strtotime((string) $row->locked_at);

        if ($lockedAt !== null && (time() - $lockedAt) < self::STALE_SECONDS) {
            return ApiResponse::error(
                ErrorCodes::IDEMPOTENT_REQUEST_IN_FLIGHT,
                'Permintaan dengan Idempotency-Key yang sama masih diproses.',
                409,
                ['idempotency_key' => $key, 'retry_after' => self::STALE_SECONDS - (time() - $lockedAt)]
            );
        }

        $this->release($principalType, $principalId, $key);

        return ApiResponse::error(
            ErrorCodes::CONFLICT,
            'Idempotency-Key sebelumnya tidak selesai. Kirim ulang dengan key baru.',
            409,
            ['idempotency_key' => $key]
        );
    }

    private function store(string $principalType, string $principalId, string $key, Request $request, Response $response): void
    {
        $content = $response->getContent();
        $cacheable = $response->getStatusCode() < 500;

        DB::table('api_idempotency_keys')
            ->where('principal_type', $principalType)
            ->where('principal_id', substr(hash('sha256', $principalId), 0, 64))
            ->where('idempotency_key', $key)
            ->update([
                'state' => $cacheable ? 'completed' : 'failed',
                'response_status' => $response->getStatusCode(),
                'response_body' => $cacheable ? (string) $content : null,
                'response_headers' => [
                    'Content-Type' => $response->headers->get('Content-Type', 'application/json'),
                ],
                'resource_type' => $request->attributes->get('api_resource_type'),
                'resource_id' => $request->attributes->get('api_resource_id') === null
                    ? null
                    : (string) $request->attributes->get('api_resource_id'),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function release(string $principalType, string $principalId, string $key): void
    {
        DB::table('api_idempotency_keys')
            ->where('principal_type', $principalType)
            ->where('principal_id', substr(hash('sha256', $principalId), 0, 64))
            ->where('idempotency_key', $key)
            ->where('state', 'pending')
            ->delete();
    }

    private function find(string $principalType, string $principalId, string $key): ?object
    {
        return DB::table('api_idempotency_keys')
            ->where('principal_type', $principalType)
            ->where('principal_id', substr(hash('sha256', $principalId), 0, 64))
            ->where('idempotency_key', $key)
            ->first();
    }

    private function fingerprint(Request $request): string
    {
        $payload = $request->except(array_keys($request->allFiles()));
        ksort($payload);

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', implode('|', [
            $request->method(),
            '/'.$request->path(),
            (string) $request->server('CONTENT_TYPE'),
            $encoded === false ? '' : $encoded,
        ]));
    }
}
