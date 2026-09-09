<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditLogger
{
    private const SENSITIVE_KEYS = [
        'password', 'api_key', 'api_secret', 'secret', 'token', 'authorization', 'bank_account_number',
    ];

    /** @param array<string, mixed> $oldValues @param array<string, mixed> $newValues */
    public function log(string $action, Model|string|null $entity = null, array $oldValues = [], array $newValues = [], ?int $actorId = null): void
    {
        [$entityType, $entityId] = $entity instanceof Model
            ? [$entity::class, $entity->getKey()]
            : [$entity, null];

        AuditLog::create([
            'actor_id' => $actorId ?? auth('admin')->id() ?? auth('vendor')->id() ?? auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $this->withoutSensitiveValues($oldValues),
            'new_values' => $this->withoutSensitiveValues($newValues),
            'ip_address' => request()?->ip(),
            'request_id' => request()?->header('X-Request-Id'),
        ]);
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function withoutSensitiveValues(array $values): array
    {
        return Arr::except($values, self::SENSITIVE_KEYS);
    }
}
