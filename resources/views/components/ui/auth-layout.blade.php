@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'wide' => false,
])

{{--
    UI auth-layout: centered Tabler card layout for login / register.
    Usage:
        <x-ui.auth-layout title="Masuk" subtitle="...">
            <form>...</form>
        </x-ui.auth-layout>
    Renders only the centered card; pages may wrap it in their own layout.
--}}

<div class="page page-center">
    <div class="container {{ $wide ? '' : 'container-tight' }} py-4">
        <div class="card card-md">
            <div class="card-body">
                @if ($title || $icon)
                    <div class="d-flex align-items-center gap-2 mb-1">
                        @if ($icon)
                            <span class="text-primary d-inline-flex"><x-admin.icon :name="$icon" :size="22" /></span>
                        @endif
                        @if ($title)
                            <h2 class="card-title mb-0">{{ $title }}</h2>
                        @endif
                    </div>
                @endif
                @if ($subtitle)
                    <p class="text-secondary mb-3">{{ $subtitle }}</p>
                @endif
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
