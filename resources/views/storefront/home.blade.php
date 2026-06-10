<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>{{ config('app.name') }} — Platform Multivendor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand-primary: #4F46E5; --brand-dark: #3730A3; }
        body { font-family: 'Inter', system-ui, sans-serif; }
        .navbar { backdrop-filter: blur(12px); background: rgba(255,255,255,.9) !important; }
        .hero {
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 50%, #3730A3 100%);
            position: relative; overflow: hidden;
        }
        .hero::before {
            content: ''; position: absolute; width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(255,255,255,.08) 0%, transparent 70%);
            top: -200px; right: -100px; border-radius: 50%;
        }
        .btn-primary { background: linear-gradient(135deg, var(--brand-primary), var(--brand-dark)); border: none; border-radius: 12px; padding: 14px 32px; font-weight: 600; }
        .btn-outline-light { border-radius: 12px; padding: 14px 32px; font-weight: 600; }
        .feature-icon { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold fs-4" href="/">
            <i class="fas fa-store-alt text-primary me-2"></i> {{ config('app.name') }}
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center gap-2">
                <li class="nav-item"><a class="nav-link" href="#">Produk</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Kategori</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Brand</a></li>
                @auth
                    @if(auth()->user()->isAdmin())
                        <a class="btn btn-primary btn-sm ms-2" href="{{ route('admin.dashboard') }}"><i class="fas fa-tachometer-alt me-1"></i> Admin Panel</a>
                    @elseif(auth()->user()->isVendor())
                        <a class="btn btn-primary btn-sm ms-2" href="{{ route('vendor.dashboard') }}"><i class="fas fa-store me-1"></i> Panel Vendor</a>
                    @else
                        <span class="nav-link">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}" class="d-inline">
                            @csrf
                            <button class="btn btn-outline-secondary btn-sm"><i class="fas fa-sign-out-alt"></i></button>
                        </form>
                    @endif
                @else
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('login') }}">Masuk</a>
                    <a class="btn btn-primary btn-sm ms-1" href="{{ route('register') }}">Daftar</a>
                @endauth
            </ul>
        </div>
    </div>
</nav>

{{-- Hero --}}
<section class="hero text-white py-5" style="min-height:85vh;">
    <div class="container position-relative" style="z-index:1;">
        <div class="row align-items-center min-vh-75">
            <div class="col-lg-7 py-5">
                <h1 class="display-3 fw-bold mb-4">Platform <span class="text-warning">Multivendor</span> #1 di Indonesia</h1>
                <p class="fs-5 mb-4 opacity-90">Bangun marketplace Anda sendiri. Ratusan vendor, ribuan produk, satu platform. Kelola semuanya dari satu dashboard.</p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="{{ route('register') }}" class="btn btn-warning btn-lg fw-bold px-4"><i class="fas fa-rocket me-2"></i> Mulai Gratis</a>
                    <a href="#" class="btn btn-outline-light btn-lg"><i class="fas fa-play me-2"></i> Lihat Demo</a>
                </div>
                <div class="row g-3 mt-5">
                    <div class="col-auto"><div class="d-flex align-items-center gap-2"><i class="fas fa-check-circle text-warning"></i> <span>Multi Vendor</span></div></div>
                    <div class="col-auto"><div class="d-flex align-items-center gap-2"><i class="fas fa-check-circle text-warning"></i> <span>Payment Gateway</span></div></div>
                    <div class="col-auto"><div class="d-flex align-items-center gap-2"><i class="fas fa-check-circle text-warning"></i> <span>Shipping Otomatis</span></div></div>
                    <div class="col-auto"><div class="d-flex align-items-center gap-2"><i class="fas fa-check-circle text-warning"></i> <span>Wallet System</span></div></div>
                </div>
            </div>
            <div class="col-lg-5 text-center d-none d-lg-block">
                <div class="bg-white bg-opacity-10 rounded-4 p-4 backdrop-blur">
                    <div class="bg-white rounded-3 p-3 text-dark text-start">
                        <div class="d-flex gap-2 mb-3"><span class="bg-danger rounded-circle" style="width:10px;height:10px;"></span><span class="bg-warning rounded-circle" style="width:10px;height:10px;"></span><span class="bg-success rounded-circle" style="width:10px;height:10px;"></span></div>
                        <div class="bg-light rounded-2 p-2 mb-2 small font-monospace">🔍 Cari produk favoritmu...</div>
                        <div class="row g-2">
                            <div class="col-4"><div class="bg-light rounded-2 p-3 text-center"><i class="fas fa-mobile-alt fa-2x text-primary"></i><div class="small mt-1">Gadget</div></div></div>
                            <div class="col-4"><div class="bg-light rounded-2 p-3 text-center"><i class="fas fa-tshirt fa-2x text-success"></i><div class="small mt-1">Fashion</div></div></div>
                            <div class="col-4"><div class="bg-light rounded-2 p-3 text-center"><i class="fas fa-home fa-2x text-warning"></i><div class="small mt-1">Rumah</div></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Features --}}
