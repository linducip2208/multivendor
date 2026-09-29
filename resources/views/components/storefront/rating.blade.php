@props([
    'rating' => 0,
    'count' => null,
    'size' => 13,
    'showValue' => true,
])

@php
    $value = max(0, min(5, (float) $rating));
    $full = (int) floor($value);
@endphp

<span {{ $attributes->merge(['class' => 'sf-rating']) }}
      @if (isset($label)) aria-label="{{ $label ?? 'Rating '.$value.' dari 5' }}" @endif>
    <span class="sf-rating__stars" aria-hidden="true">
        @for ($i = 1; $i <= 5; $i++)
            <x-storefront.icon
                name="star"
                :size="$size"
                :stroke="0"
                style="color:{{ $i <= $full ? '#f59e0b' : ($i === $full + 1 && $value > $full ? '#f59e0b99' : 'var(--sf-text-subtle)') }}"
            />
        @endfor
    </span>
    @if ($showValue && $value > 0)
        <span class="sf-bold" style="color:var(--sf-text)">{{ \App\Support\Currency::number($value, 1) }}</span>
    @endif
    @if ($count !== null)
        <span class="sf-rating__count">({{ \App\Support\Currency::number($count) }})</span>
    @endif
</span>
