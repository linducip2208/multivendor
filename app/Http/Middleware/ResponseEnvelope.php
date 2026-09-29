<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Api\ErrorCodes;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Normalises every API body into the shared envelope and turns any uncaught
 * throwable into a machine readable problem shape.
 *
 * Legacy keys are never removed. A response that already carried `data`,
 * `links` and `current_page` keeps all of them; the envelope is added around
 * what is there, and Laravel's own `meta` block is merged into the envelope's
 * `meta` rather than overwritten, so `meta.current_page` still resolves.
 */
class ResponseEnvelope
{
    private const ENVELOPE_KEYS = ['success', 'data', 'meta', 'message', 'request_id'];

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (Throwable $e) {
            return $this->problem($request, $e);
        }

        if (! $this->isJsonPayload($response)) {
            return $this->withRequestId($request, $response);
        }

        $content = $response->getContent();
        $payload = $content === false ? null : json_decode((string) $content, true);

        if (! is_array($payload)) {
            return $this->withRequestId($request, $response);
        }

        $normalised = $this->normalise($payload, $request);

        return $this->withRequestId($request, new JsonResponse(
            $normalised,
            $response->getStatusCode(),
            $response->headers->all(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        ));
    }

    private function problem(Request $request, Throwable $e): JsonResponse
    {
        $status = ErrorCodes::statusFor($e);
        $requestId = ApiResponse::requestId($request);
        $code = ErrorCodes::for($e);
        $debug = (bool) config('app.debug', false);

        if ($status >= 500) {
            Log::error('api.error', [
                'request_id' => $requestId,
                'method' => $request->method(),
                'path' => $request->path(),
                'code' => $code,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => str_replace(base_path(), '', $e->getFile()),
                'line' => $e->getLine(),
            ]);
        }

        $response = ApiResponse::error(
            $code,
            ErrorCodes::messageFor($e, $debug),
            $status,
            ErrorCodes::errorsFor($e),
            ErrorCodes::headersFor($e)
        );

        $payload = $response->getData(true);
        $payload['request_id'] = $requestId;

        if ($status >= 500) {
            $payload['error'] = [
                'type' => class_basename($e),
                'debug' => $debug ? [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'file' => str_replace(base_path(), '', $e->getFile()),
                    'line' => $e->getLine(),
                ] : null,
            ];
        }

        return new JsonResponse(
            $payload,
            $status,
            $response->headers->all(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    private function normalise(array $payload, Request $request): array
    {
        $legacy = $payload;
        $data = $payload;
        $message = 'OK';
        $errors = null;
        $success = true;
        $code = null;

        if (array_key_exists('data', $payload)) {
            $data = $payload['data'];
            unset($legacy['data']);
        }

        if (isset($payload['message']) && is_string($payload['message'])) {
            $message = $payload['message'];
            unset($legacy['message']);
        }

        if (array_key_exists('success', $payload)) {
            $success = (bool) $payload['success'];
            unset($legacy['success']);
        }

        if (isset($payload['code']) && is_string($payload['code'])) {
            $code = $payload['code'];
            unset($legacy['code']);
        }

        if (isset($payload['errors'])) {
            $errors = $payload['errors'];
            unset($legacy['errors']);
        }

        $meta = ApiResponse::meta();
        $legacyMeta = $legacy['meta'] ?? null;
        unset($legacy['meta']);

        if (is_array($legacyMeta)) {
            $meta = array_merge($meta, $legacyMeta);
            $meta['pagination'] = array_filter(
                $legacyMeta,
                static fn ($value, $key): bool => ! in_array($key, self::ENVELOPE_KEYS, true),
                ARRAY_FILTER_USE_BOTH
            );
        }

        $envelope = [
            'success' => $success,
            'data' => $data,
            'meta' => $meta,
            'message' => $message,
            'errors' => $errors,
            'request_id' => ApiResponse::requestId($request),
        ];

        if ($code !== null) {
            $envelope['code'] = $code;
        }

        return array_merge($envelope, $legacy);
    }

    private function isJsonPayload(Response $response): bool
    {
        if ($response instanceof JsonResponse) {
            return true;
        }

        return str_contains((string) $response->headers->get('Content-Type'), 'json');
    }

    private function withRequestId(Request $request, Response $response): Response
    {
        $response->headers->set('X-Request-Id', ApiResponse::requestId($request));

        return $response;
    }
}
