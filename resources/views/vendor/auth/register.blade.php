<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>Daftar Toko — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand-primary: {{ $whitelabel['brandColor'] }}; --brand-dark: {{ $whitelabel['brandColorDark'] }}; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f1f5f9; }
        .register-hero { background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-dark) 100%); }
        .register-card { border: none; border-radius: 20px; box-shadow: 0 4px 40px rgba(0,0,0,.06); }
        .form-control, .form-select { border-radius: 12px; padding: 11px 15px; border: 1.5px solid #e2e8f0; }
        .form-control:focus, .form-select:focus { border-color: var(--brand-primary); box-shadow: 0 0 0 3px rgba(5,150,105,.12); }
        .btn-brand { background: linear-gradient(135deg, var(--brand-primary), var(--brand-dark)); border: none; border-radius: 12px; padding: 12px 24px; font-weight: 600; color: #fff; }
        .btn-brand:hover { color: #fff; transform: translateY(-1px); }
        .tier-chip { border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 10px 12px; }
    </style>
</head>
<body>
<div class="min-vh-100 d-flex">
    <div class="col-lg-4 d-none d-lg-flex register-hero flex-column justify-content-center p-5 text-white">
        <div>
            <i class="fas fa-store-alt fa-3x mb-3"></i>
            <h1 class="h2 fw-bold">Buka Toko Anda</h1>
            <p class="opacity-75">Satu formulir, peninjauan admin, lalu toko Anda langsung tayang.</p>

            <ol class="mt-4 ps-3 small opacity-75 mb-0">
                <li class="mb-2">Lengkapi data toko dan rekening pencairan.</li>
                <li class="mb-2">Unggah dokumen verifikasi sesuai ketentuan.</li>
                <li>Admin meninjau dan menyetujui pengajuan Anda.</li>
            </ol>

            <div class="mt-5 small opacity-75">
                <div class="fw-semibold mb-2">Tingkat komisi</div>
                @foreach ($tiers as $tier)
                    <div class="d-flex justify-content-between border-top border-light border-opacity-25 py-1">
                        <span class="text-capitalize">{{ $tier['label'] }}</span>
                        <span>{{ number_format($tier['value'], 2, ',', '.') }}%</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-8 d-flex align-items-center justify-content-center p-4">
        <div class="register-card bg-white w-100" style="max-width: 720px;">
            <div class="card-body p-4 p-md-5">
                <h2 class="fw-bold mb-1">Pengajuan Toko</h2>
                <p class="text-muted mb-4">Isi data berikut. Kolom bertanda bintang wajib diisi.</p>

                @if ($errors->any())
                    <div class="alert alert-danger py-2">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('vendor.register.store') }}" enctype="multipart/form-data">
                    @csrf

                    <h3 class="h6 fw-semibold text-uppercase text-muted mb-3">Identitas toko</h3>
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-7">
                            <label class="form-label" for="shop_name">Nama toko *</label>
                            <input type="text" id="shop_name" name="shop_name" class="form-control" value="{{ old('shop_name') }}" required maxlength="160">
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label" for="category">Kategori</label>
                            <input type="text" id="category" name="category" class="form-control" value="{{ old('category') }}" maxlength="80" placeholder="mis. Fesyen">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Deskripsi toko</label>
                            <textarea id="description" name="description" class="form-control" rows="3" maxlength="2000" placeholder="Ceritakan produk apa yang Anda jual.">{{ old('description') }}</textarea>
                        </div>
                    </div>

                    <h3 class="h6 fw-semibold text-uppercase text-muted mb-3">Data pemilik</h3>
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="owner_name">Nama lengkap *</label>
                            <input type="text" id="owner_name" name="owner_name" class="form-control" value="{{ old('owner_name') }}" required maxlength="120">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="phone">Telepon</label>
                            <input type="tel" id="phone" name="phone" class="form-control" value="{{ old('phone') }}" maxlength="32">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="email">Email *</label>
                            <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" required maxlength="160">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="password">Kata sandi *</label>
                            <input type="password" id="password" name="password" class="form-control" required minlength="8">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="password_confirmation">Ulangi sandi *</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required minlength="8">
                        </div>
                    </div>

                    <h3 class="h6 fw-semibold text-uppercase text-muted mb-3">Alamat</h3>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label class="form-label" for="address">Alamat lengkap</label>
                            <textarea id="address" name="address" class="form-control" rows="2" maxlength="500">{{ old('address') }}</textarea>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="city">Kota</label>
                            <input type="text" id="city" name="city" class="form-control" value="{{ old('city') }}" maxlength="100">
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label" for="province">Provinsi</label>
                            <input type="text" id="province" name="province" class="form-control" value="{{ old('province') }}" maxlength="100">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="postal_code">Kode pos</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-control" value="{{ old('postal_code') }}" maxlength="12">
                        </div>
                    </div>

                    <h3 class="h6 fw-semibold text-uppercase text-muted mb-3">Rekening pencairan</h3>
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="bank_name">Bank</label>
                            <input type="text" id="bank_name" name="bank_name" class="form-control" value="{{ old('bank_name') }}" maxlength="120">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="bank_account_name">Atas nama</label>
                            <input type="text" id="bank_account_name" name="bank_account_name" class="form-control" value="{{ old('bank_account_name') }}" maxlength="120">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="bank_account_number">Nomor rekening</label>
                            <input type="text" id="bank_account_number" name="bank_account_number" class="form-control" value="{{ old('bank_account_number') }}" maxlength="64" pattern="[0-9\-\s]{6,64}">
                            <div class="form-text">Nomor rekening disimpan aman dan hanya ditampilkan empat digit terakhir.</div>
                        </div>
                    </div>

                    <h3 class="h6 fw-semibold text-uppercase text-muted mb-3">Dokumen verifikasi</h3>
                    <div class="mb-4">
                        <label class="form-label" for="doc_identity">Kartu tanda penduduk</label>
                        <input type="file" id="doc_identity" name="documents[0][file]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        <input type="hidden" name="documents[0][kind]" value="identity">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="doc_business">Dokumen usaha</label>
                        <input type="file" id="doc_business" name="documents[1][file]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        <input type="hidden" name="documents[1][kind]" value="business_license">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="doc_tax">NPWP / dokumen pajak</label>
                        <input type="file" id="doc_tax" name="documents[2][file]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        <input type="hidden" name="documents[2][kind]" value="tax_document">
                    </div>

                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <button type="submit" class="btn btn-brand px-4">
                            <i class="fas fa-paper-plane me-2"></i> Kirim pengajuan
                        </button>
                        <a href="{{ route('vendor.login') }}" class="btn btn-link text-muted">Sudah punya akun? Masuk</a>
                    </div>
                </form>

                <div class="text-center mt-4">
                    <a href="{{ url('/') }}" class="text-decoration-none small text-muted">
                        <i class="fas fa-arrow-left me-1"></i> Kembali ke etalase
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
