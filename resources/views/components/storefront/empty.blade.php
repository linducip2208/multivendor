@props([
    'title' => 'Belum ada apa-apa di sini',
    'text' => null,
    'icon' => 'box',
    'href' => null,
    'label' => null,
])

<div {{ $attributes->merge(['class' => 'sf-empty']) }}>
    <div class="sf-empty__icon">
        <x-storefront.icon :name="$icon" :size="36" />
    </div>
    <h3 class="sf-empty__title">{{ $title }}</h3>
    @if ($text)
        <p class="sf-empty__text">{{ $text }}</p>
    @endif
    @if ($href && $label)
        <a href="{{ $href }}" class="sf-btn sf-btn--primary">{{ $label }}</a>
    @endif
    {{ $slot }}
</div>
