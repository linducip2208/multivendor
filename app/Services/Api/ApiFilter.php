<?php

declare(strict_types=1);

namespace App\Services\Api;

use Closure;
use Illuminate\Contracts\Pagination\CursorPaginator as CursorPaginatorContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Whitelist driven filtering, sorting, relation includes and pagination.
 *
 * Only keys declared here can ever reach the query builder. There is no
 * arbitrary `?with=` escape hatch: relation names come from a fixed list.
 */
final class ApiFilter
{
    private array $filters = [];

    private array $sorts = [];

    private array $includes = [];

    private array $searchable = [];

    private ?string $defaultSort = null;

    private string $defaultDirection = 'desc';

    private string $cursorColumn = 'id';

    private string $cursorDirection = 'desc';

    private bool $allowCursor = false;

    public static function for(string $resource): self
    {
        return ApiCatalog::make($resource);
    }

    public function searchable(array $columns): self
    {
        $this->searchable = $columns;

        return $this;
    }

    public function filter(string $key, string|Closure $resolver): self
    {
        $this->filters[$key] = $resolver;

        return $this;
    }

    public function filters(array $map): self
    {
        foreach ($map as $key => $resolver) {
            $this->filter($key, $resolver);
        }

        return $this;
    }

    public function sort(string $key, string $column): self
    {
        $this->sorts[$key] = $column;

        return $this;
    }

    public function sorts(array $map): self
    {
        foreach ($map as $key => $column) {
            $this->sort($key, $column);
        }

        return $this;
    }

    public function defaultSort(string $key, string $direction = 'desc'): self
    {
        $this->defaultSort = $key;
        $this->defaultDirection = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        $this->sorts[$key] ??= $key;

        return $this;
    }

    public function include(string $key, string $relation): self
    {
        $this->includes[$key] = $relation;

        return $this;
    }

    public function includes(array $map): self
    {
        foreach ($map as $key => $relation) {
            $this->include($key, $relation);
        }

        return $this;
    }

    public function cursor(string $column = 'id', string $direction = 'desc'): self
    {
        $this->allowCursor = true;
        $this->cursorColumn = $column;
        $this->cursorDirection = $direction === 'asc' ? 'asc' : 'desc';

        return $this;
    }

    public function perPage(Request $request): int
    {
        $raw = $request->query('per_page', $request->query('limit'));

        if (! is_numeric($raw)) {
            return \App\Support\ApiResponse::DEFAULT_PER_PAGE;
        }

        return max(1, min(\App\Support\ApiResponse::MAX_PER_PAGE, (int) $raw));
    }

    public function wantsCursor(Request $request): bool
    {
        return $this->allowCursor && ($request->query('cursor') !== null || $request->query('pagination') === 'cursor');
    }

    public function resolvedFilters(Request $request): array
    {
        $out = [];

        foreach ($this->filters as $key => $resolver) {
            $value = $request->query($key);

            if ($value === null || $value === '') {
                continue;
            }

            $out[$key] = is_array($value) ? array_values($value) : $value;
        }

        $search = trim((string) $request->query('search', $request->query('q', '')));

        if ($search !== '') {
            $out['search'] = $search;
        }

        return $out;
    }

    public function resolvedSorts(Request $request): array
    {
        $raw = trim((string) $request->query('sort', ''));

        if ($raw === '') {
            return $this->defaultSort === null ? [] : [$this->defaultSort => ($this->sorts[$this->defaultSort] ?? $this->defaultSort).'|'.$this->defaultDirection];
        }

        $out = [];

        foreach (explode(',', $raw) as $token) {
            $token = trim($token);

            if ($token === '') {
                continue;
            }

            $direction = Str::startsWith($token, '-') ? 'desc' : 'asc';
            $key = Str::startsWith($token, '-') ? substr($token, 1) : $token;
            $key = ltrim($key, '+');

            if (isset($this->sorts[$key])) {
                $out[$key] = $this->sorts[$key].'|'.$direction;
            }
        }

        if ($out === [] && $this->defaultSort !== null) {
            return [$this->defaultSort => ($this->sorts[$this->defaultSort] ?? $this->defaultSort).'|'.$this->defaultDirection];
        }

        return $out;
    }

