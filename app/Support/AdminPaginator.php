<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Bridges a service-produced pagination array back to a real paginator so the
 * shared `x-admin.pagination` component can render it. Keeping the conversion
 * in one place means no Blade ever has to build a query string by hand.
 */
final class AdminPaginator
{
    /**
     * @param  array<string, mixed>  $pagination
     */
    public static function fromArray(array $pagination, int $total, int $perPage, int $currentPage, ?string $path = null): LengthAwarePaginator
    {
        $perPage = max(1, $perPage);
        $currentPage = max(1, $currentPage);

        return new LengthAwarePaginator(
            [],
            max(0, $total),
            $perPage,
            $currentPage,
            ['path' => $path ?? (string) request()->url(), 'query' => request()->query()],
        );
    }
}
