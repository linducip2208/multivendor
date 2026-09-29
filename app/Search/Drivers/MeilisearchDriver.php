<?php

declare(strict_types=1);

namespace App\Search\Drivers;

use App\Search\Exceptions\SearchUnavailable;
use App\Search\SearchQuery;
use App\Search\SearchResult;
use App\Search\TypoWindow;
use App\Support\TextNormalizer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Meilisearch driver.
 *
 * Speaks the Meilisearch v1 HTTP API directly so no SDK is required. Every
 * transport or protocol failure raises {@see SearchUnavailable}, which
 * {@see \App\Search\SearchManager} catches to fall back to the database driver.
 */
class MeilisearchDriver extends AbstractExternalDriver
{
    public function name(): string
    {
        return 'meilisearch';
    }

    public function search(SearchQuery $query): SearchResult
    {
        $started = microtime(true);
        $this->guardConfiguration();

        $payload = [
            'q' => $query->term,
            'offset' => ($query->page - 1) * $query->perPage,
            'limit' => $query->perPage,
            'filter' => $this->buildFilter($query),
            'facets' => array_keys((array) config('search.meilisearch.filterable', [])),
            'sort' => $this->buildSort($query),
            'attributesToRetrieve' => ['*'],
            'showRankingScore' => true,
            'matchingStrategy' => 'all',
        ];

        $index = (string) config('search.meilisearch.index', 'products');
        $response = $this->request('POST', $this->endpoint('/indexes/'.$index.'/search'), ['json' => $payload]);

        $hits = (array) ($response['hits'] ?? []);
        $total = (int) ($response['estimatedTotalHits'] ?? $response['nbHits'] ?? 0);
        $items = [];
        foreach (array_values($hits) as $index2 => $hit) {
            $items[] = $this->normaliseHits((array) $hit, $index2 + 1);
        }

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $query->perPage,
            $query->page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page'],
        );

