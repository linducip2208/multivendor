@props(['name' => 'circle', 'size' => 20, 'stroke' => 1.75, 'class' => '', 'label' => null])

<svg
    {{ $attributes->merge(['class' => trim('adm-icon '.$class)]) }}
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="{{ $stroke }}"
    stroke-linecap="round"
    stroke-linejoin="round"
    @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif
    focusable="false"
>{!! \App\Support\Icons::path((string) $name) !!}</svg>
