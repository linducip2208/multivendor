@props([
    'title' => null,
    'breadcrumb' => [],
    'actions' => null,
    'subtitle' => null,
    'pageClass' => null,
    'nav' => 'admin',
    'showCommandPalette' => true,
])

@php
    $wl = $whitelabel ?? [
        'logo' => null,
        'favicon' => null,
        'brandColor' => '#206bc4',
        'brandColorDark' => '#1b5aa5',
        'appName' => config('app.name', 'Backoffice'),
        'borderRadius' => 10,
        'fontFamily' => 'Inter',
        'sidebarWidth' => 250,
        'topbarHeight' => 60,
        'darkMode' => false,
        'showLang' => true,
    ];

    $isVendor = $nav === 'vendor';
    $homeRoute = $isVendor ? 'vendor.dashboard' : 'admin.dashboard';
    $navConfig = $isVendor ? 'vendor_navigation' : 'navigation';
    $themeStorageKey = 'admin-theme';

    $brandColor = (string) ($wl['brandColor'] ?: '#206bc4');
    $brandDark = (string) ($wl['brandColorDark'] ?: '#1b5aa5');

    $hex = ltrim($brandColor, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2) ?: '20');
    $g = hexdec(substr($hex, 2, 2) ?: '6b');
    $b = hexdec(substr($hex, 4, 2) ?: 'c4');
    $brandRgbTrim = $r.','.$g.','.$b;

    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    $onBrand = $luminance > 0.62 ? '#182433' : '#ffffff';

    $sidebarWidth = (int) ($wl['sidebarWidth'] ?: 250);
    $topbarHeight = (int) ($wl['topbarHeight'] ?: 60);
    $borderRadius = (int) ($wl['borderRadius'] ?: 10);
    $fontFamily = (string) ($wl['fontFamily'] ?: 'Inter');
    $appName = (string) ($wl['appName'] ?: config('app.name', 'Backoffice'));
    $favicon = $wl['favicon'] ?: asset('favicon.svg');
    $defaultDark = (bool) ($wl['darkMode'] ?? false);

    $actionHtml = match (true) {
        $actions instanceof \Illuminate\Contracts\Support\Htmlable => $actions->toHtml(),
        is_string($actions) => $actions,
        default => '',
    };
    $actionHtml = trim($actionHtml);

    $navGroups = config($navConfig, []) ?: [];
    $commandItems = [];
    foreach ($navGroups as $group) {
        foreach (($group['items'] ?? []) as $item) {
            if (! isset($item['route']) || ! \Route::has($item['route'])) {
                continue;
            }
            $commandItems[] = [
                'label' => (string) ($item['label'] ?? ''),
                'url' => route($item['route']),
                'group' => (string) ($group['label'] ?? ''),
                'icon' => (string) ($item['icon'] ?? 'circle'),
            ];
        }
    }

    $pageTitle = trim((string) ($title ?: ''));
    if ($pageTitle === '') {
        foreach ($navGroups as $group) {
            foreach (($group['items'] ?? []) as $item) {
                if (isset($item['route']) && \Route::has($item['route']) && request()->routeIs($item['match'] ?? $item['route'])) {
                    $pageTitle = (string) ($item['label'] ?? '');
                    break 2;
                }
            }
        }
    }
    if ($pageTitle === '') {
        $pageTitle = $appName;
    }

    $crumbs = [];
    foreach ((array) $breadcrumb as $crumb) {
        if (is_string($crumb)) {
            $crumbs[] = ['label' => $crumb, 'href' => null];
            continue;
        }
        if (is_array($crumb) && ($crumb['label'] ?? '') !== '') {
            $crumbs[] = ['label' => (string) $crumb['label'], 'href' => $crumb['href'] ?? null];
        }
    }

    $errorBag = $errors ?? null;
    $errorList = $errorBag !== null ? $errorBag->all() : [];
