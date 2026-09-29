<?php

declare(strict_types=1);

namespace App\Search;

use App\Support\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class FacetBuilder
{
    public function __construct(private readonly SearchQuery $query, private readonly SearchPlan $plan) {}

    public function build(Builder $base): array
    {
        if (! (bool) config('search.facets.enabled', true)) {
            return $this->empty();
        }

        $ids = $this->idSubquery($base);
        $limit = (int) config('search.facets.limit', 12);

        return [
            'categories' => $this->categoryFacet($ids, $limit),
            'brands' => $this->brandFacet($ids, $limit),
            'shops' => $this->shopFacet($ids, $limit),
            'price' => $this->priceFacet($ids),
            'rating' => $this->ratingFacet($ids),
            'in_stock' => $this->stockFacet($ids),
            'attributes' => $this->attributeFacet($ids),
            'total' => $this->countFacet($ids),
        ];
    }

    public function empty(): array
    {
        return [
            'categories' => [],
            'brands' => [],
            'shops' => [],
            'price' => ['min' => 0.0, 'max' => 0.0, 'total' => 0, 'buckets' => []],
            'rating' => ['buckets' => []],
            'in_stock' => ['in' => 0, 'out' => 0],
            'attributes' => [],
            'total' => 0,
        ];
    }

    private function idSubquery(Builder $base)
    {
        return $base->select([
            'products.id',
            'products.category_id',
            'products.brand_id',
            'products.shop_id',
            'products.price',
            'products.rating_average',
            'products.current_stock',
        ]);
    }

    private function countFacet($ids): int
    {
        try {
            return (int) DB::query()
                ->fromSub($ids, 'facet_base')
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function categoryFacet($ids, int $limit): array
    {
        try {
            return DB::table('categories')
                ->joinSub($ids, 'facet_base', 'categories.id', '=', 'facet_base.category_id')
                ->groupBy('categories.id', 'categories.name', 'categories.slug')
                ->orderByDesc(DB::raw('count(*)'))
                ->orderBy('categories.name')
                ->limit($limit)
                ->get(['categories.id', 'categories.name', 'categories.slug', DB::raw('count(*) as aggregate')])
                ->mapWithKeys(fn ($row) => [
                    (int) $row->id => [
                        'name' => (string) $row->name,
                        'slug' => (string) $row->slug,
                        'url' => $this->safeRoute('categories.show', ['slug' => $row->slug]),
                        'count' => (int) $row->aggregate,
                        'selected' => in_array((int) $row->id, $this->plan->categoryIds, true),
                    ],
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function brandFacet($ids, int $limit): array
    {
        try {
            return DB::table('brands')
                ->joinSub($ids, 'facet_base', 'brands.id', '=', 'facet_base.brand_id')
                ->groupBy('brands.id', 'brands.name', 'brands.slug')
                ->orderByDesc(DB::raw('count(*)'))
                ->orderBy('brands.name')
                ->limit($limit)
                ->get(['brands.id', 'brands.name', 'brands.slug', DB::raw('count(*) as aggregate')])
                ->mapWithKeys(fn ($row) => [
                    (int) $row->id => [
                        'name' => (string) $row->name,
                        'slug' => (string) $row->slug,
                        'url' => $this->safeRoute('brands.show', ['slug' => $row->slug]),
                        'count' => (int) $row->aggregate,
                        'selected' => in_array((int) $row->id, $this->plan->brandIds, true),
                    ],
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function shopFacet($ids, int $limit): array
    {
        try {
            return DB::table('shops')
                ->joinSub($ids, 'facet_base', 'shops.id', '=', 'facet_base.shop_id')
                ->groupBy('shops.id', 'shops.name', 'shops.slug')
                ->orderByDesc(DB::raw('count(*)'))
                ->orderBy('shops.name')
                ->limit($limit)
                ->get(['shops.id', 'shops.name', 'shops.slug', DB::raw('count(*) as aggregate')])
                ->mapWithKeys(fn ($row) => [
                    (int) $row->id => [
                        'name' => (string) $row->name,
                        'slug' => (string) $row->slug,
                        'url' => $this->safeRoute('shop.show', ['slug' => $row->slug]),
                        'count' => (int) $row->aggregate,
                        'selected' => in_array((int) $row->id, $this->plan->shopIds, true),
                    ],
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function priceFacet($ids): array
    {
        $buckets = (array) config('search.facets.price_buckets', []);
        $empty = ['min' => 0.0, 'max' => 0.0, 'total' => 0, 'buckets' => []];

        try {
            $bounds = DB::query()
                ->fromSub($ids, 'facet_base')
                ->selectRaw('MIN(price) as min_price, MAX(price) as max_price, COUNT(*) as aggregate')
                ->first();

            $min = (float) ($bounds->min_price ?? 0);
            $max = (float) ($bounds->max_price ?? 0);
            $total = (int) ($bounds->aggregate ?? 0);

            if ($total === 0) {
                return $empty;
            }

            $cases = [];
            foreach ($buckets as $index => $bucket) {
                $from = (float) $bucket['from'];
                $to = $bucket['to'] === null ? null : (float) $bucket['to'];
                $cases[$index] = $to === null
                    ? ['condition' => 'price >= ?', 'bindings' => [$from], 'from' => $from, 'to' => null]
                    : ['condition' => 'price >= ? AND price < ?', 'bindings' => [$from, $to], 'from' => $from, 'to' => $to];
            }

            $selects = [];
            $bindings = [];
            foreach ($cases as $index => $case) {
                $selects[] = sprintf('SUM(CASE WHEN %s THEN 1 ELSE 0 END) as bucket_%d', $case['condition'], $index);
                foreach ($case['bindings'] as $binding) {
                    $bindings[] = $binding;
                }
            }

            $row = DB::query()->fromSub($ids, 'facet_base')->selectRaw(implode(', ', $selects), $bindings)->first();

            $out = [];
            foreach ($cases as $index => $case) {
                $count = (int) ($row->{'bucket_'.$index} ?? 0);
                $out[] = [
                    'from' => $case['from'],
                    'to' => $case['to'],
                    'label' => $case['to'] === null
                        ? Currency::compact($case['from']).'+'
                        : Currency::number($case['from']).' - '.Currency::number($case['to']),
                    'count' => $count,
                ];
            }

            return ['min' => $min, 'max' => $max, 'total' => $total, 'buckets' => $out];
        } catch (Throwable) {
            return $empty;
        }
    }

    private function ratingFacet($ids): array
    {
        $buckets = (array) config('search.facets.rating_buckets', []);

        try {
            $selects = [];
            $bindings = [];
            foreach ($buckets as $index => $bucket) {
                $min = (float) $bucket['min'];
                $max = $bucket['max'] === null ? null : (float) $bucket['max'];
                $condition = $max === null
                    ? 'rating_average >= ?'
                    : 'rating_average >= ? AND rating_average < ?';
                $selects[] = sprintf('SUM(CASE WHEN %s THEN 1 ELSE 0 END) as bucket_%d', $condition, $index);
                $bindings[] = $min;
                if ($max !== null) {
                    $bindings[] = $max;
                }
            }

            $row = DB::query()->fromSub($ids, 'facet_base')->selectRaw(implode(', ', $selects), $bindings)->first();

            $out = [];
            foreach ($buckets as $index => $bucket) {
                $out[] = [
                    'key' => (string) $bucket['key'],
                    'label' => (string) $bucket['label'],
                    'min' => (float) $bucket['min'],
                    'max' => $bucket['max'] === null ? null : (float) $bucket['max'],
                    'count' => (int) ($row->{'bucket_'.$index} ?? 0),
                ];
            }

            return ['buckets' => $out];
        } catch (Throwable) {
            return ['buckets' => []];
        }
    }

    private function stockFacet($ids): array
    {
        try {
            $row = DB::query()
                ->fromSub($ids, 'facet_base')
                ->selectRaw('SUM(CASE WHEN current_stock > 0 THEN 1 ELSE 0 END) as in_stock, SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END) as out_stock')
                ->first();

            return [
                'in' => (int) ($row->in_stock ?? 0),
                'out' => (int) ($row->out_stock ?? 0),
            ];
        } catch (Throwable) {
            return ['in' => 0, 'out' => 0];
        }
    }

    private function attributeFacet($ids): array
    {
        $limit = (int) config('search.facets.attribute_limit', 40);

        try {
            $rows = DB::table('product_attributes as pa')
                ->joinSub($ids, 'facet_base', 'pa.product_id', '=', 'facet_base.id')
                ->join('attributes as a', 'a.id', '=', 'pa.attribute_id')
                ->leftJoin('attribute_values as av', 'av.id', '=', 'pa.attribute_value_id')
                ->groupBy('a.name', 'av.value')
                ->orderByDesc(DB::raw('count(distinct pa.product_id)'))
                ->orderBy('a.name')
                ->limit($limit)
                ->get(['a.name', 'av.value', DB::raw('count(distinct pa.product_id) as aggregate')]);

            $facets = [];
            foreach ($rows as $row) {
                $name = trim((string) $row->name);
                $value = trim((string) ($row->value ?? ''));
                if ($name === '' || $value === '') {
                    continue;
                }
                $facets[$name][] = ['value' => $value, 'count' => (int) $row->aggregate];
            }

            return $facets;
        } catch (Throwable) {
            return [];
        }
    }

    private function safeRoute(string $name, array $parameters): ?string
    {
        try {
            return route($name, $parameters);
        } catch (Throwable) {
            return null;
        }
    }
}
