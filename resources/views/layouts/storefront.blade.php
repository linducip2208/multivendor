<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="{{ $whitelabel['favicon'] ?? asset('favicon.svg') }}">
    <title>@yield('title', $whitelabel['appName'])</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family={{ $whitelabel['fontFamily'] ?? 'Inter' }}:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-primary: {{ $whitelabel['brandColor'] }};
            --brand-dark: {{ $whitelabel['brandColorDark'] }};
            --border-radius: {{ $whitelabel['borderRadius'] ?? 14 }}px;
        }
        body { font-family: '{{ $whitelabel['fontFamily'] ?? 'Inter' }}', system-ui, sans-serif; background: #f8f9fc; }
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
        <a class="navbar-brand fw-bold" href="/">
            @if($whitelabel['logo'])
                <img src="{{ $whitelabel['logo'] }}" alt="{{ $whitelabel['appName'] }}" style="max-height:32px;">
            @else
                <i class="fas fa-store-alt text-primary me-2"></i>{{ $whitelabel['appName'] }}
            @endif
        </a>
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
<script>
(function(){
  var lastShown = localStorage.getItem('popupLastShown');
  var now = Date.now();
  if(!lastShown || (now - parseInt(lastShown)) > 600000){
    setTimeout(function(){
      var el = document.getElementById('sourceCodePopup');
      if(el){ el.style.display = 'flex'; }
      localStorage.setItem('popupLastShown', Date.now().toString());
    },30000);
  }
})();
</script>
@stack('scripts')

{{-- Source Code Sales Popup --}}
<div id="sourceCodePopup" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:99999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:20px;max-width:600px;width:90%;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.3)">
    <div style="background:linear-gradient(135deg,#4F46E5,#7C3AED);padding:20px 24px;color:#fff;display:flex;justify-content:space-between;align-items:center">
      <h5 style="margin:0;font-weight:700">🚀 Butuh Aplikasi Seperti Ini?</h5>
      <button onclick="document.getElementById('sourceCodePopup').style.display='none'" style="background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer">&times;</button>
    </div>
    <div style="padding:24px">
      <h4 style="font-weight:700;text-align:center;margin-bottom:16px">Source Code Multivendor — Pengganti Shopee, Tokopedia, Lazada</h4>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px">
        <div style="border:1px solid #e2e8f0;border-radius:12px;padding:12px;text-align:center"><div style="font-size:2rem;color:#4F46E5">🏪</div><div style="font-weight:700">Multi-Vendor</div><small style="color:#64748b">Ratusan toko 1 platform</small></div>
        <div style="border:1px solid #e2e8f0;border-radius:12px;padding:12px;text-align:center"><div style="font-size:2rem;color:#059669">💳</div><div style="font-weight:700">Payment Gateway</div><small style="color:#64748b">Midtrans, Xendit, Tripay</small></div>
        <div style="border:1px solid #e2e8f0;border-radius:12px;padding:12px;text-align:center"><div style="font-size:2rem;color:#D97706">🚚</div><div style="font-weight:700">Ongkos Kirim</div><small style="color:#64748b">JNE, J&T, SiCepat</small></div>
        <div style="border:1px solid #e2e8f0;border-radius:12px;padding:12px;text-align:center"><div style="font-size:2rem;color:#9333EA">🤖</div><div style="font-weight:700">AI Analytics</div><small style="color:#64748b">DeepSeek, OpenAI, Ollama</small></div>
      </div>
      <div style="text-align:center;background:#f1f5f9;border-radius:12px;padding:12px;margin-bottom:16px">
        <div style="font-weight:700;font-size:1.2rem">💰 Mulai dari Rp 5.000.000</div>
        <small style="color:#64748b">Free Install + Setup + 1 Bulan Support</small>
      </div>
      <div style="display:flex;gap:8px;justify-content:center">
        <a href="https://wa.me/6281296052010?text=Halo%20saya%20tertarik%20source%20code%20multivendor%20ecommerce" target="_blank" style="background:#25D366;color:#fff;padding:12px 24px;border-radius:12px;text-decoration:none;font-weight:600;font-size:1.1rem">💬 Chat WhatsApp</a>
        <button onclick="document.getElementById('sourceCodePopup').style.display='none'" style="border:1px solid #e2e8f0;background:#fff;padding:12px 24px;border-radius:12px;cursor:pointer;color:#64748b">Nanti Saja</button>
      </div>
    </div>
  </div>
</div>
<a href="https://wa.me/6281296052010?text=Halo%20saya%20mau%20tanya" target="_blank" style="position:fixed;bottom:24px;right:24px;z-index:9999;width:56px;height:56px;background:#25D366;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(37,211,102,.4);text-decoration:none;" title="Chat WhatsApp">
    <i class="fab fa-whatsapp fa-2x text-white"></i>
</a>
</body>
</html>
