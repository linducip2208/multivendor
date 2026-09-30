@extends('layouts.storefront')

@section('content')
    @php
        $groups = collect($shops);
        $itemCount = (int) $groups->sum(fn ($group) => $group['items']->sum('quantity'));
        $courierSetting = (string) \App\Models\SystemSetting::get('shipping_couriers', '');
        $couriers = $courierSetting !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $courierSetting))))
            : ['jne', 'jnt', 'sicepat', 'tiki', 'anteraja', 'pos'];
        $checkoutUrl = route('checkout.process');
        $pickupWarehouses = collect();
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('warehouses', 'allow_pickup')) {
                $pickupWarehouses = \App\Models\Warehouse::query()->where('is_active', true)
                    ->where('allow_pickup', true)->orderBy('name')->get(['id', 'name', 'code', 'city']);
            }
        } catch (\Throwable) {
            $pickupWarehouses = collect();
        }
        $steps = [
            ['label' => 'Alamat', 'state' => 'is-done'],
            ['label' => 'Pengiriman', 'state' => 'is-active'],
            ['label' => 'Pembayaran', 'state' => ''],
        ];
    @endphp

    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <a href="{{ route('cart.index') }}">Keranjang</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Checkout</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-checkout-title">
        <div class="sf-container">
            <h1 class="sf-section-head__title" id="sf-checkout-title" style="font-size:clamp(1.5rem,1.2rem+1.4vw,2.25rem)">Checkout</h1>

            <ol class="sf-steps" style="margin-top:18px" aria-label="Tahapan checkout">
                @foreach ($steps as $index => $step)
                    <li class="sf-step {{ $step['state'] }}">
                        <span class="sf-step__num" aria-hidden="true">{{ $index + 1 }}</span>
                        <span>{{ $step['label'] }}</span>
                    </li>
                    @if (! $loop->last)
                        <span class="sf-step__line {{ $step['state'] === 'is-done' ? 'is-done' : '' }}" aria-hidden="true"></span>
                    @endif
                @endforeach
            </ol>

            @if ($groups->isEmpty())
                <x-storefront.empty
                    title="Keranjang Anda kosong"
                    text="Tambahkan produk ke keranjang sebelum melanjutkan ke checkout."
                    :href="route('products.index')"
                    label="Mulai belanja"
                    icon="cart"
                />
            @else
                @if ($paymentGateways->isEmpty())
                    <x-storefront.alert type="warning" title="Metode pembayaran belum tersedia">
                        Administrator belum mengaktifkan payment gateway, jadi pesanan belum dapat diproses. Hubungi tim dukungan untuk bantuan lebih lanjut.
                    </x-storefront.alert>
                @endif

                {{-- ADITIF global-checkout: currency + country (display terkonversi, charge tetap IDR). --}}
                <div class="sf-card" style="margin-bottom:20px" aria-labelledby="sf-global-title">
                    <div class="sf-card__body">
                        <h2 class="sf-footer__title" id="sf-global-title" style="font-size:.95rem">
                            Mata uang &amp; negara / Currency &amp; country
                        </h2>
                        <form method="GET" action="{{ route('checkout.index') }}" class="sf-row sf-row--wrap" style="gap:10px;align-items:flex-end">
                            <div class="sf-field" style="min-width:180px">
                                <label class="sf-label form-label" for="sf-global-currency">Currency / Mata uang</label>
                                <select class="sf-select form-select" id="sf-global-currency" name="currency">
                                    @foreach (($currencies ?? collect()) as $cur)
                                        <option value="{{ $cur->code }}" @selected(($displayCurrency ?? 'IDR') === $cur->code)>
                                            {{ $cur->code }} ({{ $cur->symbol ?? $cur->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sf-field" style="min-width:200px">
                                <label class="sf-label form-label" for="sf-global-country">Country / Negara</label>
                                <select class="sf-select form-select" id="sf-global-country" name="checkout_country">
                                    @foreach (($countries ?? collect()) as $ct)
                                        <option value="{{ $ct->iso2 }}" @selected(($checkoutCountry ?? 'ID') === $ct->iso2)>
                                            {{ $ct->iso2 }} — {{ $ct->name ?? $ct->iso2 }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="sf-btn sf-btn--outline sf-btn--sm">Terapkan / Apply</button>
                        </form>
                        <p class="sf-small sf-muted sf-mb-0" style="margin-top:10px">
                            Total terkonversi hanya tampilan / Converted total is display-only.
                            Penagihan tetap IDR kecuali gateway mendukung mata uang tersebut (capability check server-side).
                            @if (! empty($displayTotal))
                                <span class="sf-badge badge" style="margin-left:6px">≈ {{ $displayTotal['formatted'] }} {{ $displayCurrency }}</span>
                                <span class="sf-tiny sf-muted">Kurs / Rate: 1 {{ $displayCurrency }} = {{ number_format((float) ($displayTotal['rate'] ?? 1), 2, ',', '.') }} IDR</span>
                            @else
                                <span class="sf-tiny sf-muted">Charge currency: IDR.</span>
                            @endif
                        </p>
                        <p class="sf-tiny sf-muted sf-mb-0">
                            Metode pembayaran &amp; ongkir difilter per negara ({{ $checkoutCountry ?? 'ID' }}) / Payment &amp; shipping methods filtered per country.
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ $checkoutUrl }}" class="sf-cartlayout" novalidate>
                    @csrf
                    <input type="hidden" name="currency" value="{{ $displayCurrency ?? 'IDR' }}">
                    <input type="hidden" name="checkout_country" value="{{ $checkoutCountry ?? 'ID' }}">
                    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey ?? old('idempotency_key') }}"> 

                    <div class="sf-stack" style="gap:20px">
                        <section class="sf-card" aria-labelledby="sf-checkout-address">
                            <div class="sf-card__body">
                                <h2 class="sf-footer__title" id="sf-checkout-address">1. Alamat pengiriman</h2>

                                @if ($addresses->isNotEmpty())
                                    <div class="sf-stack" style="gap:10px;margin-bottom:18px">
                                        @foreach ($addresses as $address)
                                            <label class="sf-shipbox" for="sf-address-{{ $address->id }}">
                                                <span class="sf-row" style="gap:10px;align-items:flex-start;flex-wrap:wrap">
                                                    <input class="sf-radio" type="radio" name="address_id" id="sf-address-{{ $address->id }}"
                                                           value="{{ $address->id }}"
                                                           data-destination="{{ $address->shipping_destination_id }}"
                                                           @checked(old('address_id', $address->id) == $address->id)>
                                                    <span style="min-width:0;flex:1">
                                                        <span class="sf-bold sf-row sf-row--wrap" style="gap:8px">
                                                            {{ $address->label ?: 'Alamat' }}
                                                            @if ($address->is_default)
                                                                <span class="sf-badge sf-badge--brand">Utama</span>
                                                            @endif
                                                        </span>
                                                        <span class="sf-small sf-muted" style="display:block">
                                                            {{ $address->receiver_name }} &middot; {{ $address->receiver_phone }}
                                                        </span>
                                                        <span class="sf-small sf-muted" style="display:block">
                                                            {{ $address->address }},
                                                            {{ collect([$address->city, $address->province, $address->postal_code])->filter()->implode(', ') }}
                                                        </span>
                                                    </span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>

                                    <p class="sf-small sf-muted">Atau gunakan alamat baru di bawah ini.</p>
                                    <hr class="sf-divider">
                                @endif

                                <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
                                    <div class="sf-field">
                                        <label class="sf-label" for="sf-new-receiver">Nama penerima <span class="sf-required">*</span></label>
                                        <input class="sf-input" id="sf-new-receiver" type="text" name="new_receiver_name"
                                               value="{{ old('new_receiver_name') }}" autocomplete="name"
                                               @error('new_receiver_name') aria-invalid="true" @enderror>
                                        @error('new_receiver_name')<span class="sf-error">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="sf-field">
                                        <label class="sf-label" for="sf-new-phone">Nomor telepon <span class="sf-required">*</span></label>
                                        <input class="sf-input" id="sf-new-phone" type="tel" name="new_receiver_phone"
                                               value="{{ old('new_receiver_phone') }}" autocomplete="tel"
                                               @error('new_receiver_phone') aria-invalid="true" @enderror>
                                        @error('new_receiver_phone')<span class="sf-error">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="sf-field" style="grid-column:1/-1">
                                        <label class="sf-label" for="sf-new-address">Alamat lengkap <span class="sf-required">*</span></label>
                                        <textarea class="sf-textarea" id="sf-new-address" name="new_address" rows="2"
                                                  autocomplete="street-address" style="min-height:80px"
                                                  @error('new_address') aria-invalid="true" @enderror>{{ old('new_address') }}</textarea>
                                        @error('new_address')<span class="sf-error">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="sf-field">
                                        <label class="sf-label" for="sf-new-city">Kota <span class="sf-required">*</span></label>
                                        <input class="sf-input" id="sf-new-city" type="text" name="new_city"
                                               value="{{ old('new_city') }}" autocomplete="address-level2"
                                               @error('new_city') aria-invalid="true" @enderror>
                                        @error('new_city')<span class="sf-error">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="sf-field">
                                        <label class="sf-label" for="sf-new-province">Provinsi <span class="sf-required">*</span></label>
                                        <input class="sf-input" id="sf-new-province" type="text" name="new_province"
                                               value="{{ old('new_province') }}" autocomplete="address-level1"
                                               @error('new_province') aria-invalid="true" @enderror>
                                        @error('new_province')<span class="sf-error">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="sf-field">
                                        <label class="sf-label" for="sf-new-postal">Kode pos</label>
                                        <input class="sf-input" id="sf-new-postal" type="text" name="new_postal_code"
                                               value="{{ old('new_postal_code') }}" autocomplete="postal-code">
                                    </div>
                                    <div class="sf-field" style="grid-column:1/-1">
                                        <label class="sf-label" for="sf-new-destination">ID tujuan pengiriman</label>
                                        <input class="sf-input" id="sf-new-destination" type="text" name="new_shipping_destination_id"
                                               value="{{ old('new_shipping_destination_id') }}"
                                               placeholder="Diisi dari kode wilayah alamat" autocomplete="off">
                                        <span class="sf-hint">
                                            Diperlukan bila penyedia pengiriman memakai kode wilayah. Kosongkan bila tidak diperlukan.
                                        </span>
                                        @error('new_shipping_destination_id')<span class="sf-error">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="sf-card" aria-labelledby="sf-checkout-shipping">
                            <div class="sf-card__body">
                                <h2 class="sf-footer__title" id="sf-checkout-shipping">2. Pengiriman per toko</h2>
                                <p class="sf-small sf-muted">
                                    Setiap toko dikirim terpisah, jadi metode pengiriman dipilih per toko.
                                </p>

                                <div class="sf-stack" style="gap:16px;margin-top:14px">
                                    @foreach ($groups as $group)
                                        @php
                                            $shopId = $group['shop']?->id;
                                            $shopKey = $shopId ?? $loop->index;
                                            $shopError = $errors->first("shipping_methods.{$shopId}.destination");
                                        @endphp
                                        <div class="sf-shipbox" data-shipping-shop="{{ $shopId }}" style="cursor:default">
                                            <div class="sf-row sf-row--between sf-row--wrap" style="gap:8px;margin-bottom:12px">
                                                <span class="sf-bold sf-row" style="gap:7px;min-width:0">
                                                    <x-storefront.icon name="store" :size="16" />
                                                    <span class="sf-clamp-2">{{ $group['shop']?->name ?? 'Toko' }}</span>
                                                </span>
                                                <span class="sf-small sf-muted sf-nowrap">
                                                    {{ \App\Support\Currency::format($group['subtotal']) }}
                                                </span>
                                            </div>

                                            <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
                                                <div class="sf-field">
                                                    <label class="sf-label" for="sf-ship-provider-{{ $shopKey }}">Penyedia</label>
                                                    <select class="sf-select" id="sf-ship-provider-{{ $shopKey }}"
                                                            name="shipping_methods[{{ $shopKey }}][provider_id]"
                                                            data-shipping-field="provider_id">
                                                        <option value="">Pilih penyedia</option>
                                                        @foreach ($shippingProviders as $provider)
                                                            <option value="{{ $provider->id }}"
                                                                    @selected((string) old("shipping_methods.{$shopKey}.provider_id") === (string) $provider->id)>
                                                                {{ $provider->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="sf-field">
                                                    <label class="sf-label" for="sf-ship-courier-{{ $shopKey }}">Kurir</label>
                                                    <select class="sf-select" id="sf-ship-courier-{{ $shopKey }}"
                                                            name="shipping_methods[{{ $shopKey }}][courier]"
                                                            data-shipping-field="courier">
                                                        @foreach ($couriers as $courier)
                                                            <option value="{{ $courier }}"
                                                                    @selected(strtolower($courier) === strtolower(\App\Models\SystemSetting::get('shipping_default_courier', 'jne')))>
                                                                {{ strtoupper($courier) }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="sf-field">
                                                    <label class="sf-label" for="sf-ship-service-{{ $shopKey }}">Layanan</label>
                                                    <input class="sf-input" id="sf-ship-service-{{ $shopKey }}" type="text"
                                                            name="shipping_methods[{{ $shopKey }}][service]"
                                                           value="{{ old("shipping_methods.{$shopKey}.service", 'Reguler') }}"
                                                           data-shipping-field="service"
                                                           list="sf-ship-services-{{ $shopKey }}">
                                                    <datalist id="sf-ship-services-{{ $shopKey }}">
                                                        <option value="Reguler"></option>
                                                        <option value="Kargo"></option>
                                                        <option value="Ekspres"></option>
                                                        <option value="Same Day"></option>
                                                        <option value="Next Day"></option>
                                                    </datalist>
                                                </div>
                                            </div>

                                            <div class="sf-row sf-row--wrap" style="gap:8px;margin-top:12px">
                                                <button type="button" class="sf-btn sf-btn--outline sf-btn--sm" data-shipping-check>
                                                    <x-storefront.icon name="truck" :size="15" /> Cek ongkos kirim
                                                </button>
                                                <span class="sf-small sf-muted" data-shipping-status role="status" aria-live="polite"></span>
                                            </div>

                                            <ul class="sf-stack" data-shipping-rates style="gap:8px;margin-top:12px;list-style:none;padding:0"></ul>

                                            <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-top:10px">
                                                <label class="sf-small sf-row" style="gap:6px;align-items:center">
                                                    <input type="checkbox" name="shipping_methods[{{ $shopKey }}][insurance]" value="1"
                                                        @checked((bool) old("shipping_methods.{$shopKey}.insurance"))>
                                                    Tambah asuransi pengiriman
                                                </label>
                                                <div class="sf-field">
                                                    <label class="sf-label" for="sf-length-{{ $shopKey }}">P × L × T (cm, opsional)</label>
                                                    <div class="sf-row" style="gap:4px">
                                                        <input class="sf-input" id="sf-length-{{ $shopKey }}" type="number" step="0.1" min="0" max="500" name="shipping_methods[{{ $shopKey }}][length]" value="{{ old("shipping_methods.{$shopKey}.length") }}" placeholder="P">
                                                        <input class="sf-input" type="number" step="0.1" min="0" max="500" name="shipping_methods[{{ $shopKey }}][width]" value="{{ old("shipping_methods.{$shopKey}.width") }}" placeholder="L">
                                                        <input class="sf-input" type="number" step="0.1" min="0" max="500" name="shipping_methods[{{ $shopKey }}][height]" value="{{ old("shipping_methods.{$shopKey}.height") }}" placeholder="T">
                                                    </div>
                                                    <span class="sf-tiny sf-muted">Berat volumetrik dihitung otomatis; kurir cadangan dipakai bila utama gagal.</span>
                                                </div>
                                            </div>

                                            <div class="sf-field" style="margin-top:10px">
                                                <label class="sf-label sf-row" style="gap:6px;align-items:center">
                                                    <input type="checkbox" name="shipping_methods[{{ $shopKey }}][pickup]" value="1"
                                                        @checked((bool) old("shipping_methods.{$shopKey}.pickup"))>
                                                    Ambil di toko (click &amp; collect — gratis ongkir)
                                                </label>
                                                @if ($pickupWarehouses->isNotEmpty())
                                                    <select class="sf-select" name="shipping_methods[{{ $shopKey }}][pickup_warehouse_id]" style="margin-top:6px">
                                                        <option value="">Pilih lokasi pengambilan</option>
                                                        @foreach ($pickupWarehouses as $pickup)
                                                            <option value="{{ $pickup->id }}"
                                                                    @selected((string) old("shipping_methods.{$shopKey}.pickup_warehouse_id") === (string) $pickup->id)>
                                                                {{ $pickup->name }} ({{ $pickup->code }}){{ $pickup->city ? ' — '.$pickup->city : '' }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <span class="sf-tiny sf-muted">Terima kode ambil 6 karakter setelah membayar; tunjukkan ke petugas saat pengambilan.</span>
                                                @endif
                                                @error("shipping_methods.{$shopKey}.pickup_warehouse_id")<span class="sf-error">{{ $message }}</span>@enderror
                                            </div>

                                            <div class="sf-field" style="margin-top:10px">
                                                <label class="sf-label" for="sf-shop-note-{{ $shopKey }}">Catatan untuk {{ $group['shop']?->name ?? 'toko ini' }} (opsional)</label>
                                                <textarea class="sf-textarea" id="sf-shop-note-{{ $shopKey }}" name="shop_notes[{{ $shopKey }}]" rows="2" maxlength="1000"
                                                    placeholder="Contoh: bungkus kado untuk toko ini">{{ old("shop_notes.{$shopKey}") }}</textarea>
                                            </div>

                                            @if ($shopError)
                                                <span class="sf-error" style="display:block;margin-top:8px">{{ $shopError }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </section>

                        <section class="sf-card" aria-labelledby="sf-checkout-slot">
                            <div class="sf-card__body">
                                <h2 class="sf-footer__title" id="sf-checkout-slot">Jadwal pengiriman (opsional)</h2>
                                <p class="sf-small sf-muted">
                                    Pilih hari dan jam kedatangan yang Anda inginkan. Kurir mengantar pada slot
                                    yang dipilih; jadwal tersimpan di setiap pesanan dan tampil di fulfillment.
                                </p>

                                @php
                                    $slotMin = now()->toDateString();
                                    $slotMax = now()->addDays(14)->toDateString();
                                    $slotTimes = \App\Services\OrderWorkflowService::DELIVERY_SLOT_TIMES;
                                @endphp

                                <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-top:12px">
                                    <div class="sf-field">
                                        <label class="sf-label" for="sf-slot-date">Hari pengiriman</label>
                                        <input class="sf-input" id="sf-slot-date" type="date" name="delivery_slot_date"
                                               value="{{ old('delivery_slot_date') }}"
                                               min="{{ $slotMin }}" max="{{ $slotMax }}"
                                               @error('delivery_slot_date') aria-invalid="true" @enderror>
                                        <span class="sf-hint">Hari ini s.d. 14 hari ke depan.</span>
                                        @error('delivery_slot_date')<span class="sf-error">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="sf-field">
                                        <label class="sf-label" for="sf-slot-time">Jam pengiriman</label>
                                        <select class="sf-select" id="sf-slot-time" name="delivery_slot_time"
                                                @error('delivery_slot_time') aria-invalid="true" @enderror>
                                            <option value="">Kapan pun (tanpa jam tertentu)</option>
                                            @foreach ($slotTimes as $slotTime)
                                                <option value="{{ $slotTime }}"
                                                        @selected((string) old('delivery_slot_time') === (string) $slotTime)>
                                                    Pukul {{ $slotTime }} WIB
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('delivery_slot_time')<span class="sf-error">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="sf-card" aria-labelledby="sf-checkout-payment">
                            <div class="sf-card__body">
                                <h2 class="sf-footer__title" id="sf-checkout-payment">3. Metode pembayaran / Payment method</h2>
                                <p class="sf-small sf-muted sf-mb-0">
                                    Menampilkan {{ $paymentGateways->count() }} metode untuk {{ $checkoutCountry ?? 'ID' }} /
                                    Showing {{ $paymentGateways->count() }} methods for {{ $checkoutCountry ?? 'ID' }}.
                                </p>

                                @error('payment_provider_id')
                                    <x-storefront.alert type="error">{{ $message }}</x-storefront.alert>
                                @enderror

                                @if ($paymentGateways->isNotEmpty())
                                    <div class="sf-stack" style="gap:10px;margin-top:12px">
                                        @foreach ($paymentGateways as $gateway)
                                            <label class="sf-shipbox" for="sf-pay-{{ $gateway->id }}">
                                                <span class="sf-row" style="gap:10px;align-items:flex-start">
                                                    <input class="sf-radio" type="radio" name="payment_provider_id" id="sf-pay-{{ $gateway->id }}"
                                                           value="{{ $gateway->id }}" required
                                                           @checked((string) old('payment_provider_id', $paymentGateways->firstWhere('is_default', true)?->id ?? $paymentGateways->first()?->id) === (string) $gateway->id)>
                                                    <span style="min-width:0;flex:1">
                                                        <span class="sf-bold" style="display:block">{{ $gateway->name }}</span>
                                                        @if ($gateway->description)
                                                            <span class="sf-small sf-muted">{{ $gateway->description }}</span>
                                                        @endif
                                                    </span>
                                                    @if ($gateway->is_default)
                                                        <span class="sf-badge sf-badge--brand">Utama</span>
                                                    @endif
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="sf-small sf-muted sf-mb-0">Belum ada metode pembayaran yang aktif.</p>
                                @endif

                                <div class="sf-field" style="margin-top:16px">
                                    <label class="sf-label" for="sf-coupon">Kode kupon</label>
                                    <input class="sf-input" id="sf-coupon" type="text" name="coupon_code"
                                           value="{{ old('coupon_code') }}" placeholder="Punya kode promo?">
                                    @error('coupon_code')<span class="sf-error">{{ $message }}</span>@enderror
                                </div>

                                <div class="sf-field" style="margin-top:12px">
                                    <label class="sf-label" for="sf-note">Catatan untuk penjual</label>
                                    <textarea class="sf-textarea" id="sf-note" name="note" rows="3" maxlength="2000"
                                              placeholder="Contoh: titip ke satpam bila rumah kosong">{{ old('note') }}</textarea>
                                    @error('note')<span class="sf-error">{{ $message }}</span>@enderror
                                </div>

                                <label class="sf-small sf-row" style="gap:6px;align-items:center;margin-top:10px">
                                    <input type="checkbox" name="insurance" value="1" @checked((bool) old('insurance'))>
                                    Tambah asuransi untuk semua pengiriman (premi dihitung dari nilai barang)
                                </label>
                                <p class="sf-tiny sf-muted sf-mb-0">Nomor invoice bernomor seri otomatis (INV-...) diterbitkan per pesanan toko.</p>

                                <hr class="sf-divider" style="margin:16px 0">

                                <h3 class="sf-footer__title" style="font-size:.95rem">Dropship (opsional)</h3>
                                <label class="sf-small sf-row" style="gap:6px;align-items:center">
                                    <input type="checkbox" name="dropship_enabled" value="1" @checked((bool) old('dropship_enabled'))>
                                    Kirim sebagai dropship (nama pengirim diganti, harga disembunyikan dari paket)
                                </label>
                                <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-top:10px">
                                    <div class="sf-field">
                                        <label class="sf-label" for="sf-dropship-name">Nama pengirim</label>
                                        <input class="sf-input" id="sf-dropship-name" type="text" name="dropship_sender_name"
                                               value="{{ old('dropship_sender_name') }}" maxlength="255" placeholder="Nama Anda">
                                    </div>
                                    <div class="sf-field">
                                        <label class="sf-label" for="sf-dropship-store">Toko pengirim (label paket)</label>
                                        <input class="sf-input" id="sf-dropship-store" type="text" name="dropship_sender_store"
                                               value="{{ old('dropship_sender_store') }}" maxlength="255" placeholder="Nama toko Anda">
                                    </div>
                                </div>
                                <label class="sf-small sf-row" style="gap:6px;align-items:center;margin-top:8px">
                                    <input type="checkbox" name="dropship_hide_price" value="1" @checked((bool) old('dropship_hide_price', true))>
                                    Sembunyikan harga dari paket
                                </label>

                                <hr class="sf-divider" style="margin:16px 0">

                                <h3 class="sf-footer__title" style="font-size:.95rem">Gift / kado (opsional)</h3>
                                <label class="sf-small sf-row" style="gap:6px;align-items:center">
                                    <input type="checkbox" name="gift_wrap" value="1" @checked((bool) old('gift_wrap'))>
                                    Bungkus kado (biaya resmi masuk grand total)
                                </label>
                                <div class="sf-field" style="margin-top:10px">
                                    <label class="sf-label" for="sf-gift-message">Kartu ucapan</label>
                                    <textarea class="sf-textarea" id="sf-gift-message" name="gift_message" rows="2" maxlength="500"
                                              placeholder="Tulis ucapan untuk penerima">{{ old('gift_message') }}</textarea>
                                    @error('gift_message')<span class="sf-error">{{ $message }}</span>@enderror
                                </div>
                                <p class="sf-tiny sf-muted sf-mb-0">Produk pre-order hanya menagih uang muka (DP) saat checkout; sisa dilunasi sebelum pengiriman.</p>
                            </div>
                        </section>
                    </div>

                    <aside class="sf-panel" style="position:sticky;top:calc(var(--sf-header-h) + 12px)" aria-labelledby="sf-checkout-summary">
                        <h2 class="sf-footer__title" id="sf-checkout-summary">Ringkasan Pesanan</h2>

                        <div class="sf-stack" style="gap:14px">
                            @foreach ($groups as $group)
                                <div>
                                    <p class="sf-small sf-bold sf-mb-0">{{ $group['shop']?->name ?? 'Toko' }}</p>
                                    @foreach ($group['items'] as $item)
                                        <p class="sf-small sf-muted sf-mb-0 sf-row sf-row--between" style="gap:10px">
                                            <span class="sf-clamp-2">{{ $item->product?->name ?? 'Produk' }} &times; {{ (int) $item->quantity }}</span>
                                            <span class="sf-nowrap">{{ \App\Support\Currency::format((float) $item->price * (int) $item->quantity) }}</span>
                                        </p>
                                    @endforeach
                                    <p class="sf-small sf-mb-0 sf-row sf-row--between" style="gap:10px">
                                        <span>Subtotal toko</span>
                                        <span class="sf-bold">{{ \App\Support\Currency::format($group['subtotal']) }}</span>
                                    </p>
                                </div>
                            @endforeach
                        </div>

                        <hr class="sf-divider">

                        <div class="sf-summary">
                            <div class="sf-summary__row">
                                <span class="sf-summary__label">Total item</span>
                                <span>{{ \App\Support\Currency::number($itemCount) }}</span>
                            </div>
                            <div class="sf-summary__row">
                                <span class="sf-summary__label">Jumlah toko</span>
                                <span>{{ \App\Support\Currency::number($groups->count()) }}</span>
                            </div>
                            <div class="sf-summary__row">
                                <span class="sf-summary__label">Ongkos kirim &amp; pajak</span>
                                <span class="sf-muted">Dihitung setelah konfirmasi</span>
                            </div>
                            <div class="sf-summary__row sf-summary__row--total">
                                <span>Subtotal</span>
                                <span>{{ \App\Support\Currency::format($total) }}</span>
                            </div>
                            @if (! empty($displayTotal))
                                <div class="sf-summary__row">
                                    <span class="sf-summary__label"> ≈ {{ $displayCurrency }} (display / tampilan)</span>
                                    <span class="sf-bold">{{ $displayTotal['formatted'] }}</span>
                                </div>
                                <p class="sf-tiny sf-muted sf-mb-0">Charge tetap IDR / Charge stays in IDR.</p>
                            @endif
                        </div>

                        <button type="submit" class="sf-btn sf-btn--primary sf-btn--block sf-btn--lg" style="margin-top:18px"
                                @disabled($paymentGateways->isEmpty())>
                            <x-storefront.icon name="wallet" :size="18" /> Bayar sekarang
                        </button>

                        <a href="{{ route('cart.index') }}" class="sf-btn sf-btn--ghost sf-btn--block" style="margin-top:8px">
                            Kembali ke keranjang
                        </a>

                        <p class="sf-tiny sf-muted sf-mb-0" style="margin-top:14px">
                            Dengan menekan tombol bayar, Anda menyetujui
                            <a href="{{ route('page.terms') }}">syarat &amp; ketentuan</a> yang berlaku.
                        </p>
                    </aside>
                </form>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (function () {
            var endpoint = @json(route('checkout.shipping-cost'));
            var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

            function destination() {
                var checked = document.querySelector('input[name="address_id"]:checked');
                if (checked) return checked.dataset.destination || '';
                var field = document.getElementById('sf-new-destination');
                return field ? field.value.trim() : '';
            }

            document.querySelectorAll('[data-shipping-check]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var box = button.closest('[data-shipping-shop]');
                    if (!box) return;

                    var shopId = box.dataset.shippingShop;
                    var provider = box.querySelector('[data-shipping-field="provider_id"]');
                    var courier = box.querySelector('[data-shipping-field="courier"]');
                    var status = box.querySelector('[data-shipping-status]');
                    var list = box.querySelector('[data-shipping-rates]');

                    if (!provider || !provider.value) {
                        status.textContent = 'Pilih penyedia pengiriman terlebih dahulu.';
                        return;
                    }

                    var target = destination();
                    if (!target) {
                        status.textContent = 'Lengkapi ID tujuan pengiriman terlebih dahulu.';
                        return;
                    }

                    button.disabled = true;
                    status.textContent = 'Menghitung ongkos kirim…';

                    fetch(endpoint, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', Accept: 'application/json' },
                        body: JSON.stringify({ shop_id: shopId, destination: target, courier: courier ? courier.value : '', provider_id: provider.value })
                    })
                        .then(function (response) { return response.json(); })
                        .then(function (json) {
                            list.innerHTML = '';
                            if (!json.success) {
                                status.textContent = json.message || 'Layanan ongkos kirim sedang tidak tersedia.';
                                return;
                            }
                            status.textContent = (json.rates || []).length + ' layanan ditemukan';
                            (json.rates || []).forEach(function (rate) {
                                var item = document.createElement('li');
                                item.className = 'sf-shipbox';
                                var label = document.createElement('label');
                                label.className = 'sf-row';
                                label.style.gap = '10px';
                                label.style.cursor = 'pointer';

                                var radio = document.createElement('input');
                                radio.type = 'radio';
                                radio.name = 'sf-rate-' + shopId;
                                radio.style.width = '17px';
                                radio.style.height = '17px';
                                radio.style.accentColor = 'var(--sf-brand)';

                                var text = document.createElement('span');
                                text.className = 'sf-small';
                                text.style.flex = '1';
                                text.textContent = rate.service + ' · ' + rate.courier + (rate.etd ? ' (' + rate.etd + ')' : '');

                                var cost = document.createElement('span');
                                cost.className = 'sf-bold';
                                cost.textContent = rate.cost;

                                label.appendChild(radio);
                                label.appendChild(text);
                                label.appendChild(cost);
                                item.appendChild(label);

                                radio.addEventListener('change', function () {
                                    var service = box.querySelector('[data-shipping-field="service"]');
                                    if (service) service.value = rate.service;
                                    var courierField = box.querySelector('[data-shipping-field="courier"]');
                                    if (courierField) courierField.value = rate.courier;
                                });

                                list.appendChild(item);
                            });
                        })
                        .catch(function () { status.textContent = 'Gagal menghitung ongkos kirim.'; })
                        .finally(function () { button.disabled = false; });
                });
            });
        })();
    </script>
@endpush
