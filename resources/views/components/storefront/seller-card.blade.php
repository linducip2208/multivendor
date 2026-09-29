@props(['shop' => null])

@if ($shop)
    <div {{ $attributes->merge(['class' => 'sf-panel']) }}>
        <div class="sf-row" style="gap:14px;align-items:flex-start">
            @if ($shop->logo_url ?? null)
                <img src="{{ $shop->logo_url }}" alt="Logo {{ $shop->name }}" class="sf-store__logo" loading="lazy" width="52" height="52">
            @else
                <span class="sf-store__logo sf-row" style="justify-content:center;background:var(--sf-bg-muted)" aria-hidden="true">
                    <x-storefront.icon name="store" :size="22" />
                </span>
            @endif

            <div style="min-width:0;flex:1">
                <p class="sf-tiny sf-bold sf-muted sf-mb-0" style="text-transform:uppercase;letter-spacing:.08em">Dijual oleh</p>
                <p class="sf-mb-0">
                    <a href="{{ route('shop.show', $shop->slug) }}" class="sf-bold" style="color:var(--sf-text)">{{ $shop->name }}</a>
                </p>

                <div class="sf-row sf-row--wrap sf-small sf-muted" style="gap:10px;margin-top:4px">
                    @if ((float) $shop->rating_average > 0)
                        <span class="sf-rating">
                            <span class="sf-rating__stars" aria-hidden="true">
                                <x-storefront.icon name="star" :size="12" :stroke="0" style="color:#f59e0b" />
                            </span>
                            <span class="sf-bold" style="color:var(--sf-text)">{{ \App\Support\Currency::number($shop->rating_average, 1) }}</span>
                            <span class="sf-rating__count">({{ \App\Support\Currency::number($shop->rating_count) }})</span>
                        </span>
                    @endif
                    @if ($shop->city)
                        <span class="sf-row" style="gap:4px">
                            <x-storefront.icon name="map-pin" :size="13" />
                            <span class="sf-truncate">{{ $shop->city }}</span>
                        </span>
                    @endif
                    @if ($shop->product_count > 0)
                        <span class="sf-row" style="gap:4px">
                            <x-storefront.icon name="box" :size="13" />
                            {{ \App\Support\Currency::number($shop->product_count) }} produk
                        </span>
                    @endif
                </div>

                @if ($shop->vacation_mode)
                    <p class="sf-tiny sf-mb-0" style="margin-top:8px;color:var(--sf-warning)">
                        {{ $shop->vacation_message ?: 'Toko sedang libur.' }}
                    </p>
                @endif
            </div>
        </div>

        <a href="{{ route('shop.show', $shop->slug) }}" class="sf-btn sf-btn--outline sf-btn--sm sf-btn--block" style="margin-top:14px">
            Kunjungi toko
        </a>
    </div>
@endif
