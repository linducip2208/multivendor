<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issues Sanctum personal access tokens without requiring the model trait.
 *
 * Rows are written to the exact `personal_access_tokens` schema Sanctum uses and
 * the plaintext format is byte identical (`{id}|{entropy}{crc32b}`), so a token
 * minted here is indistinguishable from one minted by `HasApiTokens::createToken`
 * and becomes usable by `auth:sanctum` the moment the trait is present.
 */
final class PersonalAccessTokenIssuer
{
    public const SCOPES = ['read', 'write', 'vendor', 'admin'];

    public function issue(
        User $user,
        string $name,
        array $scopes = ['read', 'write'],
        ?DateTimeInterface $expiresAt = null
    ): array {
        $plain = $this->generateTokenString();
        $abilities = $this->normaliseScopes($scopes);
        $now = now();

        $id = DB::table('personal_access_tokens')->insertGetId([
            'tokenable_type' => $user->getMorphClass(),
            'tokenable_id' => $user->getKey(),
            'name' => $name,
            'token' => hash('sha256', $plain),
            'abilities' => json_encode($abilities, JSON_UNESCAPED_SLASHES),
            'last_used_at' => null,
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'id' => (int) $id,
            'plain_text_token' => $plain,
            'token' => $id.'|'.$plain,
            'name' => $name,
            'scopes' => $abilities,
            'expires_at' => $expiresAt === null ? null : $expiresAt->format(DATE_ATOM),
        ];
    }

    public function revoke(User $user, string $tokenId): bool
    {
        return DB::table('personal_access_tokens')
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->where('id', $tokenId)
            ->delete() > 0;
    }

    public function revokeAll(User $user): int
    {
        return DB::table('personal_access_tokens')
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->delete();
    }

    public function tokensFor(User $user): array
    {
        return DB::table('personal_access_tokens')
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->orderByDesc('id')
            ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'scopes' => $this->decodeScopes($row->abilities ?? null),
                'last_used_at' => \App\Support\ApiResponse::iso($row->last_used_at),
                'expires_at' => \App\Support\ApiResponse::iso($row->expires_at),
                'created_at' => \App\Support\ApiResponse::iso($row->created_at),
            ])
            ->all();
    }

    public function generateTokenString(): string
    {
        $entropy = Str::random(40);

        return sprintf('%s%s%s', (string) config('sanctum.token_prefix', ''), $entropy, hash('crc32b', $entropy));
    }

    public function normaliseScopes(array $scopes): array
    {
        $clean = array_values(array_unique(array_filter(
            array_map(static fn ($scope): string => strtolower(trim((string) $scope)), $scopes),
            static fn (string $scope): bool => $scope === '*' || in_array($scope, self::SCOPES, true)
        )));

        return $clean === [] ? ['read'] : $clean;
    }

    public function decodeScopes(mixed $abilities): array
    {
        if (is_array($abilities)) {
            return array_values($abilities);
        }

        if (! is_string($abilities) || trim($abilities) === '') {
            return ['*'];
        }

        $decoded = json_decode($abilities, true);

        return is_array($decoded) ? array_values($decoded) : ['*'];
    }
}