<section class="py-5">
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Fitur Lengkap Multivendor</h2>
            <p class="text-muted">Semua yang Anda butuhkan untuk menjalankan marketplace</p>
        </div>
        <div class="row g-4">
            @php
            $features = [
                ['icon'=>'fa-store-alt','color'=>'primary','t'=>'Multi Vendor','d'=>'Vendor bisa daftar, buka toko, dan kelola produk sendiri. Admin kontrol penuh + approve/reject.'],
                ['icon'=>'fa-box','color'=>'success','t'=>'Manajemen Produk','d'=>'Produk dengan varian, stok, diskon, SEO meta. Support produk fisik & digital.'],
                ['icon'=>'fa-shopping-cart','color'=>'warning','t'=>'Smart Cart & Checkout','d'=>'Global cart dari banyak toko dalam 1 checkout. Split order per vendor otomatis.'],
                ['icon'=>'fa-credit-card','color'=>'info','t'=>'Payment Gateway','d'=>'10 gateway preset. Midtrans, Xendit, Tripay, Duitku, OY, iPaymu, Faspay, DOKU, ESIA Pay. BYOK.'],
                ['icon'=>'fa-truck','color'=>'danger','t'=>'Shipping System','d'=>'16 kurir preset. RajaOngkir, JNE, J&T, SiCepat, TIKI, POS, GoSend, GrabExpress, Borzo, Deliveree.'],
                ['icon'=>'fa-robot','color'=>'purple','t'=>'AI Analytics','d'=>'10 AI provider: DeepSeek, OpenAI, Groq, Ollama, dll. Analisis produk paling laris + rekomendasi.'],
                ['icon'=>'fa-wallet','color'=>'teal','t'=>'Wallet & Payout','d'=>'Dompet digital customer + vendor. Komisi otomatis per transaksi. Pencairan dana vendor.'],
                ['icon'=>'fa-ticket-alt','color'=>'orange','t'=>'Kupon & Flash Deal','d'=>'Kupon diskon (%, Rp, free ongkir). Flash deal dengan timer. Deal of the day.'],
                ['icon'=>'fa-chart-bar','color'=>'indigo','t'=>'Laporan & Analitik','d'=>'Revenue, sales, product, transaction reports. Top produk table. AI-powered insight.'],
                ['icon'=>'fa-blog','color'=>'pink','t'=>'Blog & SEO','d'=>'Blog untuk konten marketing. Sitemap auto-generate. IndexNow auto-submit. robots.txt.'],
                ['icon'=>'fa-users','color'=>'primary','t'=>'Pelanggan','d'=>'Data pelanggan lengkap: wallet, alamat, order history. Customer login & register.'],
                ['icon'=>'fa-bell','color'=>'warning','t'=>'Notifikasi','d'=>'Push notification ke pelanggan. In-app notification system. Firebase ready.'],
                ['icon'=>'fa-image','color'=>'danger','t'=>'Banner','d'=>'Kelola banner marketing: hero, sidebar, footer, popup. Atur posisi & urutan.'],
                ['icon'=>'fa-plug','color'=>'info','t'=>'Integrasi Dinamis','d'=>'Semua provider BYOK. User input API key sendiri. Payment, shipping, AI — key terpisah.'],
                ['icon'=>'fa-shield-alt','color'=>'success','t'=>'Keamanan','d'=>'API key dienkripsi di database. CSRF protection. Input validation. XSS prevention.'],
                ['icon'=>'fa-coins','color'=>'warning','t'=>'Loyalty & Referral','d'=>'Poin reward dari belanja + referral. Tukar poin ke wallet. Kode referral unik per user.'],
            ];
            @endphp
            @foreach($features as $f)
            <div class="col-md-6 col-lg-3"><div class="card border-0 shadow-sm h-100 rounded-4 p-4 text-center card-lift"><div class="feature-icon bg-{{ $f['color'] }}-subtle text-{{ $f['color'] }} mx-auto mb-3"><i class="fas {{ $f['icon'] }}"></i></div><h5 class="fw-bold small">{{ $f['t'] }}</h5><p class="text-muted small mb-0">{{ $f['d'] }}</p></div></div>
            @endforeach
        </div>
    </div>
