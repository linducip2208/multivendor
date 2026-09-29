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
        $socialProviders = ['google' => 'Google', 'facebook' => 'Facebook', 'github' => 'GitHub'];
        $hasSocial = \Illuminate\Support\Facades\Route::has('social.redirect');
    @endphp

    <div class="sf-container sf-section sf-section--tight">
        <div class="sf-auth">
            <section class="sf-panel" aria-labelledby="sf-login-title">
                <h1 class="sf-mb-0" id="sf-login-title" style="font-size:clamp(1.4rem,1.2rem+1vw,1.9rem)">Masuk ke akun Anda</h1>
                <p class="sf-small sf-muted">
                    Masuk untuk melanjutkan belanja, melacak pesanan, dan menyimpan produk favorit.
                </p>

                @if ($hasSocial)
                    <div class="sf-row sf-row--wrap" style="gap:8px;margin:20px 0">
                        @foreach ($socialProviders as $provider => $label)
                            <a href="{{ route('social.redirect', $provider) }}" class="sf-btn sf-btn--outline sf-btn--sm">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                    <div class="sf-row" style="gap:12px;margin-bottom:20px">
                        <span style="flex:1;height:1px;background:var(--sf-border)" aria-hidden="true"></span>
                        <span class="sf-tiny sf-muted">atau</span>
                        <span style="flex:1;height:1px;background:var(--sf-border)" aria-hidden="true"></span>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="sf-stack" style="gap:16px" novalidate>
                    @csrf

                    <div class="sf-field">
                        <label class="sf-label" for="sf-login-email">Email <span class="sf-required">*</span></label>
                        <input class="sf-input" id="sf-login-email" type="email" name="email" required autocomplete="email"
                               value="{{ old('email') }}" @error('email') aria-invalid="true" aria-describedby="sf-login-email-error" @enderror>
                        @error('email')
                            <span class="sf-error" id="sf-login-email-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="sf-field">
                        <label class="sf-label" for="sf-login-password">Kata sandi <span class="sf-required">*</span></label>
                        <input class="sf-input" id="sf-login-password" type="password" name="password" required autocomplete="current-password"
                               @error('password') aria-invalid="true" aria-describedby="sf-login-password-error" @enderror>
                        @error('password')
                            <span class="sf-error" id="sf-login-password-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <label class="sf-checkbox" for="sf-login-remember">
                        <input type="checkbox" id="sf-login-remember" name="remember" value="1" @checked(old('remember'))>
                        <span>Ingat saya di perangkat ini</span>
                    </label>

                    <button type="submit" class="sf-btn sf-btn--primary sf-btn--block sf-btn--lg">Masuk</button>
                </form>

                <p class="sf-small sf-muted sf-mb-0" style="margin-top:18px">
                    Belum punya akun?
                    @if (\App\Models\SystemSetting::get('customer_registration_open', true))
                        <a href="{{ route('register') }}">Daftar sekarang</a>
                    @else
                        Pendaftaran pelanggan sedang ditutup.
                    @endif
                </p>
            </section>

            <aside class="sf-auth__aside" aria-labelledby="sf-login-aside-title">
                <p class="sf-hero__eyebrow">{{ $appName }}</p>
                <h2 class="sf-mb-0" id="sf-login-aside-title" style="font-size:clamp(1.5rem,1.2rem+1.4vw,2.1rem)">
                    Satu akun untuk semua toko
                </h2>
                <p class="sf-mb-0" style="opacity:.9;max-width:44ch">
                    Pesanan dari banyak penjual dikelola dalam satu keranjang, satu checkout, dan satu halaman
                    status pengiriman.
                </p>

                <ul class="sf-auth__list" style="margin-top:26px">
                    <li class="sf-row" style="gap:12px;align-items:flex-start">
                        <span class="sf-row" style="justify-content:center;width:38px;height:38px;border-radius:var(--sf-radius-sm);background:rgba(255,255,255,.16);flex-shrink:0">
                            <x-storefront.icon name="package" :size="19" />
                        </span>
                        <span>Lacak pesanan dari setiap toko tanpa berpindah aplikasi.</span>
                    </li>
                    <li class="sf-row" style="gap:12px;align-items:flex-start">
                        <span class="sf-row" style="justify-content:center;width:38px;height:38px;border-radius:var(--sf-radius-sm);background:rgba(255,255,255,.16);flex-shrink:0">
                            <x-storefront.icon name="heart" :size="19" />
                        </span>
                        <span>Simpan produk favorit dan bandingkan sebelum membeli.</span>
                    </li>
                    <li class="sf-row" style="gap:12px;align-items:flex-start">
                        <span class="sf-row" style="justify-content:center;width:38px;height:38px;border-radius:var(--sf-radius-sm);background:rgba(255,255,255,.16);flex-shrink:0">
                            <x-storefront.icon name="coins" :size="19" />
                        </span>
                        <span>Kumpulkan poin loyalitas dan tukarkan ke dompet digital.</span>
                    </li>
                </ul>

                <a href="{{ route('products.index') }}" class="sf-btn sf-btn--block" style="background:#fff;color:var(--sf-brand);margin-top:26px">
                    <x-storefront.icon name="store" :size="17" /> Belanja sebagai tamu
                </a>
            </aside>
        </div>
    </div>
@endsection
