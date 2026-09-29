<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;

/**
 * Request-scoped tenant accessor.
 *
 * Resolved once by {@see ResolveTenant} and read through
 * `app(TenantContext::class)`. A null tenant means "single-tenant install",
 * which is the default and keeps every existing query unscoped.
 */
class TenantContext
{
    public function __construct(public readonly ?Tenant $tenant = null) {}

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function isMultiTenant(): bool
    {
        return $this->tenant !== null;
    }

    public function branding(string $key, mixed $default = null): mixed
    {
        return data_get($this->tenant?->theme, $key, $default);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->tenant?->{$key} ?? $default;
    }

    public function currency(): string
    {
        return (string) ($this->tenant?->currency_code ?: 'IDR');
    }

    public function timezone(): string
    {
        return (string) ($this->tenant?->timezone ?: config('app.timezone'));
    }

    public function locale(): string
    {
        return (string) ($this->tenant?->locale ?: config('app.locale'));
    }

    public function feature(string $feature): bool
    {
        $enabled = $this->tenant?->enabled_features;

        if (is_string($enabled)) {
            $enabled = json_decode($enabled, true);
        }

        return is_array($enabled) ? (bool) ($enabled[$feature] ?? true) : true;
    }

    /**
     * Scope guard used by tenant-aware queries.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scope($query, string $column = 'tenant_id')
    {
        if (! $this->isMultiTenant() || ! method_exists($query, 'getModel')) {
            return $query;
        }

        return $query->where($query->getModel()->qualifyColumn($column), $this->id());
    }
}
