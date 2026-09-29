@extends('layouts.storefront')

@push('head')
    <style>
        .sf-auth { display: grid; gap: 28px; align-items: center; }
        @media (min-width: 992px) { .sf-auth { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 44px; } }
        .sf-auth__aside {
            border-radius: var(--sf-radius-lg);
            padding: clamp(28px, 4vw, 48px);
            background: linear-gradient(135deg, var(--sf-brand) 0%, var(--sf-brand-700) 100%);
            color: var(--sf-brand-contrast);
        }
        .sf-auth__aside h2 { color: inherit; }
        .sf-auth__list { display: grid; gap: 14px; margin: 0; padding: 0; list-style: none; }
    </style>
@endpush

@section('content')
    @php
        $appName = $whitelabel['appName'] ?? config('app.name');
        $registrationOpen = (bool) \App\Models\SystemSetting::get('customer_registration_open', true);
        $hasSocial = \Illuminate\Support\Facades\Route::has('social.redirect');
    @endphp

    <div class="sf-container sf-section sf-section--tight">
        <div class="sf-auth">
            <section class="sf-panel" aria-labelledby="sf-register-title">
                <h1 class="sf-mb-0" id="sf-register-title" style="font-size:clamp(1.4rem,1.2rem+1vw,1.9rem)">Buat akun pelanggan</h1>
                <p class="sf-small sf-muted">
                    Gratis, hanya butuh satu menit, dan langsung bisa checkout bersama banyak toko.
                </p>

                @unless ($registrationOpen)
                    <x-storefront.alert type="warning" title="Pendaftaran ditutup" style="margin-top:18px">
                        Saat ini pendaftaran pelanggan baru sedang tidak dibuka. Silakan kembali lagi nanti.
                    </x-storefront.alert>

                    <p class="sf-small sf-muted" style="margin-top:18px">
                        Sudah punya akun? <a href="{{ route('login') }}">Masuk di sini</a>.
                    </p>
                @else
                    @if ($hasSocial)
                        <div class="sf-row sf-row--wrap" style="gap:8px;margin:20px 0">
                            @foreach (['google' => 'Google', 'facebook' => 'Facebook', 'github' => 'GitHub'] as $provider => $label)
                                <a href="{{ route('social.redirect', $provider) }}" class="sf-btn sf-btn--outline sf-btn--sm">
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>
                        <div class="sf-row" style="gap:12px;margin-bottom:20px">
                            <span style="flex:1;height:1px;background:var(--sf-border)" aria-hidden="true"></span>
                            <span class="sf-tiny sf-muted">atau daftar dengan email</span>
                            <span style="flex:1;height:1px;background:var(--sf-border)" aria-hidden="true"></span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register') }}" class="sf-stack" style="gap:16px" novalidate>
                        @csrf

                        <div class="sf-field">
                            <label class="sf-label" for="sf-register-name">Nama lengkap <span class="sf-required">*</span></label>
                            <input class="sf-input" id="sf-register-name" type="text" name="name" required autocomplete="name"
                                   value="{{ old('name') }}" @error('name') aria-invalid="true" aria-describedby="sf-register-name-error" @enderror>
                            @error('name')
                                <span class="sf-error" id="sf-register-name-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="sf-register-email">Email <span class="sf-required">*</span></label>
                            <input class="sf-input" id="sf-register-email" type="email" name="email" required autocomplete="email"
                                   value="{{ old('email') }}" @error('email') aria-invalid="true" aria-describedby="sf-register-email-error" @enderror>
                            @error('email')
                                <span class="sf-error" id="sf-register-email-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="sf-field">
                            <label class="sf-label" for="sf-register-phone">Nomor telepon</label>
                            <input class="sf-input" id="sf-register-phone" type="tel" name="phone" autocomplete="tel"
                                   value="{{ old('phone') }}" @error('phone') aria-invalid="true" aria-describedby="sf-register-phone-error" @enderror>
                            @error('phone')
                                <span class="sf-error" id="sf-register-phone-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
                            <div class="sf-field">
                                <label class="sf-label" for="sf-register-password">Password <span class="sf-required">*</span></label>
                                <input class="sf-input" id="sf-register-password" type="password" name="password" required
                                       autocomplete="new-password" @error('password') aria-invalid="true" aria-describedby="sf-register-password-error" @enderror>
                                @error('password')
                                    <span class="sf-error" id="sf-register-password-error">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="sf-field">
                                <label class="sf-label" for="sf-register-password-confirm">Ulangi password <span class="sf-required">*</span></label>
                                <input class="sf-input" id="sf-register-password-confirm" type="password" name="password_confirmation"
                                       required autocomplete="new-password">
                            </div>
                        </div>
                        <p class="sf-hint sf-mb-0">Gunakan minimal 8 karakter.</p>

                        <div class="sf-field">
                            <label class="sf-label" for="sf-register-referral">Kode referral</label>
                            <input class="sf-input" id="sf-register-referral" type="text" name="referral_code"
                                   value="{{ old('referral_code') }}" autocomplete="off"
                                   @error('referral_code') aria-invalid="true" aria-describedby="sf-register-referral-error" @enderror>
                            @error('referral_code')
                                <span class="sf-error" id="sf-register-referral-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <label class="sf-checkbox" for="sf-register-terms">
                            <input type="checkbox" id="sf-register-terms" name="terms" value="1" required
                                   @checked(old('terms')) @error('terms') aria-invalid="true" aria-describedby="sf-register-terms-error" @enderror>
                            <span>Saya menyetujui <a href="{{ route('page.terms') }}">syarat &amp; ketentuan</a> serta
                                <a href="{{ route('page.privacy') }}">kebijakan privasi</a>.</span>
                        </label>
                        @error('terms')
                            <span class="sf-error" id="sf-register-terms-error">{{ $message }}</span>
                        @enderror

                        <button type="submit" class="sf-btn sf-btn--primary sf-btn--block sf-btn--lg">
                            <x-storefront.icon name="user" :size="18" /> Buat akun
                        </button>
                    </form>

                    <p class="sf-small sf-muted sf-mb-0" style="margin-top:18px">
                        Sudah punya akun? <a href="{{ route('login') }}">Masuk</a>.
                    </p>
                @endunless
            </section>

            <aside class="sf-auth__aside" aria-labelledby="sf-register-aside-title">
                <p class="sf-hero__eyebrow">{{ $appName }}</p>
                <h2 class="sf-mb-0" id="sf-register-aside-title" style="font-size:clamp(1.5rem,1.2rem+1.4vw,2.1rem)">
                    Belanja lebih cepat, lebih aman
                </h2>
                <p class="sf-mb-0" style="opacity:.9;max-width:44ch">
                    Satu akun untuk melacak pesanan, menyimpan alamat, dan mengelola dompet digital.
                </p>

                <ul class="sf-auth__list" style="margin-top:26px">
                    <li class="sf-row" style="gap:12px;align-items:flex-start">
                        <span class="sf-row" style="justify-content:center;width:38px;height:38px;border-radius:var(--sf-radius-sm);background:rgba(255,255,255,.16);flex-shrink:0">
                            <x-storefront.icon name="map-pin" :size="19" />
                        </span>
                        <span>Simpan alamat pengiriman agar checkout berikutnya jauh lebih singkat.</span>
                    </li>
                    <li class="sf-row" style="gap:12px;align-items:flex-start">
                        <span class="sf-row" style="justify-content:center;width:38px;height:38px;border-radius:var(--sf-radius-sm);background:rgba(255,255,255,.16);flex-shrink:0">
                            <x-storefront.icon name="bell" :size="19" />
                        </span>
                        <span>Terima pembaruan status pesanan dan promo yang benar-benar relevan.</span>
                    </li>
                    <li class="sf-row" style="gap:12px;align-items:flex-start">
                        <span class="sf-row" style="justify-content:center;width:38px;height:38px;border-radius:var(--sf-radius-sm);background:rgba(255,255,255,.16);flex-shrink:0">
                            <x-storefront.icon name="lock" :size="19" />
                        </span>
                        <span>Data Anda terenkripsi dan tidak dibagikan kepada penjual.</span>
                    </li>
                </ul>

                <a href="{{ route('page.terms') }}" class="sf-btn sf-btn--block" style="background:#fff;color:var(--sf-brand);margin-top:26px">
                    Baca syarat &amp; ketentuan
                </a>
            </aside>
        </div>
    </div>
@endsection
