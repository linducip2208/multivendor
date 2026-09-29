@props([
    'type' => 'success',
    'title' => null,
    'dismissible' => false,
])

@php
    $icon = match ($type) {
        'success' => 'check-circle',
        'error' => 'alert-circle',
        'warning' => 'alert-circle',
        'info' => 'info',
        default => 'info',
    };
@endphp

<div {{ $attributes->merge(['class' => 'sf-alert sf-alert--'.$type]) }} role="{{ $type === 'error' ? 'alert' : 'status' }}">
    <span class="sf-alert__icon"><x-storefront.icon :name="$icon" :size="18" /></span>
    <div class="sf-stack" style="gap:4px;flex:1">
        @if ($title)
            <strong>{{ $title }}</strong>
        @endif
        <div>{{ $slot }}</div>
    </div>
    @if ($dismissible)
        <button type="button" class="sf-iconbtn" data-alert-dismiss aria-label="Tutup">&times;</button>
    @endif
</div>
