@props(['items' => [], 'limit' => null, 'empty' => 'Belum ada aktivitas'])

@php
    $entries = [];
    foreach ((array) $items as $item) {
        if (! is_array($item)) {
            continue;
        }
        $entries[] = [
            'actor' => (string) ($item['actor'] ?? ''),
            'action' => (string) ($item['action'] ?? ''),
            'at' => $item['at'] ?? null,
            'icon' => (string) ($item['icon'] ?? 'activity'),
            'url' => $item['url'] ?? null,
        ];
    }

    if ($limit !== null) {
        $entries = array_slice($entries, 0, max(1, (int) $limit));
    }
@endphp

@if ($entries !== [])
    <ul class="admin-activity list-unstyled mb-0">
        @foreach ($entries as $entry)
            <li class="admin-activity__item d-flex gap-3 {{ $loop->last ? '' : 'py-3 border-bottom' }}">
                <span class="avatar avatar-sm text-primary bg-primary-lt flex-shrink-0" aria-hidden="true">
                    <x-admin.icon :name="$entry['icon']" :size="16" />
                </span>
                <div class="flex-fill">
                    <div class="small">
                        @if ($entry['actor'] !== '')
                            <span class="fw-semibold">{{ $entry['actor'] }}</span>
                        @endif
                        <span class="text-secondary">{{ $entry['action'] }}</span>
                    </div>
                    @if ($entry['at'] !== null && $entry['at'] !== '')
                        <div class="text-secondary admin-activity__at">{{ $entry['at'] }}</div>
                    @endif
                </div>
                @if ($entry['url'])
                    <a href="{{ $entry['url'] }}" class="btn btn-ghost-light btn-sm" aria-label="Buka detail">
                        <x-admin.icon name="chevron-right" :size="16" />
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
@else
    <x-admin.empty-state icon="activity" :text="$empty" compact />
@endif
