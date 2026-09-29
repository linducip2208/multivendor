<?php

declare(strict_types=1);

namespace App\Search\Drivers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Search\Contracts\SearchDriver;
use App\Search\FacetBuilder;
use App\Search\QueryPlanner;
use App\Search\SearchPlan;
use App\Search\SearchQuery;
use App\Search\SearchResult;
use App\Search\SpellCorrector;
use App\Search\SynonymRepository;
use App\Search\TypoWindow;
use App\Support\Currency;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Portable MySQL search driver.
 *
 * Runs on every database the project supports without requiring a FULLTEXT
 * index. Relevance is a deterministic SQL expression so pagination stays
 * correct. When the strict pass finds nothing a second, typo-tolerant pass runs
 * and every candidate is re-verified through {@see TextNormalizer::similarity()}
 * before it is shown.
 */
class DatabaseSearchDriver implements SearchDriver
{
    private const TYPO_COLUMNS = [
        'products.name',
        'products.sku',
        'products.short_description',
        'brands.name',
    ];

    private static string|false|null $attributeValueColumn = false;

    public function __construct(
        private readonly QueryPlanner $planner = new QueryPlanner,
    ) {}

    public function name(): string
    {
        return 'database';
    }

    public function search(SearchQuery $query): SearchResult
    {
        $started = microtime(true);

        if (trim($query->term) !== '' && TextNormalizer::normalize($query->term) === '') {
            return new SearchResult(
                items: new Collection,
                total: 0,
                page: $query->page,
                perPage: $query->perPage,
                facets: (new FacetBuilder($query, $this->planner->plan($query)))->empty(),
                tookMs: round((microtime(true) - $started) * 1000, 1),
                paginator: null,
                meta: ['driver' => 'database', 'typo_applied' => false, 'operators' => [], 'phrases' => [], 'excluded' => []],
            );
        }

        $plan = $this->planner->plan($query);

        $typoMode = false;
        $paginator = $this->strictPage($query, $plan);

        if ($paginator->total() === 0 && $plan->typoEligible && $query->typoTolerance && $query->page <= 1) {
            $typoMode = true;
            $paginator = $this->typoPage($query, $plan);
        }

        $facets = $paginator->total() === 0
            ? (new FacetBuilder($query, $plan))->empty()
            : (new FacetBuilder($query, $plan))->build($this->baseQuery($query, $plan, $typoMode));

        return new SearchResult(
            items: new Collection($paginator->items()),
            total: $paginator->total(),
            page: $query->page,
            perPage: $query->perPage,
            facets: $facets,
            tookMs: round((microtime(true) - $started) * 1000, 1),
            paginator: $paginator,
            meta: [
                'driver' => 'database',
                'typo_applied' => $typoMode,
                'operators' => $plan->operatorsUsed(),
                'phrases' => $plan->parsed->phrases,
                'excluded' => $plan->parsed->mustNot,
            ],
        );
    }

    public function suggest(string $term, int $limit = 6): array
    {
        $normalised = TextNormalizer::normalize($term);
        $empty = ['products' => [], 'categories' => [], 'brands' => [], 'shops' => [], 'terms' => []];

        if ($normalised === '' || mb_strlen($normalised) < (int) config('search.suggest.min_length', 2)) {
            return $empty;
        }

        $window = TypoWindow::fromConfig();
        $threshold = (float) config('search.typo.similarity_threshold', 0.74);
        $candidates = $this->suggestNeedles($normalised, $window);

        return [
            'products' => $this->suggestProducts($normalised, $candidates, $limit, $threshold),
            'categories' => $this->suggestCategories($normalised, $candidates, min($limit, (int) config('search.suggest.category_limit', 4)), $threshold),
            'brands' => $this->suggestBrands($normalised, $candidates, min($limit, (int) config('search.suggest.brand_limit', 4)), $threshold),
            'shops' => $this->suggestShops($normalised, $candidates, min($limit, (int) config('search.suggest.shop_limit', 4)), $threshold),
            'terms' => $this->suggestTerms($term, $normalised, $limit),
        ];
    }

    /* ------------------------------------------------------------------ */

