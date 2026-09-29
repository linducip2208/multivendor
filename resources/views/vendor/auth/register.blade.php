<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>Daftar Toko — {{ config('app.name') }}</title>
    @vite(['resources/css/tabler.css', 'resources/js/tabler.js'])
    <style>
        :root { --brand-primary: {{ $whitelabel['brandColor'] }}; --brand-dark: {{ $whitelabel['brandColorDark'] }}; }
        .auth-hero { background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-dark) 100%); }
        .btn-brand { background: linear-gradient(135deg, var(--brand-primary), var(--brand-dark)); border: none; color: #fff; }
        .btn-brand:hover { color: #fff; filter: brightness(1.05); }
    </style>
</head>
<body class="d-flex flex-column">
<div class="page flex-fill">
    <div class="container py-4" style="max-width: 760px;">
        <div class="text-center mb-4">
            <a href="{{ url('/') }}" class="navbar-brand navbar-brand-autodark">
                <span class="navbar-brand-icon"><x-admin.icon name="store" :size="32" /></span>
                <span>{{ config('app.name') }}</span>
            </a>
            <h1 class="h2 mt-3 mb-1">Pengajuan Toko</h1>
            <p class="text-secondary">Isi data berikut. Kolom bertanda bintang wajib diisi.</p>
        </div>
        <div class="card">
            <div class="card-body p-4">
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <div class="d-flex"><span class="me-2"><x-admin.icon name="alert-circle" :size="18" /></span><div class="fw-semibold">Periksa kembali formulir</div></div>
                        <ul class="mb-0 mt-2 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form method="POST" action="{{ route('vendor.register.store') }}" enctype="multipart/form-data" autocomplete="off" novalidate>
                    @csrf
                    <h3 class="card-title mt-2">Identitas toko</h3>
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

                    <h3 class="card-title">Data pemilik</h3>
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

                    <h3 class="card-title">Alamat</h3>
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

                    <h3 class="card-title">Rekening pencairan</h3>
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
                            <div class="form-hint">Nomor rekening disimpan aman dan hanya ditampilkan empat digit terakhir.</div>
                        </div>
                    </div>

                    <h3 class="card-title">Dokumen verifikasi</h3>
                    <div class="mb-3">
                        <label class="form-label" for="doc_identity">Kartu tanda penduduk</label>
                        <input type="file" id="doc_identity" name="documents[0][file]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        <input type="hidden" name="documents[0][kind]" value="identity">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="doc_business">Dokumen usaha</label>
                        <input type="file" id="doc_business" name="documents[1][file]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        <input type="hidden" name="documents[1][kind]" value="business_license">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="doc_tax">NPWP / dokumen pajak</label>
                        <input type="file" id="doc_tax" name="documents[2][file]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        <input type="hidden" name="documents[2][kind]" value="tax_document">
                    </div>

                    @isset($tiers)
                        <div class="card card-sm bg-light mb-4">
                            <div class="card-body">
                                <div class="fw-semibold mb-2">Tingkat komisi</div>
                                @foreach ($tiers as $tier)
                                    <div class="d-flex justify-content-between border-top py-1 small">
                                        <span class="text-capitalize">{{ $tier['label'] }}</span>
                                        <span>{{ number_format($tier['value'], 2, ',', '.') }}%</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endisset

                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <button type="submit" class="btn btn-brand px-4">Kirim pengajuan</button>
                        <a href="{{ route('vendor.login') }}" class="btn btn-link text-secondary">Sudah punya akun? Masuk</a>
                    </div>
                </form>
            </div>
        </div>
        <div class="text-center text-secondary mt-3">
            <a href="{{ url('/') }}" class="link-secondary"><x-admin.icon name="arrow-left" :size="14" /> Kembali ke etalase</a>
        </div>
    </div>
</div>
</body>
</html>
