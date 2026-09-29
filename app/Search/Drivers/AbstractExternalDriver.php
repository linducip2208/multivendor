<?php

declare(strict_types=1);

namespace App\Search\Drivers;

use App\Search\Contracts\SearchDriver;
use App\Search\Exceptions\SearchUnavailable;
use App\Search\SearchQuery;
use App\Search\SearchResult;
use App\Support\Currency;
use App\Support\TextNormalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

abstract class AbstractExternalDriver implements SearchDriver
{
    protected function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::acceptJson()
            ->timeout($this->timeout())
            ->connectTimeout($this->connectTimeout())
            ->withHeaders($this->headers());
    }

    abstract protected function headers(): array;

    abstract protected function timeout(): int;

    abstract protected function connectTimeout(): int;

    protected function decode(Response $response, string $endpoint): array
    {
        if ($response->serverError() || $response->clientError()) {
            throw SearchUnavailable::response($this->name(), $response->status(), $response->body());
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw SearchUnavailable::response($this->name(), $response->status(), 'non-json payload from '.$endpoint);
        }

        return $payload;
    }

    protected function request(string $method, string $endpoint, array $options = []): array
    {
        try {
            $pending = $this->http();

            $response = match (strtoupper($method)) {
                'GET' => $pending->get($endpoint, $options['query'] ?? []),
                'DELETE' => $pending->delete($endpoint),
                'PUT' => $pending->put($endpoint, $options['json'] ?? []),
                default => $pending->post($endpoint, $options['json'] ?? []),
            };
        } catch (ConnectionException $exception) {
            throw SearchUnavailable::connection($this->name(), $endpoint, $exception);
        } catch (Throwable $exception) {
            throw SearchUnavailable::connection($this->name(), $endpoint, $exception);
        }

        return $this->decode($response, $endpoint);
    }

    /**
     * @return array<string, mixed>
     */
    protected function normaliseHits(array $hit, int $index): array
    {
        $price = (float) ($hit['price'] ?? $hit['effective_price'] ?? 0);
        $effective = (float) ($hit['effective_price'] ?? $price);

        return [
            'id' => (int) ($hit['id'] ?? 0),
            'position' => $index,
            'score' => (float) ($hit['_rankingScore'] ?? $hit['_score'] ?? 0),
            'name' => (string) ($hit['name'] ?? ''),
            'slug' => (string) ($hit['slug'] ?? ''),
            'sku' => (string) ($hit['sku'] ?? ''),
            'brand' => $hit['brand_name'] ?? null,
            'shop' => $hit['shop_name'] ?? null,
            'categories' => $hit['category_names'] ?? [],
            'price' => $price,
            'effective_price' => $effective,
            'price_label' => Currency::format($effective),
            'stock' => (int) ($hit['current_stock'] ?? 0),
            'rating' => (float) ($hit['rating_average'] ?? 0),
            'rating_count' => (int) ($hit['rating_count'] ?? 0),
            'sold_count' => (int) ($hit['sold_count'] ?? 0),
            'popularity' => (float) ($hit['popularity'] ?? 0),
            'in_stock' => (int) ($hit['in_stock'] ?? 0) === 1,
            'thumbnail' => $this->thumbnail($hit),
            'url' => $this->safeRoute('products.show', ['slug' => $hit['slug'] ?? '']),
            'created_at' => $hit['created_at'] ?? null,
            'tenant_id' => $hit['tenant_id'] ?? null,
            'raw' => $hit,
        ];
    }

    protected function entitySuggestion(string $type, array $hit): array
    {
        return [
            'id' => (int) ($hit['id'] ?? 0),
            'name' => (string) ($hit['name'] ?? ''),
            'slug' => (string) ($hit['slug'] ?? ''),
            'url' => match ($type) {
                'category' => $this->safeRoute('categories.show', ['slug' => $hit['slug'] ?? '']),
                'brand' => $this->safeRoute('brands.show', ['slug' => $hit['slug'] ?? '']),
                default => $this->safeRoute('shop.show', ['slug' => $hit['slug'] ?? '']),
            },
        ];
    }

    protected function emptyResult(SearchQuery $query, float $tookMs, array $facets = []): SearchResult
    {
        return new SearchResult(
            items: new Collection,
            total: 0,
            page: $query->page,
            perPage: $query->perPage,
            facets: $facets,
            tookMs: $tookMs,
            paginator: null,
        );
    }

    protected function thumbnail(array $hit): ?string
    {
        $thumbnail = $hit['thumbnail'] ?? null;

        return $thumbnail === null || $thumbnail === '' ? null : url('img/'.ltrim((string) $thumbnail, '/'));
    }

    protected function safeRoute(string $name, array $parameters): ?string
    {
        try {
            return route($name, $parameters);
        } catch (Throwable) {
            return null;
        }
    }

    protected function emptySuggest(): array
    {
        return ['products' => [], 'categories' => [], 'brands' => [], 'shops' => [], 'terms' => []];
    }

    protected function normaliseSynonyms(string $term): array
    {
        $normalised = TextNormalizer::normalize($term);
        $variants = [$normalised];

        foreach (preg_split('/\s+/u', $normalised) ?: [] as $token) {
            $variants[] = $token;
        }

        return array_values(array_unique(array_filter($variants)));
    }
}
