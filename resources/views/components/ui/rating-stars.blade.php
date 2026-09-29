@props([
    'rating' => 0,
    'max' => 5,
    'size' => 16,
])

{{--
    UI rating-stars: Tabler star icons via x-admin.icon (no FontAwesome).
    Usage: <x-ui.rating-stars :rating="$review->rating" />
--}}

@php
    $value = max(0, min((int) $max, (int) round((float) $rating)));
    $maxStars = max(1, (int) $max);
@endphp

<span {{ $attributes->merge(['class' => 'text-warning d-inline-flex align-items-center gap-1']) }} role="img" aria-label="Rating {{ $value }} dari {{ $maxStars }}">
    @for ($i = 1; $i <= $maxStars; $i++)
        <x-admin.icon
            name="star"
            :size="$size"
            class="{{ $i <= $value ? 'text-warning' : 'text-muted opacity-25' }}"
        />
    @endfor
</span>
