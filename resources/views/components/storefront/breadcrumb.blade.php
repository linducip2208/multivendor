@props(['items' => []])

@if (count($items) > 1)
    <nav aria-label="Breadcrumb" class="sf-breadcrumb">
        @foreach ($items as $item)
            @if (! $loop->first)
                <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            @endif
            @if (! $loop->last && ($item['href'] ?? null))
                <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
            @else
                <span aria-current="page">{{ $item['label'] }}</span>
            @endif
        @endforeach
    </nav>
@endif
