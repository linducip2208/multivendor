<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Inline SVG icon set for the backoffice component layer.
 *
 * The admin shell must not depend on an icon CDN or an icon webfont, so every
 * glyph is a 24x24 stroke path inlined by the Blade component. Names follow
 * the vocabulary used by config/navigation.php, with a synonym table so
 * Feather, Tabler and Lucide spellings all resolve.
 */
final class Icons
{
    /** @var array<string, string> */
    private const PATHS = [
        'activity' => '<path d="M3 12.5h4l2.5 7 4-15 2.5 8h5"/>',
        'alert-circle' => '<circle cx="12" cy="12" r="8.7"/><path d="M12 7.6v5M12 16.2h.01"/>',
        'alert-triangle' => '<path d="M10.6 3.8 2.4 18a1.6 1.6 0 0 0 1.4 2.4h16.4a1.6 1.6 0 0 0 1.4-2.4L13.4 3.8a1.6 1.6 0 0 0-2.8 0z"/><path d="M12 9v4.2M12 16.4h.01"/>',
        'arrow-down' => '<path d="M12 4v16"/><path d="m5.5 13.5 6.5 6.5 6.5-6.5"/>',
        'arrow-left' => '<path d="M20 12H4"/><path d="m10.5 5.5-6.5 6.5 6.5 6.5"/>',
        'arrow-right' => '<path d="M4 12h16"/><path d="m13.5 5.5 6.5 6.5-6.5 6.5"/>',
        'arrow-up' => '<path d="M12 20V4"/><path d="m5.5 10.5 6.5-6.5 6.5 6.5"/>',
        'award' => '<circle cx="12" cy="8.5" r="5.5"/><path d="M8.6 13.2 7 21.5 12 19l5 2.5-1.6-8.3"/>',
        'bar-chart' => '<path d="M3.5 20.5h17"/><rect x="5" y="11" width="3.6" height="7" rx="1"/><rect x="10.2" y="6.5" width="3.6" height="11.5" rx="1"/><rect x="15.4" y="14" width="3.6" height="4" rx="1"/>',
        'barcode' => '<path d="M4 5v14M7 5v14M10.5 5v10M14 5v14M17 5v10M20 5v14"/>',
        'bell' => '<path d="M18 9.2a6 6 0 1 0-12 0c0 5.8-2 7-2 7h16s-2-1.2-2-7z"/><path d="M10.2 19.6a2 2 0 0 0 3.6 0"/>',
        'book' => '<path d="M4.5 5A1.5 1.5 0 0 1 6 3.5h13v17H6A1.5 1.5 0 0 0 4.5 22z"/><path d="M4.5 5v15"/><path d="M8.5 7.5h7"/>',
        'bot' => '<rect x="4" y="8" width="16" height="12" rx="2.4"/><path d="M12 4.6V8"/><circle cx="12" cy="3.2" r="1.2"/><path d="M9 13v1.6M15 13v1.6"/><path d="M2.5 12.5v3M21.5 12.5v3"/>',
        'box' => '<rect x="3" y="3.5" width="18" height="17" rx="2"/><path d="M3 9h18M9.5 20.5V9"/>',
        'building' => '<rect x="4" y="3" width="16" height="18" rx="1.6"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'check' => '<path d="m4.5 12.5 5 5 10-11"/>',
        'check-circle' => '<circle cx="12" cy="12" r="8.7"/><path d="m8.2 12.2 2.6 2.6 5-5.2"/>',
        'chevron-down' => '<path d="m6 9.5 6 6 6-6"/>',
        'chevron-left' => '<path d="m14.5 5.5-6.5 6.5 6.5 6.5"/>',
        'chevron-right' => '<path d="m9.5 5.5 6.5 6.5-6.5 6.5"/>',
        'chevron-up' => '<path d="m6 14.5 6-6 6 6"/>',
        'circle' => '<circle cx="12" cy="12" r="8.7"/>',
        'clock' => '<circle cx="12" cy="12" r="8.7"/><path d="M12 6.9V12l3.4 2"/>',
        'code' => '<path d="m8.5 8.5-4 3.5 4 3.5M15.5 8.5l4 3.5-4 3.5M13.5 5l-3 14"/>',
        'copy' => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4.5A1.5 1.5 0 0 1 3 13.5v-9A1.5 1.5 0 0 1 4.5 3h9A1.5 1.5 0 0 1 15 4.5V5"/>',
        'corner-up-right' => '<path d="M6 18V9.5A2.5 2.5 0 0 1 8.5 7H19"/><path d="m14.5 2.5 4.5 4.5-4.5 4.5"/>',
        'cpu' => '<rect x="6.5" y="6.5" width="11" height="11" rx="2"/><rect x="9.8" y="9.8" width="4.4" height="4.4" rx="1"/><path d="M9.5 3.5v3M14.5 3.5v3M9.5 17.5v3M14.5 17.5v3M3.5 9.5h3M3.5 14.5h3M17.5 9.5h3M17.5 14.5h3"/>',
        'credit-card' => '<rect x="2.5" y="5" width="19" height="14" rx="2.4"/><path d="M2.5 9.5h19"/><path d="M6 15h4"/>',
        'dashboard' => '<rect x="3" y="3" width="7.5" height="8.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="5.5" rx="1.5"/><rect x="13.5" y="11.5" width="7.5" height="9.5" rx="1.5"/><rect x="3" y="14.5" width="7.5" height="6.5" rx="1.5"/>',
        'database' => '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'download' => '<path d="M12 3.5v11.5"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M4 19.5h16"/>',
        'edit' => '<path d="M4 20h4L19 9a2.1 2.1 0 0 0-3-3L5 17z"/><path d="M14.5 7.5 17 10"/>',
        'external-link' => '<path d="M14 4.5h5.5V10"/><path d="M19.5 4.5 11 13"/><path d="M18 13.5v5A1.5 1.5 0 0 1 16.5 20h-11A1.5 1.5 0 0 1 4 18.5v-11A1.5 1.5 0 0 1 5.5 6h5"/>',
        'eye' => '<path d="M2.5 12S6.3 5.5 12 5.5 21.5 12 21.5 12 17.7 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="M4 4.5 19.5 20"/><path d="M9.5 6A9.6 9.6 0 0 1 12 5.5c5.7 0 9.5 6.5 9.5 6.5a17 17 0 0 1-3 3.7"/><path d="M6.4 8A17 17 0 0 0 2.5 12S6.3 18.5 12 18.5a9.7 9.7 0 0 0 3.5-.65"/><path d="M10.2 10.2a2.6 2.6 0 0 0 3.6 3.6"/>',
        'file' => '<path d="M13.5 3.5H7a1.5 1.5 0 0 0-1.5 1.5v14A1.5 1.5 0 0 0 7 20.5h10a1.5 1.5 0 0 0 1.5-1.5V8.5z"/><path d="M13.5 3.5v5h5"/>',
        'file-bar' => '<path d="M13.5 3.5H7a1.5 1.5 0 0 0-1.5 1.5v14A1.5 1.5 0 0 0 7 20.5h10a1.5 1.5 0 0 0 1.5-1.5V8.5z"/><path d="M13.5 3.5v5h5"/><path d="M9 17.5V14M12 17.5v-5M15 17.5v-2.5"/>',
        'file-code' => '<path d="M13.5 3.5H7a1.5 1.5 0 0 0-1.5 1.5v14A1.5 1.5 0 0 0 7 20.5h10a1.5 1.5 0 0 0 1.5-1.5V8.5z"/><path d="M13.5 3.5v5h5"/><path d="m10 12.5-2 2 2 2M14 12.5l2 2-2 2"/>',
        'file-text' => '<path d="M13.5 3.5H7a1.5 1.5 0 0 0-1.5 1.5v14A1.5 1.5 0 0 0 7 20.5h10a1.5 1.5 0 0 0 1.5-1.5V8.5z"/><path d="M13.5 3.5v5h5"/><path d="M9 13h6M9 16.5h6"/>',
        'filter' => '<path d="M3.5 5.5h17l-6.5 7.5v6l-4 2v-8z"/>',
        'folder' => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h4.2l2 2.5h6.8A1.5 1.5 0 0 1 20 8v10.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5z"/>',
        'gauge' => '<path d="M4 17a9 9 0 1 1 16 0"/><path d="m12 13 4-4"/><circle cx="12" cy="15" r="1.4"/>',
        'globe' => '<circle cx="12" cy="12" r="8.7"/><path d="M3.3 12h17.4"/><path d="M12 3.3a14 14 0 0 1 0 17.4 14 14 0 0 1 0-17.4z"/>',
        'grid' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
        'heart' => '<path d="M12 20.3 4 12.4a4.6 4.6 0 0 1 6.5-6.5L12 7.3l1.5-1.4a4.6 4.6 0 0 1 6.5 6.5z"/>',
        'heart-pulse' => '<path d="M12 20.5S3.5 15.4 3.5 9.4A4.6 4.6 0 0 1 8.1 4.8c1.6 0 3 .8 3.9 2a4.6 4.6 0 0 1 3.9-2 4.6 4.6 0 0 1 4.6 4.6c0 6-8.5 11.1-8.5 11.1z"/><path d="M7 12h2.5l1.5-3 3 5 1.5-2H17"/>',
        'help-circle' => '<circle cx="12" cy="12" r="8.7"/><path d="M9.6 9.6a2.5 2.5 0 1 1 3.4 2.3c-.7.3-1 .9-1 1.6v.3M12 16.8h.01"/>',
        'history' => '<path d="M3.5 12a8.5 8.5 0 1 0 2.5-6"/><path d="M3.2 4.2v4.6h4.6"/><path d="M12 7.6V12l3 2"/>',
        'home' => '<path d="M3 10.6 12 3.2l9 7.4V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'image' => '<rect x="3" y="4.5" width="18" height="15" rx="2.2"/><circle cx="8.6" cy="9.8" r="1.7"/><path d="m3.4 17.5 4.9-4.9 3.9 3.9 3-3 5.4 5.4"/>',
        'inbox' => '<path d="M3.5 13.5h4l1.5 3h6l1.5-3h4"/><path d="M6.2 4.5h11.6l2.7 9v5a1.5 1.5 0 0 1-1.5 1.5H5a1.5 1.5 0 0 1-1.5-1.5v-5z"/>',
        'info' => '<circle cx="12" cy="12" r="8.7"/><path d="M12 11.2v5.2M12 7.8h.01"/>',
        'key' => '<circle cx="8" cy="12" r="4.5"/><path d="M12.5 12H21l-1.5 1.5M17.5 12v3"/>',
        'layers' => '<path d="m12 2.8 9.2 4.7-9.2 4.7-9.2-4.7z"/><path d="m2.8 12.5 9.2 4.7 9.2-4.7"/><path d="m2.8 17 9.2 4.7 9.2-4.7"/>',
        'link' => '<path d="M10 13.6a3.6 3.6 0 0 0 5.1 0l3-3a3.6 3.6 0 0 0-5.1-5.1l-1.4 1.4"/><path d="M14 10.4a3.6 3.6 0 0 0-5.1 0l-3 3a3.6 3.6 0 0 0 5.1 5.1l1.4-1.4"/>',
        'list' => '<path d="M8.5 6h12M8.5 12h12M8.5 18h12"/><path d="M3.6 6h.01M3.6 12h.01M3.6 18h.01"/>',
        'list-ordered' => '<path d="M10 6h11M10 12h11M10 18h11"/><path d="M4 4.5h1.5V8M3.6 12.4c0-1.1 2-1.2 2 0 0 .7-2 1.1-2 2.3h2.2M3.6 18h2.2l-1.5.9h.3c.9 0 1.3.5 1.3 1.1s-.5 1-1.3 1-1.3-.4-1.3-1"/>',
        'lock' => '<rect x="4.5" y="10" width="15" height="10.5" rx="2.2"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10"/>',
        'logout' => '<path d="M15 17.5V19a1.5 1.5 0 0 1-1.5 1.5h-8A1.5 1.5 0 0 1 4 19V5a1.5 1.5 0 0 1 1.5-1.5h8A1.5 1.5 0 0 1 15 5v1.5"/><path d="M20 12H9.5"/><path d="m16.5 8.5 3.5 3.5-3.5 3.5"/>',
        'mail' => '<rect x="2.8" y="5" width="18.4" height="14" rx="2.4"/><path d="m3.4 6.8 8.6 6 8.6-6"/>',
        'map-pin' => '<path d="M20 10.2c0 5.6-8 11.8-8 11.8s-8-6.2-8-11.8a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="2.8"/>',
        'megaphone' => '<path d="M4 10.5v3a1.5 1.5 0 0 0 1.5 1.5H7l1 4.5h2.5L9.5 15h2L18 18.5V5.5L11.5 9H5.5A1.5 1.5 0 0 0 4 10.5z"/><path d="M18 9.5a3.2 3.2 0 0 1 0 5"/>',
        'menu' => '<path d="M4 6.5h16M4 12h16M4 17.5h16"/>',
        'message-circle' => '<path d="M21 11.7a8.6 8.6 0 0 1-9 8.6 9 9 0 0 1-3.6-.8L3.5 21l1.5-4.6A8.5 8.5 0 0 1 4 11.7 8.6 8.6 0 0 1 12.5 3.2 8.6 8.6 0 0 1 21 11.7z"/>',
        'message-square' => '<path d="M4 5.5h16v11H9.5L4 20.5z"/><path d="M8 9.5h8M8 13h5"/>',
        'minus' => '<path d="M5 12h14"/>',
        'monitor' => '<rect x="2.5" y="4" width="19" height="13" rx="2.2"/><path d="M8.5 21h7M12 17v4"/>',
        'moon' => '<path d="M20.5 14.5A8.6 8.6 0 0 1 9.5 3.6a8.7 8.7 0 1 0 11 10.9z"/>',
        'package' => '<path d="M12 2.6 20.8 7v10L12 21.4 3.2 17V7z"/><path d="M3.2 7 12 11.4 20.8 7"/><path d="M12 11.4v10"/>',
        'palette' => '<path d="M12 3a9 9 0 0 0 0 18c1.2 0 2-.8 2-1.8 0-.5-.2-.9-.5-1.2-.3-.3-.5-.7-.5-1.2 0-1 .8-1.8 1.8-1.8H16a5 5 0 0 0 5-5c0-3.9-4-7-9-7z"/><circle cx="7.5" cy="11" r="1.2" fill="currentColor" stroke="none"/><circle cx="11" cy="7.5" r="1.2" fill="currentColor" stroke="none"/><circle cx="15.5" cy="9" r="1.2" fill="currentColor" stroke="none"/>',
        'percent' => '<path d="m5 19 14-14"/><circle cx="7.5" cy="7.5" r="2.6"/><circle cx="16.5" cy="16.5" r="2.6"/>',
        'phone' => '<path d="M5 3.5h3.5l1.8 4.5-2.2 1.6a12 12 0 0 0 5.8 5.8l1.6-2.2 4.5 1.8V19a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 3 5.7 2 2 0 0 1 5 3.5z"/>',
        'plug' => '<path d="M9 3.5v5M15 3.5v5"/><path d="M6.5 8.5h11v3a5.5 5.5 0 0 1-11 0z"/><path d="M12 17v3.5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'printer' => '<path d="M7 8.5V3.5h10v5"/><rect x="3.5" y="8.5" width="17" height="8" rx="1.8"/><rect x="7" y="14" width="10" height="6.5" rx="1"/>',
        'puzzle' => '<path d="M14 3.5h-4v2.6a1.7 1.7 0 0 1-2.6 1.4L5.6 5.7 3.5 7.8l1.8 1.8a1.7 1.7 0 0 1-1.4 2.6H1.3v4h2.6a1.7 1.7 0 0 1 1.4 2.6l-1.8 1.8 2.1 2.1 1.8-1.8a1.7 1.7 0 0 1 2.6 1.4v2.6h4v-2.6a1.7 1.7 0 0 1 2.6-1.4l1.8 1.8 2.1-2.1-1.8-1.8a1.7 1.7 0 0 1 1.4-2.6h2.6v-4h-2.6a1.7 1.7 0 0 1-1.4-2.6l1.8-1.8-2.1-2.1-1.8 1.8a1.7 1.7 0 0 1-2.6-1.4z"/>',
        'receipt' => '<path d="M6 3.5h12v17l-2.5-1.6-2.5 1.6-2.5-1.6L8 20.5 6 18.9z"/><path d="M9.2 8.5h5.6M9.2 12.4h5.6"/>',
        'refresh' => '<path d="M3.5 12a8.5 8.5 0 0 1 14.4-6.1L20.5 8.4"/><path d="M20.5 3.6v4.8h-4.8"/><path d="M20.5 12a8.5 8.5 0 0 1-14.4 6.1L3.5 15.6"/><path d="M3.5 20.4v-4.8h4.8"/>',
        'scan-line' => '<path d="M3.5 8V5.5A2 2 0 0 1 5.5 3.5H8M16 3.5h2.5A2 2 0 0 1 20.5 5.5V8M20.5 16v2.5a2 2 0 0 1-2 2H16M8 20.5H5.5a2 2 0 0 1-2-2V16M3.5 12h17"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20.5 20.5-3.9-3.9"/>',
        'server' => '<rect x="3" y="4" width="18" height="7" rx="1.8"/><rect x="3" y="13" width="18" height="7" rx="1.8"/><path d="M7 7.5h.01M7 16.5h.01"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.1 14.2a1.6 1.6 0 0 0 .3 1.8l.1.1a1.9 1.9 0 1 1-2.7 2.7l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5v.1a1.9 1.9 0 0 1-3.8 0v-.2a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a1.9 1.9 0 1 1-2.7-2.7l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1h-.1a1.9 1.9 0 0 1 0-3.8h.2a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a1.9 1.9 0 1 1 2.7-2.7l.1.1a1.6 1.6 0 0 0 1.8.3h.1a1.6 1.6 0 0 0 1-1.5v-.1a1.9 1.9 0 0 1 3.8 0v.2a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a1.9 1.9 0 1 1 2.7 2.7l-.1.1a1.6 1.6 0 0 0-.3 1.8v.1a1.6 1.6 0 0 0 1.5 1h.1a1.9 1.9 0 0 1 0 3.8h-.2a1.6 1.6 0 0 0-1.5 1z"/>',
        'shield' => '<path d="M12 2.8 4.8 5.7v5.6c0 4.4 3 8 7.2 9.9 4.2-1.9 7.2-5.5 7.2-9.9V5.7z"/>',
        'shopping-bag' => '<path d="M5.5 7.5h13l1 12.5a1 1 0 0 1-1 1h-13a1 1 0 0 1-1-1z"/><path d="M9 10V6.2a3 3 0 0 1 6 0V10"/>',
        'sort' => '<path d="M7.5 4.5v15M4 8l3.5-3.5L11 8"/><path d="M16.5 19.5v-15M13 16l3.5 3.5L20 16"/>',
        'shopping-cart' => '<path d="M2 3h2.3l2.4 12.1a1.5 1.5 0 0 0 1.5 1.2h8.7a1.5 1.5 0 0 0 1.5-1.2L20.5 7H5.4"/><circle cx="9.5" cy="20" r="1.4"/><circle cx="17.5" cy="20" r="1.4"/>',
        'sliders' => '<path d="M4 8h9M17 8h3M4 16h3M11 16h9"/><circle cx="15" cy="8" r="2"/><circle cx="9" cy="16" r="2"/>',
        'sparkles' => '<path d="m12 3 1.9 4.6 4.6 1.9-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9z"/><path d="m18.6 15.2 1 2.4 2.4 1-2.4 1-1 2.4-1-2.4-2.4-1 2.4-1z"/>',
        'star' => '<path d="m12 2.8 2.9 5.9 6.5.95-4.7 4.6 1.1 6.5-5.8-3.05L6.2 20.8l1.1-6.5-4.7-4.6 6.5-.95z" fill="currentColor" stroke="none"/>',
        'store' => '<path d="M3.5 9.5V20a1 1 0 0 0 1 1h15a1 1 0 0 0 1-1V9.5"/><path d="M2.5 7.6 4.2 3h15.6l1.7 4.6a3 3 0 0 1-5.4 2.4A3 3 0 0 1 12 9a3 3 0 0 1-4.1 1.2A3 3 0 0 1 2.5 7.6z"/><path d="M9.5 21v-5.5h5V21"/>',
        'sun' => '<circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.2M12 19.3v2.2M2.5 12h2.2M19.3 12h2.2M5.2 5.2l1.6 1.6M17.2 17.2l1.6 1.6M18.8 5.2l-1.6 1.6M6.8 17.2l-1.6 1.6"/>',
        'tag' => '<path d="M11 3.5H4.5A1 1 0 0 0 3.5 4.5V11L13 20.5 20.5 13z"/><circle cx="7.8" cy="7.8" r="1.4"/>',
        'target' => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/>',
        'ticket' => '<path d="M3 8.6V6.6A1.6 1.6 0 0 1 4.6 5h14.8A1.6 1.6 0 0 1 21 6.6v2a2.4 2.4 0 0 0 0 4.8v2a1.6 1.6 0 0 1-1.6 1.6H4.6A1.6 1.6 0 0 1 3 15.4v-2a2.4 2.4 0 0 0 0-4.8z"/><path d="M13.5 8v8"/>',
        'toggle-left' => '<rect x="2.5" y="6.5" width="19" height="11" rx="5.5"/><circle cx="7.8" cy="12" r="2.8" fill="currentColor" stroke="none"/>',
        'trash' => '<path d="M4 6.5h16M9.5 6.5V4.8A1.3 1.3 0 0 1 10.8 3.5h2.4a1.3 1.3 0 0 1 1.3 1.3v1.7"/><path d="m6.5 6.5 1 13.2a1.3 1.3 0 0 0 1.3 1.2h6.4a1.3 1.3 0 0 0 1.3-1.2l1-13.2"/>',
        'trending-up' => '<path d="m3 17 6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
        'truck' => '<path d="M2.8 6.5h10.4v10H2.8z"/><path d="M13.2 10h3.9l3.1 3.4v3.1h-7z"/><circle cx="7" cy="18.4" r="1.8"/><circle cx="17" cy="18.4" r="1.8"/>',
        'undo' => '<path d="M4.5 9.5H14a5.5 5.5 0 0 1 0 11H9"/><path d="m8.5 5.5-4 4 4 4"/>',
        'upload' => '<path d="M12 20.5V9"/><path d="m7.5 13.5 4.5-4.5 4.5 4.5"/><path d="M4 4.5h16"/>',
        'user' => '<circle cx="12" cy="8" r="3.8"/><path d="M4.5 21v-1.2a6 6 0 0 1 6-6h3a6 6 0 0 1 6 6V21"/>',
        'user-check' => '<circle cx="9.5" cy="8" r="3.8"/><path d="M2.5 21v-1.2a6 6 0 0 1 6-6h2a5.9 5.9 0 0 1 5.4 3.2"/><path d="m15 17.5 2 2 4-4"/>',
        'user-cog' => '<circle cx="9.5" cy="8" r="3.8"/><path d="M2.5 21v-1.2a6 6 0 0 1 6-6h2c.9 0 1.8.2 2.5.6"/><circle cx="17.5" cy="17" r="2.4"/><path d="M17.5 13.4v1.3M17.5 19.3v1.3M14.2 17h1.3M19.5 17h1.3M15.1 14.6l.9.9M19 18.5l.9.9M19.9 14.6l-.9.9M16 18.5l-.9.9"/>',
        'user-plus' => '<circle cx="9.5" cy="8" r="3.8"/><path d="M2.5 21v-1.2a6 6 0 0 1 6-6h2a6 6 0 0 1 6 6V21"/><path d="M19 6.5v6M22 9.5h-6"/>',
        'users' => '<circle cx="9" cy="8" r="3.6"/><path d="M3 20v-1a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v1"/><path d="M16.5 5.2a3.4 3.4 0 0 1 0 5.6"/><path d="M18.5 14.3A4.6 4.6 0 0 1 21 18.9V20"/>',
        'wallet' => '<rect x="2.8" y="6" width="18.4" height="13" rx="2.4"/><path d="M2.8 10.2h18.4"/><path d="M16.6 14.6h1.6"/>',
        'webhook' => '<circle cx="12" cy="5" r="2.5"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/><path d="m13.8 7.2-6.3 9.3M16.4 16l-3.2-9.3M8 18.5h8"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'zap' => '<path d="M13.2 2.5 4.8 13.2h6L10.4 21.5l8.8-11.2h-6.4z"/>',
    ];

