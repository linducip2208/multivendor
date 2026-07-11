<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>Masuk Vendor — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand-primary: {{ $whitelabel['brandColor'] }}; --brand-dark: {{ $whitelabel['brandColorDark'] }}; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f1f5f9; }
        .login-left { background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-dark) 100%); position: relative; overflow: hidden; }
        .login-left::before { content: ''; position: absolute; width: 400px; height: 400px; background: radial-gradient(circle, rgba(255,255,255,.08) 0%, transparent 70%); top: -100px; right: -100px; border-radius: 50%; }
        .login-left::after { content: ''; position: absolute; width: 300px; height: 300px; background: radial-gradient(circle, rgba(255,255,255,.05) 0%, transparent 70%); bottom: -50px; left: -50px; border-radius: 50%; }
        .login-form-card { border: none; border-radius: 20px; box-shadow: 0 4px 40px rgba(0,0,0,.06); }
        .form-control { border-radius: 12px; padding: 12px 16px; border: 1.5px solid #e2e8f0; }
        .form-control:focus { border-color: var(--brand-primary); box-shadow: 0 0 0 3px rgba(5,150,105,.12); }
        .btn-login { background: linear-gradient(135deg, var(--brand-primary), var(--brand-dark)); border: none; border-radius: 12px; padding: 12px 24px; font-weight: 600; color: #fff; box-shadow: 0 4px 16px rgba(5,150,105,.3); }
        .btn-login:hover { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(5,150,105,.4); }
        .demo-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 14px; }
        @media (max-width: 767px) { .login-left { min-height: 200px; } }
    </style>
</head>
<body>
<div class="min-vh-100 d-flex">
    {{-- Left: Brand Hero — Green Theme --}}
    <div class="col-lg-5 d-none d-lg-flex login-left flex-column justify-content-center p-5 text-white">
        <div style="position:relative;z-index:1;">
            <div class="mb-4">
                <i class="fas fa-store-alt fa-3x mb-3"></i>
                <h1 class="display-5 fw-bold">Panel Vendor</h1>
                <p class="opacity-75 fs-5">Kelola toko, produk, dan pesanan Anda</p>
            </div>
            <div class="row g-3 mt-5">
                <div class="col-4">
                    <div class="p-3 rounded-4" style="background:rgba(255,255,255,.1);backdrop-filter:blur(8px);">
                        <i class="fas fa-box fa-2x mb-2 opacity-75"></i>
                        <div class="fw-semibold small">Kelola Produk</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-3 rounded-4" style="background:rgba(255,255,255,.1);backdrop-filter:blur(8px);">
                        <i class="fas fa-shopping-cart fa-2x mb-2 opacity-75"></i>
                        <div class="fw-semibold small">Proses Pesanan</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-3 rounded-4" style="background:rgba(255,255,255,.1);backdrop-filter:blur(8px);">
                        <i class="fas fa-chart-line fa-2x mb-2 opacity-75"></i>
                        <div class="fw-semibold small">Pantau Penjualan</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Right: Login Form --}}
    <div class="col-lg-7 d-flex align-items-center justify-content-center p-4">
        <div class="card login-form-card w-100" style="max-width:460px;">
            <div class="card-body p-5">
                <div class="d-lg-none text-center mb-4">
                    <i class="fas fa-store-alt fa-2x text-success mb-2"></i>
                    <h4 class="fw-bold">{{ config('app.name') }}</h4>
                </div>
                <h2 class="fw-bold mb-1">Masuk Vendor</h2>
                <p class="text-muted mb-4">Kelola toko dan produk Anda</p>

                @if($errors->any())
                    <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-2"></i>{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('vendor.login') }}">
                    @csrf
                    <div class="mb-3"><label class="form-label fw-medium">Email</label><input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="vendor@multivendor.test" required autofocus></div>
                    <div class="mb-3"><label class="form-label fw-medium">Password</label><input type="password" name="password" class="form-control" placeholder="Masukkan password" required></div>
                    <div class="mb-4 form-check"><input type="checkbox" name="remember" class="form-check-input" id="remember"><label class="form-check-label" for="remember">Ingat saya</label></div>
                    <button type="submit" class="btn btn-login w-100"><i class="fas fa-sign-in-alt me-2"></i> Masuk</button>
                </form>

                <div class="demo-box p-4 mt-4">
                    <div class="fw-semibold mb-2 text-dark"><i class="fas fa-flask me-1 text-warning"></i> Demo Login</div>
                    <div class="small text-muted font-monospace">
                        <div><span class="fw-bold">Vendor:</span> vendor@multivendor.test / password</div>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="{{ url('/') }}" class="text-decoration-none small text-muted"><i class="fas fa-arrow-left me-1"></i> Kembali ke toko</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
