@php
    /**
     * Renders a JSON-LD graph node. Use `@jsonschema` / `@json` escaping so a
     * double quote in a product name can never break out of the script block.
     */
    $payload = $slot->isEmpty() ? ($data ?? []) : $data;
@endphp
<script type="application/ld+json">{!! json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
