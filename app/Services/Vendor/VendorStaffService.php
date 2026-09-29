<?php

declare(strict_types=1);

namespace App\Services\Vendor;

use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

/**
 * Shop staff directory.
 *
 * Staff rows are owned by the shop, not by the auth table: an invited member
 * has no `users` row until they accept, and deactivating one must never touch
 * the platform account. Every write runs inside a transaction so the
 * entitlement check and the insert cannot race.
 */
final class VendorStaffService
{
    public const ROLES = [
        'manager' => 'Manajer',
        'staff' => 'Staf',
        'finance' => 'Keuangan',
        'warehouse' => 'Gudang',
        'support' => 'CS',
    ];

    public const PERMISSIONS = [
        'products.view', 'products.manage', 'orders.view', 'orders.fulfill',
        'inventory.manage', 'finance.view', 'customers.view', 'content.manage',
    ];

    public function __construct(private readonly VendorScope $scope) {}

    public function index(string $search = ''): array
    {
        $shopId = $this->scope->shopId();

        $rows = $this->query()
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')))
            ->orderBy('is_active')
            ->orderBy('name')
            ->get();

        return [
            'staff' => $rows,
            'roles' => self::ROLES,
            'permissions' => self::PERMISSIONS,
            'search' => $search,
            'active' => $rows->where('is_active', true)->count(),
            'pending' => $rows->whereNull('joined_at')->count(),
        ];
    }

    public function store(array $payload): object
    {
        $shopId = $this->scope->shopId();
        $email = strtolower(trim((string) $payload['email']));

        return DB::transaction(function () use ($shopId, $email, $payload): object {
            $exists = DB::table('shop_staff')
                ->where('shop_id', $shopId)
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if ($exists !== null) {
                throw ValidationException::withMessages(['email' => 'Anggota dengan email ini sudah terdaftar.']);
            }

            app(VendorSubscriptionService::class)->assertAllows('staff', 1, 'anggota tim');

            $permissions = array_values(array_intersect(
                (array) ($payload['permissions'] ?? []),
                self::PERMISSIONS
            ));

            $id = DB::table('shop_staff')->insertGetId([
                'shop_id' => $shopId,
                'user_id' => null,
                'name' => VendorScope::clean($payload['name'], 120),
                'email' => $email,
                'phone' => VendorScope::cleanNullable($payload['phone'] ?? null, 32),
                'role' => in_array($payload['role'] ?? 'staff', array_keys(self::ROLES), true) ? $payload['role'] : 'staff',
                'permissions' => $permissions === [] ? null : json_encode($permissions),
                'is_active' => true,
                'invited_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            app(AuditLogger::class)->log('vendor.staff.invited', 'shop_staff', [], [
                'id' => $id,
                'role' => $payload['role'] ?? 'staff',
            ], $this->scope->userId());

            return DB::table('shop_staff')->where('id', $id)->first();
        }, 3);
    }

    public function destroy(int $staffId): void
    {
        $shopId = $this->scope->shopId();

        $row = DB::table('shop_staff')->where('id', $staffId)->where('shop_id', $shopId)->lockForUpdate()->first();

        abort_if($row === null, 404);

        DB::transaction(function () use ($row, $staffId): void {
            DB::table('shop_staff')->where('id', $staffId)->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

            app(AuditLogger::class)->log('vendor.staff.deactivated', 'shop_staff', [
                'is_active' => true,
            ], [
                'is_active' => false,
            ], $this->scope->userId());
        }, 3);
    }

    public function toggle(int $staffId): bool
    {
        $shopId = $this->scope->shopId();

        return DB::transaction(function () use ($staffId, $shopId): bool {
            $row = DB::table('shop_staff')->where('id', $staffId)->where('shop_id', $shopId)->lockForUpdate()->first();

            abort_if($row === null, 404);

            $next = ! (bool) $row->is_active;

            if ($next) {
                app(VendorSubscriptionService::class)->assertAllows('staff', 1, 'anggota tim');
            }

            DB::table('shop_staff')->where('id', $staffId)->update([
                'is_active' => $next,
                'updated_at' => now(),
            ]);

            return $next;
        }, 3);
    }

    public function show(int $staffId): object
    {
        $row = $this->query()->where('id', $staffId)->first();

        abort_if($row === null, 404);

        return $row;
    }

    /**
     * Matriks izin granular per menu vendor + bawaan tiap peran.
     *
     * @return array{menus: array<string, array{label: string, permissions: list<string>}>, role_defaults: array<string, list<string>>}
     */
    public static function menuMatrix(): array
    {
        $menus = [
            'produk' => ['label' => 'Produk & Katalog', 'permissions' => ['products.view', 'products.manage']],
            'pesanan' => ['label' => 'Pesanan & Fulfillment', 'permissions' => ['orders.view', 'orders.fulfill']],
            'inventori' => ['label' => 'Inventori & Gudang', 'permissions' => ['inventory.manage']],
            'keuangan' => ['label' => 'Keuangan', 'permissions' => ['finance.view']],
            'pelanggan' => ['label' => 'Pelanggan & Chat', 'permissions' => ['customers.view']],
            'konten' => ['label' => 'Konten & Promo', 'permissions' => ['content.manage']],
        ];
        $roleDefaults = [
            'manager' => self::PERMISSIONS,
            'staff' => ['products.view', 'orders.view', 'customers.view'],
            'finance' => ['finance.view', 'orders.view'],
            'warehouse' => ['inventory.manage', 'orders.fulfill', 'products.view'],
            'support' => ['customers.view', 'orders.view', 'content.manage'],
        ];

        return ['menus' => $menus, 'role_defaults' => $roleDefaults];
    }

    private function query()
    {
        return DB::table('shop_staff')->where('shop_id', $this->scope->shopId());
    }
}
