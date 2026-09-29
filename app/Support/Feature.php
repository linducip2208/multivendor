<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PlatformFeature;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Runtime feature flags.
 *
 * A feature can be switched off per deployment through
 * `SystemSetting::set('feature_<key>', 1)` and is additionally gated by the
 * `PLATFORM_FEATURES` env allow-list so an operator can hard-disable a module
 * from configuration without touching the database.
 */
final class Feature
{
    private const CACHE_KEY = 'platform_features';

    /** @return array<string, bool> */
    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $enabled = [];

            foreach (PlatformFeature::cases() as $feature) {
                $enabled[$feature->value] = $feature->isCore()
                    || (bool) SystemSetting::get('feature_'.$feature->value);
            }

            $allowList = array_filter(array_map(
                'trim',
                explode(',', (string) (SystemSetting::get('platform_features', '') ?? ''))
            ));

            if ($allowList !== []) {
                foreach ($enabled as $key => $_) {
                    $enabled[$key] = $key === 'multi_vendor' || $key === 'storefront' || in_array($key, $allowList, true);
                }
            }

            return $enabled;
        });
    }

    public static function enabled(PlatformFeature|string $feature): bool
    {
        $key = $feature instanceof PlatformFeature ? $feature->value : (string) $feature;

        if ($key === 'multi_vendor' || $key === 'storefront') {
            return true;
        }

        return (bool) (self::all()[$key] ?? false);
    }

    public static function disabled(PlatformFeature|string $feature): bool
    {
        return ! self::enabled($feature);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
