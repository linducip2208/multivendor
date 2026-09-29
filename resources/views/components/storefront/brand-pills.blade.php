@props(['brands' => []])

{{-- Contract: Collection<Brand>. Non-model entries are skipped. --}}
@if (count($brands) > 0)
    <div {{ $attributes->merge(['class' => 'sf-brands']) }}>
        @foreach ($brands as $brand)
            @if (! is_object($brand))
                @continue
            @endif
            <a href="{{ route('brands.show', $brand->slug) }}" class="sf-brand">
                @if ($brand->logo_url)
                    <img src="{{ $brand->logo_url }}" alt="{{ $brand->name }}" loading="lazy" width="120" height="40" decoding="async">
                @else
                    <span>{{ $brand->name }}</span>
                @endif
            </a>
        @endforeach
    </div>
@endif
