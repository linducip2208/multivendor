@extends('layouts.storefront')

@section('content')
    @php
        $hasSocial = \Illuminate\Support\Facades\Route::has('social.redirect');
        $providers = ['google' => 'Google', 'facebook' => 'Facebook', 'github' => 'GitHub'];
    @endphp

    <div class="sf-container sf-section sf-section--tight">
        <div class="sf-panel" style="max-width:460px;margin-inline:auto">
            <h1 class="sf-mb-0" style="font-size:clamp(1.3rem,1.1rem+.8vw,1.7rem);text-align:center">Masuk atau Daftar</h1>
            <p class="sf-small sf-muted sf-text-center">
                Gunakan penyedia akun yang Anda pilih untuk melanjutkan.
            </p>

            @if ($hasSocial)
                <div class="sf-stack" style="gap:10px;margin-top:20px">
                    @foreach ($providers as $provider => $label)
                        <a href="{{ route('social.redirect', $provider) }}" class="sf-btn sf-btn--outline sf-btn--block">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                <div class="sf-row" style="gap:12px;margin-block:20px">
                    <span style="flex:1;height:1px;background:var(--sf-border)" aria-hidden="true"></span>
                    <span class="sf-tiny sf-muted">atau</span>
                    <span style="flex:1;height:1px;background:var(--sf-border)" aria-hidden="true"></span>
                </div>
            @endif

            <a href="{{ route('login') }}" class="sf-btn sf-btn--primary sf-btn--block sf-btn--lg">
                <x-storefront.icon name="mail" :size="17" /> Masuk dengan email
            </a>

            @if (\App\Models\SystemSetting::get('customer_registration_open', true))
                <p class="sf-small sf-muted sf-text-center sf-mb-0" style="margin-top:18px">
                    Belum punya akun? <a href="{{ route('register') }}">Daftar sekarang</a>
                </p>
            @endif
        </div>
    </div>
@endsection
