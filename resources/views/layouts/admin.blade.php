<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', 'Admin Panel') — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    @stack('head')
    <style>
        :root {
            --brand-primary: #4F46E5;
            --brand-dark: #3730A3;
            --sidebar-width: 250px;
            --topbar-height: 60px;
        }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: #f8f9fc; }
        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0; width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--brand-primary) 0%, var(--brand-dark) 100%);
            color: #fff; z-index: 1040; overflow-y: auto; transition: transform .3s;
            box-shadow: 4px 0 24px rgba(79,70,229,.12);
        }
        .sidebar .logo { padding: 20px; font-size: 1.25rem; font-weight: 700; border-bottom: 1px solid rgba(255,255,255,.1); }
        .sidebar .nav-link { color: rgba(255,255,255,.75); padding: 12px 20px; font-size: .875rem; border-left: 3px solid transparent; transition: all .2s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,.1); border-left-color: #fff; }
        .sidebar .nav-link i { width: 22px; text-align: center; margin-right: 10px; }
        .sidebar .nav-section { padding: 16px 20px 6px; font-size: .7rem; text-transform: uppercase; letter-spacing: .08em; color: rgba(255,255,255,.45); }
        .topbar {
            position: fixed; top: 0; right: 0; left: var(--sidebar-width); height: var(--topbar-height);
            background: rgba(255,255,255,.85); backdrop-filter: blur(12px); z-index: 1030;
            display: flex; align-items: center; justify-content: space-between; padding: 0 24px;
            border-bottom: 1px solid rgba(0,0,0,.06);
        }
        .main-content { margin-left: var(--sidebar-width); margin-top: var(--topbar-height); padding: 24px; min-height: calc(100vh - var(--topbar-height)); }
        .card-stat { border: none; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.04); transition: all .3s; }
        .card-stat:hover { transform: translateY(-4px); box-shadow: 0 8px 28px rgba(0,0,0,.08); }
        .card-stat .stat-value { font-size: 1.75rem; font-weight: 800; }
        .card-stat .stat-label { font-size: .8rem; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; }

        @media (max-width: 1023px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .topbar { left: 0; }
            .main-content { margin-left: 0; }
            .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 1035; }
            .sidebar-overlay.show { display: block; }
        }
        @media (max-width: 640px) {
            .topbar { padding: 0 12px; }
            .main-content { padding: 16px; }
            .card-stat .stat-value { font-size: 1.35rem; }
        }
        @media (prefers-reduced-motion: reduce) {
            .sidebar, .card-stat { transition: none; animation-duration: .01ms !important; }
        }
    </style>
