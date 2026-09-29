@props([
    'text' => null,
    'color' => 'secondary',
    'pill' => false,
    'dot' => false,
    'icon' => null,
])

@php
    $color = in_array($color, ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'], true) ? $color : 'secondary';
@endphp

<span
    {{ $attributes->merge(['class' => 'badge bg-'.$color.'-lt text-'.$color.' '.($pill ? 'rounded-pill' : '')]) }}
>
    @if ($dot)
        <span class="badge-dot bg-{{ $color }} me-1" aria-hidden="true"></span>
    @endif
    @if ($icon)
        <x-admin.icon :name="$icon" :size="12" class="me-1" />
    @endif
    {{ $text ?? $slot }}
</span>
