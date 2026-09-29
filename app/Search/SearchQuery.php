<?php

declare(strict_types=1);

namespace App\Search;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Immutable, driver-agnostic search request.
 *
 * Built by an HTTP request (storefront or API) and consumed by any
 * {@see \App\Search\Contracts\SearchDriver}.
 */
final class SearchQuery
{
    /**
     * @param  list<int>  $categoryIds
     * @param  list<int>  $brandIds
     * @param  list<int>  $shopIds
     * @param  list<string>  $attributeFilters  e.g. ['Warna'=>'Merah']
     * @param  list<string>  $sorts  e.g. ['relevance', 'price_asc']
     */
    public function __construct(
        public readonly string $term = '',
        public readonly array $categoryIds = [],
        public readonly array $brandIds = [],
        public readonly array $shopIds = [],
        public readonly ?float $minPrice = null,
        public readonly ?float $maxPrice = null,
        public readonly ?float $minRating = null,
        public readonly bool $inStockOnly = false,
        public readonly ?string $destination = null,
        public readonly array $attributeFilters = [],
        public readonly array $sorts = ['relevance'],
        public readonly int $page = 1,
        public readonly int $perPage = 24,
        public readonly bool $typoTolerance = true,
        public readonly ?string $driver = null,
    ) {}

    public static function fromRequest(\Illuminate\Http\Request $request, int $perPage = 24): self
    {
        $toArray = static fn (mixed $v): array => is_array($v)
            ? array_values(array_filter(array_map('intval', $v)))
            : (is_string($v) && $v !== '' ? array_values(array_filter(array_map('intval', explode(',', $v)))) : []);

        $term = trim((string) $request->input('q', $request->input('search', '')));

        return new self(
            term: $term,
            categoryIds: $toArray($request->input('category')),
            brandIds: $toArray($request->input('brand')),
            shopIds: $toArray($request->input('shop')),
            minPrice: $request->filled('min_price') ? (float) $request->input('min_price') : null,
            maxPrice: $request->filled('max_price') ? (float) $request->input('max_price') : null,
            minRating: $request->filled('min_rating') ? (float) $request->input('min_rating') : null,
            inStockOnly: $request->boolean('in_stock'),
            destination: $request->input('destination'),
            attributeFilters: (array) $request->input('attributes', []),
            sorts: array_values(array_filter([(string) $request->input('sort', 'relevance')])),
            page: max(1, (int) $request->input('page', 1)),
            perPage: max(1, min(72, $perPage)),
            typoTolerance: ! $request->boolean('exact'),
        );
    }

    public function hasTerm(): bool
    {
        return $this->term !== '';
    }

    public function hasFilters(): bool
    {
        return $this->categoryIds !== []
            || $this->brandIds !== []
            || $this->shopIds !== []
            || $this->minPrice !== null
            || $this->maxPrice !== null
            || $this->minRating !== null
            || $this->inStockOnly
            || $this->attributeFilters !== [];
    }

    /** Any filter or sort other than the default makes the page non-canonical. */
    public function isFaceted(): bool
    {
        return $this->hasFilters() || ($this->sorts[0] ?? 'relevance') !== 'relevance' || $this->page > 1;
    }

    public function tokens(bool $stem = false): array
    {
        return \App\Support\TextNormalizer::tokenize($this->term, $stem);
    }

    public function withPage(int $page): self
    {
        return new self(
            $this->term, $this->categoryIds, $this->brandIds, $this->shopIds,
            $this->minPrice, $this->maxPrice, $this->minRating, $this->inStockOnly,
            $this->destination, $this->attributeFilters, $this->sorts,
            max(1, $page), $this->perPage, $this->typoTolerance, $this->driver,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'q' => $this->term ?: null,
            'category' => $this->categoryIds ?: null,
            'brand' => $this->brandIds ?: null,
            'shop' => $this->shopIds ?: null,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'min_rating' => $this->minRating,
            'in_stock' => $this->inStockOnly ? 1 : null,
            'attributes' => $this->attributeFilters ?: null,
            'sort' => $this->sorts[0] ?? null,
        ], static fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    public function cacheKey(string $prefix = 'search:query'): string
    {
        return $prefix.':'.sha1($this->cachePayload());
    }

    public function cachePayload(): string
    {
        return (string) json_encode([
            'term' => mb_strtolower($this->term),
            'category' => $this->categoryIds,
            'brand' => $this->brandIds,
            'shop' => $this->shopIds,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'min_rating' => $this->minRating,
            'in_stock' => $this->inStockOnly,
            'destination' => $this->destination,
            'attributes' => $this->attributeFilters,
            'sort' => $this->sorts,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'typo' => $this->typoTolerance,
            'driver' => $this->driver,
        ]);
    }
}
