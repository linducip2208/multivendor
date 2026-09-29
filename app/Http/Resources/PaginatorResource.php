<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps any paginator (offset or cursor) into the envelope's data + meta pair.
 */
class PaginatorResource extends JsonResource
{
    private array $filters = [];

    private ?string $sort = null;

    public function withFilters(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    public function withSort(?string $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $paginator = $this->resource;

        if (! $paginator instanceof Paginator) {
            return [
                'data' => $paginator,
                'meta' => ApiResponse::meta(),
            ];
        }

        return [
            'data' => $this->resolveItems($paginator, $request),
            'meta' => ApiResponse::metaFrom($paginator, $this->filters, $this->sort),
        ];
    }

    private function resolveItems(Paginator $paginator, Request $request): array
    {
        $items = $paginator instanceof \Illuminate\Contracts\Pagination\CursorPaginator
            ? $paginator->items()
            : $paginator->getCollection();

        return $items instanceof \Illuminate\Support\Collection
            ? $items->map(fn ($item) => $this->renderItem($item, $request))->all()
            : (array) $items;
    }

    private function renderItem(mixed $item, Request $request): mixed
    {
        return $item instanceof JsonResource ? $item->toArray($request) : $item;
    }
}