@endphp
<!doctype html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-bs-theme="{{ $defaultDark ? 'dark' : 'light' }}"
    data-bs-navbar="sticky"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $brandColor }}">
    <meta name="color-scheme" content="{{ $defaultDark ? 'dark light' : 'light dark' }}">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="{{ $favicon }}">

    <title>@if ($pageTitle !== $appName){{ $pageTitle }} — @endif{{ $appName }}</title>

    <script>
        (function () {
            var key = {{ \Illuminate\Support\Js::from($themeStorageKey) }};
            var fallbackDark = @json($defaultDark);
            var theme = 'light';
            try {
                var stored = window.localStorage.getItem(key);
                if (stored === 'dark' || stored === 'light') {
                    theme = stored;
                } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    theme = 'dark';
                } else {
                    theme = fallbackDark ? 'dark' : 'light';
                }
            } catch (error) {
                theme = fallbackDark ? 'dark' : 'light';
            }
            var root = document.documentElement;
            root.setAttribute('data-bs-theme', theme);
            root.style.colorScheme = theme;
        })();
    </script>

    @vite(['resources/css/tabler.css', 'resources/js/tabler.js', 'resources/js/admin.js'])

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/css/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">

    @stack('head')

    <style>
        :root {
            --brand-primary: {{ $brandColor }};
            --brand-dark: {{ $brandDark }};
            --brand-rgb: {{ $brandRgbTrim }};
            --brand-on-color: {{ $onBrand }};
            --tblr-primary: {{ $brandColor }};
            --tblr-primary-rgb: {{ $brandRgbTrim }};
            --tblr-primary-fg: {{ $onBrand }};
            --tblr-link-color: {{ $brandColor }};
            --tblr-link-hover-color: {{ $brandDark }};
            --tblr-btn-primary: {{ $brandColor }};
            --tblr-btn-primary-hover: {{ $brandDark }};
            --tblr-btn-primary-active: {{ $brandDark }};
            --tblr-sidebar-width: {{ $sidebarWidth }}px;
            --tblr-sidebar-folded-width: {{ $sidebarWidth }}px;
            --tblr-navbar-height: {{ $topbarHeight }}px;
            --tblr-topbar-height: {{ $topbarHeight }}px;
            --tblr-border-radius: {{ $borderRadius }}px;
            --tblr-border-radius-sm: {{ max(4, (int) round($borderRadius * 0.7)) }}px;
            --tblr-border-radius-lg: {{ $borderRadius + 4 }}px;
            --tblr-border-radius-xl: {{ $borderRadius + 8 }}px;
            --tblr-font-sans-serif: {{ $fontFamily }}, Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            --tblr-body-font-family: {{ $fontFamily }}, Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            --tblr-nav-link-icon-width: 1.25rem;
            --tblr-nav-link-icon-height: 1.25rem;
            --tblr-nav-link-icon-margin-end: .625rem;
            --tblr-sidebar-inset: .75rem;
            --tblr-page-padding: 1.5rem;
        }
        html,
        body {
            font-family: var(--tblr-body-font-family);
        }
        .admin-topbar {
            min-height: var(--tblr-topbar-height);
        }
        .admin-topbar > .container-xl {
            min-height: var(--tblr-topbar-height);
        }
        .admin-shell .admin-sidebar {
            background: var(--adm-sidebar-bg);
            border-inline-end-color: rgb(255 255 255 / 7%);
            --tblr-navbar-bg: var(--adm-sidebar-bg);
            --tblr-navbar-color: var(--adm-sidebar-text);
            --tblr-sidebar-brand-color: #fff;
            --tblr-navbar-active-bg: color-mix(in srgb, var(--brand-primary) 82%, var(--adm-sidebar-bg));
            --tblr-navbar-active-color: #fff;
        }
        .admin-shell .admin-sidebar .admin-sidebar__nav.navbar-nav {
            --tblr-nav-link-icon-color: var(--adm-sidebar-text);
            --tblr-navbar-active-bg: color-mix(in srgb, var(--brand-primary) 82%, var(--adm-sidebar-bg));
            --tblr-navbar-active-color: #fff;
        }
        @media (max-width: 991.98px) {
            .admin-shell .admin-sidebar {
                width: min({{ $sidebarWidth }}px, 84vw) !important;
            }
        }
    </style>
</head>
<body class="tabler-panel admin-shell {{ $pageClass }}">
    <a class="admin-skip-link" href="#admin-main">Lewati ke konten utama</a>

    <div class="page">
        <x-admin.sidebar :nav="$nav" :app-name="$appName" :logo="$wl['logo'] ?? null" :home-route="$homeRoute" />

        <x-admin.navbar :nav="$nav" :title="$pageTitle" />

        <div class="page-wrapper">
            <div class="page-body">
                <main id="admin-main" class="container-xl admin-main" tabindex="-1">
                    <x-admin.breadcrumb :items="$crumbs" />

                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                        <div>
                            <h2 class="admin-page-title mb-0">{{ $pageTitle }}</h2>
                            @if ($subtitle)
                                <p class="text-secondary mb-0 mt-1">{{ $subtitle }}</p>
                            @endif
                        </div>
                        @if ($actionHtml !== '')
                            <div class="admin-page-actions d-flex flex-wrap gap-2">{!! $actionHtml !!}</div>
                        @endif
                    </div>

                    @foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'] as $sessionKey => $alertType)
                        @if (session($sessionKey))
                            <x-admin.alert :type="$alertType">{{ session($sessionKey) }}</x-admin.alert>
                        @endif
                    @endforeach

                    @if (session('status'))
                        <x-admin.alert type="success">{{ session('status') }}</x-admin.alert>
                    @endif

                    @if ($errorList !== [])
                        <div class="alert alert-danger" role="alert">
                            <h4 class="alert-title mb-2">Periksa kembali isian Anda</h4>
                            <ul class="mb-0 ps-3">
                                @foreach ($errorList as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>

            <footer class="footer footer-transparent d-block d-lg-none mt-3 py-3 text-center small text-secondary">
                {{ $appName }}
            </footer>
        </div>
    </div>

    @if ($showCommandPalette)
        <x-admin.command-palette :items="$commandItems" />
    @endif

    <x-admin.confirm-dialog />
    <x-admin.toast-container />

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.min.js"></script>

    @stack('scripts')
</body>
</html>
