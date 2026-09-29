<?php

declare(strict_types=1);

namespace App\Search\Drivers;

use App\Search\Exceptions\SearchUnavailable;
use App\Search\SearchQuery;
use App\Search\SearchResult;
use App\Support\TextNormalizer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Typesense driver.
 *
 * Speaks the Typesense v24+ HTTP API directly so no SDK is required. Every
 * transport or protocol failure raises {@see SearchUnavailable}, which
 * {@see \App\Search\SearchManager} catches to fall back to the database driver.
 */
class TypesenseDriver extends AbstractExternalDriver
{
    public function name(): string
    {
        return 'typesense';
    }

    public function search(SearchQuery $query): SearchResult
    {
        $started = microtime(true);
        $this->guardConfiguration();

        $parameters = [
            'q' => $query->term === '' ? '*' : $query->term,
            'query_by' => (string) config('search.typesense.query_by', 'name,sku'),
            'query_by_weights' => (string) config('search.typesense.query_by_weights', ''),
            'page' => $query->page,
            'per_page' => min($query->perPage, (int) config('search.typesense.pagination.max_hits', 5000)),
            'filter_by' => $this->buildFilter($query),
            'facet_by' => (string) config('search.typesense.facet_by', ''),
            'max_facet_values' => (int) config('search.facets.limit', 12),
            'sort_by' => $this->buildSort($query),
            'exhaustive_search' => 'true',
            'include_fields' => '*',
        ];

        $collection = (string) config('search.typesense.collection', 'products');
        $response = $this->request('GET', $this->endpoint('/collections/'.$collection.'/documents/search'), ['query' => $parameters]);

        $hits = (array) ($response['hits'] ?? []);
        $items = [];
        foreach (array_values($hits) as $index => $hit) {
            $items[] = $this->normaliseHits((array) ($hit['document'] ?? $hit), (int) ($index + 1));
        }

        $total = (int) ($response['found'] ?? count($items));
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
            facets: $this->buildFacets($response),
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

        $collections = (array) config('search.typesense.suggest_collections', []);
        $searches = [];
        $types = [];

        foreach ($collections as $type => $collection) {
            $searches[] = [
                'collection' => (string) $collection,
                'q' => $normalised,
                'query_by' => 'name',
                'per_page' => $type === 'products' ? $limit : min($limit, 4),
                'prefix' => $type === 'products' ? 'false,true' : 'true',
            ];
            $types[] = (string) $type;
        }

        if ($searches === []) {
            return $this->emptySuggest();
        }

        $response = $this->request('GET', $this->endpoint('/multi_search'), ['query' => ['searches' => $searches]]);

        $products = [];
        $categories = [];
        $brands = [];
        $shops = [];

        foreach (array_values((array) ($response['results'] ?? [])) as $index => $result) {
            $type = $types[$index] ?? 'products';

            foreach ((array) ($result['hits'] ?? []) as $hit) {
                $document = (array) ($hit['document'] ?? $hit);

                if ($type === 'products') {
                    if (count($products) >= $limit) {
                        continue;
                    }
                    $item = $this->normaliseHits($document, count($products) + 1);
                    $products[] = [
                        'id' => $item['id'],
                        'name' => $item['name'],
                        'sku' => $item['sku'],
                        'thumbnail' => $item['thumbnail'],
                        'url' => $item['url'],
                        'price_label' => $item['price_label'],
                        'shop_name' => $item['shop'],
                    ];

                    continue;
                }

                if ($type === 'categories') {
                    if (count($categories) < min($limit, 4)) {
                        $categories[] = $this->entitySuggestion('category', $document);
                    }

                    continue;
                }

                if ($type === 'brands') {
                    if (count($brands) < min($limit, 4)) {
                        $brands[] = $this->entitySuggestion('brand', $document);
                    }

                    continue;
                }

                if (count($shops) < min($limit, 4)) {
                    $shops[] = $this->entitySuggestion('shop', $document);
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

    public function health(): array
    {
        $this->guardConfiguration();

        return $this->request('GET', $this->endpoint('/health'));
    }

    public function collection(string $name): array
    {
        $this->guardConfiguration();

        return $this->request('GET', $this->endpoint('/collections/'.$name));
    }

    public function importDocuments(string $jsonLines, string $action = 'upsert'): array
    {
        $this->guardConfiguration();
        $collection = (string) config('search.typesense.collection', 'products');

        try {
            $response = $this->http()
                ->withBody($jsonLines, 'text/plain')
                ->post($this->endpoint('/collections/'.$collection.'/documents/import').'?action='.urlencode($action));
        } catch (Throwable $exception) {
            throw SearchUnavailable::connection($this->name(), $this->endpoint('/import'), $exception);
        }

        return $this->decode($response, $this->endpoint('/import'));
    }

    /* ------------------------------------------------------------------ */

    protected function headers(): array
    {
        $key = (string) config('search.typesense.api_key', '');

        return $key === '' ? [] : ['X-TYPESENSE-API-KEY' => $key];
    }

    protected function timeout(): int
    {
        return (int) config('search.typesense.timeout', 3);
    }

    protected function connectTimeout(): int
    {
        return (int) config('search.typesense.connect_timeout', 2);
    }

    private function endpoint(string $path): string
    {
        return ((string) config('search.typesense.host', '')).$path;
    }

    private function guardConfiguration(): void
    {
        $host = (string) config('search.typesense.host', '');
        $key = (string) config('search.typesense.api_key', '');

        if ($host === '' || ! preg_match('#^https?://#i', $host)) {
            throw SearchUnavailable::misconfigured($this->name(), 'search.typesense.host');
        }

        if ($key === '') {
            throw SearchUnavailable::misconfigured($this->name(), 'search.typesense.api_key');
        }
    }

    private function buildFilter(SearchQuery $query): string
    {
        $filters = [(string) config('search.typesense.filter_by', 'status := approved && published := true')];

        if ($query->categoryIds !== []) {
            $filters[] = 'category_id:=[('.implode(',', array_map('intval', $query->categoryIds)).')]';
        }
        if ($query->brandIds !== []) {
            $filters[] = 'brand_id:=[('.implode(',', array_map('intval', $query->brandIds)).')]';
        }
        if ($query->shopIds !== []) {
            $filters[] = 'shop_id:=[('.implode(',', array_map('intval', $query->shopIds)).')]';
        }
        if ($query->minPrice !== null) {
            $filters[] = 'price:>='.(float) $query->minPrice;
        }
        if ($query->maxPrice !== null) {
            $filters[] = 'price:<='.(float) $query->maxPrice;
        }
        if ($query->minRating !== null && $query->minRating > 0) {
            $filters[] = 'rating_average:>='.(float) $query->minRating;
        }
        if ($query->inStockOnly) {
            $filters[] = 'in_stock:=1';
        }

        foreach ($query->attributeFilters as $name => $values) {
            if (! is_string($name) || $name === '') {
                continue;
            }
            foreach ((array) $values as $value) {
                $filters[] = 'attribute_values:='.$this->escapeValue($name.':'.$value);
            }
        }

        return implode(' && ', $filters);
    }

    private function buildSort(SearchQuery $query): string
    {
        return match ($query->sorts[0] ?? 'relevance') {
            'price_asc' => 'price:asc',
            'price_desc' => 'price:desc',
            'newest' => 'created_at:desc',
            'rating' => 'rating_average:desc,rating_count:desc',
            'popular' => 'sold_count:desc,view_count:desc',
            'name' => 'name:asc',
            default => '_text_match:desc,popularity:desc',
        };
    }

    private function buildFacets(array $response): array
    {
        $facets = (array) ($response['facet_counts'] ?? []);
        $out = ['categories' => [], 'brands' => [], 'shops' => [], 'price' => [], 'rating' => [], 'in_stock' => [], 'attributes' => [], 'total' => (int) ($response['found'] ?? 0)];

        foreach ($facets as $facet) {
            $name = (string) ($facet['field_name'] ?? '');
            $counts = (array) ($facet['counts'] ?? []);

            switch ($name) {
                case 'category_names':
                case 'brand_name':
                case 'shop_name':
                    $key = match ($name) {
                        'category_names' => 'categories',
                        'brand_name' => 'brands',
                        default => 'shops',
                    };
                    foreach ($counts as $count) {
                        $out[$key][(string) $count['value']] = ['name' => (string) $count['value'], 'count' => (int) $count['count']];
                    }
                    break;
                case 'attribute_values':
                    foreach ($counts as $count) {
                        $out['attributes'][(string) $count['value']] = ['value' => (string) $count['value'], 'count' => (int) $count['count']];
                    }
                    break;
                default:
                    $out[$name] = $counts;
            }
        }

        return $out;
    }

    private function escapeValue(string $value): string
    {
        return '`'.str_replace(['\\', '`'], ['\\\\', '\\`'], $value).'`';
    }
}
