<?php

declare(strict_types=1);

namespace App\Search;

use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Throwable;

class QueryPlanner
{
    public function plan(SearchQuery $query): SearchPlan
    {
        $parsed = QueryParser::parse($query->term);
        $window = TypoWindow::fromConfig();

        $variants = [];
        $typoEligible = false;

        foreach ($parsed->rawTokens as $token) {
            $stemmed = TextNormalizer::stem($token);
            $group = [$stemmed];
            if ($stemmed !== $token) {
                $group[] = $token;
            }

            foreach (SynonymRepository::expand($token) as $synonym) {
                $stemmedSynonym = TextNormalizer::stem($synonym);
                if ($stemmedSynonym !== '') {
                    $group[] = $stemmedSynonym;
                }
                if ($synonym !== $stemmedSynonym) {
                    $group[] = $synonym;
                }
            }

            $group = array_values(array_unique(array_filter($group)));
            if ($group === []) {
                continue;
            }

            if ($query->typoTolerance && (bool) config('search.typo.enabled', true)) {
                foreach ($group as $candidate) {
                    if ($window->isEligible($candidate)) {
                        $typoEligible = true;
                        break;
                    }
                }
            }

            $variants[] = $group;
        }

        $minPrice = $query->minPrice ?? $parsed->minPrice;
        $maxPrice = $query->maxPrice ?? $parsed->maxPrice;

        return new SearchPlan(
            parsed: $parsed,
            variants: $variants,
            brandIds: $this->resolveBrands($query->brandIds, $parsed->brandTerms),
            shopIds: $this->resolveShops($query->shopIds, $parsed->shopTerms),
            categoryIds: $this->resolveCategories($query->categoryIds, $parsed->categoryTerms),
            minPrice: $minPrice,
            maxPrice: $maxPrice,
            minRating: $query->minRating ?? $parsed->minRating,
            maxRating: $parsed->maxRating,
            inStock: $query->inStockOnly ? true : $parsed->inStock,
            attributeFilters: $this->mergeAttributes($query->attributeFilters, $parsed->attributes),
            typoEligible: $typoEligible,
        );
    }

    public function resolveCategories(array $ids, array $terms): array
    {
        $terms = $this->cleanTerms($terms);
        if ($terms === []) {
            return array_values(array_unique(array_map('intval', $ids)));
        }

        $tree = $this->categoryTree();
        $matched = [];

        foreach ($terms as $term) {
            $needle = TextNormalizer::normalize($term);
            if ($needle === '') {
                continue;
            }
            foreach ($tree as $node) {
                if (str_contains($node['haystack'], $needle) || TextNormalizer::similarity($node['name'], $needle) >= 0.8) {
                    $matched[(int) $node['id']] = true;
                }
            }
        }

        $childrenByParent = [];
        foreach ($tree as $node) {
            if ($node['parentId'] === null) {
                continue;
            }
            $childrenByParent[$node['parentId']][] = $node['id'];
        }

        $frontier = array_keys($matched);
        for ($depth = 0; $depth < 6 && $frontier !== []; $depth++) {
            $next = [];
            foreach ($frontier as $id) {
                foreach ($childrenByParent[$id] ?? [] as $childId) {
                    if (! isset($matched[$childId])) {
                        $matched[$childId] = true;
                        $next[] = $childId;
                    }
                }
            }
            $frontier = $next;
        }

        return array_values(array_unique(array_merge(
            array_map('intval', $ids),
            array_map('intval', array_keys($matched)),
        )));
    }

    public function resolveBrands(array $ids, array $terms): array
    {
        return $this->resolveEntities('brands', $ids, $terms, 'status');
    }

    public function resolveShops(array $ids, array $terms): array
    {
        return $this->resolveEntities('shops', $ids, $terms, 'status');
    }

    private function resolveEntities(string $table, array $ids, array $terms, ?string $statusColumn = null): array
    {
        $terms = $this->cleanTerms($terms);
        if ($terms === []) {
            return array_values(array_unique(array_map('intval', $ids)));
        }

        $found = [];

        try {
            $query = DB::table($table)->whereIn('name', $terms)->limit(30)->pluck('id');
            $found = array_map('intval', $query->all());
        } catch (Throwable) {
            $found = [];
        }

        if ($found === []) {
            foreach ($terms as $term) {
                $needle = TextNormalizer::normalize($term);
                if ($needle === '') {
                    continue;
                }
                try {
                    $rows = DB::table($table)
                        ->where('name', 'like', '%'.$this->escapeLike($needle).'%')
                        ->limit(8)
                        ->pluck('id');
                    foreach ($rows as $id) {
                        $found[] = (int) $id;
                    }
                } catch (Throwable) {
                    break;
                }
            }
        }

        return array_values(array_unique(array_merge(
            array_map('intval', $ids),
            array_slice(array_values(array_unique($found)), 0, 30),
        )));
    }

    private function mergeAttributes(array $requestAttributes, array $operatorAttributes): array
    {
        $merged = [];

        foreach ($requestAttributes as $name => $value) {
            if (is_string($name) && $name !== '') {
                $values = is_array($value) ? $value : [$value];
                $values = array_values(array_filter(array_map(
                    static fn ($v) => TextNormalizer::normalize((string) $v),
                    $values,
                ), static fn ($v) => $v !== ''));
                if ($values !== []) {
                    $merged[$name] = $values;
                }
            }
        }

        foreach ($operatorAttributes as $name => $value) {
            $merged[$name] = array_values(array_unique(array_merge(
                (array) ($merged[$name] ?? []),
                [$value],
            )));
        }

        return $merged;
    }

    private function cleanTerms(array $terms): array
    {
        $clean = [];
        foreach ($terms as $term) {
            $value = TextNormalizer::normalize((string) $term);
            if ($value !== '') {
                $clean[] = $value;
            }
        }

        return array_values(array_unique(array_slice($clean, 0, 8)));
    }

    private function categoryTree(): array
    {
        return cache()->remember('search:category_tree', 900, static function (): array {
            try {
                $rows = DB::table('categories')->get(['id', 'parent_id', 'name']);
            } catch (Throwable) {
                return [];
            }

            $tree = [];
            foreach ($rows as $row) {
                $name = (string) $row->name;
                $tree[] = [
                    'id' => (int) $row->id,
                    'parentId' => $row->parent_id === null ? null : (int) $row->parent_id,
                    'name' => $name,
                    'haystack' => TextNormalizer::normalize($name),
                ];
            }

            return $tree;
        });
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
