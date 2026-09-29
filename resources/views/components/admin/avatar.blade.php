@props([
    'name' => null,
    'src' => null,
    'size' => 'md',
    'status' => 'none',
    'label' => null,
])

@php
    $name = (string) ($name ?: '');
    $initials = '';
    foreach (preg_split('/\s+/', trim($name)) ?: [] as $part) {
        if ($part === '') {
            continue;
        }
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        if (mb_strlen($initials) >= 2) {
            break;
        }
    }

    $size = in_array($size, ['xs', 'sm', 'md', 'lg', 'xl'], true) ? $size : 'md';
    $status = in_array($status, ['online', 'offline', 'none'], true) ? $status : 'none';
    $statusTone = $status === 'online' ? 'bg-green' : ($status === 'offline' ? 'bg-secondary' : '');
@endphp

<span {{ $attributes->merge(['class' => 'admin-avatar avatar avatar-'.$size.' position-relative d-inline-flex']) }}>
    @if ($src)
        <img src="{{ $src }}" alt="{{ $label ?? $name }}" loading="lazy">
    @else
        <span class="admin-avatar__initials" aria-hidden="true">{{ $initials !== '' ? $initials : '?' }}</span>
        <span class="visually-hidden">{{ $name }}</span>
    @endif
    @if ($status !== 'none')
        <span class="admin-avatar__status {{ $statusTone }}" title="{{ $status === 'online' ? 'Online' : 'Offline' }}"></span>
    @endif
</span>
