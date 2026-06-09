<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', 'Vendor Panel') — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <style>
        :root { --brand-primary: #059669; --brand-dark: #065f46; --sidebar-width: 250px; --topbar-height: 60px; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f8f9fc; }
        .sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: var(--sidebar-width); background: linear-gradient(180deg, var(--brand-primary) 0%, var(--brand-dark) 100%); color: #fff; z-index: 1040; overflow-y: auto; transition: transform .3s; }
        .sidebar .logo { padding: 20px; font-size: 1.2rem; font-weight: 700; border-bottom: 1px solid rgba(255,255,255,.1); }
        .sidebar .nav-link { color: rgba(255,255,255,.75); padding: 12px 20px; font-size: .875rem; border-left: 3px solid transparent; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,.1); border-left-color: #fff; }
        .sidebar .nav-link i { width: 22px; text-align: center; margin-right: 10px; }
        .sidebar .nav-section { padding: 16px 20px 6px; font-size: .7rem; text-transform: uppercase; letter-spacing: .08em; color: rgba(255,255,255,.45); }
        .topbar { position: fixed; top: 0; right: 0; left: var(--sidebar-width); height: var(--topbar-height); background: rgba(255,255,255,.85); backdrop-filter: blur(12px); z-index: 1030; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; border-bottom: 1px solid rgba(0,0,0,.06); }
        .main-content { margin-left: var(--sidebar-width); margin-top: var(--topbar-height); padding: 24px; min-height: calc(100vh - var(--topbar-height)); }
        .card-stat { border: none; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.04); }
        @media (max-width: 1023px) {
            .sidebar { transform: translateX(-100%); } .sidebar.show { transform: translateX(0); }
            .topbar { left: 0; } .main-content { margin-left: 0; }
        }
    </style>
    @stack('head')
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1035;"></div>

