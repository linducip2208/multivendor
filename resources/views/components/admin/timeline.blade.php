@props(['items' => [], 'compact' => false])

@php
    $states = ['done', 'current', 'pending'];
    $entries = [];
    foreach ((array) $items as $item) {
        if (! is_array($item)) {
            continue;
        }
        $entries[] = [
            'title' => (string) ($item['title'] ?? ''),
            'meta' => $item['meta'] ?? null,
            'body' => $item['body'] ?? null,
            'state' => in_array($item['state'] ?? 'pending', $states, true) ? $item['state'] : 'pending',
        ];
    }
@endphp

@if ($entries !== [])
    <ul class="admin-timeline list-unstyled mb-0 {{ $compact ? 'admin-timeline--compact' : '' }}">
        @foreach ($entries as $entry)
            @php
                $state = $entry['state'];
                $dotIcon = $state === 'done' ? 'check' : ($state === 'current' ? 'clock' : 'minus');
                $tone = $state === 'done' ? 'success' : ($state === 'current' ? 'primary' : 'secondary');
            @endphp
            <li class="admin-timeline__item d-flex gap-3 {{ $loop->last ? '' : 'pb-4' }}" data-state="{{ $state }}">
                <div class="flex-shrink-0">
                    <span class="admin-timeline__dot text-{{ $tone }} bg-{{ $tone }}-lt" aria-hidden="true">
                        <x-admin.icon :name="$dotIcon" :size="14" />
                    </span>
                </div>
                <div class="flex-fill">
                    <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2">
                        <span class="fw-semibold {{ $state === 'pending' ? 'text-secondary' : '' }}">{{ $entry['title'] }}</span>
                        @if ($entry['meta'] !== null && $entry['meta'] !== '')
                            <span class="small text-secondary">{{ $entry['meta'] }}</span>
                        @endif
                    </div>
                    @if ($entry['body'])
                        <div class="text-secondary small mt-1">{{ $entry['body'] }}</div>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
@endif