</section>

{{-- Deal of the Day --}}
@if($dealOfTheDay && $dealOfTheDay->product)
<section class="py-5 bg-gradient" style="background:linear-gradient(135deg,#fef3c7,#fde68a);">
    <div class="container"><div class="text-center mb-4"><span class="badge bg-danger fs-5 mb-2">⚡ DEAL OF THE DAY</span><h2 class="fw-bold">{{ $dealOfTheDay->product->name }}</h2></div>
        <div class="row align-items-center justify-content-center"><div class="col-md-4 text-center">
            <div class="bg-white rounded-4 p-4 shadow-sm">
                @if($dealOfTheDay->product->thumbnail)<img src="{{ url('img/'.$dealOfTheDay->product->thumbnail) }}" class="img-fluid rounded-3" style="max-height:250px;object-fit:contain;">@else<i class="fas fa-box fa-5x text-muted opacity-25"></i>@endif
            </div></div>
            <div class="col-md-4"><div class="text-center"><p class="text-muted">{{ Str::limit(strip_tags($dealOfTheDay->product->short_description ?? ''), 100) }}</p>
                <div class="mb-3"><span class="fs-2 fw-bold text-danger">Rp {{ number_format($dealOfTheDay->product->getEffectivePrice(), 0, ',', '.') }}</span><br><span class="text-muted text-decoration-line-through">Rp {{ number_format($dealOfTheDay->product->price, 0, ',', '.') }}</span> <span class="badge bg-danger">-{{ $dealOfTheDay->discount_type === 'percentage' ? $dealOfTheDay->discount_value.'%' : 'Rp'.number_format($dealOfTheDay->discount_value,0,',','.') }}</span></div>
                <a href="{{ route('products.show', $dealOfTheDay->product->slug) }}" class="btn btn-danger btn-lg px-5"><i class="fas fa-shopping-cart me-2"></i>Beli Sekarang</a>
            </div></div>
        </div>
    </div>
</section>
@endif

{{-- Featured Products --}}
@if($featuredDeals->count() > 0)
<section class="py-5"><div class="container"><div class="text-center mb-4"><h2 class="fw-bold">⭐ Produk Unggulan</h2></div>
    <div class="row g-3">@foreach($featuredDeals as $fp)
    <div class="col-6 col-md-3"><a href="{{ route('products.show', $fp->slug) }}" class="text-decoration-none"><div class="card product-card h-100"><div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height:180px;">@if($fp->thumbnail)<img src="{{ url('img/'.$fp->thumbnail) }}" class="w-100 h-100" style="object-fit:contain;">@else<i class="fas fa-box fa-3x text-muted opacity-25"></i>@endif</div><div class="card-body p-3"><div class="small text-muted mb-1">{{ $fp->shop->name ?? '' }}</div><h6 class="fw-semibold small line-clamp-2 text-dark">{{ $fp->name }}</h6><span class="fw-bold text-primary">Rp {{ number_format($fp->getEffectivePrice(),0,',','.') }}</span></div></div></a></div>@endforeach</div>
</div></section>
@endif