    /** @var array<string, string> */
    private const ALIASES = [
        'area-chart' => 'bar-chart',
        'arrow-up-right' => 'trending-up',
        'cash-coin' => 'wallet',
        'cash' => 'credit-card',
        'cart' => 'shopping-cart',
        'category' => 'folder',
        'chart-bar' => 'bar-chart',
        'chart-line' => 'trending-up',
        'chart-pie' => 'gauge',
        'clock-1' => 'clock',
        'currency-dollar' => 'wallet',
        'dashboard-2' => 'dashboard',
        'dots-vertical' => 'menu',
        'edit-3' => 'edit',
        'file-text-2' => 'file-text',
        'grid-3x3' => 'grid',
        'help-circle-2' => 'help-circle',
        'home-2' => 'home',
        'key-square' => 'key',
        'layout-grid' => 'grid',
        'life-buoy' => 'help-circle',
        'list-checks' => 'list',
        'mailbox' => 'inbox',
        'message-circle-2' => 'message-circle',
        'message-square-2' => 'message-square',
        'mood' => 'sun',
        'pencil' => 'edit',
        'refresh-cw' => 'refresh',
        'rotate-ccw' => 'refresh',
        'rotate-cw' => 'refresh',
        'scan' => 'scan-line',
        'scan-barcode' => 'barcode',
        'settings-2' => 'settings',
        'shield-check' => 'shield',
        'shopping' => 'shopping-cart',
        'shopping-basket' => 'shopping-bag',
        'shopping-card' => 'credit-card',
        'sliders-2' => 'sliders',
        'sort-asc' => 'arrow-up',
        'sort-desc' => 'arrow-down',
        'sparkle' => 'sparkles',
        'star-filled' => 'star',
        'sun-moon' => 'sun',
        'toggle-left-2' => 'toggle-left',
        'trending-down' => 'arrow-down',
        'trending-up-2' => 'trending-up',
        'user-2' => 'user',
        'user-circle' => 'user',
        'users-2' => 'users',
        'wallet-2' => 'wallet',
        'world' => 'globe',
    ];

    public static function has(string $name): bool
    {
        return isset(self::PATHS[$name]) || isset(self::ALIASES[$name]);
    }

    public static function path(string $name): string
    {
        $key = strtolower(trim($name));

        if (isset(self::PATHS[$key])) {
            return self::PATHS[$key];
        }

        $key = str_replace('_', '-', $key);

        if (isset(self::PATHS[$key])) {
            return self::PATHS[$key];
        }

        if (isset(self::ALIASES[$key], self::PATHS[self::ALIASES[$key]])) {
            return self::PATHS[self::ALIASES[$key]];
        }

        return self::PATHS['circle'];
    }
}
