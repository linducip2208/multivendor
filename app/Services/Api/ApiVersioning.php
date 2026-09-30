<?php

declare(strict_types=1);

namespace App\Services\Api;

/**
 * Versioning/deprecation konsisten: v4 aktif; v1..v3 deprecated dengan Sunset.
 */
final class ApiVersioning
{
    public const CURRENT = 'v4';

    public const SUPPORTED = ['v1', 'v2', 'v3', 'v4'];

    public const DEPRECATED = ['v1', 'v2', 'v3'];

    public const SUNSET = 'Sat, 01 Aug 2026 00:00:00 GMT';

    public static function versionFromPath(string $path): ?string
    {
        if (preg_match('#^api/(v\d+)(/|$)#', ltrim($path, '/'), $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    public static function isSupported(?string $version): bool
    {
        return $version !== null && in_array(strtolower($version), self::SUPPORTED, true);
    }

    public static function isDeprecated(?string $version): bool
    {
        return $version !== null && in_array(strtolower($version), self::DEPRECATED, true);
    }

    /** @return array<string,string> */
    public static function deprecationHeaders(string $version): array
    {
        if (! self::isDeprecated($version)) {
            return ['X-Api-Version' => strtolower($version)];
        }

        return [
            'Deprecation' => 'true',
            'Sunset' => self::SUNSET,
            'Sunset-Link' => '</api/'.self::CURRENT.'>; rel="successor-version"',
            'X-Api-Version' => strtolower($version),
            'X-Api-Deprecated' => 'Versi '.strtolower($version).' masih didukung tetapi disarankan pindah ke '.self::CURRENT.' untuk baca publik.',
        ];
    }
}
