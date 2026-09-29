<?php

declare(strict_types=1);

namespace App\Search\Contracts;

use App\Search\SearchQuery;
use App\Search\SearchResult;

/**
 * Search driver contract.
 *
 * The storefront, the API and the vendor backoffice all talk to this interface
 * only, so swapping MySQL LIKE for FULLTEXT, Meilisearch, Typesense or
 * OpenSearch never requires touching a controller or a Blade file.
 */
interface SearchDriver
{
    public function name(): string;

    /**
     * @return SearchResult<int>
     */
    public function search(SearchQuery $query): SearchResult;

    /**
     * Lightweight prefix/suggestion lookup for the header autocomplete.
     *
     * @return array{products: list<array>, categories: list<array>, brands: list<array>, shops: list<array>, terms: list<array>}
     */
    public function suggest(string $term, int $limit = 6): array;
}
