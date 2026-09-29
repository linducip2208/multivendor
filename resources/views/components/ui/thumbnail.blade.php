@props([
    'src' => null,
    'alt' => '',
    'icon' => 'image',
    'size' => 20,
    'imgClass' => 'w-100',
    'style' => 'height:200px;object-fit:contain;',
])

{{--
    UI thumbnail: <img> with Tabler icon fallback when src is empty.
    Usage: <x-ui.thumbnail :src="$url" :alt="$name" />
--}}

@if (! empty($src))
    <img
        src="{{ $src }}"
        alt="{{ $alt }}"
        {{ $attributes->merge(['class' => $imgClass]) }}
        @if ($style) style="{{ $style }}" @endif
        loading="lazy"
    >
@else
    <span {{ $attributes->merge(['class' => 'd-inline-flex align-items-center justify-content-center bg-light text-muted w-100']) }} @if ($style) style="{{ $style }}" @endif role="img" aria-label="{{ $alt ?: 'No image' }}">
        <x-admin.icon :name="$icon" :size="$size * 2" class="opacity-25" />
    </span>
@endif
