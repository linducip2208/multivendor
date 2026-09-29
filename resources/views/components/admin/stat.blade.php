@props([
    'label' => null,
    'value' => null,
    'icon' => null,
    'color' => 'primary',
    'trend' => null,
    'trendLabel' => null,
    'money' => false,
    'href' => null,
    'hint' => null,
])

@php
    $color = in_array($color, ['primary', 'success', 'warning', 'danger', 'info', 'secondary', 'dark'], true) ? $color : 'primary';

    $display = $value;
    if ($money && (is_numeric($value) || $value === null)) {
        $amount = (float) $value;
        $display = abs($amount) >= 1000
            ? \App\Support\Currency::compact($amount)
            : \App\Support\Currency::format($amount);
    } elseif (is_numeric($value)) {
        $display = \App\Support\Currency::number((float) $value, (float) $value == (int) $value ? 0 : 2);
    }

    $trendDirection = null;
    if ($trend !== null && is_numeric($trend)) {
        $trendDirection = (float) $trend > 0 ? 'up' : ((float) $trend < 0 ? 'down' : 'flat');
    }
@endphp

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'card card-sm admin-card admin-stat h-100']) }}
>
    <div class="card-body d-flex align-items-start justify-content-between gap-3">
        <div class="flex-fill">
            <div class="admin-stat__label mb-1">{{ $label }}</div>
            <div class="admin-stat__value text-break">{{ $display }}</div>
            @if ($trendDirection)
                <div class="small mt-1 d-inline-flex align-items-center gap-1 text-{{ $trendDirection === 'down' ? 'danger' : ($trendDirection === 'up' ? 'success' : 'secondary') }}">
                    <x-admin.icon :name="$trendDirection === 'up' ? 'trending-up' : ($trendDirection === 'down' ? 'arrow-down' : 'minus')" :size="14" />
                    <span>{{ number_format(abs((float) $trend), 1) }}%</span>
                    @if ($trendLabel)
                        <span class="text-secondary">{{ $trendLabel }}</span>
                    @endif
                </div>
            @elseif ($hint)
                <div class="small text-secondary mt-1">{{ $hint }}</div>
            @endif
        </div>
        @if ($icon)
            <span class="avatar avatar-sm text-{{ $color }} bg-{{ $color }}-lt" aria-hidden="true">
                <x-admin.icon :name="$icon" :size="18" />
            </span>
        @endif
    </div>
</{{ $tag }}>
