<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Api\ApiPrincipal;
use App\Services\Api\ErrorCodes;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the scope set carried by the caller's token or api key.
 *
 * Usage: `ApiScope:read` or `ApiScope:write,read` (all listed scopes required).
 */
class ApiScope
{
    public function handle(Request $request, Closure $next, string ...$required): Response
    {
        $principal = $request->attributes->get('api_principal');

        if (! $principal instanceof ApiPrincipal) {
            return ApiResponse::error(
                ErrorCodes::UNAUTHENTICATED,
                'Autentikasi diperlukan.',
                401
            );
        }

        $missing = array_values(array_filter(
            $required === [] ? ['read'] : $required,
            static fn (string $scope): bool => ! $principal->can($scope)
        ));

        if ($missing !== []) {
            return ApiResponse::error(
                ErrorCodes::MISSING_SCOPE,
                'Token tidak memiliki scope yang dibutuhkan: '.implode(', ', $missing).'.',
                403,
                ['required_scopes' => $missing, 'granted_scopes' => $principal->scopes]
            );
        }

        return $next($request);
    }
}
