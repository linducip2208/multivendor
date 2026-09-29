@props(['title' => null, 'subtitle' => null, 'level' => 'h2'])

@php
    $level = in_array($level, ['h1', 'h2', 'h3', 'h4'], true) ? $level : 'h2';
@endphp

@if ($title || $subtitle || ! \Illuminate\Support\Str::of((string) $slot)->trim()->isEmpty())
    <div {{ $attributes->merge(['class' => 'd-flex flex-wrap align-items-start justify-content-between gap-3 mb-3']) }}>
        <div>
            @if ($title)
                <{{ $level }} class="admin-page-title mb-0">{{ $title }}</{{ $level }}>
            @endif
            @if ($subtitle)
                <p class="text-secondary mb-0 mt-1">{{ $subtitle }}</p>
            @endif
            @if (! $title && ! $subtitle && ! \Illuminate\Support\Str::of((string) $slot)->trim()->isEmpty())
                <{{ $level }} class="admin-page-title mb-0">{{ $slot }}</{{ $level }}>
            @endif
        </div>
        @isset($actions)
            <div class="admin-page-actions d-flex flex-wrap gap-2">{{ $actions }}</div>
        @endisset
    </div>
@endif
