@extends('layouts.storefront')

@section('content')
    @php $addresses = collect($addresses ?? []); @endphp

    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <a href="{{ route('account.dashboard') }}">Dasbor</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Alamat</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-addresses-title">
        <div class="sf-container">
            <h1 class="sf-section-head__title" id="sf-addresses-title">Alamat Pengiriman</h1>
            <p class="sf-muted sf-small" style="max-width:60ch">
                Alamat yang tersimpan dapat dipilih langsung saat checkout.
            </p>

            <div class="sf-account">
                <aside>
                    <x-storefront.account-nav current="account.addresses" />
                </aside>

                <div class="sf-stack" style="gap:20px">
                    <section class="sf-card" aria-labelledby="sf-addresses-list">
                        <div class="sf-card__body">
                            <h2 class="sf-footer__title" id="sf-addresses-list">Alamat tersimpan</h2>

                            @if ($addresses->isNotEmpty())
                                <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));margin-top:12px">
                                    @foreach ($addresses as $address)
                                        <div class="sf-shipbox" style="cursor:default">
                                            <div class="sf-row sf-row--between sf-row--wrap" style="gap:10px">
                                                <div style="min-width:0;flex:1 1 160px">
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
                                <x-storefront.empty
                                    title="Belum ada alamat tersimpan"
                                    text="Tambahkan alamat agar checkout berikutnya jauh lebih cepat."
                                    icon="map-pin"
                                />
                            @endif
                        </div>
                    </section>

                    <section class="sf-card" aria-labelledby="sf-addresses-add">
                        <div class="sf-card__body">
                            <h2 class="sf-footer__title" id="sf-addresses-add">Tambah alamat baru</h2>

                            <form method="POST" action="{{ route('profile.address.store') }}" class="sf-grid"
                                  style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))" novalidate>
                                @csrf
                                <div class="sf-field">
                                    <label class="sf-label" for="sf-acc-label">Label <span class="sf-required">*</span></label>
                                    <input class="sf-input" id="sf-acc-label" type="text" name="label" required
                                           value="{{ old('label') }}" placeholder="Rumah, Kantor, …">
                                    @error('label')<span class="sf-error">{{ $message }}</span>@enderror
                                </div>
                                <div class="sf-field">
                                    <label class="sf-label" for="sf-acc-name">Nama penerima <span class="sf-required">*</span></label>
                                    <input class="sf-input" id="sf-acc-name" type="text" name="receiver_name" required
                                           value="{{ old('receiver_name', auth()->user()?->name ?? '') }}">
                                    @error('receiver_name')<span class="sf-error">{{ $message }}</span>@enderror
                                </div>
                                <div class="sf-field">
                                    <label class="sf-label" for="sf-acc-phone">Nomor telepon <span class="sf-required">*</span></label>
                                    <input class="sf-input" id="sf-acc-phone" type="tel" name="receiver_phone" required
                                           value="{{ old('receiver_phone', auth()->user()?->phone ?? '') }}">
                                    @error('receiver_phone')<span class="sf-error">{{ $message }}</span>@enderror
                                </div>
                                <div class="sf-field" style="grid-column:1/-1">
                                    <label class="sf-label" for="sf-acc-street">Alamat lengkap <span class="sf-required">*</span></label>
                                    <textarea class="sf-textarea" id="sf-acc-street" name="address" rows="2" required
                                              style="min-height:80px">{{ old('address') }}</textarea>
                                    @error('address')<span class="sf-error">{{ $message }}</span>@enderror
                                </div>
                                <div class="sf-field">
                                    <label class="sf-label" for="sf-acc-city">Kota <span class="sf-required">*</span></label>
                                    <input class="sf-input" id="sf-acc-city" type="text" name="city" required value="{{ old('city') }}">
                                    @error('city')<span class="sf-error">{{ $message }}</span>@enderror
                                </div>
                                <div class="sf-field">
                                    <label class="sf-label" for="sf-acc-province">Provinsi <span class="sf-required">*</span></label>
                                    <input class="sf-input" id="sf-acc-province" type="text" name="province" required value="{{ old('province') }}">
                                    @error('province')<span class="sf-error">{{ $message }}</span>@enderror
                                </div>
                                <div class="sf-field">
                                    <label class="sf-label" for="sf-acc-postal">Kode pos</label>
                                    <input class="sf-input" id="sf-acc-postal" type="text" name="postal_code" value="{{ old('postal_code') }}">
                                </div>
                                <div class="sf-field">
                                    <label class="sf-label" for="sf-acc-destination">ID tujuan pengiriman</label>
                                    <input class="sf-input" id="sf-acc-destination" type="text" name="shipping_destination_id"
                                           value="{{ old('shipping_destination_id') }}" placeholder="Opsional">
                                </div>
                                <div class="sf-field" style="grid-column:1/-1">
                                    <label class="sf-checkbox" for="sf-acc-default">
                                        <input type="checkbox" id="sf-acc-default" name="is_default" value="1" @checked(old('is_default'))>
                                        <span>Jadikan alamat utama</span>
                                    </label>
                                </div>
                                <div style="grid-column:1/-1">
                                    <button type="submit" class="sf-btn sf-btn--primary">
                                        <x-storefront.icon name="plus" :size="16" /> Simpan alamat
                                    </button>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </section>
@endsection
