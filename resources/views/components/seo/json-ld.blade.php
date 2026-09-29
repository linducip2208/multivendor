@props(['data' => null, 'variants' => []])
@php
    /**
     * Renders a JSON-LD graph node. Use `@jsonschema` / `@json` escaping so a
     * double quote in a product name can never break out of the script block.
     *
     * Variant Offer hook: when the payload is a Product and variant offers are
     * supplied — either via the :variants attribute or an embedded
     * `variantOffers` / `variants` key — the single `offers` node is expanded
     * into an offers array (one Offer per variant, base offer first).
     * Without variants the payload renders exactly as given, so existing
     * Organization / WebSite / SearchAction / BreadcrumbList graphs are intact.
     */
    $payload = $slot->isEmpty() ? ($data ?? []) : $data;

    $variantList = $variants ?? [];
    if (($variantList === [] || $variantList === null) && is_array($payload)) {
        $variantList = $payload['variantOffers'] ?? $payload['variants'] ?? [];
    }
    if (! is_array($variantList)) {
        $variantList = [];
    }

    if (
        is_array($payload)
        && ($payload['@type'] ?? null) === 'Product'
        && $variantList !== []
        && isset($payload['offers']) && is_array($payload['offers'])
        && ! isset($payload['offers'][0])
    ) {
        $baseOffer = $payload['offers'];
        $baseUrl = (string) ($baseOffer['url'] ?? $payload['url'] ?? '');
        $currency = (string) ($baseOffer['priceCurrency'] ?? \App\Support\Currency::config()['code']);
        $decimals = (int) \App\Support\Currency::config()['decimals'];
        $condition = (string) ($baseOffer['itemCondition'] ?? 'https://schema.org/NewCondition');
        $seller = $baseOffer['seller'] ?? null;

        $offers = [$baseOffer];

        foreach ($variantList as $variant) {
            $variant = is_array($variant) ? $variant : [];
            $rawPrice = $variant['price'] ?? $variant['effective_price'] ?? null;
            if (! is_numeric($rawPrice)) {
                continue;
            }
            $stock = isset($variant['stock']) ? (int) $variant['stock'] : null;
            $variantId = $variant['id'] ?? null;
            $variantUrl = $baseUrl !== '' && $variantId !== null && $variantId !== ''
                ? $baseUrl.(str_contains($baseUrl, '?') ? '&' : '?').'variant='.$variantId
                : ($baseUrl !== '' ? $baseUrl : null);

            $offer = [
                '@type' => 'Offer',
                'priceCurrency' => $currency,
                'price' => number_format((float) $rawPrice, $decimals, '.', ''),
                'availability' => $stock === null
                    ? ($baseOffer['availability'] ?? 'https://schema.org/InStock')
                    : ($stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'),
                'itemCondition' => $condition,
            ];
            if ($variantUrl !== null) {
                $offer['url'] = $variantUrl;
            }
            if (isset($variant['sku']) && $variant['sku'] !== '' && $variant['sku'] !== null) {
                $offer['sku'] = (string) $variant['sku'];
            }
            if ($seller !== null) {
                $offer['seller'] = $seller;
            }

            $offers[] = $offer;
        }

        if (count($offers) > 1) {
            $payload['offers'] = $offers;
        }
        unset($payload['variantOffers'], $payload['variants']);
    }
@endphp
<script type="application/ld+json">{!! json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
