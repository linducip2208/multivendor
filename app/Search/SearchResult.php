<?php

declare(strict_types=1);

namespace App\Search;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @template T
 */
final class SearchResult
{
    /**
     * @param  Collection<int, T>  $items
     * @param  array<string, int>  $facets
     */
    public function __construct(
        public readonly Collection $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        public readonly array $facets = [],
        public readonly float $tookMs = 0.0,
        public readonly ?LengthAwarePaginator $paginator = null,
        public readonly array $meta = [],
    ) {}

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function ids(): array
    {
        return $this->items->map(fn ($m) => is_array($m) ? ($m['id'] ?? null) : ($m->id ?? null))
            ->filter()
            ->values()
            ->all();
    }
}
