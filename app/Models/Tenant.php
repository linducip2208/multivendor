<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'domain', 'domains', 'logo_url', 'favicon_url', 'theme', 'currency_code', 'timezone', 'locale', 'contact_email', 'contact_phone', 'enabled_features', 'default_commission_rate', 'is_active', 'trial_ends_at'])]
class Tenant extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'domains' => 'array',
            'theme' => 'array',
            'enabled_features' => 'array',
            'default_commission_rate' => 'decimal:2',
            'is_active' => 'boolean',
            'trial_ends_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(SaasSubscription::class);
    }

    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class);
    }
}
