<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Bahasa yang tersedia untuk mesin i18n database-driven.
 *
 * Unlimited: tidak ada batas jumlah baris; tambah bahasa cukup insert.
 * Flag RTL: kolom is_rtl + direction ('ltr'|'rtl').
 */
class Language extends Model
{
    protected $fillable = [
        'code',
        'name',
        'native_name',
        'direction',
        'is_rtl',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'is_rtl' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }

    public function isRtl(): bool
    {
        return (bool) $this->is_rtl || $this->direction === 'rtl';
    }

    public function canonicalCode(): string
    {
        return self::canonicalize($this->code);
    }

    public static function canonicalize(string $code): string
    {
        $code = str_replace('_', '-', trim($code));
        if ($code === '') {
            return 'en';
        }
        $parts = explode('-', $code);

        if (count($parts) === 1) {
            return strtolower($parts[0]);
        }

        return strtolower($parts[0]).'-'.strtoupper(implode('-', array_slice($parts, 1)));
    }

    public static function baseCode(string $code): string
    {
        $code = str_replace('_', '-', trim($code));

        return strtolower(explode('-', $code)[0]);
    }
}
