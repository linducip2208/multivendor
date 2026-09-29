<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Backoffice staff.
 *
 * `employee` is a real, limited role: it may sign in to the backoffice and is
 * granted a default permission set on first save. A staff member can never edit,
 * deactivate or delete a super admin, nor change their own role.
 */
class EmployeeController extends Controller
{
    private const ASSIGNABLE = ['admin', 'employee'];

    private const DEFAULT_EMPLOYEE_PERMISSIONS = [
        'dashboard.view',
        'orders.view',
        'products.view',
        'categories.view',
        'customers.view',
        'reports.view',
    ];

    public function index(Request $request): View
    {
        $query = User::query()->whereIn('role', ['admin', 'employee']);

        if (($search = trim((string) $request->query('search', ''))) !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 15;
        $total = (int) (clone $query)->count();

        $rows = $query->orderBy('name')->forPage($page, $perPage)->get()
            ->map(fn (User $user): array => $this->row($user))
            ->all();

        return view('admin.users.index', [
            'rows' => $rows,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    public function show(User $user): View
    {
        $this->authorizeTarget($user);

        return view('admin.users.show', [
            'employee' => $this->row($user),
            'permissions' => $this->permissionOptions(),
            'granted' => $this->grantedSlugs($user),
            'orders' => $user->orders()->orderByDesc('id')->limit(10)->get()
                ->map(fn (\App\Models\Order $order): array => [
                    'id' => (int) $order->id,
                    'order_number' => (string) $order->order_number,
                    'total' => (float) $order->total,
                    'total_formatted' => \App\Support\Currency::format((float) $order->total),
                    'order_status' => (string) $order->order_status,
                    'order_status_label' => \App\Enums\OrderStatus::fromStored((string) $order->order_status)->label(),
                    'order_status_badge' => \App\Enums\OrderStatus::fromStored((string) $order->order_status)->badge(),
                    'created_at' => (string) ($order->created_at?->format('Y-m-d H:i') ?? ''),
                    'url' => route('admin.orders.show', (int) $order->id),
                ])
                ->all(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function grantedSlugs(User $user): array
    {
        try {
            return DB::table('role_user')
                ->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->join('permission_role', 'permission_role.role_id', '=', 'roles.id')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->where('role_user.user_id', $user->id)
                ->distinct()
                ->orderBy('permissions.slug')
                ->pluck('permissions.slug')
                ->map(fn ($slug): string => (string) $slug)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<int>
     */
    private function permissionIdsFor(User $user): array
    {
        try {
            return DB::table('role_user')
                ->join('permission_role', 'permission_role.role_id', '=', 'role_user.role_id')
                ->where('role_user.user_id', $user->id)
                ->distinct()
                ->pluck('permission_role.permission_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => self::ASSIGNABLE,
            'permissions' => $this->permissionOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(self::ASSIGNABLE)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        $employee = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => (string) $validated['name'],
                'email' => (string) $validated['email'],
                'password' => Hash::make((string) $validated['password']),
                'role' => (string) $validated['role'],
                'status' => 'active',
            ]);

            $this->syncRole($user, (string) $validated['role'], (array) ($validated['permissions'] ?? []));

            return $user;
        });

        app(AuditLogger::class)->log('employee.created', $employee, [], ['role' => $employee->role], auth('admin')->id());

        return redirect()->route('admin.users.index')->with('success', 'Staf "'.$employee->name.'" ditambahkan.');
    }

    public function edit(User $user): View
    {
        $this->authorizeTarget($user);

        return view('admin.users.edit', [
            'employee' => $this->row($user),
            'roles' => self::ASSIGNABLE,
            'permissions' => $this->permissionOptions(),
            'selectedPermissions' => $this->permissionIdsFor($user),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeTarget($user);

        $actor = auth('admin')->user();

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ];

        if ($user->isSuperAdmin()) {
            unset($rules['permissions']);
        } else {
            $rules['role'] = ['required', Rule::in(self::ASSIGNABLE)];
        }

        $validated = $request->validate($rules, [
            'email.unique' => 'Email tersebut sudah dipakai akun lain.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $before = $user->only(['name', 'email', 'role', 'status']);

        DB::transaction(function () use ($user, $validated, $actor): void {
            $user->forceFill([
                'name' => (string) $validated['name'],
                'email' => (string) $validated['email'],
            ])->save();

            if (($validated['password'] ?? '') !== '') {
                $user->forceFill(['password' => Hash::make((string) $validated['password'])])->save();
            }

            if (! $user->isSuperAdmin()) {
                $role = (string) ($validated['role'] ?? $user->role);
                $user->forceFill(['role' => $role])->save();
                $this->syncRole($user, $role, (array) ($validated['permissions'] ?? []));
            }
        });

        Permissions::flush();

        app(AuditLogger::class)->log('employee.updated', $user, $before, $user->only(['name', 'email', 'role']), $actor?->id);

        return redirect()->route('admin.users.index')->with('success', 'Akun "'.$user->name.'" diperbarui.');
    }

    public function destroy(User $employee): RedirectResponse
    {
        $this->authorizeTarget($employee);

        abort_if((int) auth('admin')->id() === (int) $employee->id, 422, 'Anda tidak dapat menghapus akun Anda sendiri.');
        abort_if($employee->isSuperAdmin(), 422, 'Akun super admin tidak dapat dihapus.');

        $snapshot = ['name' => (string) $employee->name, 'role' => (string) $employee->role];
        $employee->delete();

        app(AuditLogger::class)->log('employee.deleted', null, $snapshot, [], auth('admin')->id());

        return back()->with('success', 'Akun staf dihapus.');
    }

    private function syncRole(User $user, string $role, array $permissionIds): void
    {
        $ids = array_map('intval', $permissionIds);

        if ($ids === [] && $role === 'employee') {
            $ids = Permission::query()->whereIn('slug', self::DEFAULT_EMPLOYEE_PERMISSIONS)->pluck('id')
                ->map(fn ($id): int => (int) $id)->all();
        }

        $model = Role::query()->firstOrCreate(
            ['slug' => $role],
            ['name' => $role === 'employee' ? 'Staf' : 'Administrator', 'is_system' => true],
        );

        DB::table('role_user')->where('user_id', $user->id)->delete();
        DB::table('role_user')->insert([
            'user_id' => (int) $user->id,
            'role_id' => (int) $model->id,
        ]);

        DB::table('permission_role')->where('role_id', $model->id)->delete();

        if ($ids !== []) {
            DB::table('permission_role')->insert(array_map(fn (int $permissionId): array => [
                'role_id' => (int) $model->id,
                'permission_id' => $permissionId,
            ], $ids));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'role' => (string) $user->role,
            'role_label' => match ($user->role) {
                'admin' => 'Administrator',
                'employee' => 'Staf',
                default => $user->role,
            },
            'status' => (string) ($user->status ?? ''),
            'is_super_admin' => (bool) $user->isSuperAdmin(),
            'permission_count' => count($this->permissionIdsFor($user)),
            'joined' => (string) ($user->created_at?->format('Y-m-d') ?? ''),
        ];
    }

    /**
     * @return list<array{group: string, options: list<array{id: int, slug: string, name: string}>}>
     */
    private function permissionOptions(): array
    {
        $grouped = Permission::query()->orderBy('module')->orderBy('name')->get()
            ->groupBy('module');

        $out = [];

        foreach ($grouped as $module => $permissions) {
            $out[] = [
                'group' => \Illuminate\Support\Str::headline((string) $module),
                'options' => $permissions->map(fn (Permission $permission): array => [
                    'id' => (int) $permission->id,
                    'slug' => (string) $permission->slug,
                    'name' => (string) $permission->name,
                ])->all(),
            ];
        }

        return $out;
    }

    private function authorizeTarget(User $employee): void
    {
        abort_if($employee->role !== 'admin' && $employee->role !== 'employee', 404, 'Akun ini bukan staf backoffice.');

        $actor = auth('admin')->user();

        abort_if($actor === null, 403);

        if ($employee->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            abort(403, 'Anda tidak dapat mengelola akun super admin.');
        }
    }
}
