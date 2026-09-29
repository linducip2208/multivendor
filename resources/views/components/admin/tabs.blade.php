@props([
    'tabs' => [],
    'pills' => false,
    'align' => null,
])

@php
    $entries = [];
    foreach ((array) $tabs as $key => $tab) {
        if (is_string($tab)) {
            $entries[] = ['label' => $tab, 'href' => '#', 'active' => false, 'icon' => null, 'count' => null];
            continue;
        }
        if (! is_array($tab)) {
            continue;
        }
        $entries[] = [
            'label' => (string) ($tab['label'] ?? ''),
            'href' => (string) ($tab['href'] ?? '#'),
            'active' => (bool) ($tab['active'] ?? false),
            'icon' => $tab['icon'] ?? null,
            'count' => $tab['count'] ?? null,
        ];
    }

    $navClass = 'nav nav-tabs';
    if ($pills) {
        $navClass = 'nav nav-pills';
    }
    if ($align !== null) {
        $navClass .= ' nav-'.$align;
    }
@endphp

@if ($entries !== [])
    <ul class="{{ $navClass }}" role="tablist">
        @foreach ($entries as $entry)
            <li class="nav-item" role="presentation">
                <a
                    href="{{ $entry['href'] }}"
                    class="nav-link {{ $entry['active'] ? 'active' : '' }}"
                    @if ($entry['active']) aria-current="page" @endif
                    @if (! $entry['active']) role="tab" aria-selected="false" @else role="tab" aria-selected="true" @endif
                >
                    @if ($entry['icon'])
                        <x-admin.icon :name="$entry['icon']" :size="16" class="me-1" />
                    @endif
                    {{ $entry['label'] }}
                    @if ($entry['count'] !== null)
                        <span class="badge bg-secondary-lt text-secondary ms-1">{{ $entry['count'] }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
@endif
