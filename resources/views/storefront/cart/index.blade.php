@extends('layouts.storefront')

@section('content')
    @php
        $groups = collect($shops);
        $lineCount = (int) $groups->sum(fn (array $group) => $group['items']->sum('quantity'));
        $freeShippingThreshold = (float) \App\Models\SystemSetting::get('free_shipping_threshold', 0);
        $remainingForFreeShipping = max(0, $freeShippingThreshold - (float) $total);
        $progress = $freeShippingThreshold > 0
            ? min(100, max(0, ((float) $total / $freeShippingThreshold) * 100))
            : 100;
    @endphp

    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Keranjang</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-cart-title">
        <div class="sf-container">
            <div class="sf-section-head">
                <h1 class="sf-section-head__title" id="sf-cart-title">Keranjang Belanja</h1>
                @if ($groups->isNotEmpty())
                    <a href="{{ route('products.index') }}" class="sf-section-head__link">
                        <x-storefront.icon name="chevron-left" :size="16" /> Lanjut belanja
                    </a>
                @endif
            </div>

            @if ($groups->isEmpty())
                <x-storefront.empty
                    title="Keranjang Anda masih kosong"
                    text="Telusuri katalog dan temukan produk yang Anda sukai. Item yang Anda pilih akan muncul di sini."
                    :href="route('products.index')"
                    label="Mulai belanja"
                    icon="cart"
                />
            @else
                <div class="sf-cartlayout">
                    <div class="sf-stack" style="gap:20px">
                        @if ($freeShippingThreshold > 0)
                            <div class="sf-panel" aria-live="polite">
                                @if ($remainingForFreeShipping > 0)
                                    <p class="sf-small sf-mb-0">
                                        Belanja <span class="sf-bold" style="color:var(--sf-brand)">{{ \App\Support\Currency::format($remainingForFreeShipping) }}</span>
                                        lagi untuk mendapatkan gratis ongkos kirim.
                                    </p>
                                @else
                                    <p class="sf-small sf-mb-0 sf-row" style="gap:6px;color:var(--sf-success)">
                                        <x-storefront.icon name="check-circle" :size="15" />
                                        Selamat, pesanan Anda memenuhi syarat gratis ongkir kirim.
                                    </p>
                                @endif
                                <span class="sf-rating-bar__track" style="display:block;margin-top:10px" role="img"
                                      aria-label="Progres gratis ongkir kirim {{ round($progress) }} persen">
                                    <span class="sf-rating-bar__fill" style="display:block;width:{{ round($progress) }}%;background:var(--sf-success)"></span>
                                </span>
                            </div>
                        @endif

                        @foreach ($groups as $group)
                            <section class="sf-card" aria-labelledby="sf-cart-shop-{{ $group['shop']?->id ?? 'x' }}">
                                <div class="sf-card__body">
                                    <div class="sf-row sf-row--between sf-row--wrap" style="gap:10px">
                                        <h2 class="sf-mb-0" id="sf-cart-shop-{{ $group['shop']?->id ?? 'x' }}" style="font-size:1rem">
                                            @if ($group['shop'])
                                                <a href="{{ route('shop.show', $group['shop']->slug) }}" class="sf-row" style="gap:7px;color:var(--sf-text)">
                                                    <x-storefront.icon name="store" :size="16" />
                                                    <span class="sf-clamp-2">{{ $group['shop']->name }}</span>
                                                </a>
                                            @else
                                                Toko
                                            @endif
                                        </h2>
                                        <span class="sf-small sf-muted sf-nowrap">
                                            Subtotal <span class="sf-bold" style="color:var(--sf-text)">{{ \App\Support\Currency::format($group['subtotal']) }}</span>
                                        </span>
                                    </div>
                                </div>

                                <div class="sf-card__body" style="padding-top:0">
                                    @foreach ($group['items'] as $item)
                                        <div class="sf-cartline">
                                            <a href="{{ $item->product?->storefront_url ?? route('products.index') }}" tabindex="-1" aria-hidden="true">
                                                @if ($item->product?->thumbnail_url)
                                                    <img src="{{ $item->product->thumbnail_url }}" alt="" class="sf-cartline__img"
                                                         loading="lazy" width="84" height="84" decoding="async">
                                                @else
                                                    <span class="sf-cartline__img sf-row" style="justify-content:center;color:var(--sf-text-subtle)">
                                                        <x-storefront.icon name="image" :size="24" />
                                                    </span>
                                                @endif
                                            </a>

                                            <div style="min-width:0">
                                                <a href="{{ $item->product?->storefront_url ?? route('products.index') }}" class="sf-cartline__name sf-clamp-2">
                                                    {{ $item->product?->name ?? 'Produk tidak tersedia' }}
                                                </a>
                                                <p class="sf-cartline__meta sf-mb-0">
                                                    @if ($item->variant)
                                                        Varian: {{ $item->variant->variant ?? ($item->product_variant_id ?: '-') }}
                                                    @endif
                                                    @if ($item->product?->is_out_of_stock)
                                                        <span class="sf-badge sf-badge--danger" style="margin-left:6px">Stok habis</span>
                                                    @endif
                                                    @if ($item->product && method_exists($item->product, 'isPreorder') && $item->product->isPreorder())
                                                        <span class="sf-badge sf-badge--brand" style="margin-left:6px">Pre-order (DP saat checkout)</span>
                                                    @endif
                                                </p>
                                                <p class="sf-small sf-muted sf-mb-0" style="margin-top:4px">
                                                    {{ \App\Support\Currency::format($item->price) }}
                                                </p>

                                                <form method="POST" action="{{ route('cart.update', $item) }}" class="sf-row" style="gap:8px;margin-top:8px">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="sf-qty" data-sf-qty>
                                                        <button type="button" class="sf-qty__btn" data-qty-dec aria-label="Kurangi jumlah">&minus;</button>
                                                        <label class="sf-sr-only" for="sf-cart-qty-{{ $item->id }}">Jumlah {{ $item->product?->name ?? 'produk' }}</label>
                                                        <input class="sf-qty__input" id="sf-cart-qty-{{ $item->id }}" type="number" name="quantity"
                                                               value="{{ (int) $item->quantity }}" min="1"
                                                               @if ($item->product?->max_qty) max="{{ (int) $item->product->max_qty }}" @endif
                                                               inputmode="numeric">
                                                        <button type="button" class="sf-qty__btn" data-qty-inc aria-label="Tambah jumlah">+</button>
                                                    </div>
                                                    <button type="submit" class="sf-btn sf-btn--outline sf-btn--sm">Perbarui</button>
                                                </form>
                                                <form method="POST" action="{{ route('cart.update', $item) }}" style="margin-top:8px">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="action" value="save_for_later">
                                                    <button type="submit" class="sf-btn sf-btn--ghost sf-btn--sm">Simpan untuk nanti</button>
                                                </form>
                                            </div>

                                            <div class="sf-cartline__right">
                                                <span class="sf-bold sf-nowrap">{{ \App\Support\Currency::format((float) $item->price * (int) $item->quantity) }}</span>
                                                <form method="POST" action="{{ route('cart.remove', $item) }}"
                                                      data-sf-confirm="Hapus produk ini dari keranjang?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="sf-iconbtn" aria-label="Hapus {{ $item->product?->name ?? 'produk' }} dari keranjang">
                                                        <x-storefront.icon name="trash" :size="17" />
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="sf-card__foot sf-row sf-row--between sf-row--wrap" style="gap:10px">
                                    <a href="{{ route('shop.show', $group['shop']->slug) }}" class="sf-small">
                                        Lihat toko lainnya
                                    </a>
                                    <span class="sf-small sf-muted">
                                        {{ \App\Support\Currency::number($group['items']->sum('quantity')) }} item
                                    </span>
                                </div>
                            </section>
                        @endforeach

                        <form method="POST" action="{{ route('cart.clear') }}" data-sf-confirm="Kosongkan seluruh keranjang?">
                            @csrf
                            <button type="submit" class="sf-btn sf-btn--ghost sf-btn--sm">
                                <x-storefront.icon name="trash" :size="15" /> Kosongkan keranjang
                            </button>
                        </form>
                    </div>

                    <aside class="sf-panel" style="position:sticky;top:calc(var(--sf-header-h) + 12px)" aria-labelledby="sf-cart-summary-title">
                        <h2 class="sf-footer__title" id="sf-cart-summary-title">Ringkasan Belanja / Order summary</h2>

                        {{-- ADITIF global-checkout: selector currency (display saja, charge tetap IDR). --}}
                        <form method="GET" action="{{ route('cart.index') }}" class="sf-row sf-row--wrap" style="gap:8px;align-items:flex-end;margin-bottom:12px">
                            <div class="sf-field" style="min-width:160px;flex:1">
                                <label class="sf-label form-label" for="sf-cart-currency">Currency / Mata uang</label>
                                <select class="sf-select form-select" id="sf-cart-currency" name="currency" onchange="this.form.submit()">
                                    @foreach (($currencies ?? collect()) as $cur)
                                        <option value="{{ $cur->code }}" @selected(($displayCurrency ?? 'IDR') === $cur->code)>
                                            {{ $cur->code }} ({{ $cur->symbol ?? $cur->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </form>
                        @if (! empty($displayTotal))
                            <p class="sf-small sf-muted sf-mb-0" role="status">
                                ≈ <span class="sf-bold">{{ $displayTotal['formatted'] }}</span> {{ $displayCurrency }}
                                <span class="sf-tiny">(tampilan / display-only — charge tetap IDR · rate 1 {{ $displayCurrency }} = {{ number_format((float) ($displayTotal['rate'] ?? 1), 2, ',', '.') }} IDR)</span>
                            </p>
                        @else
                            <p class="sf-tiny sf-muted sf-mb-0">Charge currency: IDR.</p>
                        @endif

                        <div class="sf-summary">
                            <div class="sf-summary__row">
                                <span class="sf-summary__label">Jumlah item</span>
                                <span class="sf-bold">{{ \App\Support\Currency::number($lineCount) }}</span>
                            </div>
                            <div class="sf-summary__row">
                                <span class="sf-summary__label">Jumlah toko</span>
                                <span class="sf-bold">{{ \App\Support\Currency::number($groups->count()) }}</span>
                            </div>
                            <div class="sf-summary__row">
                                <span class="sf-summary__label">Subtotal produk</span>
                                <span class="sf-bold">{{ \App\Support\Currency::format($total) }}</span>
                            </div>
                            <div class="sf-summary__row">
                                <span class="sf-summary__label">Ongkos kirim</span>
                                <span class="sf-muted">Dihitung saat checkout</span>
                            </div>
                            <div class="sf-summary__row sf-summary__row--total">
                                <span>Total sementara / Subtotal</span>
                                <span>{{ \App\Support\Currency::format($total) }}</span>
                            </div>
                            @if (! empty($displayTotal))
                                <div class="sf-summary__row">
                                    <span class="sf-summary__label">≈ {{ $displayCurrency }} (display)</span>
                                    <span class="sf-bold">{{ $displayTotal['formatted'] }}</span>
                                </div>
                            @endif
                        </div>

                        <a href="{{ route('checkout.index') }}" class="sf-btn sf-btn--primary sf-btn--block sf-btn--lg" style="margin-top:18px">
                            Lanjut ke checkout
                        </a>
                        <a href="{{ route('products.index') }}" class="sf-btn sf-btn--ghost sf-btn--block" style="margin-top:8px">
                            Lanjut belanja
                        </a>

                        <p class="sf-tiny sf-muted sf-mb-0" style="margin-top:14px">
                            Pesanan diproses terpisah untuk setiap toko. Ongkos kirim dan pajak akhir dihitung
                            setelah Anda memilih metode pengiriman pada halaman checkout.
                        </p>
                        @if (! empty($repeatSchedules ?? []))
                            <div class="sf-panel" style="margin-top:14px">
                                <h3 class="sf-footer__title" style="font-size:.9rem">Repeat-order langganan ({{ count($repeatSchedules) }})</h3>
                                <p class="sf-tiny sf-muted">Jadwal aktif membuat draf cart otomatis, bukan order langsung.</p>
                                @foreach ($repeatSchedules as $schedule)
                                    <p class="sf-tiny sf-muted sf-mb-0">
                                        {{ $schedule->product_name ?? 'Produk' }} × {{ (int) $schedule->quantity }} ·
                                        {{ $schedule->frequency }} · berikutnya
                                        {{ $schedule->next_run_at ? \Carbon\Carbon::parse($schedule->next_run_at)->format('d/m/Y') : '-' }}
                                    </p>
                                @endforeach
                            </div>
                        @endif
                        <div class="sf-panel" style="margin-top:14px">
                            <h3 class="sf-footer__title" style="font-size:.9rem">Estimasi ongkir di keranjang</h3>
                            @foreach ($groups as $group)
                                <p class="sf-tiny sf-muted sf-mb-0">
                                    {{ $group['shop']?->name ?? 'Toko' }}: ±{{ number_format((float) ($group['weight'] ?? 0) / 1000, 1) }} kg.
                                    Estimasi pasti tampil setelah pilih tujuan &amp; kurir di checkout (fallback kurir otomatis bila utama gagal).
                                </p>
                            @endforeach
                        </div>
                    </aside>
                </div>

                @if (($saved ?? collect())->isNotEmpty())
                    <section class="sf-card" style="margin-top:20px" aria-labelledby="sf-saved-title">
                        <div class="sf-card__body">
                            <h2 id="sf-saved-title" style="font-size:1rem">Simpan untuk nanti ({{ $saved->count() }} item)</h2>
                            <p class="sf-small sf-muted">Item ini diparkir dan tidak ikut dihitung pada checkout.</p>
                            @foreach ($saved as $item)
                                <div class="sf-row sf-row--between sf-row--wrap" style="gap:8px;padding:8px 0;border-top:1px solid var(--sf-border)">
                                    <span class="sf-small">{{ $item->product?->name ?? 'Produk' }} × {{ (int) $item->quantity }}</span>
                                    <form method="POST" action="{{ route('cart.update', $item) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="action" value="move_to_cart">
                                        <button type="submit" class="sf-btn sf-btn--outline sf-btn--sm">Kembalikan ke keranjang</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endif

            @if ($groups->isEmpty() && ($saved ?? collect())->isNotEmpty())
                <section class="sf-card" style="margin-top:20px" aria-labelledby="sf-saved-title-empty">
                    <div class="sf-card__body">
                        <h2 id="sf-saved-title-empty" style="font-size:1rem">Simpan untuk nanti ({{ $saved->count() }} item)</h2>
                        <p class="sf-small sf-muted">Keranjang aktif kosong. Item yang diparkir tidak ikut checkout.</p>
                        @foreach ($saved as $item)
                            <div class="sf-row sf-row--between sf-row--wrap" style="gap:8px;padding:8px 0;border-top:1px solid var(--sf-border)">
                                <span class="sf-small">{{ $item->product?->name ?? 'Produk' }} × {{ (int) $item->quantity }}</span>
                                <form method="POST" action="{{ route('cart.update', $item) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="action" value="move_to_cart">
                                    <button type="submit" class="sf-btn sf-btn--outline sf-btn--sm">Kembalikan ke keranjang</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </section>
@endsection
