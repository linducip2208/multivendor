@extends('layouts.storefront')

@section('content')
    @php $user = auth()->user(); @endphp

    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Profil Saya</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-profile-title">
        <div class="sf-container">
            <h1 class="sf-section-head__title" id="sf-profile-title">Profil Saya</h1>

            <div class="sf-cartlayout">
                <div class="sf-stack" style="gap:20px">
                    <section class="sf-card" aria-labelledby="sf-profile-details">
                        <div class="sf-card__body">
                            <h2 class="sf-footer__title" id="sf-profile-details">Data akun</h2>

                            <form method="POST" action="{{ route('profile.update') }}" class="sf-stack" style="gap:16px" novalidate>
                                @csrf
                                @method('PUT')

                                <div class="sf-field">
                                    <label class="sf-label" for="sf-profile-name">Nama lengkap <span class="sf-required">*</span></label>
                                    <input class="sf-input" id="sf-profile-name" type="text" name="name" required
                                           value="{{ old('name', $user->name) }}"
                                           @error('name') aria-invalid="true" aria-describedby="sf-profile-name-error" @enderror>
                                    @error('name')
                                        <span class="sf-error" id="sf-profile-name-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="sf-field">
                                    <label class="sf-label" for="sf-profile-email">Email</label>
                                    <input class="sf-input" id="sf-profile-email" type="email" value="{{ $user->email }}" disabled
                                           aria-describedby="sf-profile-email-hint">
                                    <span class="sf-hint" id="sf-profile-email-hint">
                                        Untuk mengubah email, hubungi tim dukungan.
                                    </span>
                                </div>

                                <div class="sf-field">
                                    <label class="sf-label" for="sf-profile-phone">Nomor telepon</label>
                                    <input class="sf-input" id="sf-profile-phone" type="tel" name="phone"
                                           value="{{ old('phone', $user->phone) }}"
                                           @error('phone') aria-invalid="true" aria-describedby="sf-profile-phone-error" @enderror>
                                    @error('phone')
                                        <span class="sf-error" id="sf-profile-phone-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
                                    <div class="sf-field">
                                        <label class="sf-label" for="sf-profile-password">Password baru</label>
                                        <input class="sf-input" id="sf-profile-password" type="password" name="password"
                                               autocomplete="new-password"
                                               @error('password') aria-invalid="true" aria-describedby="sf-profile-password-error" @enderror>
                                        @error('password')
                                            <span class="sf-error" id="sf-profile-password-error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="sf-field">
                                        <label class="sf-label" for="sf-profile-password-confirm">Ulangi password baru</label>
                                        <input class="sf-input" id="sf-profile-password-confirm" type="password"
                                               name="password_confirmation" autocomplete="new-password">
                                    </div>
                                </div>
                                <p class="sf-hint sf-mb-0">Kosongkan bila tidak ingin mengganti password.</p>

                                <div>
                                    <button type="submit" class="sf-btn sf-btn--primary">Simpan perubahan</button>
                                </div>
                            </form>
                        </div>
                    </section>

                    <section class="sf-card" aria-labelledby="sf-profile-addresses">
                        <div class="sf-card__body">
                            <h2 class="sf-footer__title" id="sf-profile-addresses">Alamat pengiriman</h2>

                            @if ($addresses->isNotEmpty())
                                <div class="sf-stack" style="gap:10px;margin-bottom:20px">
                                    @foreach ($addresses as $address)
                                        <div class="sf-shipbox" style="cursor:default">
                                            <div class="sf-row sf-row--between sf-row--wrap" style="gap:10px">
                                                <div style="min-width:0;flex:1 1 200px">
                                                    <p class="sf-bold sf-mb-0">
                                                        {{ $address->label ?: 'Alamat' }}
                                                        @if ($address->is_default)
                                                            <span class="sf-badge sf-badge--brand">Utama</span>
                                                        @endif
                                                    </p>
                                                    <p class="sf-small sf-muted sf-mb-0">
                                                        {{ $address->receiver_name }} &middot; {{ $address->receiver_phone }}
                                                    </p>
                                                    <p class="sf-small sf-muted sf-mb-0" style="overflow-wrap:anywhere">
                                                        {{ $address->address }},
                                                        {{ collect([$address->city, $address->province, $address->postal_code])->filter()->implode(', ') }}
                                                    </p>
                                                </div>
                                                <form method="POST" action="{{ route('profile.address.destroy', $address) }}"
                                                      data-sf-confirm="Hapus alamat ini?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="sf-iconbtn" aria-label="Hapus alamat {{ $address->label ?: '' }}">
                                                        <x-storefront.icon name="trash" :size="17" />
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="sf-small sf-muted">Belum ada alamat tersimpan.</p>
                            @endif

                            <h3 class="sf-footer__title">Tambah alamat</h3>
                            <form method="POST" action="{{ route('profile.address.store') }}" class="sf-grid"
                                  style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))" novalidate>
                                @csrf
                                <div class="sf-field">
                                    <label class="sf-label" for="sf-address-label">Label <span class="sf-required">*</span></label>
                                    <input class="sf-input" id="sf-address-label" type="text" name="label" required
                                           value="{{ old('label') }}" placeholder="Rumah, Kantor, …">
                                </div>
                                <div class="sf-field">
                                    <label class="sf-label" for="sf-address-name">Nama penerima <span class="sf-required">*</span></label>
                                    <input class="sf-input" id="sf-address-name" type="text" name="receiver_name" required
                                           value="{{ old('receiver_name', $user->name) }}">
                                </div>
                                <div class="sf-field">
                                    <label class="sf-label" for="sf-address-phone">Nomor telepon <span class="sf-required">*</span></label>
                                    <input class="sf-input" id="sf-address-phone" type="tel" name="receiver_phone" required
                                           value="{{ old('receiver_phone', $user->phone) }}">
                                </div>
                                <div class="sf-field" style="grid-column:1/-1">
                                    <label class="sf-label" for="sf-address-street">Alamat lengkap <span class="sf-required">*</span></label>
                                    <textarea class="sf-textarea" id="sf-address-street" name="address" rows="2" required
                                              style="min-height:80px">{{ old('address') }}</textarea>
                                </div>
                                <div class="sf-field">
                                    <label class="sf-label" for="sf-address-city">Kota <span class="sf-required">*</span></label>
                                    <input class="sf-input" id="sf-address-city" type="text" name="city" required value="{{ old('city') }}">
                                </div>
                                <div class="sf-field">
                                    <label class="sf-label" for="sf-address-province">Provinsi <span class="sf-required">*</span></label>
                                    <input class="sf-input" id="sf-address-province" type="text" name="province" required value="{{ old('province') }}">
                                </div>
                                <div class="sf-field">
                                    <label class="sf-label" for="sf-address-postal">Kode pos</label>
                                    <input class="sf-input" id="sf-address-postal" type="text" name="postal_code" value="{{ old('postal_code') }}">
                                </div>
                                <div class="sf-field">
                                    <label class="sf-label" for="sf-address-destination">ID tujuan pengiriman</label>
                                    <input class="sf-input" id="sf-address-destination" type="text" name="shipping_destination_id"
                                           value="{{ old('shipping_destination_id') }}" placeholder="Opsional">
                                </div>
                                <div class="sf-field" style="grid-column:1/-1">
                                    <label class="sf-checkbox" for="sf-address-default">
                                        <input type="checkbox" id="sf-address-default" name="is_default" value="1" @checked(old('is_default'))>
                                        <span>Jadikan alamat utama</span>
                                    </label>
                                </div>
                                <div style="grid-column:1/-1">
                                    <button type="submit" class="sf-btn sf-btn--primary sf-btn--sm">
                                        <x-storefront.icon name="plus" :size="15" /> Tambah alamat
                                    </button>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>

                <aside class="sf-stack" style="gap:16px">
                    <section class="sf-panel" aria-labelledby="sf-profile-wallet">
                        <h2 class="sf-footer__title" id="sf-profile-wallet">Dompet digital</h2>
                        <p class="sf-mb-0" style="font-size:1.6rem;font-weight:800">
                            {{ \App\Support\Currency::format($wallet?->balance ?? 0) }}
                        </p>
                        <a href="{{ route('account.wallet') }}" class="sf-btn sf-btn--outline sf-btn--sm" style="margin-top:14px">
                            Lihat mutasi dompet
                        </a>
                    </section>

                    @if ($user->referral_code)
                        <section class="sf-panel" aria-labelledby="sf-profile-referral">
                            <h2 class="sf-footer__title" id="sf-profile-referral">Kode referral</h2>
                            <p class="sf-mb-0">
                                <code style="font-size:1.1rem;font-weight:700;letter-spacing:.06em">{{ $user->referral_code }}</code>
                            </p>
                            <button type="button" class="sf-btn sf-btn--ghost sf-btn--sm" style="margin-top:12px"
                                    data-sf-copy="{{ $user->referral_code }}" aria-label="Salin kode referral">
                                <x-storefront.icon name="copy" :size="15" /> Salin kode
                            </button>
                        </section>
                    @endif

                    <section class="sf-panel" aria-labelledby="sf-profile-links">
                        <h2 class="sf-footer__title" id="sf-profile-links">Tautan cepat</h2>
                        <div class="sf-stack" style="gap:8px">
                            <a href="{{ route('account.dashboard') }}" class="sf-btn sf-btn--ghost sf-btn--block">
                                <x-storefront.icon name="home" :size="16" /> Dashboard akun
                            </a>
                            <a href="{{ route('orders.index') }}" class="sf-btn sf-btn--ghost sf-btn--block">
                                <x-storefront.icon name="package" :size="16" /> Pesanan saya
                            </a>
                            <a href="{{ route('account.security') }}" class="sf-btn sf-btn--ghost sf-btn--block">
                                <x-storefront.icon name="lock" :size="16" /> Keamanan akun
                            </a>
                            <a href="{{ route('tickets.index') }}" class="sf-btn sf-btn--ghost sf-btn--block">
                                <x-storefront.icon name="headset" :size="16" /> Tiket dukungan
                            </a>
                        </div>
                    </section>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="sf-btn sf-btn--outline sf-btn--block">
                            <x-storefront.icon name="logout" :size="16" /> Keluar dari akun
                        </button>
                    </form>
                </aside>
            </div>
        </div>
    </section>
@endsection
