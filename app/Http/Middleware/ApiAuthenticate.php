<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Api\ApiKeyService;
use App\Services\Api\ApiPrincipal;
use App\Services\Api\ErrorCodes;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates an API caller from a Sanctum bearer token or an `X-Api-Key`.
 *
 * Both credential types resolve to the same `ApiPrincipal`, so controllers only
 * ever ask for `$request->user()` and a scope set.
 */
class ApiAuthenticate
{
    public function __construct(private readonly ApiKeyService $keys) {}

    public function handle(Request $request, Closure $next): Response
    {
        $principal = $this->resolveApiKey($request) ?? $this->resolveToken($request) ?? $this->resolveSession($request);

        if ($principal === null) {
            return ApiResponse::error(
                ErrorCodes::UNAUTHENTICATED,
                'Token API tidak valid atau sudah kedaluwarsa.',
                401,
                [],
                ['WWW-Authenticate' => 'Bearer realm="api"']
            );
        }

        $request->setUserResolver(static fn (): User => $principal->user);
        $request->attributes->set('api_principal', $principal);
        $request->attributes->set('api_scopes', $principal->scopes);
        $request->attributes->set('api_credential', $principal->credential);

        if ($principal->credentialId !== null) {
            $request->attributes->set('api_key_id', $principal->credentialId);
        }

        return $next($request);
    }

    private function resolveApiKey(Request $request): ?ApiPrincipal
    {
        $plain = trim((string) $request->header('X-Api-Key', ''));

        if ($plain === '') {
            return null;
        }

        $row = $this->keys->findByPlaintext($plain);

        if ($row === null || ! $this->keys->isUsable($row)) {
            return null;
        }

        $user = $this->keys->resolveUser($row);

        if ($user === null) {
            return null;
        }

        $this->keys->touch($row, (string) $request->ip());

        return new ApiPrincipal(
            $user,
            $this->keys->decodeScopes($row->scopes ?? null),
            ApiPrincipal::CREDENTIAL_KEY,
            (int) $row->id
        );
    }

    private function resolveToken(Request $request): ?ApiPrincipal
    {
        $plain = (string) $request->bearerToken();

        if ($plain === '' || ! str_contains($plain, '|')) {
            return null;
        }

        $row = DB::table('personal_access_tokens')
            ->where('token', hash('sha256', $plain))
            ->first();

        if ($row === null || (string) $row->tokenable_type !== (new User)->getMorphClass()) {
            return null;
        }

        if ($row->expires_at !== null && strtotime((string) $row->expires_at) < time()) {
            return null;
        }

        $user = User::find($row->tokenable_id);

        if ($user === null || (string) $user->status !== 'active') {
            return null;
        }

        if ($row->last_used_at === null || (time() - (int) strtotime((string) $row->last_used_at)) >= 60) {
            DB::table('personal_access_tokens')
                ->where('id', $row->id)
                ->update(['last_used_at' => now(), 'updated_at' => now()]);
        }

        $abilities = json_decode((string) $row->abilities, true);

        return new ApiPrincipal(
            $user,
            is_array($abilities) ? array_values($abilities) : ['*'],
            ApiPrincipal::CREDENTIAL_TOKEN,
            (int) $row->id
        );
    }

    private function resolveSession(Request $request): ?ApiPrincipal
    {
        if (! $request->hasSession()) {
            return null;
        }

        $user = $request->user();

        return $user instanceof User ? new ApiPrincipal($user, ['*'], ApiPrincipal::CREDENTIAL_SESSION, null) : null;
    }
}
