<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

#[Fillable([
    'name', 'type', 'api_format', 'base_url', 'api_key_encrypted',
    'api_secret_encrypted', 'extra_headers', 'config', 'is_active',
    'is_default', 'sort_order', 'description'
])]
class Provider extends Model
{
    protected function casts(): array
    {
        return [
            'extra_headers' => 'json',
            'config' => 'json',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function setApiKeyEncryptedAttribute($value): void
    {
        if ($value) {
            $this->attributes['api_key_encrypted'] = Crypt::encryptString($value);
        }
    }

    public function getApiKeyAttribute(): ?string
    {
        if (empty($this->attributes['api_key_encrypted'] ?? null)) return null;
        try {
            return Crypt::decryptString($this->attributes['api_key_encrypted']);
        } catch (\Exception) {
            return null;
        }
    }

    public function setApiSecretEncryptedAttribute($value): void
    {
        if ($value) {
            $this->attributes['api_secret_encrypted'] = Crypt::encryptString($value);
        }
    }

    public function getApiSecretAttribute(): ?string
    {
        if (empty($this->attributes['api_secret_encrypted'] ?? null)) return null;
        try {
            return Crypt::decryptString($this->attributes['api_secret_encrypted']);
        } catch (\Exception) {
            return null;
        }
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function getMaskedKey(): string
    {
        $key = $this->getApiKeyAttribute();
        if (!$key || strlen($key) <= 8) return '***';
        return substr($key, 0, 4) . '****' . substr($key, -4);
    }
}
