@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Keamanan')
@section('subtitle', 'Kontrol akses dan proteksi akun toko')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Pengaturan', 'href' => route('vendor.settings.index')],
    ['label' => 'Keamanan'],
])

@section('content')
    <x-admin.tabs class="mb-3" :tabs="[
        ['label' => 'Profil toko', 'href' => route('vendor.settings.index'), 'icon' => 'store'],
        ['label' => 'Pengiriman', 'href' => route('vendor.shipping.index'), 'icon' => 'truck'],
        ['label' => 'Notifikasi', 'href' => route('vendor.notifications.index'), 'icon' => 'bell'],
        ['label' => 'Keamanan', 'href' => route('vendor.security.index'), 'active' => true, 'icon' => 'shield'],
    ]" />

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <form method="POST" action="{{ route('vendor.security.update') }}">
                @csrf
                @method('PUT')

                <x-admin.card title="Autentikasi" icon="shield">
                    <x-admin.form-field name="two_factor_enabled" label="Aktifkan autentikasi dua faktor" type="checkbox" :value="$two_factor_enabled ? 1 : 0" help="Minta kode sekali pakai setiap kali masuk dari perangkat baru." />
                    <x-admin.form-field name="login_alert" label="Kirim peringatan login" type="checkbox" :value="$login_alert ? 1 : 0" help="Kirim email dan notifikasi dalam aplikasi setiap kali ada percobaan login." />
                    <x-admin.form-field name="login_alert_after" label="Peringatan setelah" type="number" :min="1" :max="100" :value="$settings->login_alert_after ?? 1" suffix="percobaan gagal" />
                    <x-admin.form-field name="session_timeout_minutes" label="Durasi sesi" type="number" :min="5" :max="10080" :value="$session_timeout_minutes" suffix="menit" help="Sesi berakhir otomatis setelah tidak ada aktivitas." />

                    <hr class="my-3" />
                    <h4 class="card-title mb-3">Kebijakan kata sandi</h4>
                    <x-admin.form-field name="password_rotation_enabled" label="Wajibkan rotasi kata sandi" type="checkbox" :value="$settings->password_rotation_enabled ?? false ? 1 : 0" />
                    <x-admin.form-field name="password_rotation_days" label="Rotasi setiap" type="number" :min="1" :max="3650" :value="$settings->password_rotation_days ?? 90" suffix="hari" />
                    <x-admin.form-field name="max_failed_attempts" label="Maksimum percobaan gagal" type="number" :min="1" :max="100" :value="$settings->max_failed_attempts ?? 5" />
                    <x-admin.form-field name="lockout_minutes" label="Durasi kunci akun" type="number" :min="1" :max="10080" :value="$settings->lockout_minutes ?? 15" suffix="menit" />

                    <hr class="my-3" />
                    <h4 class="card-title mb-3">Daftar putih IP</h4>
                    <x-admin.form-field
                        name="ip_allowlist"
                        label="IP yang diizinkan"
                        type="textarea"
                        :rows="3"
                        :value="implode("\n", $ip_allowlist)"
                        help="Satu alamat per baris. Alamat yang tidak terdaftar tetap dapat masuk, tetapi akan dicatat sebagai peringatan."
                    />

                    <hr class="my-3" />
                    <h4 class="card-title mb-3">Ganti kata sandi</h4>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-admin.form-field name="password" label="Kata sandi baru" type="password" help="Minimal 8 karakter. Kosongkan jika tidak ingin mengganti." />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-admin.form-field name="password_confirmation" label="Ulangi kata sandi" type="password" />
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <x-admin.icon name="shield" :size="16" class="me-1" />
                            <span>Simpan pengaturan</span>
                        </button>
                    </div>
                </x-admin.card>
            </form>
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Status keamanan" icon="shield-check">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="avatar avatar-lg {{ $two_factor_enabled ? 'text-success bg-success-lt' : 'text-warning bg-warning-lt' }}">
                        <x-admin.icon :name="$two_factor_enabled ? 'shield-check' : 'alert-triangle'" :size="24" />
                    </span>
                    <div>
                        <div class="fw-semibold">{{ $two_factor_enabled ? 'Proteksi kuat' : 'Proteksi dasar' }}</div>
                        <div class="text-secondary small">
                            {{ $two_factor_enabled ? 'Autentikasi dua faktor aktif.' : 'Autentikasi dua faktor belum diaktifkan.' }}
                        </div>
                    </div>
                </div>

                <dl class="row mb-0 small">
                    <dt class="col-7 text-secondary fw-normal">Peringatan login</dt>
                    <dd class="col-5 text-end">{{ $login_alert ? $__status('active') : $__status('inactive') }}</dd>
                    <dt class="col-7 text-secondary fw-normal">Perubahan kata sandi</dt>
                    <dd class="col-5 text-end">
                        {{ $settings->last_password_change_at ? \Carbon\Carbon::parse($settings->last_password_change_at)->diffForHumans(short: true) : '—' }}
                    </dd>
                    <dt class="col-7 text-secondary fw-normal">IP diizinkan</dt>
                    <dd class="col-5 text-end">{{ count($ip_allowlist) }}</dd>
                </dl>
            </x-admin.card>

            <x-admin.card title="Akses tim" icon="users" class="mt-3">
                <p class="text-secondary small">
                    Setiap anggota tim sebaiknya memakai akun sendiri agar aktivitas dapat diaudit.
                </p>
                <a href="{{ route('vendor.staff.index') }}" class="btn btn-outline-primary w-100">
                    <x-admin.icon name="users" :size="16" class="me-1" />
                    <span>Kelola anggota tim</span>
                </a>
            </x-admin.card>
        </div>
    </div>
@endsection
