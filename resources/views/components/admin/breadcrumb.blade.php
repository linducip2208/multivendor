@props(['items' => []])

@if (is_string($items) && trim($items) !== '')
    <nav aria-label="Breadcrumb" class="admin-breadcrumb mb-2">
        {!! $items !!}
    </nav>
@else
@php
    $crumbs = [];
    foreach ((array) $items as $item) {
        if (is_string($item)) {
            $crumbs[] = ['label' => $item, 'href' => null];
            continue;
        }
        if (is_array($item) && ($item['label'] ?? '') !== '') {
            $crumbs[] = ['label' => (string) $item['label'], 'href' => $item['href'] ?? null];
        }
    }
@endphp

@if (count($crumbs) > 1)
    <nav aria-label="Breadcrumb" class="admin-breadcrumb mb-2">
        <ol class="breadcrumb mb-0">
            @foreach ($crumbs as $index => $crumb)
                @php $isLast = $index === count($crumbs) - 1; @endphp
                <li class="breadcrumb-item {{ $isLast ? 'active' : '' }}" @if ($isLast) aria-current="page" @endif>
                    @if ($isLast || !$crumb['href'])
                        {{ $crumb['label'] }}
                    @else
                        <a href="{{ $crumb['href'] }}">{{ $crumb['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@elseif (count($crumbs) === 1)
    <nav aria-label="Breadcrumb" class="admin-breadcrumb mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item active" aria-current="page">{{ $crumbs[0]['label'] }}</li>
        </ol>
    </nav>
@endif
@endif
