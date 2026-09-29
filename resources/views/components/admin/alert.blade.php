@props([
    'type' => 'info',
    'title' => null,
    'dismissible' => true,
    'icon' => null,
])

@php
    $type = in_array($type, ['success', 'danger', 'warning', 'info', 'primary'], true) ? $type : 'info';
    $icon = $icon ?: ($type === 'success' ? 'check-circle' : ($type === 'danger' || $type === 'warning' ? 'alert-triangle' : 'info'));
@endphp

<div
    {{ $attributes->merge(['class' => 'alert alert-'.$type.' '.($dismissible ? 'alert-dismissible pe-4' : '')]) }}
    role="alert"
>
    @if ($dismissible)
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    @endif
    <div class="d-flex">
        <span class="d-inline-flex me-2"><x-admin.icon :name="$icon" :size="20" /></span>
        <div>
            @if ($title)
                <h4 class="alert-title mb-1">{{ $title }}</h4>
            @endif
            <div class="text-secondary">{{ $slot }}</div>
        </div>
    </div>
</div>
