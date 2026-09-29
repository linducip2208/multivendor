@props([
    'product' => null,
    'estimate' => null,
])

@php
    $hasEstimate = is_string($estimate) && trim($estimate) !== '';
    $weight = (float) ($product->weight ?? 0);
    $shippedFrom = $product?->shop?->province ?: null;
@endphp

<div {{ $attributes->merge(['class' => 'sf-panel']) }}>
    <h3 class="sf-footer__title" style="margin-bottom:12px">
        <x-storefront.icon name="truck" :size="16" style="display:inline;vertical-align:-3px;margin-right:6px" />
        Pengiriman
    </h3>

    <dl class="sf-summary">
        <div class="sf-summary__row">
            <dt class="sf-summary__label">Perkiraan tiba</dt>
            <dd class="sf-mb-0 sf-bold" style="color:var(--sf-text)">
                {{ $hasEstimate ? $estimate : 'Setelah pembayaran dikonfirmasi' }}
            </dd>
        </div>
        @if ($shippedFrom)
            <div class="sf-summary__row">
                <dt class="sf-summary__label">Dikirim dari</dt>
                <dd class="sf-mb-0 sf-truncate" style="max-width:60%">{{ $shippedFrom }}</dd>
            </div>
        @endif
        @if ($weight > 0)
            <div class="sf-summary__row">
                <dt class="sf-summary__label">Berat</dt>
                <dd class="sf-mb-0">{{ \App\Support\Currency::number($weight, 2) }} kg</dd>
            </div>
        @endif
        @if ($product?->refundable)
            <div class="sf-summary__row">
                <dt class="sf-summary__label">Retur</dt>
                <dd class="sf-mb-0">Bisa retur</dd>
            </div>
        @endif
    </dl>

    <a href="{{ route('page.return') }}" class="sf-small sf-row" style="gap:5px;margin-top:14px">
        <x-storefront.icon name="refresh" :size="14" /> Lihat kebijakan pengiriman &amp; retur
    </a>
</div>