{{-- Flash Deals --}}
@if($flashDeals->count() > 0)
<section class="py-5 bg-light"><div class="container"><div class="text-center mb-4"><h2 class="fw-bold">⚡ Flash Deals</h2></div>
@foreach($flashDeals as $fd)
<div class="mb-4"><h5 class="fw-bold mb-3">{{ $fd->title }} <small class="text-muted">s/d {{ $fd->end_date->format('d M H:i') }}</small></h5><div class="row g-3">@foreach($fd->products->take(4) as $fp)<div class="col-6 col-md-3"><a href="{{ route('products.show', $fp->slug) }}" class="text-decoration-none"><div class="card product-card h-100"><div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height:150px;">@if($fp->thumbnail)<img src="{{ url('img/'.$fp->thumbnail) }}" class="w-100 h-100" style="object-fit:contain;">@else<i class="fas fa-box fa-3x text-muted opacity-25"></i>@endif</div><div class="card-body p-2"><h6 class="fw-semibold small line-clamp-2 text-dark">{{ $fp->name }}</h6><span class="fw-bold text-danger">Rp {{ number_format($fp->getEffectivePrice(),0,',','.') }}</span></div></div></a></div>@endforeach</div></div>
@endforeach
</div></section>
@endif

{{-- Demo Accounts --}}
<section class="py-5 bg-light">
    <div class="container py-4">
        <div class="text-center mb-4">
            <h2 class="fw-bold">Coba Demo</h2>
            <p class="text-muted">Gunakan akun berikut untuk mencoba platform</p>
        </div>
        <div class="row g-3 justify-content-center">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="feature-icon bg-primary-subtle text-primary"><i class="fas fa-user-shield"></i></div>
                            <div><h6 class="fw-bold mb-0">Admin</h6><small class="text-muted">Panel administrasi</small></div>
                        </div>
                        <div class="bg-light rounded-3 p-3 font-monospace small">
                            <div>admin@multivendor.test</div>
                            <div>password</div>
                        </div>
                        <a href="{{ route('admin.login') }}" class="btn btn-outline-primary btn-sm w-100 mt-3">Login Admin</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="feature-icon bg-success-subtle text-success"><i class="fas fa-store"></i></div>
                            <div><h6 class="fw-bold mb-0">Vendor</h6><small class="text-muted">Panel toko</small></div>
                        </div>
                        <div class="bg-light rounded-3 p-3 font-monospace small">
                            <div>vendor@multivendor.test</div>
                            <div>password</div>
                        </div>
                        <a href="{{ route('vendor.login') }}" class="btn btn-outline-success btn-sm w-100 mt-3">Login Vendor</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="feature-icon bg-warning-subtle text-warning"><i class="fas fa-user"></i></div>
                            <div><h6 class="fw-bold mb-0">Customer</h6><small class="text-muted">Belanja online</small></div>
                        </div>
                        <div class="bg-light rounded-3 p-3 font-monospace small">
                            <div>customer@multivendor.test</div>
                            <div>password</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Footer --}}
<footer class="bg-dark text-white py-4">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-3">
                <h5 class="fw-bold"><i class="fas fa-store-alt me-2"></i>{{ config('app.name') }}</h5>
                <p class="small opacity-75">Platform multivendor e-commerce lengkap dengan payment gateway, shipping, wallet, dan promo marketing.</p>
            </div>
            <div class="col-md-2 mb-3">
                <h6 class="fw-bold">Menu</h6>
                <ul class="list-unstyled small opacity-75">
                    <li><a href="/" class="text-white text-decoration-none">Home</a></li>
                    <li><a href="#" class="text-white text-decoration-none">Produk</a></li>
                    <li><a href="#" class="text-white text-decoration-none">Blog</a></li>
                </ul>
            </div>
            <div class="col-md-2 mb-3">
                <h6 class="fw-bold">Panel</h6>
                <ul class="list-unstyled small opacity-75">
                    <li><a href="{{ route('admin.login') }}" class="text-white text-decoration-none">Admin</a></li>
                    <li><a href="{{ route('vendor.login') }}" class="text-white text-decoration-none">Vendor</a></li>
                </ul>
            </div>
            <div class="col-md-4 mb-3">
                <h6 class="fw-bold">Kontak</h6>
                <p class="small opacity-75"><i class="fas fa-envelope me-2"></i> support@multivendor.test</p>
                <p class="small opacity-75"><i class="fab fa-whatsapp me-2"></i> +62 812-3456-7890</p>
            </div>
        </div>
        <hr class="opacity-25">
        <div class="text-center small opacity-50">&copy; {{ date('Y') }} {{ config('app.name') }}. Powered by Laravel.</div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