</head>
<body>
    {{-- Mobile sidebar overlay --}}
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    {{-- Sidebar — Alur Bisnis --}}
    <nav class="sidebar" id="sidebar">
        <div class="logo">
            <i class="fas fa-store-alt me-2"></i> {{ config('app.name') }}
        </div>
        <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>

        <div class="nav-section">🏪 Master Data</div>
        <a href="{{ route('admin.vendors.index') }}" class="nav-link {{ request()->routeIs('admin.vendors.*') ? 'active' : '' }}">
            <i class="fas fa-store"></i> Vendor
        </a>
        <a href="{{ route('admin.products.index') }}" class="nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
            <i class="fas fa-box"></i> Moderasi Produk
        </a>
        <a href="{{ route('admin.categories.index') }}" class="nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
            <i class="fas fa-list-alt"></i> Kategori
        </a>
        <a href="{{ route('admin.brands.index') }}" class="nav-link {{ request()->routeIs('admin.brands.*') ? 'active' : '' }}">
            <i class="fas fa-tag"></i> Brand
        </a>

        <div class="nav-section">💰 Transaksi</div>
        <a href="{{ route('admin.orders.index') }}" class="nav-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
            <i class="fas fa-shopping-cart"></i> Pesanan
        </a>
        <a href="{{ route('admin.transactions.index') }}" class="nav-link {{ request()->routeIs('admin.transactions.*') ? 'active' : '' }}">
            <i class="fas fa-money-bill-wave"></i> Transaksi
        </a>

        <div class="nav-section">🎯 Promosi</div>
        <a href="{{ route('admin.coupons.index') }}" class="nav-link {{ request()->routeIs('admin.coupons.*') ? 'active' : '' }}">
            <i class="fas fa-ticket-alt"></i> Kupon
        </a>
        <a href="{{ route('admin.flashdeals.index') }}" class="nav-link {{ request()->routeIs('admin.flashdeals.*') ? 'active' : '' }}">
            <i class="fas fa-bolt"></i> Flash Deal
        </a>
        <a href="{{ route('admin.deals.index') }}" class="nav-link {{ request()->routeIs('admin.deals.*') ? 'active' : '' }}">
            <i class="fas fa-calendar-check"></i> Deal of the Day
        </a>
        <a href="{{ route('admin.featured-deals.index') }}" class="nav-link {{ request()->routeIs('admin.featured-deals.*') ? 'active' : '' }}">
            <i class="fas fa-star"></i> Featured Deal
        </a>
        <a href="{{ route('admin.most-demanded.index') }}" class="nav-link {{ request()->routeIs('admin.most-demanded.*') ? 'active' : '' }}">
            <i class="fas fa-fire"></i> Most Demanded
        </a>

        <div class="nav-section">👥 Pengguna</div>
        <a href="{{ route('admin.customers.index') }}" class="nav-link {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}">
            <i class="fas fa-users"></i> Pelanggan
        </a>
        <a href="{{ route('admin.delivery-men.index') }}" class="nav-link {{ request()->routeIs('admin.delivery-men.*') ? 'active' : '' }}">
            <i class="fas fa-truck"></i> Kurir
        </a>
        <a href="{{ route('admin.employees.index') }}" class="nav-link {{ request()->routeIs('admin.employees.*') ? 'active' : '' }}">
            <i class="fas fa-user-tie"></i> Employee
        </a>
        <a href="{{ route('admin.roles.index') }}" class="nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
            <i class="fas fa-user-tag"></i> Custom Role
        </a>

        <div class="nav-section">📊 Laporan</div>
        <a href="{{ route('admin.reports.index') }}" class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
            <i class="fas fa-file-invoice"></i> Laporan (AI)
        </a>
        <a href="{{ route('admin.stock-report.index') }}" class="nav-link {{ request()->routeIs('admin.stock-report.*') ? 'active' : '' }}">
            <i class="fas fa-boxes"></i> Stok Produk
        </a>
        <a href="{{ route('admin.vendor-sale-report.index') }}" class="nav-link {{ request()->routeIs('admin.vendor-sale-report.*') ? 'active' : '' }}">
            <i class="fas fa-store-alt"></i> Penjualan Vendor
        </a>
        <a href="{{ route('admin.export.index') }}" class="nav-link {{ request()->routeIs('admin.export.*') ? 'active' : '' }}">
            <i class="fas fa-file-export"></i> Export CSV
        </a>

        <div class="nav-section">📣 Marketing</div>
        <a href="{{ route('admin.banners.index') }}" class="nav-link {{ request()->routeIs('admin.banners.*') ? 'active' : '' }}">
            <i class="fas fa-image"></i> Banner
        </a>
        <a href="{{ route('admin.blog.index') }}" class="nav-link {{ request()->routeIs('admin.blog.*') ? 'active' : '' }}">
            <i class="fas fa-blog"></i> Blog
        </a>
        <a href="{{ route('admin.push-notifications.index') }}" class="nav-link {{ request()->routeIs('admin.push-notifications.*') ? 'active' : '' }}">
            <i class="fas fa-bell"></i> Notifikasi
        </a>
        <a href="{{ route('admin.product-seo.index') }}" class="nav-link {{ request()->routeIs('admin.product-seo.*') ? 'active' : '' }}">
            <i class="fas fa-search"></i> SEO Produk
        </a>
        <a href="{{ route('admin.support-tickets.index') }}" class="nav-link {{ request()->routeIs('admin.support-tickets.*') ? 'active' : '' }}">
            <i class="fas fa-headset"></i> Tiket Support
        </a>
        <a href="{{ route('admin.pages.index') }}" class="nav-link {{ request()->routeIs('admin.pages.*') ? 'active' : '' }}">
            <i class="fas fa-file-alt"></i> Halaman
        </a>
        <a href="{{ route('admin.contacts.index') }}" class="nav-link {{ request()->routeIs('admin.contacts.*') ? 'active' : '' }}">
            <i class="fas fa-address-book"></i> Kontak
        </a>

        <div class="nav-section">🔌 Integrasi</div>
        <a href="{{ route('admin.providers.index') }}" class="nav-link {{ request()->routeIs('admin.providers.*') ? 'active' : '' }}">
            <i class="fas fa-plug"></i> Provider
        </a>
        <a href="{{ route('admin.sms-gateway.index') }}" class="nav-link {{ request()->routeIs('admin.sms-gateway.*') ? 'active' : '' }}">
            <i class="fas fa-sms"></i> SMS Gateway
        </a>
        <a href="{{ route('admin.third-party.index') }}" class="nav-link {{ request()->routeIs('admin.third-party.*') ? 'active' : '' }}">
            <i class="fas fa-cogs"></i> 3rd Party
        </a>
        <a href="{{ route('admin.file-manager.index') }}" class="nav-link {{ request()->routeIs('admin.file-manager.*') ? 'active' : '' }}">
            <i class="fas fa-folder"></i> File Manager
        </a>

        <div class="nav-section">💳 Keuangan</div>
        <a href="{{ route('admin.withdraws.index') }}" class="nav-link {{ request()->routeIs('admin.withdraws.*') ? 'active' : '' }}">
            <i class="fas fa-hand-holding-usd"></i> Withdraw
        </a>
        <a href="{{ route('admin.offline-payment.index') }}" class="nav-link {{ request()->routeIs('admin.offline-payment.*') ? 'active' : '' }}">
            <i class="fas fa-money-check"></i> Pembayaran Offline
        </a>
        <a href="{{ route('admin.email-templates.index') }}" class="nav-link {{ request()->routeIs('admin.email-templates.*') ? 'active' : '' }}">
            <i class="fas fa-envelope"></i> Email Templates
        </a>

        <div class="nav-section">⚙️ Sistem</div>
        <a href="{{ route('admin.settings') }}" class="nav-link {{ request()->routeIs('admin.settings') ? 'active' : '' }}">
            <i class="fas fa-cog"></i> Pengaturan
        </a>
        <a href="{{ route('admin.language.index') }}" class="nav-link {{ request()->routeIs('admin.language.*') ? 'active' : '' }}">
            <i class="fas fa-language"></i> Bahasa
        </a>
        <a href="{{ route('admin.currency.index') }}" class="nav-link {{ request()->routeIs('admin.currency.*') ? 'active' : '' }}">
            <i class="fas fa-dollar-sign"></i> Mata Uang
        </a>
        <a href="{{ route('admin.vat.index') }}" class="nav-link {{ request()->routeIs('admin.vat.*') ? 'active' : '' }}">
            <i class="fas fa-percent"></i> Pajak / VAT
        </a>
        <a href="{{ route('admin.translation.index') }}" class="nav-link {{ request()->routeIs('admin.translation.*') ? 'active' : '' }}">
            <i class="fas fa-language"></i> Translation DB
        </a>
        <a href="{{ route('admin.inhouse-shop.index') }}" class="nav-link {{ request()->routeIs('admin.inhouse-shop.*') ? 'active' : '' }}">
            <i class="fas fa-store"></i> Inhouse Shop
        </a>
        <a href="{{ route('admin.vendor-settings.index') }}" class="nav-link {{ request()->routeIs('admin.vendor-settings.*') ? 'active' : '' }}">
            <i class="fas fa-cog"></i> Vendor Settings
        </a>
        <a href="{{ route('admin.maintenance.index') }}" class="nav-link {{ request()->routeIs('admin.maintenance.*') ? 'active' : '' }}">
            <i class="fas fa-tools"></i> Maintenance
        </a>
        <a href="{{ route('admin.logout') }}" class="nav-link" onclick="event.preventDefault(); document.getElementById('logoutForm').submit();">
            <i class="fas fa-sign-out-alt"></i> Keluar
        </a>
        <form id="logoutForm" action="{{ route('admin.logout') }}" method="POST" class="d-none">
            @csrf
        </form>
    </nav>

    {{-- Topbar --}}
    <header class="topbar">
        <button class="btn btn-link text-dark d-lg-none p-0" onclick="toggleSidebar()">
            <i class="fas fa-bars fa-lg"></i>
        </button>
        <div></div>
        <div class="d-flex align-items-center gap-2">
            <a href="?lang=id" class="btn btn-sm btn-link text-decoration-none {{ app()->getLocale() === 'id' ? 'fw-bold' : 'text-muted' }}">ID</a>
            <span class="text-muted">|</span>
            <a href="?lang=en" class="btn btn-sm btn-link text-decoration-none {{ app()->getLocale() === 'en' ? 'fw-bold' : 'text-muted' }}">EN</a>
        <div class="d-flex align-items-center gap-3 ms-auto">
            <div class="dropdown">
                <button class="btn btn-light btn-sm dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle fa-lg"></i>
                    <span>{{ auth('admin')->user()?->name ?? 'Admin' }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#"><i class="fas fa-user-cog me-2"></i> Profil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="{{ route('admin.logout') }}" onclick="event.preventDefault(); document.getElementById('logoutForm').submit();"><i class="fas fa-sign-out-alt me-2"></i> Keluar</a></li>
                </ul>
            </div>
        </div>
    </header>

    {{-- Main Content --}}
    <main class="main-content">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }
    </script>
    @stack('scripts')
</body>
</html>
