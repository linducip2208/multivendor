<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>Daftar — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand-primary: #4F46E5; --brand-dark: #3730A3; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f1f5f9; }
        .register-card { border: none; border-radius: 20px; box-shadow: 0 4px 40px rgba(0,0,0,.06); }
        .form-control { border-radius: 12px; padding: 12px 16px; border: 1.5px solid #e2e8f0; }
        .form-control:focus { border-color: var(--brand-primary); box-shadow: 0 0 0 3px rgba(79,70,229,.12); }
        .btn-register { background: linear-gradient(135deg, var(--brand-primary), var(--brand-dark)); border: none; border-radius: 12px; padding: 12px 24px; font-weight: 600; color: #fff; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 p-4">
<div class="card register-card w-100" style="max-width:480px;">
    <div class="card-body p-5">
        <div class="text-center mb-4">
            <i class="fas fa-store-alt fa-3x text-primary mb-2"></i>
            <h3 class="fw-bold">Daftar</h3>
            <p class="text-muted">Buat akun di {{ config('app.name') }}</p>
        </div>
        <form method="POST" action="{{ route('register') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-medium">Nama Lengkap</label>
                <input type="text" name="name" class="form-control" placeholder="Nama Anda" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-medium">Email</label>
                <input type="email" name="email" class="form-control" placeholder="email@example.com" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-medium">No. HP</label>
                <input type="tel" name="phone" class="form-control" placeholder="08123456789">
            </div>
            <div class="mb-3">
                <label class="form-label fw-medium">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Min. 8 karakter" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-medium">Konfirmasi Password</label>
                <input type="password" name="password_confirmation" class="form-control" placeholder="Ulangi password" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-medium">Kode Referral (opsional)</label>
                <input type="text" name="referral_code" class="form-control" placeholder="Masukkan kode referral">
            </div>
            <button type="submit" class="btn btn-register w-100"><i class="fas fa-user-plus me-2"></i> Daftar Sekarang</button>
        </form>
        <p class="text-center mt-3 mb-0">Sudah punya akun? <a href="{{ route('login') }}" class="fw-semibold">Masuk</a></p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
