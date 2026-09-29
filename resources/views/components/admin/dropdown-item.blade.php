@props([
    'href' => null,
    'icon' => null,
    'variant' => null,
    'label' => null,
    'disabled' => false,
    'active' => false,
])

@php
    $destructive = in_array($variant, ['danger', 'destructive', 'warning'], true);
    $class = 'dropdown-item'.($destructive ? ' dropdown-item-destructive text-danger' : '').($active ? ' active' : '').($disabled ? ' disabled' : '');
@endphp

<li>
    @if ($href && ! $disabled)
        <a
            href="{{ $href }}"
            {{ $attributes->merge(['class' => $class]) }}
            @if ($active) aria-current="true" @endif
        >
            @if ($icon)
                <x-admin.icon :name="$icon" :size="16" class="me-2" />
            @endif
            <span>{{ $label ?? $slot }}</span>
        </a>
    @else
        <span {{ $attributes->merge(['class' => $class]) }}>
            @if ($icon)
                <x-admin.icon :name="$icon" :size="16" class="me-2" />
            @endif
            <span>{{ $label ?? $slot }}</span>
        </span>
    @endif
</li>
