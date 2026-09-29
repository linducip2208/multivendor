@props([
    'items' => [],
    'title' => null,
    'href' => null,
    'linkLabel' => 'Lihat semua',
    'icon' => null,
    'iconColor' => 'var(--sf-brand)',
])

@if (count($items) > 0)
    <section {{ $attributes->merge(['class' => 'sf-section']) }}>
        <div class="sf-container">
            <div class="sf-section-head">
                <div>
                    @if ($icon)
                        <span class="sf-section-head__eyebrow" style="color:{{ $iconColor }}">{{ $icon }}</span>
                    @endif
                    @if ($title)
                        <h2 class="sf-section-head__title">{{ $title }}</h2>
                    @endif
                    @if (isset($subtitle) && $subtitle)
                        <p class="sf-muted sf-small sf-mt-0">{{ $subtitle }}</p>
                    @endif
                </div>
                @if ($href)
                    <a href="{{ $href }}" class="sf-section-head__link">
                        {{ $linkLabel }} <x-storefront.icon name="arrow-right" :size="16" />
                    </a>
                @endif
            </div>
            {{ $slot }}
        </div>
    </section>
@endif
