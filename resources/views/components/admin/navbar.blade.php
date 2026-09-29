@props([
    'nav' => 'admin',
    'title' => null,
    'searchAction' => null,
    'searchPlaceholder' => null,
    'profileRoute' => null,
])

@php
    $wl = $whitelabel ?? [];
    $guard = $nav === 'vendor' ? 'vendor' : 'admin';
    $auth = auth($guard);
    $user = $auth->user();
    $logoutRoute = \Route::has($nav.'.logout') ? $nav.'.logout' : null;
    $profileRoute = $profileRoute ?: ($nav.'.profile');
    $profileUrl = \Route::has($profileRoute) ? route($profileRoute) : null;
    $locale = app()->getLocale();
    $showLang = (bool) ($wl['showLang'] ?? true);
    $displayName = $user?->name
        ?? ($nav === 'vendor' ? ($user?->shop?->name ?? 'Vendor') : 'Admin');

    $unread = 0;
    try {
        if ($user) {
            $unread = (int) \App\Models\Notification::query()
                ->where('notifiable_id', $user->getAuthIdentifier())
                ->whereNull('read_at')
                ->count();
        }
    } catch (\Throwable $e) {
        $unread = 0;
    }
@endphp

<header class="navbar navbar-expand-md d-print-none admin-topbar">
    <div class="container-xl">
        <button
            class="navbar-toggler admin-topbar__hamburger"
            type="button"
            data-sidebar-toggle
            aria-controls="admin-sidebar"
            aria-expanded="false"
            aria-label="Buka navigasi"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <h1 class="navbar-title admin-topbar__title mb-0">{{ $title }}</h1>

        <div class="navbar-nav flex-row order-md-last align-items-center gap-1 gap-md-2 ms-auto">

            @if ($searchAction)
                <form action="{{ $searchAction }}" method="GET" class="admin-topbar__search d-none d-lg-flex" role="search">
                    <div class="input-icon">
                        <x-admin.icon name="search" :size="18" class="input-icon-addon" />
                        <input
                            type="search"
                            name="search"
                            class="form-control"
                            value="{{ request('search') }}"
                            placeholder="{{ $searchPlaceholder ?? 'Cari data...' }}"
                            aria-label="{{ $searchPlaceholder ?? 'Cari data' }}"
                        >
                    </div>
                </form>
            @endif

            <button
                type="button"
                class="btn btn-ghost-light admin-command-trigger"
                data-command-toggle
                aria-label="Buka pencarian perintah"
                title="Pencarian perintah (Ctrl+K)"
            >
                <x-admin.icon name="search" :size="18" />
                <span class="d-none d-xl-inline admin-command-trigger__hint">Cari</span>
                <kbd class="d-none d-xl-inline">Ctrl K</kbd>
            </button>

            @if ($showLang)
                <div class="nav-item dropdown admin-lang">
                    <a
                        href="#"
                        class="btn btn-ghost-light"
                        data-bs-toggle="dropdown"
                        data-bs-auto-close="outside"
                        role="button"
                        aria-expanded="false"
                        aria-label="Ganti bahasa"
                    >
                        <x-admin.icon name="globe" :size="18" />
                        <span class="d-none d-md-inline">{{ strtoupper($locale) }}</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item {{ $locale === 'id' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['lang' => 'id']) }}">Bahasa Indonesia</a>
                        <a class="dropdown-item {{ $locale === 'en' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}">English</a>
                    </div>
                </div>
            @endif

            <div class="nav-item dropdown">
                <a
                    href="#"
                    class="nav-link d-flex lh-1 p-0 px-2"
                    data-bs-toggle="dropdown"
                    data-bs-auto-close="outside"
                    role="button"
                    aria-expanded="false"
                    aria-label="Notifikasi"
                >
                    <span class="position-relative">
                        <x-admin.icon name="bell" :size="20" />
                        <span class="admin-unread-badge {{ $unread > 0 ? '' : 'd-none' }}" data-unread-badge>{{ $unread > 99 ? '99+' : $unread }}</span>
                    </span>
                </a>
                <x-admin.notification-center :nav="$nav" :unread="$unread" align="dropdown-menu-end" />
            </div>

            <button
                type="button"
                class="btn btn-ghost-light"
                data-theme-toggle
                aria-label="Ganti tema"
                title="Ganti tema terang/gelap"
            >
                <span class="theme-toggle-icon" data-theme-icon-light><x-admin.icon name="moon" :size="18" /></span>
                <span class="theme-toggle-icon d-none" data-theme-icon-dark><x-admin.icon name="sun" :size="18" /></span>
            </button>

            <div class="nav-item dropdown">
                <a
                    href="#"
                    class="nav-link d-flex lh-1 text-reset p-0 px-2"
                    data-bs-toggle="dropdown"
                    data-bs-auto-close="outside"
                    role="button"
                    aria-expanded="false"
                    aria-label="Menu pengguna"
                >
                    <x-admin.avatar :name="$displayName" :status="null" size="sm" class="me-2" />
                    <div class="d-none d-xl-block">
                        <div class="admin-topbar__user-name">{{ \Illuminate\Support\Str::limit($displayName, 18) }}</div>
                        <div class="admin-topbar__user-role">{{ $nav === 'vendor' ? 'Seller' : 'Administrator' }}</div>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <div class="dropdown-header d-xl-none">
                        <div class="fw-semibold">{{ $displayName }}</div>
                    </div>
                    <x-admin.dropdown-item
                        :href="$profileUrl"
                        icon="user"
                        :disabled="$profileUrl === null"
                        label="Profil Saya"
                    />
                    @if ($logoutRoute)
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route($logoutRoute) }}">
                            @csrf
                            <button type="submit" class="dropdown-item dropdown-item-destructive">
                                <x-admin.icon name="logout" :size="16" />
                                <span>Keluar</span>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</header>
