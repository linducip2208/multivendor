@props([
    'icon' => 'inbox',
    'title' => null,
    'text' => null,
    'actionLabel' => null,
    'actionUrl' => null,
    'compact' => false,
])

<div
    {{ $attributes->merge(['class' => 'text-center '.($compact ? 'py-3' : 'py-5')]) }}
>
    <div class="mb-3">
        <span class="avatar avatar-lg text-secondary bg-secondary-lt" aria-hidden="true">
            <x-admin.icon :name="$icon" :size="$compact ? 22 : 32" />
        </span>
    </div>
    @if ($title)
        <p class="fw-semibold mb-1 {{ $compact ? 'mb-1' : 'fs-5' }}">{{ $title }}</p>
    @endif
    @if ($text)
        <p class="text-secondary small mb-3">{{ $text }}</p>
    @endif
    @if ($actionLabel && $actionUrl)
        <a href="{{ $actionUrl }}" class="btn btn-primary btn-sm">
            <x-admin.icon name="plus" :size="16" />
            <span>{{ $actionLabel }}</span>
        </a>
    @endif
    {{ $slot }}
</div>
