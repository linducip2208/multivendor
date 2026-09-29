<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps every API request with a correlation id used in bodies, headers and logs.
 */
class ApiRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = ApiResponse::requestId($request);
        $request->attributes->set(ApiResponse::REQUEST_ID_ATTRIBUTE, $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
