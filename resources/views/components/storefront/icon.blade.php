@props(['name', 'size' => 20, 'stroke' => 1.75, 'class' => ''])

@php
    /**
     * Inline SVG icon set (Lucide-style, 24x24, stroke based).
     *
     * The storefront deliberately does NOT load an external icon CDN: it avoids
     * a render-blocking third-party request, avoids shipping ~70 kB of font
     * glyphs, and keeps the design system self-contained for white-label use.
     */
    $paths = [
        'home' => '<path d="M3 9.5 12 3l9 6.5V20a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'cart' => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h2.2l2.4 12.2a1.5 1.5 0 0 0 1.5 1.2h8.8a1.5 1.5 0 0 0 1.5-1.2L21 7H5.5"/>',
        'heart' => '<path d="M12 20.5 3.9 12.4a5 5 0 0 1 7.1-7l1 1 1-1a5 5 0 0 1 7.1 7z"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
        'star' => '<path d="m12 2.5 2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 17.4 6.2 20.4l1.1-6.5L2.6 9.3l6.5-.9z" fill="currentColor" stroke="none"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'chevron-left' => '<path d="m15 6-6 6 6 6"/>',
        'arrow-right' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'close' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'filter' => '<path d="M3 5h18M6 12h12M10 19h4"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        'truck' => '<path d="M2 7h11v9H2zM13 10h4.5l3 3.5V16H13z"/><circle cx="6.5" cy="18.5" r="1.8"/><circle cx="17" cy="18.5" r="1.8"/>',
        'shield-check' => '<path d="M12 3 5 6v5.5c0 4.3 2.9 7.9 7 9.5 4.1-1.6 7-5.2 7-9.5V6z"/><path d="m9 12 2 2 4-4"/>',
        'refresh' => '<path d="M3 12a9 9 0 0 1 15.3-6.4L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15.3 6.4L3 16"/><path d="M3 21v-5h5"/>',
        'headset' => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="2.5" y="13.5" width="4" height="6" rx="1.5"/><rect x="17.5" y="13.5" width="4" height="6" rx="1.5"/><path d="M20 19.5a3 3 0 0 1-3 3h-3"/>',
        'map-pin' => '<path d="M20 10c0 5.5-8 12-8 12s-8-6.5-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="2.8"/>',
        'phone' => '<path d="M5 3h3.5l1.8 4.5-2.2 1.6a12 12 0 0 0 5.8 5.8l1.6-2.2L20 14.5V18a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 3 5.2 2 2 0 0 1 5 3z"/>',
        'mail' => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="m3 7 9 6 9-6"/>',
        'tag' => '<path d="M3 11.5V4.5A1.5 1.5 0 0 1 4.5 3h7l9 9-8 8z"/><circle cx="7.8" cy="7.8" r="1.4"/>',
        'wallet' => '<rect x="2.5" y="6" width="19" height="13" rx="2.5"/><path d="M2.5 10h19M17 14.5h1.5"/>',
        'coins' => '<ellipse cx="9" cy="7" rx="6" ry="3"/><path d="M3 7v4c0 1.7 2.7 3 6 3s6-1.3 6-3V7"/><path d="M3 11v4c0 1.7 2.7 3 6 3 1 0 2-.1 2.8-.3"/><ellipse cx="17" cy="16" rx="4" ry="2"/>',
        'package' => '<path d="M12 2.5 21 7v10l-9 4.5L3 17V7z"/><path d="M3 7l9 4.5L21 7M12 11.5V21"/>',
        'store' => '<path d="M3 9.5V20a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V9.5"/><path d="M2 7.5 4 3h16l2 4.5a3 3 0 0 1-5.5 2A3 3 0 0 1 12 9a3 3 0 0 1-4.5.5A3 3 0 0 1 2 7.5z"/><path d="M9 21v-6h6v6"/>',
        'box' => '<rect x="3" y="3" width="18" height="18" rx="2.5"/><path d="M3 9h18M9 21V9"/>',
        'trash' => '<path d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13"/>',
        'check' => '<path d="m4 12.5 5 5L20 6.5"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9.5"/><path d="m8 12 2.8 2.8L16 9.5"/>',
        'alert-circle' => '<circle cx="12" cy="12" r="9.5"/><path d="M12 7.5v5.5M12 16.3h.01"/>',
        'info' => '<circle cx="12" cy="12" r="9.5"/><path d="M12 11v5.5M12 7.7h.01"/>',
        'clock' => '<circle cx="12" cy="12" r="9.5"/><path d="M12 6.5V12l3.5 2"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'bell' => '<path d="M18 9a6 6 0 1 0-12 0c0 6-2 7-2 7h16s-2-1-2-7z"/><path d="M10.5 20a2 2 0 0 0 3 0"/>',
        'eye' => '<path d="M2 12s3.8-6.5 10-6.5S22 12 22 12s-3.8 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'zoom-in' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5M11 8.5v5M8.5 11h5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'scale' => '<path d="M12 3v18M7 6h10M5 10l-2.5 6h5zM19 10l2.5 6h-5zM7 21h10"/>',
        'ticket' => '<path d="M3 8.5V6.5A1.5 1.5 0 0 1 4.5 5h15A1.5 1.5 0 0 1 21 6.5v2a2.5 2.5 0 0 0 0 7v2a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5v-2a2.5 2.5 0 0 0 0-5z"/><path d="M13 8v8"/>',
        'trending' => '<path d="m3 17 6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
        'flame' => '<path d="M12 22c3.9 0 6.5-2.6 6.5-6 0-4.5-4-6.5-4-10 0 0-2 1.5-2 4 0 1.5-1 2-1.7 1.3C9.8 10 9 8.5 9 8.5 7.5 10 5.5 12 5.5 16c0 3.4 2.6 6 6.5 6z"/>',
        'award' => '<circle cx="12" cy="9" r="5.5"/><path d="M8.5 13.5 7 22l5-2.5L17 22l-1.5-8.5"/>',
        'sparkles' => '<path d="m12 3 1.9 4.6L18.5 9.5 13.9 11.4 12 16l-1.9-4.6L5.5 9.5l4.6-1.9z"/><path d="M18.5 15.5 19.3 18l2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8z"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5M3 17.5l9 5 9-5"/>',
        'globe' => '<circle cx="12" cy="12" r="9.5"/><path d="M2.5 12h19M12 2.5a15 15 0 0 1 0 19 15 15 0 0 1 0-19z"/>',
        'lock' => '<rect x="4.5" y="10" width="15" height="10.5" rx="2.5"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10"/>',
        'logout' => '<path d="M15 17v2a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v2"/><path d="M20 12H9m11 0-3.5-3.5M20 12l-3.5 3.5"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 14.5a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 9 19.4a1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 9a1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2.5"/><circle cx="8.5" cy="9.5" r="1.8"/><path d="m3 17 5-5 4 4 3-3 6 6"/>',
        'video' => '<rect x="2.5" y="6" width="14" height="12" rx="2.5"/><path d="m16.5 11 5-3v8l-5-3z"/>',
        'percent' => '<path d="m5 19 14-14"/><circle cx="7.5" cy="7.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/>',
        'book' => '<path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H19v18H5.5A1.5 1.5 0 0 1 4 19.5z"/><path d="M8 3v18"/>',
        'copy' => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v6a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 4 19V8a1.5 1.5 0 0 1 1.5-1.5H11"/>',
        'sun' => '<circle cx="12" cy="12" r="4.5"/><path d="M12 1.5v3M12 19.5v3M1.5 12h3M19.5 12h3M4.6 4.6l2.1 2.1M17.3 17.3l2.1 2.1M4.6 19.4l2.1-2.1M17.3 6.7l2.1-2.1"/>',
        'moon' => '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5z"/>',
    ];

    $path = $paths[$name] ?? $paths['box'];
@endphp

<svg
    {{ $attributes->merge(['class' => 'sf-icon '.$class]) }}
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="{{ $stroke }}"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
>{!! $path !!}</svg>
