@props(['shops' => []])

@if (count($shops) > 0)
    <div {{ $attributes->merge(['class' => 'sf-stores']) }}>
        @foreach ($shops as $shop)
            <a href="{{ route('shop.show', $shop->slug) }}" class="sf-store">
                @if ($shop->logo_url)
                    <img src="{{ $shop->logo_url }}" alt="Logo {{ $shop->name }}" class="sf-store__logo" loading="lazy" width="52" height="52" decoding="async">
                @else
                    <span class="sf-store__logo sf-row" style="justify-content:center">
                        <x-storefront.icon name="store" :size="22" />
                    </span>
                @endif
                <span style="min-width:0;flex:1">
                    <span class="sf-store__name sf-truncate" style="display:block">{{ $shop->name }}</span>
                    <span class="sf-store__meta">
                        @if (isset($shop->rating) && $shop->rating > 0)
                            <x-storefront.icon name="star" :size="11" :stroke="0" style="color:#f59e0b" />
                            {{ \App\Support\Currency::number($shop->rating, 1) }}
                        @endif
                        @if (isset($shop->products_count))
                            · {{ \App\Support\Currency::number($shop->products_count) }} produk
                        @endif
                        @if (($shop->city ?? null))
                            · {{ $shop->city }}
                        @endif
                    </span>
                </span>
            </a>
        @endforeach
    </div>
@endif