    private function strictPage(SearchQuery $query, SearchPlan $plan): LengthAwarePaginator
    {
        $builder = $this->baseQuery($query, $plan, false);
        $this->applySort($builder, $query, $plan);

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page)->withQueryString();
    }

    private function typoPage(SearchQuery $query, SearchPlan $plan): LengthAwarePaginator
    {
        $max = (int) config('search.typo.max_candidates', 250);
        $threshold = (float) config('search.typo.similarity_threshold', 0.74);

        $builder = $this->baseQuery($query, $plan, true);
        $this->applySort($builder, $query, $plan);

        $rows = $builder->limit(max(1, $max))->get();

        $verified = $rows
            ->filter(fn (Product $product) => $this->passesTypoVerification($product, $plan, $threshold))
            ->sortByDesc(fn (Product $product) => $this->typoScore($product, $plan))
            ->values();

        $total = $verified->count();
        $offset = ($query->page - 1) * $query->perPage;
        $items = $verified->slice($offset, $query->perPage)->values()->all();

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $query->perPage,
            $query->page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page'],
        );

        return $paginator->withQueryString();
    }

    private function baseQuery(SearchQuery $query, SearchPlan $plan, bool $typoMode): Builder
    {
        $builder = Product::query()
            ->from('products')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('shops', 'shops.id', '=', 'products.shop_id')
            ->where('products.status', 'approved')
            ->where('products.published', true)
            ->whereNotNull('products.shop_id')
            ->where('shops.status', 'active')
            ->whereNull('shops.deleted_at')
            ->select('products.*')
            ->with(['shop', 'category', 'brand']);

        $this->applyKeyword($builder, $plan, $typoMode);
        $this->applyFacets($builder, $plan);
        $this->applyAttributes($builder, $plan->attributeFilters);
        $this->applyExclusions($builder, $plan);

        return $builder;
    }

    private function applyKeyword(Builder $builder, SearchPlan $plan, bool $typoMode): void
    {
        if (! $plan->hasTerm()) {
            return;
        }

        $builder->where(function (Builder $outer) use ($plan, $typoMode): void {
            if ($typoMode) {
                $this->typoClause($outer, $plan);

                return;
            }

            $outer->where(function (Builder $strict) use ($plan): void {
                foreach ($plan->parsed->phrases as $phrase) {
                    $this->likeAny($strict, '%'.$this->escapeLike($phrase).'%');
                }

                foreach ($plan->variants as $group) {
                    $strict->where(function (Builder $token) use ($group): void {
                        $this->likeAny($token, '%'.$this->escapeLike($group[0]).'%');
                        foreach (array_slice($group, 1) as $variant) {
                            $this->likeAny($token, '%'.$this->escapeLike($variant).'%');
                        }
                    });
                }
            });
        });
    }

    private function typoClause(Builder $outer, SearchPlan $plan): void
    {
        $window = TypoWindow::fromConfig();

        $outer->where(function (Builder $inner) use ($plan, $window): void {
            foreach ($plan->variants as $group) {
                $inner->where(function (Builder $token) use ($group, $window): void {
                    $this->likeAny($token, '%'.$this->escapeLike($group[0]).'%');
                    foreach (array_slice($group, 1) as $variant) {
                        $this->likeAny($token, '%'.$this->escapeLike($variant).'%');
                    }

                    $token->orWhere(function (Builder $typo) use ($group, $window): void {
                        $this->patternClause($typo, $group, $window);
                        $this->anchorClause($typo, $group, $window);
                    });
                });
            }

            foreach ($plan->parsed->phrases as $phrase) {
                $this->likeAny($inner, '%'.$this->escapeLike($phrase).'%');
            }
        });
    }

    private function patternClause(Builder $builder, array $group, TypoWindow $window): void
    {
        $budget = (int) config('search.typo.max_total_patterns', 24);
        $patterns = [];

        foreach ($group as $variant) {
            foreach ($window->patterns($variant) as $pattern) {
                $patterns[$pattern] = true;
            }
        }

        $patterns = array_slice(array_keys($patterns), 0, max(1, $budget));
        if ($patterns === []) {
            return;
        }

        $columns = array_slice(self::TYPO_COLUMNS, 0, 2);
        $needles = [];
        foreach ($patterns as $pattern) {
            $needles[] = '%'.$this->escapeLike($pattern).'%';
        }

        $builder->orWhere(function (Builder $or) use ($columns, $needles): void {
            $or->where(function (Builder $all) use ($columns, $needles): void {
                foreach ($needles as $index => $needle) {
                    if ($index === 0) {
                        foreach ($columns as $column) {
                            $all->orWhere($column, 'like', $needle);
                        }

                        continue;
                    }

                    $all->orWhere(function (Builder $grouped) use ($columns, $needle): void {
                        foreach ($columns as $column) {
                            $grouped->orWhere($column, 'like', $needle);
                        }
                    });
                }
            });
        });
    }

    private function anchorClause(Builder $builder, array $group, TypoWindow $window): void
    {
        foreach ($group as $variant) {
            $anchors = $window->anchors($variant);
            if (count($anchors) < 3) {
                continue;
            }

            $sums = [];
            $bindings = [];
            foreach ($anchors as $anchor) {
                $needle = '%'.$this->escapeLike($anchor).'%';
                foreach (self::TYPO_COLUMNS as $column) {
                    $sums[] = 'CASE WHEN '.$column.' LIKE ? THEN 1 ELSE 0 END';
                    $bindings[] = $needle;
                }
            }

            $builder->orWhereRaw(
                '('.implode(' + ', $sums).') >= ?',
                array_merge($bindings, [$window->anchorThreshold($variant)])
            );
        }
    }

    private function likeAny(Builder $builder, string $needle): void
    {
        $builder->orWhere(function (Builder $or) use ($needle): void {
            foreach ($this->searchableColumns() as $column) {
                $or->orWhere($column, 'like', $needle);
            }
        });
    }

    private function applyFacets(Builder $builder, SearchPlan $plan): void
    {
        if ($plan->categoryIds !== []) {
            $builder->whereIn('products.category_id', $plan->categoryIds);
        }
        if ($plan->brandIds !== []) {
            $builder->whereIn('products.brand_id', $plan->brandIds);
        }
        if ($plan->shopIds !== []) {
            $builder->whereIn('products.shop_id', $plan->shopIds);
        }
        if ($plan->minPrice !== null) {
            $builder->where('products.price', '>=', $plan->minPrice);
        }
        if ($plan->maxPrice !== null) {
            $builder->where('products.price', '<=', $plan->maxPrice);
        }
        if ($plan->inStock === true) {
            $builder->where('products.current_stock', '>', 0);
        } elseif ($plan->inStock === false) {
            $builder->where('products.current_stock', '<=', 0);
        }
        if ($plan->minRating !== null && $plan->minRating > 0) {
            $builder->where('products.rating_average', '>=', $plan->minRating);
        }
        if ($plan->maxRating !== null) {
            $builder->where('products.rating_average', '<', $plan->maxRating);
        }
    }

    private function applyAttributes(Builder $builder, array $filters): void
    {
        foreach ($filters as $name => $values) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            foreach ((array) $values as $value) {
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }

                $legacyColumn = $this->attributeValueColumn();

                $builder->whereExists(function (QueryBuilder $sub) use ($name, $value, $legacyColumn): void {
                    $sub->selectRaw('1')
                        ->from('product_attributes as pa')
                        ->join('attributes as pa_attr', 'pa_attr.id', '=', 'pa.attribute_id')
                        ->leftJoin('attribute_values as pa_val', 'pa_val.id', '=', 'pa.attribute_value_id')
                        ->whereColumn('pa.product_id', 'products.id')
                        ->where('pa_attr.name', $name)
                        ->where(function (QueryBuilder $match) use ($value, $legacyColumn): void {
                            $match->where('pa_val.value', $value);

                            if ($legacyColumn !== null) {
                                $match->orWhere('pa.'.$legacyColumn, $value);
                            }
                        });
                });
            }
        }
    }

    private function attributeValueColumn(): ?string
    {
        if (self::$attributeValueColumn !== false) {
            return self::$attributeValueColumn;
        }

        try {
            self::$attributeValueColumn = Schema::hasColumn('product_attributes', 'value') ? 'value' : null;
        } catch (Throwable) {
            self::$attributeValueColumn = null;
        }

        return self::$attributeValueColumn;
    }

    private function applyExclusions(Builder $builder, SearchPlan $plan): void
    {
        $excluded = $plan->parsed->mustNot;
        if ($excluded === []) {
            return;
        }

        $builder->where(function (Builder $outer) use ($excluded): void {
            foreach ($excluded as $term) {
                $needle = '%'.$this->escapeLike($term).'%';
                $outer->where(function (Builder $inner) use ($needle): void {
                    foreach ($this->searchableColumns() as $column) {
                        $inner->orWhere($column, 'like', $needle);
                    }
                });
            }
        });
    }

    private function applySort(Builder $builder, SearchQuery $query, SearchPlan $plan): void
    {
        match ($query->sorts[0] ?? 'relevance') {
            'price_asc' => $builder->orderBy('products.price', 'asc')->orderBy('products.id', 'desc'),
            'price_desc' => $builder->orderBy('products.price', 'desc')->orderBy('products.id', 'asc'),
            'name' => $builder->orderBy('products.name', 'asc'),
            'newest' => $builder->orderByDesc('products.created_at')->orderByDesc('products.id'),
            'rating' => $builder->orderByDesc('products.rating_average')->orderByDesc('products.rating_count'),
            'popular' => $builder->orderByDesc('products.sold_count')->orderByDesc('products.view_count'),
            default => $this->applyRelevanceSort($builder, $plan),
        };
    }

    private function applyRelevanceSort(Builder $builder, SearchPlan $plan): void
    {
        if (! $plan->hasTerm()) {
            $builder->orderByDesc('products.featured')
                ->orderByDesc('products.sold_count')
                ->orderBy('products.id', 'desc');

            return;
        }

        $weights = (array) config('search.ranking.weights', []);
        $normalised = TextNormalizer::normalize($plan->parsed->raw);

        $score = [];
        $bindings = [];

        $score[] = sprintf('CASE WHEN products.sku = ? THEN %F ELSE 0 END', (float) ($weights['sku_exact'] ?? 1200));
        $bindings[] = $normalised;

        $score[] = sprintf('CASE WHEN products.barcode = ? THEN %F ELSE 0 END', (float) ($weights['barcode_exact'] ?? 1150));
        $bindings[] = $normalised;

        $score[] = sprintf('CASE WHEN products.name = ? THEN %F ELSE 0 END', (float) ($weights['name_exact'] ?? 1000));
        $bindings[] = $normalised;

        $score[] = sprintf('CASE WHEN products.name LIKE ? THEN %F ELSE 0 END', (float) ($weights['name_prefix'] ?? 700));
        $bindings[] = $this->escapeLike($normalised).'%';

        $score[] = sprintf('CASE WHEN products.name LIKE ? THEN %F ELSE 0 END', (float) ($weights['name_contains'] ?? 480));
        $bindings[] = '%'.$this->escapeLike($normalised).'%';

        $columns = $this->searchableColumns();

        foreach ($plan->variants as $group) {
            $primary = $group[0];

            $score[] = sprintf('CASE WHEN products.name LIKE ? THEN %F ELSE 0 END', (float) ($weights['token_name_prefix'] ?? 220));
            $bindings[] = $this->escapeLike($primary).'%';

            $coverage = [];
            foreach ($group as $variant) {
                $like = '%'.$this->escapeLike($variant).'%';
                foreach ($columns as $column) {
                    $coverage[] = $column.' LIKE ?';
                    $bindings[] = $like;
                }
            }

            $score[] = sprintf(
                'CASE WHEN (%s) THEN %F ELSE 0 END',
                implode(' OR ', $coverage),
                (float) ($weights['token_coverage'] ?? 150)
            );

            $score[] = sprintf('CASE WHEN categories.name LIKE ? THEN %F ELSE 0 END', (float) ($weights['category_match'] ?? 120));
            $bindings[] = '%'.$this->escapeLike($primary).'%';

            $score[] = sprintf('CASE WHEN brands.name LIKE ? THEN %F ELSE 0 END', (float) ($weights['brand_match'] ?? 110));
            $bindings[] = '%'.$this->escapeLike($primary).'%';

            $score[] = sprintf('CASE WHEN shops.name LIKE ? THEN %F ELSE 0 END', (float) ($weights['shop_match'] ?? 90));
            $bindings[] = '%'.$this->escapeLike($primary).'%';
        }

        foreach ($plan->parsed->phrases as $phrase) {
            $like = '%'.$this->escapeLike($phrase).'%';
            $score[] = sprintf(
                'CASE WHEN (products.name LIKE ? OR products.short_description LIKE ?) THEN %F ELSE 0 END',
                (float) ($weights['phrase_bonus'] ?? 320)
            );
            $bindings[] = $like;
            $bindings[] = $like;
        }

        $popularity = (array) config('search.ranking.popularity', []);
        // Portable capped ratios (CASE WHEN works on MySQL/SQLite/PgSQL;
        // LEAST() is MySQL-only and breaks sqlite test runs).
        $score[] = sprintf(
            'CASE WHEN COALESCE(products.sold_count, 0) > %d THEN %d ELSE COALESCE(products.sold_count, 0) END / %F',
            (int) ($popularity['sold_cap'] ?? 500),
            (int) ($popularity['sold_cap'] ?? 500),
            (float) ($popularity['sold_divisor'] ?? 10.0)
        );
        $score[] = sprintf(
            'CASE WHEN COALESCE(products.view_count, 0) > %d THEN %d ELSE COALESCE(products.view_count, 0) END / %F',
            (int) ($popularity['view_cap'] ?? 2000),
            (int) ($popularity['view_cap'] ?? 2000),
            (float) ($popularity['view_divisor'] ?? 100.0)
        );
        $score[] = sprintf('COALESCE(products.rating_average, 0) * %F', (float) ($weights['rating'] ?? 6));
        $score[] = 'CASE WHEN COALESCE(products.rating_count, 0) > 50 THEN 50 ELSE COALESCE(products.rating_count, 0) END / 10.0';

        $freshness = (array) config('search.ranking.freshness', []);
        $bonus = (array) ($freshness['bonus'] ?? []);
        foreach ((array) ($freshness['days'] ?? []) as $index => $day) {
            $score[] = sprintf('CASE WHEN products.created_at >= ? THEN %F ELSE 0 END', (float) ($bonus[$index] ?? 0));
            $bindings[] = now()->subDays((int) $day);
        }

        $score[] = sprintf('CASE WHEN products.current_stock > 0 THEN %F ELSE 0 END', (float) ($weights['in_stock'] ?? 45));
        $score[] = sprintf('CASE WHEN products.featured = 1 THEN %F ELSE 0 END', (float) ($weights['featured'] ?? 18));

        $builder
            ->selectRaw('('.implode(' + ', $score).') as relevance_score', $bindings)
            ->orderByDesc('relevance_score')
            ->orderBy('products.id', 'desc');
    }

    private function passesTypoVerification(Product $product, SearchPlan $plan, float $threshold): bool
    {
        $haystack = $this->haystack($product);

        foreach ($plan->variants as $group) {
            $matched = false;
            foreach ($group as $variant) {
                if (TypoWindow::verify($variant, $haystack, $threshold)) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                return false;
            }
        }

        $normalised = TextNormalizer::normalize($haystack);
        foreach ($plan->parsed->mustNot as $excluded) {
            if ($excluded !== '' && str_contains($normalised, $excluded)) {
                return false;
            }
        }

        return true;
    }

    private function typoScore(Product $product, SearchPlan $plan): float
    {
        $haystack = TextNormalizer::normalize($this->haystack($product));
        $total = 0.0;

        foreach ($plan->variants as $group) {
            $best = 0.0;
            foreach ($group as $variant) {
                $best = max($best, TypoWindow::score($variant, $haystack));
            }
            $total += $best;
        }

        return $total + min(5.0, ($product->sold_count ?? 0) / 100.0);
    }

    private function haystack(Product $product): string
    {
        return implode(' ', array_filter([
            (string) $product->name,
            (string) ($product->sku ?? ''),
            (string) ($product->short_description ?? ''),
            (string) ($product->search_keywords ?? ''),
            (string) ($product->brand?->name ?? ''),
            (string) ($product->category?->name ?? ''),
            (string) ($product->shop?->name ?? ''),
        ], static fn (string $value): bool => $value !== ''));
    }

    /* ------------------------------------------------------------------ */

    private function suggestNeedles(string $normalised, TypoWindow $window): array
    {
        $tokens = [];

        foreach (preg_split('/\s+/u', $normalised) ?: [] as $token) {
            $token = (string) $token;
            $tokens[$token] = true;
            $tokens[(string) TextNormalizer::stem($token)] = true;
            foreach (SynonymRepository::expand($token) as $synonym) {
                $synonym = (string) $synonym;
                $tokens[$synonym] = true;
                $tokens[(string) TextNormalizer::stem($synonym)] = true;
            }
        }

        $contains = [];
        $prefix = [];
        $typo = [];

        foreach (array_keys($tokens) as $token) {
            $token = (string) $token;
            if ($token === '') {
                continue;
            }
            $contains[$token] = true;
            $prefix[$token.'%'] = true;
            if ($window->isEligible($token)) {
                foreach ($window->patterns($token) as $pattern) {
                    $typo[$pattern] = true;
                }
            }
        }

        return [
            'contains' => array_keys($contains),
            'prefix' => array_keys($prefix),
            'typo' => array_slice(array_keys($typo), 0, 16),
        ];
    }

    private function suggestProducts(string $normalised, array $needles, int $limit, float $threshold): array
    {
        if ($limit <= 0 || $needles['contains'] === []) {
            return [];
        }

        try {
            $rows = Product::query()
                ->from('products')
                ->leftJoin('shops', 'shops.id', '=', 'products.shop_id')
                ->where('products.status', 'approved')
                ->where('products.published', true)
                ->where('shops.status', 'active')
                ->whereNull('shops.deleted_at')
                ->where(function (Builder $q) use ($needles): void {
                    $this->suggestWhere($q, $needles, ['products.name', 'products.sku', 'products.barcode']);
                })
                ->orderBy('products.sold_count', 'desc')
                ->limit($limit * 5)
                ->get(['products.id', 'products.name', 'products.slug', 'products.thumbnail', 'products.sku', 'products.price', 'products.special_price'])
                ->all();
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (count($out) >= $limit) {
                break;
            }
            if (! $this->suggestionIsClose($normalised, (string) $row->name, $threshold)) {
                continue;
            }

            $price = (float) $row->price;
            $special = $row->special_price === null ? null : (float) $row->special_price;

            $out[] = [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'sku' => (string) ($row->sku ?? ''),
                'thumbnail' => $row->thumbnail ? url('img/'.ltrim((string) $row->thumbnail, '/')) : null,
                'url' => $this->safeRoute('products.show', ['slug' => $row->slug]),
                'price_label' => Currency::format($special !== null && $special > 0 && $special < $price ? $special : $price),
                'shop_name' => null,
            ];
        }

        return $out;
    }

    private function suggestCategories(string $normalised, array $needles, int $limit, float $threshold): array
    {
        if ($limit <= 0 || $needles['contains'] === []) {
            return [];
        }

        try {
            $rows = Category::query()
                ->from('categories')
                ->where('categories.status', true)
                ->where(function (Builder $q) use ($needles): void {
                    $this->suggestWhere($q, $needles, ['categories.name']);
                })
                ->orderBy('categories.sort_order')
                ->limit($limit * 4)
                ->get(['categories.id', 'categories.name', 'categories.slug'])
                ->all();
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (count($out) >= $limit) {
                break;
            }
            if (! $this->suggestionIsClose($normalised, (string) $row->name, $threshold)) {
                continue;
            }
            $out[] = [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'url' => $this->safeRoute('categories.show', ['slug' => $row->slug]),
            ];
        }

        return $out;
    }

    private function suggestBrands(string $normalised, array $needles, int $limit, float $threshold): array
    {
        if ($limit <= 0 || $needles['contains'] === []) {
            return [];
        }

        try {
            $rows = DB::table('brands')
                ->where(function (QueryBuilder $q) use ($needles): void {
                    $this->suggestWhere($q, $needles, ['brands.name']);
                })
                ->orderBy('brands.name')
                ->limit($limit * 4)
                ->get(['brands.id', 'brands.name', 'brands.slug'])
                ->all();
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (count($out) >= $limit) {
                break;
            }
            if (! $this->suggestionIsClose($normalised, (string) $row->name, $threshold)) {
                continue;
            }
            $out[] = [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'url' => $this->safeRoute('brands.show', ['slug' => $row->slug]),
            ];
        }

        return $out;
    }

    private function suggestShops(string $normalised, array $needles, int $limit, float $threshold): array
    {
        if ($limit <= 0 || $needles['contains'] === []) {
            return [];
        }

        try {
            $rows = Shop::query()
                ->from('shops')
                ->where('shops.status', 'active')
                ->where(function (Builder $q) use ($needles): void {
                    $this->suggestWhere($q, $needles, ['shops.name']);
                })
                ->orderBy('shops.name')
                ->limit($limit * 4)
                ->get(['shops.id', 'shops.name', 'shops.slug'])
                ->all();
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (count($out) >= $limit) {
                break;
            }
            if (! $this->suggestionIsClose($normalised, (string) $row->name, $threshold)) {
                continue;
            }
            $out[] = [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'url' => $this->safeRoute('shop.show', ['slug' => $row->slug]),
            ];
        }

        return $out;
    }

    private function suggestWhere($builder, array $needles, array $columns): void
    {
        $builder->where(function ($inner) use ($needles, $columns): void {
            foreach ($needles['prefix'] as $needle) {
                foreach ($columns as $column) {
                    $inner->orWhere($column, 'like', $this->escapeLike($needle));
                }
            }

            foreach ($needles['contains'] as $needle) {
                foreach ($columns as $column) {
                    $inner->orWhere($column, 'like', '%'.$this->escapeLike($needle).'%');
                }
            }

            foreach ($needles['typo'] as $pattern) {
                foreach ($columns as $column) {
                    $inner->orWhere($column, 'like', '%'.$this->escapeLike($pattern).'%');
                }
            }
        });
    }

    private function suggestTerms(string $rawTerm, string $normalised, int $limit): array
    {
        $limit = (int) config('search.suggest.term_limit', 4);
        if ($limit <= 0) {
            return [];
        }

        $out = [];
        $searchUrl = $this->safeRoute('search', ['q' => $rawTerm]);

        foreach (SpellCorrector::suggest($normalised, (int) config('search.suggest.did_you_mean_limit', 3)) as $suggestion) {
            $out[] = [
                'kind' => 'correction',
                'label' => 'Adakah Anda mencari "'.$suggestion['term'].'"?',
                'term' => $suggestion['term'],
                'url' => $this->safeRoute('search', ['q' => $suggestion['term']]) ?? $searchUrl,
            ];
        }

        foreach (SynonymRepository::expand($normalised) as $synonym) {
            if (count($out) >= $limit) {
                break;
            }
            $out[] = [
                'kind' => 'synonym',
                'label' => 'Cari "'.$synonym.'"',
                'term' => $synonym,
                'url' => $this->safeRoute('search', ['q' => $synonym]) ?? $searchUrl,
            ];
        }

        if ($out === [] && $searchUrl !== null) {
            $out[] = [
                'kind' => 'all',
                'label' => 'Cari "'.$rawTerm.'" di seluruh katalog',
                'term' => $rawTerm,
                'url' => $searchUrl,
            ];
        }

        return array_slice($out, 0, $limit);
    }

    private function suggestionIsClose(string $normalised, string $candidate, float $threshold): bool
    {
        $candidate = TextNormalizer::normalize($candidate);
        if ($candidate === '' || $normalised === '') {
            return false;
        }

        if (str_contains($candidate, $normalised)) {
            return true;
        }

        $needleTokens = preg_split('/\s+/u', $normalised) ?: [];
        $candidateWords = preg_split('/\s+/u', $candidate) ?: [];

        foreach ($needleTokens as $token) {
            if (mb_strlen($token) < 3) {
                if (in_array($token, $candidateWords, true)) {
                    return true;
                }
                continue;
            }
            foreach ($candidateWords as $word) {
                if (TextNormalizer::similarity($token, $word) >= $threshold) {
                    return true;
                }
            }
        }

        return false;
    }

    private function searchableColumns(): array
    {
        return [
            'products.name',
            'products.sku',
            'products.barcode',
            'products.short_description',
            'products.search_keywords',
            'categories.name',
            'brands.name',
            'shops.name',
        ];
    }

    private function safeRoute(string $name, array $parameters): ?string
    {
        try {
            return route($name, $parameters);
        } catch (Throwable) {
            return null;
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
