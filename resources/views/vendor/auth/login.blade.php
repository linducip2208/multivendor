<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>Masuk Vendor — {{ config('app.name') }}</title>
    @vite(['resources/css/tabler.css', 'resources/js/tabler.js'])
    <style>
        :root { --brand-primary: {{ $whitelabel['brandColor'] }}; --brand-dark: {{ $whitelabel['brandColorDark'] }}; }
        .auth-hero { background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-dark) 100%); }
        .btn-brand { background: linear-gradient(135deg, var(--brand-primary), var(--brand-dark)); border: none; color: #fff; }
        .btn-brand:hover { color: #fff; filter: brightness(1.05); }
    </style>
</head>
<body class="d-flex flex-column">
<div class="page page-center flex-fill">
    <div class="container container-tight py-4">
        <div class="text-center mb-4">
            <a href="{{ url('/') }}" class="navbar-brand navbar-brand-autodark">
                <span class="navbar-brand-icon"><x-admin.icon name="store" :size="32" /></span>
                <span>{{ config('app.name') }}</span>
            </a>
            <h1 class="h2 mt-3 mb-1">Masuk Vendor</h1>
            <p class="text-secondary">Kelola toko dan produk Anda</p>
        </div>
        <div class="card card-md">
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <span class="me-2"><x-admin.icon name="alert-circle" :size="18" /></span>
                        <div>{{ $errors->first() }}</div>
                    </div>
                @endif
                <form method="POST" action="{{ route('vendor.login') }}" autocomplete="off" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="vendor@multivendor.test" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kata sandi</label>
                        <input type="password" name="password" class="form-control" placeholder="Masukkan kata sandi" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-check">
                            <input type="checkbox" name="remember" class="form-check-input">
                            <span class="form-check-label">Ingat saya</span>
                        </label>
                    </div>
                    <div class="form-footer">
                        <button type="submit" class="btn btn-brand w-100">Masuk</button>
                    </div>
                </form>
                <div class="alert alert-success mt-4 mb-0">
                    <div class="fw-semibold mb-1">Demo Masuk</div>
                    <div class="small font-monospace">Vendor: vendor@multivendor.test / password</div>
                </div>
            </div>
        </div>
        <div class="text-center text-secondary mt-3">
            <a href="{{ url('/') }}" class="link-secondary"><x-admin.icon name="arrow-left" :size="14" /> Kembali ke toko</a>
            <span class="mx-1">·</span>
            <a href="{{ route('vendor.register') }}" class="link-secondary">Daftar toko</a>
        </div>
    </div>
</div>
</body>
</html>