    public function resolvedIncludes(Request $request): array
    {
        $raw = (string) $request->query('include', '');

        if (trim($raw) === '') {
            return [];
        }

        $out = [];

        foreach (explode(',', $raw) as $token) {
            $key = trim($token);

            if ($key !== '' && isset($this->includes[$key])) {
                $out[$this->includes[$key]] = $key;
            }
        }

        return $out;
    }

    public function sortSummary(Request $request): ?string
    {
        $raw = trim((string) $request->query('sort', ''));

        if ($raw !== '') {
            return $raw;
        }

        if ($this->defaultSort === null) {
            return null;
        }

        return $this->defaultDirection === 'desc' ? '-'.$this->defaultSort : $this->defaultSort;
    }

    public function apply(Builder $query, Request $request): Builder
    {
        $this->applyFilters($query, $request);
        $this->applySearch($query, $request);
        $this->applySort($query, $request);
        $query->with($this->resolvedIncludes($request));

        return $query;
    }

    public function paginate(Builder $query, Request $request): LengthAwarePaginator|CursorPaginatorContract
    {
        $query = $this->apply($query, $request);

        if ($this->wantsCursor($request)) {
            return $this->cursorPaginate($query, $request);
        }

        $page = max(1, (int) $request->query('page', 1));

        return $query->paginate($this->perPage($request), ['*'], 'page', $page);
    }

    public function cursorPaginate(Builder $query, Request $request): CursorPaginatorContract
    {
        $query = $this->applyCursorOrder($query, $request);
        $perPage = $this->perPage($request);

        return $query->cursorPaginate($perPage, ['*'], 'cursor');
    }

    public function applyCursorOrder(Builder $query, Request $request): Builder
    {
        $this->applyFilters($query, $request);
        $this->applySearch($query, $request);
        $this->applySort($query, $request);
        $query->with($this->resolvedIncludes($request));

        if (trim((string) $request->query('sort', '')) === '') {
            $query->orderBy($this->cursorColumn, $this->cursorDirection);
        }

        return $query;
    }

    public function meta(Request $request): array
    {
        return \App\Support\ApiResponse::meta([
            'sort' => $this->sortSummary($request),
            'filters' => (object) $this->resolvedFilters($request),
            'include' => array_values($this->resolvedIncludes($request)),
            'pagination' => $this->wantsCursor($request) ? 'cursor' : 'offset',
        ]);
    }

    public function allowedFilters(): array
    {
        return array_keys($this->filters);
    }

    public function allowedSorts(): array
    {
        return array_keys($this->sorts);
    }

    public function allowedIncludes(): array
    {
        return array_keys($this->includes);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        foreach ($this->filters as $key => $resolver) {
            $input = $request->query($key);

            if ($input === null || $input === '') {
                continue;
            }

            if ($resolver instanceof Closure) {
                $resolver($query, $input);

                continue;
            }

            $query->where($resolver, $input);
        }
    }

    private function applySearch(Builder $query, Request $request): void
    {
        if ($this->searchable === []) {
            return;
        }

        $search = trim((string) $request->query('search', $request->query('q', '')));

        if ($search === '') {
            return;
        }

        $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
        $query->where(function (Builder $inner) use ($term): void {
            foreach ($this->searchable as $index => $column) {
                $index === 0
                    ? $inner->where($column, 'like', $term)
                    : $inner->orWhere($column, 'like', $term);
            }
        });
    }

    private function applySort(Builder $query, Request $request): void
    {
        $sorts = $this->resolvedSorts($request);
        $ordered = [];

        foreach ($sorts as $spec) {
            [$column, $direction] = array_pad(explode('|', $spec, 2), 2, 'asc');
            $query->orderBy($column, $direction === 'desc' ? 'desc' : 'asc');
            $ordered[] = $column;
        }

        // Deterministic tiebreak so cursor/offset pages never skip or repeat
        // rows when the requested sort column is not unique.
        if ($this->allowCursor && ! in_array($this->cursorColumn, $ordered, true)) {
            $query->orderBy($this->cursorColumn, $this->cursorDirection);
        }
    }
}
