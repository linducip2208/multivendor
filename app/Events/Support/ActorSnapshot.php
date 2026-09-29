<?php

declare(strict_types=1);

namespace App\Events\Support;

use App\Models\Shop;
use App\Models\User;

final class ActorSnapshot
{
    /** @return array<string, mixed> */
    public static function user(?User $user): array
    {
        if (! $user) {
            return ['id' => null, 'name' => null, 'email' => null, 'phone' => null];
        }

        return [
            'id' => (int) $user->getKey(),
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'phone' => (string) $user->phone,
            'role' => (string) $user->role,
        ];
    }

    /** @return array<string, mixed> */
    public static function shop(?Shop $shop): array
    {
        if (! $shop) {
            return ['id' => null, 'name' => null, 'slug' => null, 'vendor_id' => null];
        }

        return [
            'id' => (int) $shop->getKey(),
            'name' => (string) $shop->name,
            'slug' => (string) $shop->slug,
            'vendor_id' => $shop->vendor_id,
            'status' => (string) $shop->status,
        ];
    }
}
