@props([
    'nav' => 'admin',
    'logoText' => null,
    'appName' => null,
    'logo' => null,
    'homeRoute' => null,
])

@php
    $wl = $whitelabel ?? [];
    $guard = $nav === 'vendor' ? 'vendor' : 'admin';
    $navGroups = config($nav === 'vendor' ? 'vendor_navigation' : 'navigation', []) ?: [];
    $appName = $appName ?: ($wl['appName'] ?? '');
    $logo = $logo ?? ($wl['logo'] ?? null);
    $logoText = $logoText ?: $appName;
    $homeRoute = $homeRoute ?: ($nav === 'vendor' ? 'vendor.dashboard' : 'admin.dashboard');
    $dashboardRoute = \Route::has($homeRoute) ? $homeRoute : null;
    $user = auth($guard)->user();
    $logoutRoute = \Route::has($nav.'.logout') ? $nav.'.logout' : null;

    $groups = [];
    foreach ($navGroups as $group) {
        $visible = [];
        foreach (($group['items'] ?? []) as $item) {
            if (! isset($item['route']) || ! \Route::has($item['route'])) {
                continue;
            }
            $item['_active'] = request()->routeIs($item['match'] ?? $item['route']);
            $visible[] = $item;
        }
        if ($visible === []) {
            continue;
        }
        $groups[] = [
            'id' => 'nav-'.$nav.'-'.preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) ($group['label'] ?? 'group'))),
            'label' => $group['label'] ?? '',
            'icon' => $group['icon'] ?? 'folder',
            'active' => (bool) collect($visible)->contains(fn ($item) => $item['_active']),
            'items' => $visible,
        ];
    }
@endphp

<div class="admin-sidebar-scrim" data-sidebar-scrim hidden aria-hidden="true"></div>

<aside
    id="admin-sidebar"
    class="navbar navbar-vertical navbar-expand-lg admin-sidebar"
    data-sidebar
    aria-label="{{ $nav === 'vendor' ? 'Navigasi vendor' : 'Navigasi utama' }}"
>
    <div class="container-fluid">
        <div class="row flex-fill">
            <div class="col admin-sidebar__col">
                <a class="admin-sidebar__brand" href="{{ $dashboardRoute ? route($dashboardRoute) : '#' }}">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="{{ $logoText }}" class="admin-sidebar__logo">
                    @else
                        <span class="admin-sidebar__mark" aria-hidden="true">
                            <x-admin.icon :name="$nav === 'vendor' ? 'store' : 'home'" :size="20" />
                        </span>
                        <span class="admin-sidebar__name">{{ $logoText }}</span>
                    @endif
                </a>

                <ul class="nav navbar-nav admin-sidebar__nav">
                    @if ($dashboardRoute)
                        <li class="nav-item">
                            <a
                                class="nav-link {{ request()->routeIs($dashboardRoute) ? 'active' : '' }}"
                                href="{{ route($dashboardRoute) }}"
                                @if (request()->routeIs($dashboardRoute)) aria-current="page" @endif
                            >
                                <span class="nav-link-icon"><x-admin.icon name="dashboard" :size="20" /></span>
                                <span class="nav-link-title">Dashboard</span>
                            </a>
                        </li>
                    @endif

                    @foreach ($groups as $group)
                        <li class="nav-item dropdown">
                            <a
                                class="nav-link dropdown-toggle {{ $group['active'] ? 'active' : '' }}"
                                href="#{{ $group['id'] }}"
                                data-bs-toggle="dropdown"
                                data-bs-auto-close="outside"
                                aria-expanded="false"
                                role="button"
                            >
                                <span class="nav-link-icon"><x-admin.icon :name="$group['icon']" :size="20" /></span>
                                <span class="nav-link-title">{{ $group['label'] }}</span>
                            </a>
                            <div class="dropdown-menu" aria-labelledby="{{ $group['id'] }}">
                                @foreach ($group['items'] as $item)
                                    <a
                                        class="dropdown-item {{ $item['_active'] ? 'active' : '' }}"
                                        href="{{ route($item['route']) }}"
                                        @if ($item['_active']) aria-current="page" @endif
                                    >
                                        <span class="nav-link-icon"><x-admin.icon :name="$item['icon'] ?? 'circle'" :size="20" /></span>
                                        <span class="nav-link-title">{{ $item['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </li>
                    @endforeach

                    @if ($dashboardRoute === null && $groups === [])
                        <li class="nav-item">
                            <span class="nav-link disabled admin-sidebar__empty">Navigasi belum dikonfigurasi.</span>
                        </li>
                    @endif
                </ul>

                <div class="mt-auto admin-sidebar__footer">
                    @if ($user?->name)
                        <p class="admin-sidebar__meta">{{ $user->name }}</p>
                    @endif
                    @if ($logoutRoute)
                        <form method="POST" action="{{ route($logoutRoute) }}">
                            @csrf
                            <button type="submit" class="btn btn-ghost-light w-100 justify-content-start admin-sidebar__logout">
                                <x-admin.icon name="logout" :size="18" />
                                <span>Keluar</span>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</aside>
