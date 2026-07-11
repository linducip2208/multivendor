@extends('layouts.admin')
@section('title', 'Pengaturan')
@section('content')
<h4 class="fw-bold mb-1"><i class="fas fa-cog me-2 text-secondary"></i> Pengaturan Sistem</h4>
<p class="text-muted small mb-4">Konfigurasi platform marketplace</p>

<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
@csrf @method('PUT')
<div class="row g-4">
    {{-- Whitelabel / Branding --}}
    <div class="col-lg-6">
        <div class="card border-0 rounded-4 shadow-sm"><div class="card-header bg-transparent border-0 pt-3 px-4"><h6 class="fw-bold mb-0"><i class="fas fa-palette me-2 text-primary"></i> Branding / Whitelabel</h6></div><div class="card-body p-4">
            <div class="mb-3"><label class="form-label fw-medium">Nama Aplikasi</label><input type="text" name="app_name" class="form-control" value="{{ old('app_name', $settings['app_name'] ?? config('app.name')) }}"></div>
            <div class="mb-3"><label class="form-label fw-medium">Warna Brand (Primary)</label><div class="input-group"><input type="color" name="brand_color" class="form-control form-control-color" value="{{ old('brand_color', $settings['brand_color'] ?? '#4F46E5') }}" style="width:60px;"><input type="text" class="form-control" value="{{ old('brand_color', $settings['brand_color'] ?? '#4F46E5') }}" readonly></div><small class="text-muted">Digunakan di sidebar, tombol, link, dan aksen seluruh panel.</small></div>
            <div class="mb-3">
                <label class="form-label fw-medium">Logo (URL atau Upload)</label>
                <input type="text" name="logo_url" class="form-control mb-2" value="{{ old('logo_url', $settings['logo_url'] ?? '') }}" placeholder="https://domain.com/logo.png — atau upload di bawah">
                <input type="file" name="logo_file" class="form-control" accept="image/*">
                @if(!empty($settings['logo_url']))
                    <img src="{{ $settings['logo_url'] }}" class="mt-2 rounded" style="max-height:60px;" alt="Logo">
                @endif
                <small class="text-muted">Upload gambar atau isi URL. Kosongkan untuk menggunakan icon default.</small>
            </div>
            <div class="mb-3"><label class="form-label fw-medium">Favicon (URL atau Upload)</label><input type="text" name="favicon_url" class="form-control mb-2" value="{{ old('favicon_url', $settings['favicon_url'] ?? '') }}" placeholder="https://domain.com/favicon.ico"><input type="file" name="favicon_file" class="form-control" accept="image/*,.ico"></div>
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 rounded-4 shadow-sm"><div class="card-header bg-transparent border-0 pt-3 px-4"><h6 class="fw-bold mb-0"><i class="fas fa-globe me-2 text-primary"></i> Umum</h6></div><div class="card-body p-4">
            <div class="mb-3"><label class="form-label fw-medium">URL Aplikasi</label><input type="url" name="app_url" class="form-control" value="{{ old('app_url', config('app.url')) }}"></div>
            <div class="row g-2"><div class="col-6"><label class="form-label fw-medium">Mata Uang</label><input type="text" name="currency" class="form-control" value="{{ old('currency', $settings['currency'] ?? 'IDR') }}" placeholder="IDR"></div><div class="col-6"><label class="form-label fw-medium">Simbol</label><input type="text" name="currency_symbol" class="form-control" value="{{ old('currency_symbol', $settings['currency_symbol'] ?? 'Rp') }}" placeholder="Rp"></div></div>
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 rounded-4 shadow-sm"><div class="card-header bg-transparent border-0 pt-3 px-4"><h6 class="fw-bold mb-0"><i class="fas fa-percent me-2 text-warning"></i> Komisi & Payout</h6></div><div class="card-body p-4">
            <div class="mb-3"><label class="form-label fw-medium">Komisi Default (%)</label><input type="number" name="commission_default" class="form-control" value="{{ old('commission_default', $settings['commission_default'] ?? 5) }}" min="0" max="100" step="0.01"><small class="text-muted">Berlaku untuk vendor baru. Bisa diubah per vendor.</small></div>
            <div class="mb-3"><label class="form-label fw-medium">Minimal Pencairan (Rp)</label><input type="number" name="min_withdraw" class="form-control" value="{{ old('min_withdraw', $settings['min_withdraw'] ?? 50000) }}" min="0"><small class="text-muted">Vendor hanya bisa withdraw jika saldo di atas nominal ini.</small></div>
            <div class="mb-3"><label class="form-label fw-medium">Prefix Order</label><input type="text" name="order_prefix" class="form-control" value="{{ old('order_prefix', $settings['order_prefix'] ?? 'ORD') }}" maxlength="10"></div>
        </div></div>
    </div>

    <div class="col-12">
        <div class="card border-0 rounded-4 shadow-sm"><div class="card-header bg-transparent border-0 pt-3 px-4"><h6 class="fw-bold mb-0"><i class="fas fa-envelope me-2 text-success"></i> SMTP / Email</h6></div><div class="card-body p-4">
            <p class="text-muted small mb-3">Digunakan untuk kirim email: notifikasi, reset password, invoice, dll. Semua field disimpan encrypted.</p>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label fw-medium">Mailer</label><select name="mail_mailer" class="form-select"><option value="smtp" {{ old('mail_mailer', config('mail.mailer')) === 'smtp' ? 'selected' : '' }}>SMTP</option><option value="sendmail" {{ old('mail_mailer', config('mail.mailer')) === 'sendmail' ? 'selected' : '' }}>Sendmail</option><option value="mailgun" {{ old('mail_mailer', config('mail.mailer')) === 'mailgun' ? 'selected' : '' }}>Mailgun</option><option value="ses" {{ old('mail_mailer', config('mail.mailer')) === 'ses' ? 'selected' : '' }}>Amazon SES</option><option value="log" {{ old('mail_mailer', config('mail.mailer')) === 'log' ? 'selected' : '' }}>Log Only (Dev)</option></select></div>
                <div class="col-md-4"><label class="form-label fw-medium">SMTP Host</label><input type="text" name="mail_host" class="form-control" value="{{ old('mail_host', config('mail.mailer') === 'log' ? '' : config('mail.host')) }}" placeholder="smtp.gmail.com"></div>
                <div class="col-md-2"><label class="form-label fw-medium">Port</label><input type="text" name="mail_port" class="form-control" value="{{ old('mail_port', config('mail.mailer') === 'log' ? '' : config('mail.port')) }}" placeholder="587"></div>
                <div class="col-md-2"><label class="form-label fw-medium">Enkripsi</label><input type="text" class="form-control" value="tls" disabled></div>
                <div class="col-md-6"><label class="form-label fw-medium">Username / Email</label><input type="text" name="mail_username" class="form-control" value="{{ old('mail_username', $settings['mail_username'] ?? '') }}" placeholder="email@gmail.com"></div>
                <div class="col-md-6"><label class="form-label fw-medium">Password (App Password)</label><input type="password" name="mail_password" class="form-control" placeholder="xxxx"><small class="text-muted">Kosongkan jika tidak diubah. Untuk Gmail, gunakan <a href="https://myaccount.google.com/apppasswords" target="_blank">App Password</a>.</small></div>
                <div class="col-md-6"><label class="form-label fw-medium">From Address</label><input type="email" name="mail_from_address" class="form-control" value="{{ old('mail_from_address', $settings['mail_from_address'] ?? config('mail.from.address')) }}" placeholder="noreply@domain.com"></div>
            </div>
        </div></div>
    </div>

    <div class="col-12"><button type="submit" class="btn btn-primary btn-lg px-5"><i class="fas fa-save me-2"></i> Simpan Semua Pengaturan</button></div>
</div>
</form>
@endsection