<nav class="sidebar" id="sidebar">
    <div class="logo"><i class="fas fa-store-alt me-2"></i> Panel Vendor</div>
    <div class="nav-section">Dashboard</div>
    <a href="{{ route('vendor.dashboard') }}" class="nav-link {{ request()->routeIs('vendor.dashboard') ? 'active' : '' }}">
        <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
    <div class="nav-section">POS</div>
    <a href="{{ route('vendor.pos.index') }}" class="nav-link {{ request()->routeIs('vendor.pos.*') ? 'active' : '' }}">
        <i class="fas fa-cash-register"></i> Point of Sale
    </a>
    <div class="nav-section">Produk</div>
    <a href="{{ route('vendor.products.index') }}" class="nav-link {{ request()->routeIs('vendor.products.*') ? 'active' : '' }}">
        <i class="fas fa-box"></i> Produk Saya
    </a>
    <a href="{{ route('vendor.bulk-import.index') }}" class="nav-link {{ request()->routeIs('vendor.bulk-import.*') ? 'active' : '' }}">
        <i class="fas fa-upload"></i> Bulk Import
    </a>
    <a href="{{ route('vendor.barcode.index') }}" class="nav-link {{ request()->routeIs('vendor.barcode.*') ? 'active' : '' }}">
        <i class="fas fa-barcode"></i> Barcode
    </a>
    <a href="{{ route('vendor.gallery.index') }}" class="nav-link {{ request()->routeIs('vendor.gallery.*') ? 'active' : '' }}">
        <i class="fas fa-images"></i> Galeri
    </a>
    <a href="{{ route('vendor.digital.index') }}" class="nav-link {{ request()->routeIs('vendor.digital.*') ? 'active' : '' }}">
        <i class="fas fa-file-download"></i> Produk Digital
    </a>
    <a href="{{ route('vendor.limited-stock.index') }}" class="nav-link {{ request()->routeIs('vendor.limited-stock.*') ? 'active' : '' }}">
        <i class="fas fa-exclamation-triangle"></i> Stok Menipis
    </a>
    <a href="{{ route('vendor.restock.index') }}" class="nav-link {{ request()->routeIs('vendor.restock.*') ? 'active' : '' }}">
        <i class="fas fa-redo"></i> Restock Request
    </a>
    <div class="nav-section">Pesanan</div>
    <a href="{{ route('vendor.orders.index') }}" class="nav-link {{ request()->routeIs('vendor.orders.*') ? 'active' : '' }}">
        <i class="fas fa-shopping-cart"></i> Pesanan
    </a>
    <a href="{{ route('vendor.refund.index') }}" class="nav-link {{ request()->routeIs('vendor.refund.*') ? 'active' : '' }}">
        <i class="fas fa-undo"></i> Refund
    </a>
    <a href="{{ route('vendor.reviews.index') }}" class="nav-link {{ request()->routeIs('vendor.reviews.*') ? 'active' : '' }}">
        <i class="fas fa-star"></i> Ulasan
    </a>
    <div class="nav-section">Promosi</div>
    <a href="{{ route('vendor.coupon.index') }}" class="nav-link {{ request()->routeIs('vendor.coupon.*') ? 'active' : '' }}">
        <i class="fas fa-ticket-alt"></i> Kupon Toko
    </a>
    <a href="{{ route('vendor.clearance.index') }}" class="nav-link {{ request()->routeIs('vendor.clearance.*') ? 'active' : '' }}">
        <i class="fas fa-tags"></i> Clearance Sale
    </a>
    <div class="nav-section">Support</div>
    <a href="{{ route('vendor.chat.inbox') }}" class="nav-link {{ request()->routeIs('vendor.chat.*') ? 'active' : '' }}">
        <i class="fas fa-comments"></i> Chat / Inbox
    </a>
    <div class="nav-section">Laporan</div>
    <a href="{{ route('vendor.report.products') }}" class="nav-link {{ request()->routeIs('vendor.report.*') ? 'active' : '' }}">
        <i class="fas fa-chart-bar"></i> Laporan
    </a>
    <div class="nav-section">Keuangan</div>
    <a href="{{ route('vendor.wallet.index') }}" class="nav-link {{ request()->routeIs('vendor.wallet.*') ? 'active' : '' }}">
        <i class="fas fa-wallet"></i> Wallet & Payout
    </a>
    <div class="nav-section">Pengaturan</div>
    <a href="{{ route('vendor.shipping.index') }}" class="nav-link {{ request()->routeIs('vendor.shipping.*') ? 'active' : '' }}">
        <i class="fas fa-shipping-fast"></i> Metode Pengiriman
    </a>
    <a href="{{ route('vendor.shop.settings') }}" class="nav-link {{ request()->routeIs('vendor.shop.*') ? 'active' : '' }}">
        <i class="fas fa-cog"></i> Pengaturan Toko
    </a>
    <a href="{{ route('vendor.logout') }}" class="nav-link" onclick="event.preventDefault(); document.getElementById('logoutForm').submit();">
        <i class="fas fa-sign-out-alt"></i> Keluar
    </a>
    <form id="logoutForm" action="{{ route('vendor.logout') }}" method="POST" class="d-none">@csrf</form>
</nav>

<header class="topbar">
    <button class="btn btn-link text-dark d-lg-none p-0" onclick="toggleSidebar()"><i class="fas fa-bars fa-lg"></i></button>
    <div class="d-flex align-items-center gap-3 ms-auto">
        <span class="fw-medium small">{{ auth('vendor')->user()?->shop?->name ?? 'Vendor' }}</span>
        <i class="fas fa-user-circle fa-lg text-muted"></i>
    </div>
</header>

<main class="main-content">
    @if(session('success'))<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}</div>@endif
    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.min.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('show');
        const overlay = document.getElementById('sidebarOverlay');
        overlay.style.display = document.getElementById('sidebar').classList.contains('show') ? 'block' : 'none';
    }
</script>
@stack('scripts')
</body>
</html>
