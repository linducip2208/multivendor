<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\Product;
use App\Search\SynonymRepository;
use App\Support\TextNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SearchIndexer
{
    private readonly int $chunk;

    public function __construct(int $chunk = 500)
    {
        $this->chunk = max(25, $chunk);
    }

    public function chunkSize(): int
    {
        return $this->chunk;
    }

    public function countIndexable(?string $updatedAfter = null): int
    {
        return Product::query()
            ->where('status', 'approved')
            ->where('published', true)
            ->when($updatedAfter !== null, fn ($q) => $q->where('updated_at', '>=', $updatedAfter))
            ->count();
    }

    public function documents(int $chunkSize, ?string $updatedAfter = null, ?callable $progress = null): \Generator
    {
        $tenantResolver = $this->tenantResolver();
        $synonyms = SynonymRepository::map();
        $size = max(25, $chunkSize);

        $cursor = Product::query()
            ->where('status', 'approved')
            ->where('published', true)
            ->when($updatedAfter !== null, fn ($q) => $q->where('updated_at', '>=', $updatedAfter))
            ->orderBy('id')
            ->lazyById($size, 'id', 'id');

        $buffer = [];
        $lastId = 0;

        foreach ($cursor as $product) {
            $buffer[] = $product;
            if (count($buffer) < $size) {
                continue;
            }

            foreach ($this->flush($buffer, $tenantResolver, $synonyms) as $document) {
                $lastId = (int) $document['id'];
                yield $document;
            }

            if ($progress !== null) {
                $progress(count($buffer), $lastId);
            }

            $buffer = [];
        }

        if ($buffer !== []) {
            foreach ($this->flush($buffer, $tenantResolver, $synonyms) as $document) {
                $lastId = (int) $document['id'];
                yield $document;
            }

            if ($progress !== null) {
                $progress(count($buffer), $lastId);
            }
        }
    }

    private function flush(array $products, callable $tenantResolver, array $synonyms): array
    {
        $context = $this->contextFor(array_map(static fn (Product $p) => (int) $p->id, $products));
        $documents = [];

        foreach ($products as $product) {
            $documents[] = $this->document($product, $context, $tenantResolver, $synonyms);
        }

        return $documents;
    }

    public function document(Product $product, array $context, callable $tenantResolver, array $synonyms = []): array
    {
        $productId = (int) $product->id;
        $price = (float) $product->price;
        $effective = $this->effectivePrice($product, $context['flash'][$productId] ?? null);
        $categoryNames = $context['categories'][(int) $product->category_id] ?? [];
        $brandName = (string) ($context['brands'][(int) $product->brand_id] ?? '');
        $shopName = (string) ($context['shops'][(int) $product->shop_id] ?? '');
        $tags = $context['tags'][$productId] ?? [];
        $attributes = $context['attributes'][$productId] ?? [];

        $name = (string) $product->name;
        $shortDescription = $this->plainText($product->short_description);
        $keywords = $this->plainText($product->search_keywords);
        $description = $this->plainText($product->description);

        $haystack = trim(implode(' ', array_filter([
            $name, $shortDescription, $keywords,
            ...$categoryNames,
            $brandName, $shopName,
            ...$tags,
            ...array_keys($attributes),
            ...array_values($attributes),
        ])));

        $tokenVariants = [];
        foreach (TextNormalizer::tokenize($haystack) as $token) {
            $tokenVariants[] = $token;
            foreach ($synonyms[$token] ?? [] as $synonym) {
                $tokenVariants[] = TextNormalizer::stem($synonym);
            }
        }

        $stock = (int) $product->current_stock;
        $rating = (float) $product->rating_average;
        $ratingCount = (int) $product->rating_count;
        $soldCount = (int) $product->sold_count;
        $viewCount = (int) $product->view_count;

        return [
            'id' => $productId,
            'type' => 'product',
            'name' => $name,
            'slug' => (string) $product->slug,
            'short_description' => $shortDescription,
            'description' => $description,
            'search_keywords' => $keywords,
            'category_id' => $product->category_id === null ? null : (int) $product->category_id,
            'category_names' => array_values($categoryNames),
            'brand_id' => $product->brand_id === null ? null : (int) $product->brand_id,
            'brand_name' => $brandName,
            'shop_id' => (int) $product->shop_id,
            'shop_name' => $shopName,
            'sku' => (string) ($product->sku ?? ''),
            'barcode' => (string) ($product->barcode ?? ''),
            'tags' => array_values($tags),
            'attributes' => $attributes,
            'attribute_values' => array_values(array_map(
                static fn (string $name, string $value): string => $name.':'.$value,
                array_keys($attributes),
                array_values($attributes),
            )),
            'price' => $price,
            'effective_price' => $effective,
            'current_stock' => $stock,
            'in_stock' => $stock > 0 ? 1 : 0,
            'rating_average' => $rating,
            'rating_count' => $ratingCount,
            'sold_count' => $soldCount,
            'view_count' => $viewCount,
            'popularity' => round($this->popularity($soldCount, $viewCount, $ratingCount, $rating, $product->created_at, $stock), 4),
            'thumbnail' => (string) ($product->thumbnail ?? ''),
            'status' => (string) $product->status,
            'published' => $product->published ? 1 : 0,
            'featured' => $product->featured ? 1 : 0,
            'created_at' => $this->timestamp($product->created_at),
            'updated_at' => $this->timestamp($product->updated_at),
            'tenant_id' => $tenantResolver($product),
            'search_tokens' => array_values(array_unique($tokenVariants)),
        ];
    }

    public function documentsForIds(array $ids): array
    {
        $tenantResolver = $this->tenantResolver();
        $synonyms = SynonymRepository::map();
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        $products = Product::query()->whereIn('id', $ids)->get();
        $context = $this->contextFor($products->pluck('id')->all());

        $documents = [];
        foreach ($products as $product) {
            $documents[] = $this->document($product, $context, $tenantResolver, $synonyms);
        }

        return $documents;
    }

    public function pendingIds(int $limit = 10000): array
    {
        return Product::query()
            ->where('status', 'approved')
            ->where('published', true)
            ->orderBy('updated_at')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    public function popularity(int $soldCount, int $viewCount, int $ratingCount, float $rating, mixed $createdAt, int $stock): float
    {
        $weights = (array) config('search.index.popularity', []);

        $score = (float) ($weights['sold_weight'] ?? 1.0) * log(1 + max(0, $soldCount));
        $score += (float) ($weights['view_weight'] ?? 0.25) * log(1 + max(0, $viewCount));
        $score += (float) ($weights['review_weight'] ?? 0.5) * log(1 + max(0, $ratingCount));
        $score += (float) ($weights['rating_weight'] ?? 2.0) * $rating;

        $halfLife = max(1.0, (float) ($weights['freshness_half_life_days'] ?? 45.0));
        $ageDays = $this->ageInDays($createdAt);
        $score += $halfLife / ($halfLife + $ageDays);

        if ($stock > 0) {
            $score += (float) ($weights['stock_bonus'] ?? 6.0);
        } else {
            $score -= (float) ($weights['out_of_stock_penalty'] ?? 12.0);
        }

        return $score;
    }

    /* ------------------------------------------------------------------ */

    private function contextFor(array $ids): array
    {
        $ids = array_values(array_map('intval', $ids));
        if ($ids === []) {
            return ['categories' => [], 'brands' => [], 'shops' => [], 'tags' => [], 'attributes' => [], 'flash' => []];
        }

        $productRows = DB::table('products')
            ->whereIn('id', $ids)
            ->get(['id', 'category_id', 'brand_id', 'shop_id']);

        $brandIds = $productRows->pluck('brand_id')->filter()->unique()->map('intval')->values()->all();
        $shopIds = $productRows->pluck('shop_id')->filter()->unique()->map('intval')->values()->all();
        $categoryIds = $productRows->pluck('category_id')->filter()->unique()->map('intval')->values()->all();

        return [
            'categories' => $this->categoryPaths($categoryIds),
            'brands' => $this->namesFor('brands', $brandIds),
            'shops' => $this->namesFor('shops', $shopIds),
            'tags' => $this->tagsFor($ids),
            'attributes' => $this->attributesFor($ids),
            'flash' => $this->flashFor($ids),
        ];
    }

    private function namesFor(string $table, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        try {
            $rows = DB::table($table)->whereIn('id', $ids)->pluck('name', 'id')->all();
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $id => $value) {
            $out[(int) $id] = (string) $value;
        }

        return $out;
    }

    private function categoryPaths(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        try {
            $rows = DB::table('categories')->get(['id', 'parent_id', 'name']);
        } catch (Throwable) {
            return [];
        }

        $nodes = [];
        foreach ($rows as $row) {
            $nodes[(int) $row->id] = [
                'parent' => $row->parent_id === null ? null : (int) $row->parent_id,
                'name' => (string) $row->name,
            ];
        }

        $resolved = [];
        $resolve = function (int $id, int $depth = 0) use (&$resolved, &$resolve, $nodes): array {
            if (isset($resolved[$id])) {
                return $resolved[$id];
            }
            if ($depth > 6 || ! isset($nodes[$id])) {
                return $resolved[$id] = [];
            }

            $path = [];
            $parent = $nodes[$id]['parent'];
            if ($parent !== null) {
                $path = $resolve($parent, $depth + 1);
            }
            $path[] = $nodes[$id]['name'];

            return $resolved[$id] = $path;
        };

        $out = [];
        foreach ($categoryIds as $categoryId) {
            $out[(int) $categoryId] = $resolve((int) $categoryId);
        }

        return $out;
    }

    private function tagsFor(array $productIds): array
    {
        try {
            $rows = DB::table('product_tag_pivot as pivot')
                ->join('product_tags as tag', 'tag.id', '=', 'pivot.product_tag_id')
                ->whereIn('pivot.product_id', $productIds)
                ->get(['pivot.product_id', 'tag.name']);
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->product_id][] = (string) $row->name;
        }

        return $out;
    }

    private function attributesFor(array $productIds): array
    {
        try {
            $rows = DB::table('product_attributes as pa')
                ->join('attributes as a', 'a.id', '=', 'pa.attribute_id')
                ->leftJoin('attribute_values as av', 'av.id', '=', 'pa.attribute_value_id')
                ->whereIn('pa.product_id', $productIds)
                ->get(['pa.product_id', 'a.name', 'av.value']);
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) $row->name);
            $value = $row->value === null ? '' : trim((string) $row->value);
            if ($name === '' || $value === '') {
                continue;
            }
            $out[(int) $row->product_id][$name] = $value;
        }

        return $out;
    }

    private function flashFor(array $productIds): array
    {
        try {
            $rows = DB::table('flash_deal_products as fdp')
                ->join('flash_deals as fd', 'fd.id', '=', 'fdp.flash_deal_id')
                ->whereIn('fdp.product_id', $productIds)
                ->where('fd.status', true)
                ->where('fd.start_date', '<=', now())
                ->where('fd.end_date', '>=', now())
                ->get(['fdp.product_id', 'fdp.discount_type', 'fdp.discount_value']);
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $productId = (int) $row->product_id;
            $current = $out[$productId] ?? null;
            if ($current === null || (float) $row->discount_value > $current['value']) {
                $out[$productId] = [
                    'type' => (string) $row->discount_type,
                    'value' => (float) $row->discount_value,
                ];
            }
        }

        return $out;
    }

    private function effectivePrice(Product $product, ?array $flash): float
    {
        $base = (float) $product->price;
        $now = now();

        if ($product->special_price !== null) {
            $starts = $product->discount_start === null ? null : Carbon::parse($product->discount_start);
            $ends = $product->discount_end === null ? null : Carbon::parse($product->discount_end);

            $active = ($starts === null || $starts->lessThanOrEqualTo($now))
                && ($ends === null || $ends->greaterThanOrEqualTo($now));

            if ($active && (float) $product->special_price < $base) {
                $base = (float) $product->special_price;
            }
        }

        if ($flash === null || $flash['value'] <= 0) {
            return round($base, 2);
        }

        $discounted = $flash['type'] === 'flat'
            ? max(0.0, $base - $flash['value'])
            : max(0.0, $base - ($base * $flash['value'] / 100));

        return round(min($base, $discounted), 2);
    }

    private function plainText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $text = strip_tags((string) $value);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function ageInDays(mixed $createdAt): float    {
        if ($createdAt === null) {
            return 365.0;
        }

        try {
            $date = $createdAt instanceof Carbon ? $createdAt : Carbon::parse((string) $createdAt);
        } catch (Throwable) {
            return 365.0;
        }

        return max(0.0, $date->diffInDays(now()));
    }

    private function timestamp(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        try {
            $date = $value instanceof Carbon ? $value : Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }

        return $date->getTimestamp();
    }

    private function tenantResolver(): callable
    {
        $configured = config('search.index.tenant_id');
        $columnReady = false;

        try {
            $columnReady = Schema::hasColumn('products', 'tenant_id');
        } catch (Throwable) {
            $columnReady = false;
        }

        return static function (Product $product) use ($configured, $columnReady) {
            if ($configured !== null && $configured !== '') {
                return (int) $configured;
            }

            if ($columnReady && $product->tenant_id !== null) {
                return (int) $product->tenant_id;
            }

            return null;
        };
    }
}
