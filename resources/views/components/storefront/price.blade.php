@props([
    'amount' => 0,
    'compareAt' => null,
    'size' => 'md',
    'showOff' => true,
])

@php
    $now = (float) $amount;
    $was = $compareAt !== null ? (float) $compareAt : null;
    $off = ($was && $was > $now && $was > 0) ? (int) round((($was - $now) / $was) * 100) : 0;
    $scale = ['sm' => '0.95rem', 'md' => '1.05rem', 'lg' => '1.6rem'][$size] ?? '1.05rem';
@endphp

<span {{ $attributes->merge(['class' => 'sf-price']) }}>
    <span class="sf-price__now" style="font-size:{{ $scale }}">{{ \App\Support\Currency::format($now) }}</span>
    @if ($was)
        <span class="sf-price__was">{{ \App\Support\Currency::format($was) }}</span>
    @endif
    @if ($showOff && $off > 0)
        <span class="sf-price__off">-{{ $off }}%</span>
    @endif
</span>
