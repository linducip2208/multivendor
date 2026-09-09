<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>Masuk — {{ config('app.name') }}</title>
    @vite(['resources/css/tabler.css', 'resources/js/tabler.js'])
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root { --brand-primary: {{ $whitelabel['brandColor'] }}; --brand-dark: {{ $whitelabel['brandColorDark'] }}; }
        body { min-height: 100vh; background: #f4f6fa; }
        .auth-hero { min-height: 100vh; background: radial-gradient(circle at 80% 10%, rgba(45,212,191,.25), transparent 34%), linear-gradient(145deg, #0b1220, #102a43 62%, #0f766e); }
        .auth-grid { min-height: 100vh; }
        .auth-form { max-width: 460px; }
        .auth-card { border: 1px solid #e7ebf0; border-radius: 16px; box-shadow: 0 16px 48px rgba(24,36,51,.09); }
        .brand-mark { width: 46px; height: 46px; display: inline-flex; align-items: center; justify-content: center; border-radius: 13px; background: rgba(255,255,255,.13); color: #fff; font-size: 1.25rem; }
        .benefit { background: rgba(255,255,255,.09); border: 1px solid rgba(255,255,255,.12); border-radius: 12px; }
        .auth-input { min-height: 46px; border-radius: 9px; }
        .btn-auth { min-height: 46px; border: 0; color: #fff; border-radius: 9px; background: linear-gradient(135deg, var(--brand-primary), var(--brand-dark)); }
        .btn-auth:hover { color: #fff; filter: brightness(1.06); transform: translateY(-1px); }
        .demo-box { background: #f8fafc; border: 1px solid #e7ebf0; border-radius: 10px; }
        @media (max-width: 991px) { .auth-hero { min-height: auto; } .auth-grid { min-height: auto; } }
    </style>
</head>
<body>
<div class="container-fluid p-0">
    <div class="row g-0 auth-grid">
        <section class="col-lg-5 auth-hero text-white d-flex flex-column justify-content-between p-4 p-xl-5">
            <a href="{{ route('home') }}" class="text-white text-decoration-none d-flex align-items-center gap-3">
                <span class="brand-mark"><i class="fas fa-store"></i></span>
                <span class="fs-3 fw-bold">{{ $whitelabel['appName'] }}</span>
            </a>
            <div class="py-5">
                <div class="text-uppercase small fw-bold opacity-75 mb-3" style="letter-spacing:.14em">Marketplace untuk semua</div>
                <h1 class="display-5 fw-bold lh-sm mb-3">Temukan produk pilihan dari toko terpercaya.</h1>
                <p class="lead opacity-75 mb-4">Satu akun untuk belanja lintas vendor, melacak pesanan, menikmati promo, dan mengumpulkan loyalty point.</p>
                <div class="row g-2">
                    <div class="col-4"><div class="benefit p-3 h-100"><i class="fas fa-shield-halved mb-3"></i><div class="small fw-semibold">Checkout aman</div></div></div>
                    <div class="col-4"><div class="benefit p-3 h-100"><i class="fas fa-truck-fast mb-3"></i><div class="small fw-semibold">Lacak kiriman</div></div></div>
                    <div class="col-4"><div class="benefit p-3 h-100"><i class="fas fa-gift mb-3"></i><div class="small fw-semibold">Reward belanja</div></div></div>
                </div>
            </div>
            <div class="small opacity-50">© {{ date('Y') }} {{ $whitelabel['appName'] }}</div>
        </section>
        <main class="col-lg-7 d-flex align-items-center justify-content-center p-3 p-md-5">
            <div class="auth-form w-100">
                <div class="mb-4">
                    <div class="d-lg-none d-inline-flex align-items-center gap-2 text-primary fw-bold mb-4"><i class="fas fa-store"></i>{{ $whitelabel['appName'] }}</div>
                    <h2 class="fw-bold mb-1">Masuk ke akun Anda</h2>
                    <p class="text-muted mb-0">Belum punya akun? <a href="{{ route('register') }}" class="fw-semibold text-decoration-none">Daftar gratis</a></p>
                </div>
                <div class="auth-card bg-white p-4 p-md-5">
                    @if($errors->any())<div class="alert alert-danger"><i class="fas fa-circle-exclamation me-2"></i>{{ $errors->first() }}</div>@endif
                    <form method="POST" action="{{ route('login') }}">
                        @csrf
                        <div class="mb-3"><label class="form-label fw-semibold">Email</label><input type="email" name="email" class="form-control auth-input" value="{{ old('email') }}" placeholder="customer@multivendor.test" required autofocus></div>
                        <div class="mb-3"><div class="d-flex justify-content-between"><label class="form-label fw-semibold">Password</label><span class="small text-muted">Hubungi CS jika lupa</span></div><input type="password" name="password" class="form-control auth-input" placeholder="Masukkan password" required></div>
                        <div class="form-check mb-4"><input type="checkbox" name="remember" class="form-check-input" id="remember"><label class="form-check-label small" for="remember">Ingat saya</label></div>
                        <button type="submit" class="btn btn-auth w-100 fw-semibold"><i class="fas fa-arrow-right-to-bracket me-2"></i>Masuk</button>
                    </form>
                    <div class="d-flex align-items-center gap-3 my-4"><hr class="flex-fill m-0"><span class="text-muted small">atau</span><hr class="flex-fill m-0"></div>
                    <a href="{{ route('social.redirect', 'google') }}" class="btn btn-outline-secondary w-100"><i class="fab fa-google me-2"></i>Lanjutkan dengan Google</a>
                    <div class="demo-box p-3 mt-4"><div class="fw-semibold small mb-2"><i class="fas fa-flask text-warning me-2"></i>Demo Customer</div><div class="font-monospace text-muted small">customer@multivendor.test / password</div></div>
                </div>
                <div class="text-center mt-4"><a href="{{ route('home') }}" class="small text-muted text-decoration-none"><i class="fas fa-arrow-left me-1"></i>Kembali ke marketplace</a></div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
