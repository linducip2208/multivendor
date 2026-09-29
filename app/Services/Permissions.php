<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * RBAC resolution.
 *
 * Before this existed, `isSuperAdmin()` was a no-op alias of `isAdmin()` and the
 * admin "Custom Role" screen wrote settings keys that nothing ever read — so a
 * permission matrix was configurable but had no effect. This service is the
 * single place that decides "may this user do X".
 *
 * Rules:
 *  - a `users.is_super_admin` flag (or a user holding the `super-admin` role)
 *    bypasses every check;
 *  - otherwise the union of every role attached through `role_user` applies;
 *  - a user with no role row falls back to the built-in defaults implied by
 *    their `users.role` value, which is how pre-RBAC installs keep working.
 */
class Permissions
{
    private const CACHE_KEY = 'permissions:matrix';
    private const CACHE_TTL = 600;

    /** @return array<string, list<string>> role slug => permission slugs */
    public function matrix(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            try {
                $rows = DB::table('permission_role')
                    ->join('roles', 'roles.id', '=', 'permission_role.role_id')
                    ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                    ->get(['roles.slug as role', 'permissions.slug as permission']);

                $matrix = [];
                foreach ($rows as $row) {
                    $matrix[$row->role][] = $row->permission;
                }

                return $matrix;
            } catch (\Throwable) {
                return [];
            }
        });
    }

    public function allows(?User $user, string $permission): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $granted = $this->granted($user);

        if (in_array('*', $granted, true) || in_array($permission, $granted, true)) {
            return true;
        }

        // A grant on the parent group implies its children (e.g. `products.*`).
        foreach ($granted as $slug) {
            if (str_ends_with($slug, '*') && str_starts_with($permission, rtrim($slug, '*'))) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function granted(User $user): array
    {
        try {
            $attached = DB::table('role_user')
                ->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->where('role_user.user_id', $user->id)
                ->pluck('roles.slug')
                ->all();
        } catch (\Throwable) {
            $attached = [];
        }

        $slugs = $attached !== [] ? $attached : [$this->roleSlugFor($user)];

        $matrix = $this->matrix();
        $granted = [];
        foreach ($slugs as $slug) {
            foreach ($matrix[$slug] ?? [] as $permission) {
                $granted[$permission] = true;
            }
        }

        // An explicitly flagged super admin gets everything even with no role row.
        if (($user->is_super_admin ?? false) && $granted === []) {
            $granted['*'] = true;
        }

        return array_keys($granted);
    }

    public function isSuperAdmin(User $user): bool
    {
        if ($user->is_super_admin ?? false) {
            return true;
        }

        try {
            $attached = DB::table('role_user')
                ->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->where('role_user.user_id', $user->id)
                ->where('roles.slug', 'super-admin')
                ->exists();
        } catch (\Throwable) {
            $attached = false;
        }

        return $attached;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The full permission catalogue, grouped for the admin matrix screen.
     *
     * @return array<string, list<array{slug: string, label: string}>>
     */
    public function catalogue(): array
    {
        try {
            return Permission::orderBy('group')->orderBy('slug')->get()
                ->groupBy('group')
                ->map(fn ($group) => $group->map(fn (Permission $p) => [
                    'slug' => $p->slug,
                    'label' => $p->name,
                ])->values()->all())
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function roleSlugFor(User $user): string
    {
        return match (true) {
            $user->isAdmin() => 'admin',
            $user->role === 'employee' => 'employee',
            $user->isVendor() => 'vendor',
            $user->isDelivery() => 'delivery',
            default => 'customer',
        };
    }
}
