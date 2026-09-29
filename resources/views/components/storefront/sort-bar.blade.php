@props([
    'result' => null,
    'query' => null,
    'baseUrl' => null,
    'label' => 'produk',
])

@php
    $baseUrl = $baseUrl ?: request()->url();
    $currentSort = $query?->sorts[0] ?? request('sort', 'relevance');
    $term = $query?->term ?? request('q', request('search', ''));
    $total = (int) ($result?->total ?? 0);

    $options = [
        'relevance' => 'Paling Relevan',
        'popular' => 'Terlaris',
        'newest' => 'Terbaru',
        'price_asc' => 'Harga Terendah',
        'price_desc' => 'Harga Tertinggi',
        'rating' => 'Rating Tertinggi',
        'name' => 'Nama A-Z',
    ];

    $carried = array_filter([
        'q' => $term !== '' ? $term : null,
        'category' => ($query?->categoryIds ?? []) !== [] ? implode(',', $query?->categoryIds) : null,
        'brand' => ($query?->brandIds ?? []) !== [] ? implode(',', $query?->brandIds) : null,
        'shop' => ($query?->shopIds ?? []) !== [] ? implode(',', $query?->shopIds) : null,
        'min_price' => $query?->minPrice,
        'max_price' => $query?->maxPrice,
        'min_rating' => $query?->minRating,
        'in_stock' => ($query?->inStockOnly ?? false) ? 1 : null,
    ], static fn ($value) => $value !== null && $value !== '' && $value !== []);
@endphp

<div class="sf-sortbar">
    <p class="sf-sortbar__count sf-mb-0" role="status" aria-live="polite">
        <span class="sf-bold" style="color:var(--sf-text)">{{ \App\Support\Currency::number($total) }}</span>
        {{ $label }}{{ $total === 1 ? '' : '' }} ditemukan
        @if ($result?->tookMs)
            <span class="sf-tiny sf-subtle"> &middot; {{ \App\Support\Currency::number($result->tookMs, 1) }} ms</span>
        @endif
    </p>

    <form method="GET" action="{{ $baseUrl }}" class="sf-row" style="gap:8px">
        @foreach ($carried as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach

        <label class="sf-sr-only" for="sf-sort-select">Urutkan hasil</label>
        <select class="sf-select" id="sf-sort-select" name="sort" style="width:auto;min-width:190px"
                onchange="this.form.submit()">
            @foreach ($options as $value => $optionLabel)
                <option value="{{ $value }}" @selected($currentSort === $value)>{{ $optionLabel }}</option>
            @endforeach
        </select>

        <button type="submit" class="sf-btn sf-btn--outline sf-btn--sm sf-hide-mobile">Urutkan</button>
    </form>
</div>
