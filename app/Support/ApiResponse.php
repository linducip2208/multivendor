<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Builds the single JSON envelope every API version speaks.
 *
 * { success, data, meta: { page, per_page, total, total_pages, sort, filters }, message, errors, request_id }
 */
final class ApiResponse
{
    public const REQUEST_ID_ATTRIBUTE = 'api_request_id';

    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

    public static function success(
        mixed $data = null,
        string $message = 'Berhasil',
        int $status = 200,
        array $meta = [],
        array $headers = []
    ): JsonResponse {
        return self::envelope(true, $data, $message, null, $status, $meta, $headers);
    }

    public static function created(mixed $data = null, string $message = 'Berhasil dibuat', array $meta = [], array $headers = []): JsonResponse
    {
        return self::success($data, $message, 201, $meta, $headers);
    }

    public static function noContent(string $message = 'Berhasil dihapus', array $headers = []): JsonResponse
    {
        return self::success(null, $message, 200, [], $headers);
    }

    public static function error(
        string $code,
        string $message,
        int $status = 400,
        array $errors = [],
        array $headers = [],
        array $extra = []
    ): JsonResponse {
        $body = self::envelope(false, null, $message, [], $status, [], $headers);
        $payload = $body->getData(true);
        $payload['code'] = $code;
        $payload['errors'] = $errors === [] ? null : $errors;
        $payload = array_merge($payload, $extra);

        return new JsonResponse($payload, $status, $body->headers->all(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function paginated(Paginator $paginator, mixed $data, string $message = 'Berhasil', array $filters = [], ?string $sort = null): JsonResponse
    {
        return self::success($data, $message, 200, self::metaFrom($paginator, $filters, $sort));
    }

    public static function metaFrom(Paginator $paginator, array $filters = [], ?string $sort = null): array
    {
        $meta = [
            'page' => null,
            'per_page' => (int) $paginator->perPage(),
            'total' => null,
            'total_pages' => null,
            'sort' => $sort,
            'filters' => (object) $filters,
        ];

        if ($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $meta['page'] = (int) $paginator->currentPage();
            $meta['total'] = (int) $paginator->total();
            $meta['total_pages'] = (int) $paginator->lastPage();
        }

        if ($paginator instanceof CursorPaginator) {
            $meta['page'] = null;
            $meta['total'] = null;
            $meta['total_pages'] = null;
            $meta['next_cursor'] = $paginator->nextCursor()?->encode();
            $meta['previous_cursor'] = $paginator->previousCursor()?->encode();
        }

        return $meta;
    }

    public static function meta(array $meta = []): array
    {
        return array_merge([
            'page' => null,
            'per_page' => null,
            'total' => null,
            'total_pages' => null,
            'sort' => null,
            'filters' => (object) [],
        ], $meta);
    }

    public static function requestId(Request $request): string
    {
        $existing = $request->attributes->get(self::REQUEST_ID_ATTRIBUTE);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $header = (string) $request->header('X-Request-Id', '');

        if ($header !== '' && preg_match('/^[A-Za-z0-9._-]{8,128}$/', $header) === 1) {
            return $header;
        }

        return bin2hex(random_bytes(12));
    }

    public static function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toIso8601String();
        }

        try {
            return Carbon::parse((string) $value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        return Money::of($value instanceof Money ? $value->toDecimal() : (string) $value)->toDecimal();
    }

    public static function moneyOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::money($value);
    }

    public static function normalise(mixed $payload, ?Request $request, string $fallbackMessage = 'Berhasil', int $status = 200): JsonResponse
    {
        $meta = self::meta();
        $data = $payload;
        $message = $fallbackMessage;
        $errors = null;
        $success = true;
        $code = null;

        if (is_array($payload)) {
            if (array_key_exists('data', $payload)) {
                $data = $payload['data'];
            }

            if (isset($payload['message']) && is_string($payload['message'])) {
                $message = $payload['message'];
            }

            if (array_key_exists('success', $payload)) {
                $success = (bool) $payload['success'];
            }

            if (array_key_exists('errors', $payload) && is_array($payload['errors'])) {
                $errors = $payload['errors'];
            }

            if (isset($payload['code']) && is_string($payload['code'])) {
                $code = $payload['code'];
            }

            if (isset($payload['meta']) && is_array($payload['meta'])) {
                $meta = array_merge($meta, $payload['meta']);
            }
        }

        $body = [
            'success' => $success,
            'data' => $data,
            'meta' => $meta,
            'message' => $message,
            'errors' => $errors,
            'request_id' => $request === null ? bin2hex(random_bytes(12)) : self::requestId($request),
        ];

        if ($code !== null) {
            $body['code'] = $code;
        }

        return new JsonResponse($body, $status, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function envelope(
        bool $success,
        mixed $data,
        string $message,
        ?array $errors,
        int $status,
        array $meta,
        array $headers
    ): JsonResponse {
        $body = [
            'success' => $success,
            'data' => $data,
            'meta' => $meta === [] ? self::meta() : self::meta($meta),
            'message' => $message,
            'errors' => $errors,
            'request_id' => request() === null ? bin2hex(random_bytes(12)) : self::requestId(request()),
        ];

        return new JsonResponse($body, $status, $headers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
