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
    protected function ok(mixed $data = null, string $message = 'OK', array $meta = [], int $status = 200): JsonResponse
    {
        return ApiResponse::success($data, $message, $status, $meta);
    }

    protected function created(mixed $data = null, string $message = 'Created', array $meta = []): JsonResponse
    {
        return ApiResponse::created($data, $message, $meta);
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
