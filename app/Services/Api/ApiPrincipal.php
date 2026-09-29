<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Models\User;

/**
 * The authenticated caller: a user plus the scopes their credential carries.
 *
 * Sanctum token abilities and api_keys.scopes both resolve to this one set, so
 * authorisation is written against a single vocabulary.
 */
final class ApiPrincipal
{
    public const CREDENTIAL_TOKEN = 'token';

    public const CREDENTIAL_KEY = 'key';

    public const CREDENTIAL_SESSION = 'session';

    public function __construct(
        public readonly User $user,
        public readonly array $scopes,
        public readonly string $credential,
        public readonly ?int $credentialId = null
    ) {}

    public function can(string $scope): bool
    {
        if (in_array('*', $this->scopes, true) || in_array('admin', $this->scopes, true)) {
            return true;
        }

        if ($scope === 'read') {
            return in_array('read', $this->scopes, true) || in_array('write', $this->scopes, true);
        }

        return in_array($scope, $this->scopes, true);
    }

    public function isToken(): bool
    {
        return $this->credential === self::CREDENTIAL_TOKEN;
    }

    public function isApiKey(): bool
    {
        return $this->credential === self::CREDENTIAL_KEY;
    }

    public function key(): string
    {
        return $this->credential.':'.$this->user->getAuthIdentifier().':'.(string) $this->credentialId;
    }

    public function toArray(): array
    {
        return [
            'user_id' => (int) $this->user->getAuthIdentifier(),
            'credential' => $this->credential,
            'credential_id' => $this->credentialId,
            'scopes' => $this->scopes,
        ];
    }
}
