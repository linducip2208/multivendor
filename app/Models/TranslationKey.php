<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kunci terjemahan ternormalisasi ("namespace.key").
 *
 * Namespace yang diizinkan (kontrak mesin i18n):
 * common|admin|vendor|customer|auth|validation|checkout|payment|shipping|
 * product|catalog|order|inventory|finance|cms|seo|marketing|notification|
 * email|api|errors|messages
 */
class TranslationKey extends Model
{
    public const NAMESPACES = [
        'common',
        'admin',
        'vendor',
        'customer',
        'auth',
        'validation',
        'checkout',
        'payment',
        'shipping',
        'product',
        'catalog',
        'order',
        'inventory',
        'finance',
        'cms',
        'seo',
        'marketing',
        'notification',
        'email',
        'api',
        'errors',
        'messages',
    ];

    protected $fillable = [
        'namespace',
        'key',
        'group_id',
        'description',
        'default_text',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(TranslationGroup::class, 'group_id');
    }

    public function fullKey(): string
    {
        return $this->namespace.'.'.$this->key;
    }

    public static function isValidNamespace(string $namespace): bool
    {
        return in_array(strtolower($namespace), self::NAMESPACES, true);
    }

    /**
     * @return array{namespace: string, key: string}
     */
    public static function split(string $fullKey): array
    {
        $fullKey = trim($fullKey, '.');
        $pos = strpos($fullKey, '.');

        if ($pos === false) {
            return ['namespace' => 'common', 'key' => $fullKey];
        }

        return [
            'namespace' => strtolower(substr($fullKey, 0, $pos)),
            'key' => substr($fullKey, $pos + 1),
        ];
    }
}
