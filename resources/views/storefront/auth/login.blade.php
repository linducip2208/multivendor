<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>Masuk — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand-primary: {{ $whitelabel['brandColor'] }}; --brand-dark: {{ $whitelabel['brandColorDark'] }}; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f1f5f9; }
        .login-card { border: none; border-radius: 20px; box-shadow: 0 4px 40px rgba(0,0,0,.06); }
        .form-control { border-radius: 12px; padding: 12px 16px; border: 1.5px solid #e2e8f0; }
        .form-control:focus { border-color: var(--brand-primary); box-shadow: 0 0 0 3px rgba(79,70,229,.12); }
        .btn-login { background: linear-gradient(135deg, var(--brand-primary), var(--brand-dark)); border: none; border-radius: 12px; padding: 12px 24px; font-weight: 600; color: #fff; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 p-4">
<div class="card login-card w-100" style="max-width:440px;">
    <div class="card-body p-5">
        <div class="text-center mb-4">
            <i class="fas fa-store-alt fa-3x text-primary mb-2"></i>
            <h3 class="fw-bold">Masuk</h3>
            <p class="text-muted">Belanja di {{ config('app.name') }}</p>
        </div>
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-medium">Email</label>
                <input type="email" name="email" class="form-control" placeholder="customer@multivendor.test" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-medium">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember">
                <label class="form-check-label" for="remember">Ingat saya</label>
            </div>
            <button type="submit" class="btn btn-login w-100"><i class="fas fa-sign-in-alt me-2"></i> Masuk</button>
        </form>
        <p class="text-center mt-3 mb-0">Belum punya akun? <a href="{{ route('register') }}" class="fw-semibold">Daftar</a></p>

        <div class="text-center mt-2"><small class="text-muted">atau</small></div>
        <div class="d-grid gap-2 mt-2">
            <a href="{{ route('social.redirect', 'google') }}" class="btn btn-outline-danger btn-sm"><i class="fab fa-google me-2"></i>Google</a>
        </div>

        <div class="mt-4 p-3 bg-light rounded-3 small">
            <div class="fw-semibold mb-2"><i class="fas fa-flask text-warning me-1"></i> Demo Login</div>
            <div class="font-monospace text-muted">
                <div>Customer: customer@multivendor.test / password</div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
