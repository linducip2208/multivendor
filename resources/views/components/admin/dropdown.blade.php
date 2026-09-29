@props([
    'label' => null,
    'variant' => 'outline-secondary',
    'icon' => null,
    'align' => 'end',
    'id' => null,
    'size' => null,
])

@php
    $variant = in_array($variant, ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark', 'outline-primary', 'outline-secondary', 'outline-success', 'outline-danger', 'outline-warning', 'link', 'ghost-light'], true)
        ? $variant
        : 'outline-secondary';
    $align = in_array($align, ['start', 'end'], true) ? $align : 'end';
    $menuId = $id ?: 'dropdown-'.substr(md5((string) $label.'|'.uniqid('', true)), 0, 8);
    $sizeClass = $size !== null ? ' btn-'.$size : '';
@endphp

<div class="dropdown admin-dropdown">
    <button
        class="btn {{ $variant }}{{ $sizeClass }}"
        type="button"
        data-bs-toggle="dropdown"
        data-bs-auto-close="outside"
        aria-expanded="false"
        aria-haspopup="true"
        id="{{ $menuId }}"
    >
        @if ($icon)
            <x-admin.icon :name="$icon" :size="16" class="{{ $label ? 'me-1' : '' }}" />
        @endif
        @if ($label)
            <span>{{ $label }}</span>
        @endif
    </button>

    <ul class="dropdown-menu dropdown-menu-{{ $align }}" aria-labelledby="{{ $menuId }}">
        {{ $slot }}
    </ul>
</div>
