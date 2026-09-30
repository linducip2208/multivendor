<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

#[Fillable([
    'name', 'type', 'api_format', 'base_url', 'api_key_encrypted',
    'api_secret_encrypted', 'extra_headers', 'config', 'is_active',
    'is_default', 'sort_order', 'description'
])]
#[Hidden(['api_key_encrypted', 'api_secret_encrypted'])]
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
        $this->attributes['api_key_encrypted'] = $value ? Crypt::encryptString($value) : null;
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
        $this->attributes['api_secret_encrypted'] = $value ? Crypt::encryptString($value) : null;
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

    // ── ADITIF pendalaman: kredensial tak pernah bocor ke log/respons ──

    /** Ringkasan kredensial aman untuk admin/API (tanpa secret plaintext). */
    public function credentialSummary(): array
    {
        return [
            'id' => (int) $this->id,
            'api_format' => $this->api_format,
            'has_key' => !empty($this->attributes['api_key_encrypted'] ?? null),
            'has_secret' => !empty($this->attributes['api_secret_encrypted'] ?? null),
            'masked_key' => $this->getMaskedKey(),
        ];
    }

    /** Config aman: kunci rahasia di dalam config di-mask (webhook_secret, dsb). */
    public function maskedConfig(): array
    {
        $config = is_array($this->config) ? $this->config : [];
        foreach ($config as $key => $value) {
            if (!is_string($value)) continue;
            $lower = strtolower((string) $key);
            if (str_contains($lower, 'secret') || str_contains($lower, 'token') || str_contains($lower, 'key') || str_contains($lower, 'password')) {
                $config[$key] = strlen($value) <= 8 ? '***' : substr($value, 0, 4).'****'.substr($value, -4);
            }
        }
        return $config;
    }

    /** Serialisasi aman: enkripsi + secret tak pernah muncul plaintext. */
    public function toArray(): array
    {
        $array = parent::toArray();
        unset($array['api_key_encrypted'], $array['api_secret_encrypted']);
        $array['masked_key'] = $this->getMaskedKey();
        if (isset($array['config']) && is_array($array['config'])) {
            $array['config'] = $this->maskedConfig();
        }
        unset($array['api_key'], $array['api_secret']);
        return $array;
    }
}