        return new SearchResult(
            items: new Collection($items),
            total: $total,
            page: $query->page,
            perPage: $query->perPage,
            facets: $this->buildFacets($response, $query),
            tookMs: round((microtime(true) - $started) * 1000, 1),
            paginator: $paginator,
        );
    }

    public function suggest(string $term, int $limit = 6): array
    {
        $this->guardConfiguration();
        $normalised = TextNormalizer::normalize($term);
        if ($normalised === '' || mb_strlen($normalised) < (int) config('search.suggest.min_length', 2)) {
            return $this->emptySuggest();
        }

        $window = TypoWindow::fromConfig();
        $requests = [];
        $types = [];
        $indexes = (array) config('search.meilisearch.suggest_index', ['products' => 'products']);

        foreach ($indexes as $type => $index) {
            $requests[] = [
                'indexUid' => (string) $index,
                'q' => $normalised,
                'limit' => $type === 'products' ? $limit : min($limit, 4),
                'attributesToRetrieve' => ['id', 'name', 'slug', 'sku', 'thumbnail', 'price', 'effective_price', 'shop_name', 'type'],
                'filter' => $this->suggestFilter((string) $type),
            ];
            $types[] = (string) $type;
        }

        if ($window->isEligible($normalised)) {
            foreach ($window->patterns($normalised) as $pattern) {
                $requests[] = [
                    'indexUid' => (string) ($indexes['products'] ?? 'products'),
                    'q' => '',
                    'limit' => $limit,
                    'filter' => $this->suggestFilter('products').' AND (name LIKE \'%'.$this->escapeLike($pattern).'%\' OR sku LIKE \'%'.$this->escapeLike($pattern).'%\')',
                ];
                $types[] = 'products';
            }
        }

        $response = $this->request('POST', $this->endpoint('/multi-search'), ['json' => ['queries' => $requests]]);
        $results = array_values((array) ($response['results'] ?? []));

        $products = [];
        $categories = [];
        $brands = [];
        $shops = [];

        foreach ($results as $index => $result) {
            $type = $types[$index] ?? 'products';

            foreach ((array) ($result['hits'] ?? []) as $hit) {
                $hit = (array) $hit;
                $documentType = (string) ($hit['type'] ?? $type);

                if ($documentType === 'product' || $documentType === 'products' || ($documentType !== 'category' && $documentType !== 'brand' && $documentType !== 'shop')) {
                    if (count($products) >= $limit) {
                        continue;
                    }

                    $normalisedHit = $this->normaliseHits($hit, count($products) + 1);
                    $products[] = [
                        'id' => $normalisedHit['id'],
                        'name' => $normalisedHit['name'],
                        'sku' => $normalisedHit['sku'],
                        'thumbnail' => $normalisedHit['thumbnail'],
                        'url' => $normalisedHit['url'],
                        'price_label' => $normalisedHit['price_label'],
                        'shop_name' => $normalisedHit['shop'],
                    ];

                    continue;
                }

                if ($documentType === 'category' || $documentType === 'categories') {
                    if (count($categories) < min($limit, 4)) {
                        $categories[] = $this->entitySuggestion('category', $hit);
                    }

                    continue;
                }

                if ($documentType === 'brand' || $documentType === 'brands') {
                    if (count($brands) < min($limit, 4)) {
                        $brands[] = $this->entitySuggestion('brand', $hit);
                    }

                    continue;
                }

                if (count($shops) < min($limit, 4)) {
                    $shops[] = $this->entitySuggestion('shop', $hit);
                }
            }
        }

        return [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'shops' => $shops,
            'terms' => $products === [] ? [[
                'kind' => 'all',
                'label' => 'Cari "'.$term.'" di seluruh katalog',
                'term' => $term,
                'url' => $this->safeRoute('search', ['q' => $term]),
            ]] : [],
        ];
    }

    public function settings(): array
    {
        $this->guardConfiguration();

        return $this->request('PATCH', $this->endpoint('/indexes/'.config('search.meilisearch.index', 'products').'/settings'), [
            'json' => [
                'searchableAttributes' => (array) config('search.meilisearch.searchable', []),
                'filterableAttributes' => (array) config('search.meilisearch.filterable', []),
                'sortableAttributes' => (array) config('search.meilisearch.sortable', []),
                'stopWords' => (array) config('search.meilisearch.stop_words', []),
                'typoTolerance' => (array) config('search.meilisearch.typo_tolerance', []),
                'synonyms' => (array) config('search.meilisearch.synonyms', []),
                'rankingRules' => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'],
            ],
        ]);
    }

    public function health(): array
    {
        $this->guardConfiguration();

        return $this->request('GET', $this->endpoint('/health'));
    }

    public function pushDocuments(array $documents, ?string $primaryKey = 'id'): array
    {
        $this->guardConfiguration();
        $index = (string) config('search.meilisearch.index', 'products');

        return $this->request('POST', $this->endpoint('/indexes/'.$index.'/documents?primaryKey='.urlencode($primaryKey)), [
            'json' => array_values($documents),
        ]);
    }

    /* ------------------------------------------------------------------ */

    protected function headers(): array
    {
        $key = (string) config('search.meilisearch.key', '');

        return $key === '' ? [] : ['Authorization' => 'Bearer '.$key];
    }

    protected function timeout(): int
    {
        return (int) config('search.meilisearch.timeout', 3);
    }

    protected function connectTimeout(): int
    {
        return (int) config('search.meilisearch.connect_timeout', 2);
    }

    private function endpoint(string $path): string
    {
        return ((string) config('search.meilisearch.host', '')).$path;
    }

    private function guardConfiguration(): void
    {
        $host = (string) config('search.meilisearch.host', '');

        if ($host === '' || ! preg_match('#^https?://#i', $host)) {
            throw SearchUnavailable::misconfigured($this->name(), 'search.meilisearch.host');
        }
    }

    private function buildFilter(SearchQuery $query): array
    {
        $filters = [
            'status = approved',
            'published = true',
        ];

        if ($query->categoryIds !== []) {
            $filters[] = 'category_id IN ['.implode(',', array_map('intval', $query->categoryIds)).']';
        }
        if ($query->brandIds !== []) {
            $filters[] = 'brand_id IN ['.implode(',', array_map('intval', $query->brandIds)).']';
        }
        if ($query->shopIds !== []) {
            $filters[] = 'shop_id IN ['.implode(',', array_map('intval', $query->shopIds)).']';
        }
        if ($query->minPrice !== null) {
            $filters[] = 'price >= '.(float) $query->minPrice;
        }
        if ($query->maxPrice !== null) {
            $filters[] = 'price <= '.(float) $query->maxPrice;
        }
        if ($query->minRating !== null && $query->minRating > 0) {
            $filters[] = 'rating_average >= '.(float) $query->minRating;
        }
        if ($query->inStockOnly) {
            $filters[] = 'in_stock = 1';
        }

        foreach ($query->attributeFilters as $name => $values) {
            if (! is_string($name) || $name === '') {
                continue;
            }
            foreach ((array) $values as $value) {
                $filters[] = 'attribute_values = \''.$this->escapeFilterValue($name.':'.$value).'\'';
            }
        }

        return $filters;
    }

    private function buildSort(SearchQuery $query): array
    {
        return match ($query->sorts[0] ?? 'relevance') {
            'price_asc' => ['price:asc'],
            'price_desc' => ['price:desc'],
            'newest' => ['created_at:desc'],
            'rating' => ['rating_average:desc', 'rating_count:desc'],
            'popular' => ['sold_count:desc', 'view_count:desc'],
            'name' => ['name:asc'],
            default => [],
        };
    }

    private function buildFacets(array $response, SearchQuery $query): array
    {
        $distribution = (array) ($response['facetDistribution'] ?? []);
        $facets = ['categories' => [], 'brands' => [], 'shops' => [], 'price' => [], 'rating' => [], 'in_stock' => [], 'attributes' => [], 'total' => (int) ($response['estimatedTotalHits'] ?? 0)];

        foreach ($distribution as $attribute => $values) {
            foreach ((array) $values as $value => $count) {
                switch ($attribute) {
                    case 'category_names':
                    case 'brand_name':
                    case 'shop_name':
                        $facets[match ($attribute) {
                            'category_names' => 'categories',
                            'brand_name' => 'brands',
                            default => 'shops',
                        }][(string) $value] = ['name' => (string) $value, 'count' => (int) $count];
                        break;
                    case 'attribute_values':
                        $facets['attributes'][(string) $value] = ['value' => (string) $value, 'count' => (int) $count];
                        break;
                    default:
                        $facets[$attribute][(string) $value] = ['value' => $value, 'count' => (int) $count];
                }
            }
        }

        return $facets;
    }

    private function suggestFilter(string $type): string
    {
        return $type === 'products' ? 'status = approved AND published = true' : 'status = true';
    }

    private function escapeFilterValue(string $value): string
    {
        return str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
