@extends('layouts.storefront')

@section('content')
    @php
        $user = auth()->user();
        $devices = collect($devices ?? []);
    @endphp

    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <a href="{{ route('account.dashboard') }}">Dashboard</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Keamanan</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-security-title">
        <div class="sf-container">
            <h1 class="sf-section-head__title" id="sf-security-title">Keamanan Akun</h1>
            <p class="sf-muted sf-small" style="max-width:60ch">
                Perbarui kata sandi dan pantau perangkat yang mengakses akun Anda.
            </p>

            <div class="sf-account">
                <aside>
                    <x-storefront.account-nav current="account.security" />
                </aside>

                <div class="sf-stack" style="gap:20px">
                    <section class="sf-card" aria-labelledby="sf-security-password">
                        <div class="sf-card__body">
                            <h2 class="sf-footer__title" id="sf-security-password">Ubah kata sandi</h2>

                            <form method="POST" action="{{ route('account.security.update') }}" class="sf-stack" style="gap:16px" novalidate>
                                @csrf
                                @method('PUT')

                                <div class="sf-field">
                                    <label class="sf-label" for="sf-current-password">Password saat ini <span class="sf-required">*</span></label>
                                    <input class="sf-input" id="sf-current-password" type="password" name="current_password" required
                                           autocomplete="current-password"
                                           @error('current_password') aria-invalid="true" aria-describedby="sf-current-password-error" @enderror>
                                    @error('current_password')
                                        <span class="sf-error" id="sf-current-password-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
                                    <div class="sf-field">
                                        <label class="sf-label" for="sf-new-password">Password baru <span class="sf-required">*</span></label>
                                        <input class="sf-input" id="sf-new-password" type="password" name="password" required
                                               autocomplete="new-password"
                                               @error('password') aria-invalid="true" aria-describedby="sf-new-password-error" @enderror>
                                        @error('password')
                                            <span class="sf-error" id="sf-new-password-error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="sf-field">
                                        <label class="sf-label" for="sf-confirm-password">Ulangi password baru <span class="sf-required">*</span></label>
                                        <input class="sf-input" id="sf-confirm-password" type="password" name="password_confirmation"
                                               required autocomplete="new-password">
                                    </div>
                                </div>
                                <p class="sf-hint sf-mb-0">Gunakan kata sandi panjang dan unik untuk setiap akun.</p>

                                <div>
                                    <button type="submit" class="sf-btn sf-btn--primary">
                                        <x-storefront.icon name="lock" :size="16" /> Perbarui password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </section>

                    <section class="sf-card" aria-labelledby="sf-security-account">
                        <div class="sf-card__body">
                            <h2 class="sf-footer__title" id="sf-security-account">Informasi akun</h2>
                            <div class="sf-summary">
                                <div class="sf-summary__row">
                                    <span class="sf-summary__label">Email</span>
                                    <span>{{ $user->email }}</span>
                                </div>
                                <div class="sf-summary__row">
                                    <span class="sf-summary__label">Email terverifikasi</span>
                                    <span>{{ $user->email_verified_at ? 'Sudah' : 'Belum' }}</span>
                                </div>
                                <div class="sf-summary__row">
                                    <span class="sf-summary__label">Bergabung sejak</span>
                                    <span>
                                        <time datetime="{{ $user->created_at?->toAtomString() }}">
                                            {{ $user->created_at?->translatedFormat('F Y') }}
                                        </time>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="sf-card" aria-labelledby="sf-security-devices">
                        <div class="sf-card__body">
                            <h2 class="sf-footer__title" id="sf-security-devices">Perangkat aktif</h2>

                            @if ($devices->isNotEmpty())
                                <div class="sf-tablewrap" style="margin-top:12px">
                                    <table class="sf-table">
                                        <caption class="sf-sr-only">Daftar perangkat yang mengakses akun</caption>
                                        <thead>
                                            <tr>
                                                <th scope="col">Perangkat</th>
                                                <th scope="col">Terakhir aktif</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($devices as $device)
                                                <tr>
                                                    <th scope="row" style="font-weight:500">
                                                        {{ $device->label ?? ($device->device_name ?? ($device->platform ?? 'Perangkat')) }}
                                                    </th>
                                                    <td>
                                                        <time datetime="{{ $device->last_seen_at?->toAtomString() ?? '' }}">
                                                            {{ $device->last_seen_at?->translatedFormat('d M Y H:i') ?? '-' }}
                                                        </time>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="sf-small sf-muted sf-mb-0">
                                    Belum ada catatan perangkat. Data ini akan terisi otomatis saat Anda masuk dari perangkat baru.
                                </p>
                            @endif
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </section>
@endsection
