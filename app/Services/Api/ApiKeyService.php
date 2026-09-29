<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Models\User;
use App\Support\ApiResponse;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Machine-to-machine credentials backed by the existing `api_keys` table.
 *
 * A key is `{prefix}.{secret}`. Only `sha256` of the full key is persisted; the
 * plaintext is returned exactly once at creation and is unrecoverable after.
 */
final class ApiKeyService
{
    public const SCOPES = ['read', 'write', 'vendor', 'admin', 'loyalty:read', 'loyalty:write', 'wallet:read', 'wallet:write', 'orders:read', 'orders:write', 'wishlist:read', 'wishlist:write', 'support:read', 'support:write', 'notifications:read'];

    public function create(
        User $owner,
        string $name,
        array $scopes = ['read', 'write'],
        ?DateTimeInterface $expiresAt = null,
        ?int $createdBy = null
    ): array {
        $prefix = strtoupper(Str::random(12));
        $secret = Str::random(48);
        $plain = $prefix.'.'.$secret;
        $scopes = $this->normaliseScopes($scopes);
        $now = now();

        $id = DB::table('api_keys')->insertGetId([
            'user_id' => $owner->getKey(),
            'name' => $name,
            'label' => $name,
            'prefix' => $prefix,
            'key_hash' => hash('sha256', $plain),
            'scopes' => json_encode($scopes, JSON_UNESCAPED_SLASHES),
            'created_by' => $createdBy ?? $owner->getKey(),
            'last_used_at' => null,
            'expires_at' => $expiresAt,
            'revoked_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'id' => (int) $id,
            'name' => $name,
            'prefix' => $prefix,
            'scopes' => $scopes,
            'key' => $plain,
            'masked_key' => $this->mask($plain),
            'expires_at' => $expiresAt === null ? null : $expiresAt->format(DATE_ATOM),
            'created_at' => $now->toIso8601String(),
        ];
    }

    public function findByPlaintext(string $plain): ?object
    {
        $plain = trim($plain);

        if ($plain === '' || ! str_contains($plain, '.')) {
            return null;
        }

        return DB::table('api_keys')
            ->where('key_hash', hash('sha256', $plain))
            ->first();
    }

    public function resolveUser(object $row): ?User
    {
        $userId = $row->user_id ?? null;

        if ($userId === null) {
            return null;
        }

        $user = User::find($userId);

        return $user === null || (string) $user->status !== 'active' ? null : $user;
    }

    public function isUsable(object $row): bool
    {
        if ($row->revoked_at !== null) {
            return false;
        }

        if ($row->expires_at !== null && strtotime((string) $row->expires_at) < time()) {
            return false;
        }

        return true;
    }

    public function touch(object $row, string $ip): void
    {
        $lastUsed = $row->last_used_at === null ? null : strtotime((string) $row->last_used_at);

        if ($lastUsed !== null && (time() - $lastUsed) < 60) {
            return;
        }

        DB::table('api_keys')->where('id', $row->id)->update([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
            'updated_at' => now(),
        ]);
    }

    public function listFor(int $ownerId): array
    {
        return DB::table('api_keys')
            ->where('user_id', $ownerId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (object $row): array => $this->present($row))
            ->all();
    }

    public function revoke(int $id, int $ownerId, ?string $reason = null): bool
    {
        return DB::table('api_keys')
            ->where('id', $id)
            ->where('user_id', $ownerId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'revoked_reason' => $reason === null ? 'revoked_by_owner' : substr($reason, 0, 191),
                'updated_at' => now(),
            ]) > 0;
    }

    public function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function normaliseScopes(array $scopes): array
    {
        $clean = array_values(array_unique(array_filter(
            array_map(static fn ($scope): string => strtolower(trim((string) $scope)), $scopes),
            static fn (string $scope): bool => $scope === '*' || in_array($scope, self::SCOPES, true)
        )));

        return $clean === [] ? ['read'] : $clean;
    }

    public function decodeScopes(mixed $scopes): array
    {
        if (is_array($scopes)) {
            return array_values($scopes);
        }

        if (! is_string($scopes) || trim($scopes) === '') {
            return ['read'];
        }

        $decoded = json_decode($scopes, true);

        return is_array($decoded) ? array_values($decoded) : ['read'];
    }

    public function present(object $row): array
    {
        $prefix = (string) $row->prefix;

        return [
            'id' => (int) $row->id,
            'name' => $row->name,
            'label' => $row->label ?? null,
            'prefix' => $prefix,
            'masked_key' => $prefix.'.'.str_repeat('*', 8),
            'scopes' => $this->decodeScopes($row->scopes ?? null),
            'user_id' => $row->user_id === null ? null : (int) $row->user_id,
            'last_used_at' => ApiResponse::iso($row->last_used_at ?? null),
            'last_used_ip' => $row->last_used_ip ?? null,
            'expires_at' => ApiResponse::iso($row->expires_at ?? null),
            'revoked_at' => ApiResponse::iso($row->revoked_at ?? null),
            'revoked_reason' => $row->revoked_reason ?? null,
            'created_at' => ApiResponse::iso($row->created_at ?? null),
        ];
    }

    public function mask(string $plain): string
    {
        $parts = explode('.', $plain, 2);

        if (count($parts) !== 2) {
            return str_repeat('*', 16);
        }

        [$prefix, $secret] = $parts;

        return $prefix.'.'.substr($secret, 0, 4).str_repeat('*', max(4, strlen($secret) - 8)).substr($secret, -4);
    }
}
