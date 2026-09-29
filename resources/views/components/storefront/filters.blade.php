@props([
    'categories' => [],
    'brands' => [],
    'shops' => [],
    'query' => null,
    'priceRange' => ['min' => 0, 'max' => 0],
    'action' => null,
])

@php
    $action = $action ?: request()->url();
    $term = $query?->term ?? request('q', '');
    $currentSort = $query?->sorts[0] ?? 'relevance';
    $selectedCategories = $query?->categoryIds ?? [];
    $selectedBrands = $query?->brandIds ?? [];
    $selectedShops = $query?->shopIds ?? [];
    $minPrice = $query?->minPrice;
    $maxPrice = $query?->maxPrice;
    $minRating = $query?->minRating;
    $inStock = (bool) ($query?->inStockOnly ?? request()->boolean('in_stock'));

    $categoryOptions = collect($categories)->filter();
    $brandOptions = collect($brands)->filter();
    $shopOptions = collect($shops)->filter();

    $hasCategory = $categoryOptions->isNotEmpty();
    $hasBrand = $brandOptions->isNotEmpty();
    $hasShop = $shopOptions->isNotEmpty();
    $hasPrice = is_array($priceRange) && $priceRange !== [];
    $hasAnyFacet = $hasCategory || $hasBrand || $hasShop || $hasPrice;

    $hasFilters = $selectedCategories !== [] || $selectedBrands !== [] || $selectedShops !== []
        || $minPrice !== null || $maxPrice !== null || $minRating !== null || $inStock;

    $ceiling = $hasPrice ? (float) ($priceRange['max'] ?? 0) : 0;
@endphp

@if ($hasAnyFacet)
    <form method="GET" action="{{ $action }}" data-sf-filter-form class="sf-stack" style="gap:0">
        @if ($term !== '')
            <input type="hidden" name="q" value="{{ $term }}">
        @endif
        <input type="hidden" name="sort" value="{{ $currentSort }}">

        <div class="sf-row sf-row--wrap" style="gap:8px;padding-bottom:16px">
            <button type="submit" class="sf-btn sf-btn--primary sf-btn--sm">Terapkan</button>
            @if ($hasFilters)
                <a href="{{ $action }}{{ $term !== '' ? '?q='.urlencode($term) : '' }}" class="sf-btn sf-btn--ghost sf-btn--sm">Reset</a>
            @endif
        </div>

        @if ($hasCategory)
            <div class="sf-facet">
                <button type="button" class="sf-facet__title" data-facet-toggle aria-expanded="true" aria-controls="sf-facet-cat">
                    Kategori
                    <x-storefront.icon name="chevron-down" :size="15" />
                </button>
                <div class="sf-facet__body" id="sf-facet-cat">
                    <div class="sf-facet__list">
                        @foreach ($categoryOptions as $category)
                            <label class="sf-checkbox" for="sf-cat-{{ $category->id }}">
                                <input type="checkbox" id="sf-cat-{{ $category->id }}" name="category[]" value="{{ $category->id }}"
                                       @checked(in_array((int) $category->id, array_map('intval', $selectedCategories), true))>
                                <span class="sf-truncate">{{ $category->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @if ($hasBrand)
            <div class="sf-facet">
                <button type="button" class="sf-facet__title" data-facet-toggle aria-expanded="true" aria-controls="sf-facet-brand">
                    Merek
                    <x-storefront.icon name="chevron-down" :size="15" />
                </button>
                <div class="sf-facet__body" id="sf-facet-brand">
                    <div class="sf-facet__list">
                        @foreach ($brandOptions as $brand)
                            <label class="sf-checkbox" for="sf-brand-{{ $brand->id }}">
                                <input type="checkbox" id="sf-brand-{{ $brand->id }}" name="brand[]" value="{{ $brand->id }}"
                                       @checked(in_array((int) $brand->id, array_map('intval', $selectedBrands), true))>
                                <span class="sf-truncate">{{ $brand->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @if ($hasShop)
            <div class="sf-facet">
                <button type="button" class="sf-facet__title" data-facet-toggle aria-expanded="true" aria-controls="sf-facet-shop">
                    Toko
                    <x-storefront.icon name="chevron-down" :size="15" />
                </button>
                <div class="sf-facet__body" id="sf-facet-shop">
                    <div class="sf-facet__list">
                        @foreach ($shopOptions as $shop)
                            <label class="sf-checkbox" for="sf-shop-{{ $shop->id }}">
                                <input type="checkbox" id="sf-shop-{{ $shop->id }}" name="shop[]" value="{{ $shop->id }}"
                                       @checked(in_array((int) $shop->id, array_map('intval', $selectedShops), true))>
                                <span class="sf-truncate">{{ $shop->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @if ($hasPrice)
            <div class="sf-facet">
                <h3 class="sf-facet__title">Rentang Harga</h3>
                <div class="sf-facet__body">
                    <div class="sf-row" style="gap:8px">
                        <div class="sf-field" style="flex:1 1 0;min-width:0">
                            <label class="sf-label" for="sf-min-price">Minimum</label>
                            <input class="sf-input" type="number" id="sf-min-price" name="min_price" inputmode="numeric" min="0"
                                   step="any" placeholder="{{ \App\Support\Currency::number(0) }}"
                                   value="{{ $minPrice !== null ? \App\Support\Currency::number($minPrice) : '' }}"
                                   @if ($ceiling > 0) max="{{ \App\Support\Currency::number($ceiling) }}" @endif>
                        </div>
                        <div class="sf-field" style="flex:1 1 0;min-width:0">
                            <label class="sf-label" for="sf-max-price">Maksimum</label>
                            <input class="sf-input" type="number" id="sf-max-price" name="max_price" inputmode="numeric" min="0"
                                   step="any" placeholder="{{ \App\Support\Currency::number($ceiling) }}"
                                   value="{{ $maxPrice !== null ? \App\Support\Currency::number($maxPrice) : '' }}">
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="sf-facet">
            <h3 class="sf-facet__title">Rating Minimum</h3>
            <div class="sf-facet__body">
                <div class="sf-facet__list">
                    @foreach ([4 => '4 ke atas', 3 => '3 ke atas', 2 => '2 ke atas'] as $value => $label)
                        <label class="sf-radio" for="sf-rating-{{ $value }}">
                            <input type="radio" id="sf-rating-{{ $value }}" name="min_rating" value="{{ $value }}"
                                   @checked($minRating !== null && (float) $minRating === (float) $value)>
                            <span class="sf-row" style="gap:5px">
                                <span class="sf-rating__stars" aria-hidden="true">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <x-storefront.icon name="star" :size="12" :stroke="0" :class="$i <= $value ? '' : 'sf-subtle'"
                                            style="color:{{ $i <= $value ? '#f59e0b' : 'var(--sf-text-subtle)' }}" />
                                    @endfor
                                </span>
                                <span class="sf-small">{{ $label }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="sf-facet">
            <div class="sf-facet__body">
                <label class="sf-checkbox" for="sf-in-stock">
                    <input type="checkbox" id="sf-in-stock" name="in_stock" value="1" @checked($inStock)>
                    <span>Stok tersedia</span>
                </label>
            </div>
        </div>
    </form>
@endif
