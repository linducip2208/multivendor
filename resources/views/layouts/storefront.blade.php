<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', config('app.name'))</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --brand-primary: #4F46E5; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f8f9fc; }
        .navbar { background: white; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .product-card { border: none; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,.04); transition: transform .25s ease, box-shadow .25s ease; overflow: hidden; }
        .product-card:hover { transform: translateY(-6px); box-shadow: 0 16px 48px rgba(0,0,0,.12); }
        .product-card .card-img-top { height: 200px; object-fit: cover; background: #f1f5f9; }
        .gallery-img:hover { transform: scale(1.05); transition: transform .2s; }
        .btn-primary { background: var(--brand-primary); border-color: var(--brand-primary); border-radius: 10px; font-weight: 500; }
        .btn-primary:hover { background: #4338CA; }
        .line-clamp-2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
        .sticky-cta { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; padding: 12px 16px; box-shadow: 0 -4px 20px rgba(0,0,0,.08); z-index: 999; display: flex; gap: 8px; align-items: center; }
        @media (min-width: 768px) { .sticky-cta { display: none; } }
        .trust-badges { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; font-size: .75rem; color: #6b7280; }
        .trust-badges i { color: #059669; margin-right: 4px; }
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 4rem; color: #e2e8f0; margin-bottom: 16px; }
        .empty-state h5 { font-weight: 600; color: #475569; }
        .empty-state .btn { margin-top: 12px; }
        .variant-badge { cursor: pointer; transition: all .15s; }
        .variant-badge:hover, .variant-badge.active { border-color: var(--brand-primary) !important; background: #eef2ff !important; color: var(--brand-primary) !important; }
        .page-link { font-size: .75rem; padding: 4px 8px; line-height: 1.2; }
        .page-link .fas, .page-link .far, .page-link svg { font-size: .65rem !important; width: 10px !important; height: 10px !important; }
        .pagination { --bs-pagination-font-size: .75rem; --bs-pagination-padding-x: .5rem; --bs-pagination-padding-y: .25rem; margin-bottom: 0; }
        .pagination svg { width: 10px !important; height: 10px !important; }
        .pagination .page-item .page-link { font-size: .75rem !important; }
        .pagination .page-item:first-child .page-link svg,
        .pagination .page-item:last-child .page-link svg,
        .pagination .page-item .page-link svg { max-width: 12px !important; max-height: 12px !important; width: 12px !important; height: 12px !important; }
        footer p, footer small { font-size: .8rem !important; }
        footer { font-size: .8rem; }
        .text-muted.small, small.text-muted { font-size: .8125rem !important; }
    </style>
    @stack('head')
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/"><i class="fas fa-store-alt text-primary me-2"></i>{{ config('app.name') }}</a>
        <button class="navbar-toggler border-0" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="/">{{ __('Home') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('products.index') }}">{{ __('Products') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('feed') }}"><i class="fas fa-fire text-danger me-1"></i>Feed</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('bundles') }}"><i class="fas fa-cubes me-1"></i>Bundle</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('blog.index') }}">Blog</a></li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                @auth
                    <a href="{{ route('wishlist.index') }}" class="btn btn-outline-danger btn-sm"><i class="fas fa-heart"></i></a>
                    <a href="{{ route('compare.index') }}" class="btn btn-outline-info btn-sm"><i class="fas fa-balance-scale"></i></a>
                    <a href="{{ route('cart.index') }}" class="btn btn-outline-secondary btn-sm position-relative">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cartCount">{{ \App\Models\Cart::where('customer_id', auth()->id())->count() }}</span>
                    </a>
                    <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-list-alt"></i> Pesanan</a>
                    <a href="{{ route('tickets.index') }}" class="btn btn-outline-warning btn-sm"><i class="fas fa-headset"></i></a>
                    <a href="{{ route('loyalty.index') }}" class="btn btn-outline-warning btn-sm"><i class="fas fa-coins"></i></a>
                    <a href="{{ route('profile.index') }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-user"></i></a>
                    <a href="{{ route('group-buys') }}" class="btn btn-outline-success btn-sm"><i class="fas fa-users"></i></a>
                    <a href="{{ route('leaderboard') }}" class="btn btn-outline-info btn-sm"><i class="fas fa-trophy"></i></a>
                    <div class="dropdown">
                        <button class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">{{ auth()->user()->name }}</button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item"><i class="fas fa-sign-out-alt me-2"></i>Keluar</button></form></li>
                        </ul>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm">Masuk</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Daftar</a>
                @endauth
            </div>
            <div class="d-flex align-items-center gap-1 ms-2">
                <a href="?lang=id" class="btn btn-sm btn-outline-secondary px-2 {{ app()->getLocale() === 'id' ? 'active fw-bold' : '' }}">ID</a>
                <a href="?lang=en" class="btn btn-sm btn-outline-secondary px-2 {{ app()->getLocale() === 'en' ? 'active fw-bold' : '' }}">EN</a>
            </div>
        </div>
    </div>
</nav>

<main class="py-4">
    @if(session('success'))<div class="container"><div class="alert alert-success">{{ session('success') }}</div></div>@endif
    @if(session('error'))<div class="container"><div class="alert alert-danger">{{ session('error') }}</div></div>@endif
    @yield('content')
</main>

<footer class="bg-dark text-white py-4 mt-5 border-top">
    <div class="container">
        <div class="row g-3 mb-3">
            <div class="col-md-4 text-center text-md-start">
                <div class="trust-badges justify-content-center justify-content-md-start mb-2">
                    <span><i class="fas fa-shield-alt"></i> Pembayaran Aman</span>
                    <span><i class="fas fa-sync-alt"></i> Garansi 7 Hari</span>
                    <span><i class="fas fa-headset"></i> Support 24/7</span>
                </div>
            </div>
            <div class="col-md-4 text-center">
                <small class="opacity-50">Didukung Pembayaran:</small>
                <div class="d-flex gap-2 justify-content-center mt-1 flex-wrap">
                    <span class="badge bg-secondary">Midtrans</span>
                    <span class="badge bg-secondary">Xendit</span>
                    <span class="badge bg-secondary">Tripay</span>
                    <span class="badge bg-secondary">Duitku</span>
                    <span class="badge bg-secondary">QRIS</span>
                </div>
            </div>
            <div class="col-md-4 text-center text-md-end">
                <small class="opacity-50">Pengiriman:</small>
                <div class="d-flex gap-2 justify-content-center justify-content-md-end mt-1 flex-wrap">
                    <span class="badge bg-dark">JNE</span>
                    <span class="badge bg-dark">J&T</span>
                    <span class="badge bg-dark">SiCepat</span>
                    <span class="badge bg-dark">TIKI</span>
                </div>
            </div>
        </div>
        <div class="text-center small opacity-50">
            &copy; {{ date('Y') }} {{ config('app.name') }}. Platform Multivendor Indonesia.
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')

{{-- Source Code Sales Popup --}}
@if(!request()->is('admin*') && !request()->is('vendor*'))
<div class="modal fade" id="sourceCodePopup" tabindex="-1" data-bs-backdrop="static">
<div class="modal fade" id="sourceCodePopup" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 rounded-4 overflow-hidden">
      <div class="modal-header border-0 text-white" style="background:linear-gradient(135deg,#4F46E5,#7C3AED)">
        <h5 class="fw-bold mb-0">🚀 Butuh Aplikasi Seperti Ini?</h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <h4 class="fw-bold text-center mb-3">Source Code Multivendor — Pengganti Shopee, Tokopedia, Lazada</h4>
        <div class="row g-3 mb-3">
          <div class="col-6"><div class="border rounded-3 p-2 text-center"><i class="fas fa-store-alt fa-2x text-primary mb-2"></i><div class="fw-bold">Multi-Vendor</div><small class="text-muted">Ratusan toko dalam 1 platform</small></div></div>
          <div class="col-6"><div class="border rounded-3 p-2 text-center"><i class="fas fa-credit-card fa-2x text-success mb-2"></i><div class="fw-bold">Payment Gateway</div><small class="text-muted">Midtrans, Xendit, Tripay, dll</small></div></div>
          <div class="col-6"><div class="border rounded-3 p-2 text-center"><i class="fas fa-truck fa-2x text-warning mb-2"></i><div class="fw-bold">Ongkos Kirim</div><small class="text-muted">JNE, J&T, SiCepat, dll</small></div></div>
          <div class="col-6"><div class="border rounded-3 p-2 text-center"><i class="fas fa-robot fa-2x text-purple mb-2"></i><div class="fw-bold">AI Analytics</div><small class="text-muted">DeepSeek, OpenAI, Ollama</small></div></div>
        </div>
        <div class="text-center bg-light rounded-3 p-3 mb-3">
          <div class="fw-bold fs-5">💰 Mulai dari Rp 5.000.000</div>
          <small class="text-muted">Free Install + Setup + 1 Bulan Support</small>
        </div>
        <div class="d-flex gap-2 justify-content-center">
          <a href="https://wa.me/6281296052010?text=Halo%20saya%20tertarik%20source%20code%20multivendor%20ecommerce" target="_blank" class="btn btn-success btn-lg px-4"><i class="fab fa-whatsapp me-2"></i>Chat WhatsApp</a>
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Nanti Saja</button>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
  if(!sessionStorage.getItem('popupShown')){
    setTimeout(function(){
      var popup = new bootstrap.Modal(document.getElementById('sourceCodePopup'));
      popup.show();
      sessionStorage.setItem('popupShown','1');
    },5000);
  }
});
</script>
@endif
<a href="https://wa.me/6281296052010?text=Halo%20saya%20mau%20tanya" target="_blank" style="position:fixed;bottom:24px;right:24px;z-index:9999;width:56px;height:56px;background:#25D366;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(37,211,102,.4);text-decoration:none;" title="Chat WhatsApp">
    <i class="fab fa-whatsapp fa-2x text-white"></i>
</a>
</body>
</html>
