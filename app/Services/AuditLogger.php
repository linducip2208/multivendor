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

    /**
     * Jejak audit eksplisit saat admin melihat/mewakili vendor atau pelanggan.
     * Dipanggil dari aksi show yang sudah ada (tanpa route baru).
     */
    public function impersonate(string $kind, int $targetId, ?int $actorId, array $context = []): void
    {
        $this->log(
            'admin.impersonate.'.$kind,
            $kind.':'.$targetId,
            [],
            ['target_id' => $targetId, 'kind' => $kind] + $context,
            $actorId,
        );
    }

    /**
     * Diff before/after yang rapi untuk audit trail (kunci berubah saja).
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function diff(array $before, array $after): array
    {
        $out = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            $from = $before[$key] ?? null;
            $to = $after[$key] ?? null;
            if ($from !== $to) {
                $out[$key] = ['from' => $from, 'to' => $to];
            }
        }

        return $out;
    }
}
