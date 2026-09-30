<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Http\Middleware\ApiAuthenticate;
use App\Http\Middleware\ApiIdempotency;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;

/**
 * Builds the OpenAPI 3.1 document from the live route table.
 *
 * Paths, methods, security, rate limiters and idempotency support are read from
 * the router, so a route that is added or removed cannot silently drift out of
 * the published spec. Only prose and request/response detail are curated.
 */
final class OpenApiSpec
{
    public const VERSIONS = ['v1', 'v2', 'v3', 'v4'];

    private const ERROR_CODES = [
        'validation_failed' => 'Payload failed validation.',
        'unauthenticated' => 'Missing, malformed or expired credential.',
        'forbidden' => 'Authenticated but not permitted, or the record is not owned by the caller.',
        'rate_limited' => 'Too many requests; a Retry-After header is returned.',
        'resource_not_found' => 'No such record, or it is not visible to this principal.',
        'conflict' => 'The request conflicts with the current state.',
        'idempotency_key_reused' => 'The Idempotency-Key was replayed with a different payload.',
        'missing_scope' => 'The credential lacks a required scope.',
        'insufficient_stock' => 'The requested quantity exceeds available stock.',
        'coupon_invalid' => 'The coupon is inactive, expired or exhausted.',
        'insufficient_wallet_balance' => 'The balance or point balance is too low.',
        'domain_rule_violation' => 'A business rule rejected the request.',
        'internal_error' => 'Unexpected server error. Correlate using request_id.',
    ];

    public function build(): array
    {
        $paths = [];

        foreach ($this->apiRoutes() as $route) {
            $operation = $this->operation($route);

            if ($operation === null) {
                continue;
            }

            [$path, $method] = $operation['target'];
            unset($operation['target']);

            $paths[$path] ??= [];
            $paths[$path][$method] = $operation;
        }

        ksort($paths);

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Multivendor Marketplace Public API',
                'version' => '1.0.0',
                'summary' => 'Public, vendor and courier REST API for the multivendor marketplace.',
                'description' => $this->description(),
                'license' => ['name' => 'Proprietary', 'identifier' => 'LicenseRef-Internal'],
            ],
            'servers' => $this->servers(),
            'tags' => $this->tags(),
            'security' => [],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Sanctum personal access token ({id}|{secret})',
                        'description' => 'POST /api/v1/auth/login returns a bearer token.',
                    ],
                    'apiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-Api-Key',
                        'description' => 'Machine credential. Only the SHA-256 hash is stored; the plaintext is shown once.',
                    ],
                ],
                'schemas' => $this->schemas(),
                'responses' => $this->responses(),
            ],
        ];
    }

    public function yaml(): string
    {
        return $this->dumpYaml($this->build(), 0);
    }

    public function endpointCount(): int
    {
        $count = 0;

        foreach ($this->apiRoutes() as $route) {
            if ($this->operation($route) !== null) {
                $count++;
            }
        }

        return $count;
    }

    private function apiRoutes(): array
    {
        $routes = [];

        foreach (Router::getRoutes()->getRoutes() as $route) {
            if ($route instanceof Route && Str::startsWith($route->uri(), 'api/v')) {
                $routes[] = $route;
            }
        }

        usort($routes, static function (Route $a, Route $b): int {
            return [$a->uri(), $a->methods()[0]] <=> [$b->uri(), $b->methods()[0]];
        });

        return $routes;
    }

    private function operation(Route $route): ?array
    {
        $uri = Str::after($route->uri(), 'api/');
        $segments = explode('/', $uri);
        $version = $segments[0] ?? '';

        if (! in_array($version, self::VERSIONS, true)) {
            return null;
        }

        $middleware = $route->gatherMiddleware();
        $secured = $this->hasMiddleware($middleware, [ApiAuthenticate::class, 'auth:sanctum', 'auth:api']);
        $writing = in_array($route->methods()[0] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $idempotent = $this->hasMiddleware($middleware, [ApiIdempotency::class]);
        $throttle = $this->throttleName($middleware);
        $key = ($route->methods()[0] ?? 'GET').' /'.$uri;
        $meta = $this->meta($key);

        return [
            'target' => ['/'.$uri, strtolower($route->methods()[0] ?? 'get')],
            'tags' => [$this->tag($segments)],
            'summary' => $meta['summary'] ?? $this->summary($route, $segments),
            'description' => $meta['description'] ?? $this->descriptionFor($route, $writing, $idempotent, $secured),
            'operationId' => Str::of($key)->replace(['GET ', 'POST ', 'PUT ', 'PATCH ', 'DELETE '], '')
                ->slug()->replace('-', '_')->toString(),
            'x-api-version' => $version,
            'x-rate-limit' => $throttle,
            'x-idempotent' => $idempotent,
            'parameters' => $this->parameters($route, $meta, $version),
            'requestBody' => $writing ? $this->requestBody($meta) : null,
            'responses' => $this->operationResponses($meta, $writing, $secured),
            'security' => $secured ? [['bearerAuth' => []], ['apiKeyAuth' => []]] : [],
        ];
    }

    private function parameters(Route $route, array $meta, string $version): array
    {
        $parameters = [];

        foreach ($route->parameterNames() as $name) {
            $parameters[] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'description' => 'Path parameter: '.$name.'.',
                'schema' => ['type' => $this->isNumericParameter($route, $name) ? 'integer' : 'string'],
            ];
        }

        foreach ($meta['query'] ?? [] as $name => $schema) {
            $parameters[] = [
                'name' => $name,
                'in' => 'query',
                'required' => false,
                'schema' => is_array($schema) ? $schema : ['type' => 'string'],
            ];
        }

        foreach ($this->paginationParameters($version) as $parameter) {
            $parameters[] = $parameter;
        }

        if ($this->hasMiddleware($route->gatherMiddleware(), [ApiIdempotency::class])) {
            $parameters[] = [
                'name' => 'Idempotency-Key',
                'in' => 'header',
                'required' => false,
                'description' => 'Replaying this key with the same payload returns the original response. A different payload answers 409.',
                'schema' => ['type' => 'string', 'maxLength' => 128],
            ];
        }

        // Aditif: header lokalisasi global — tidak mengubah parameter existing.
        // ID: bahasa (Accept-Language: id-ID/en-US), mata uang (X-Currency),
        // negara (X-Country). EN: locale/currency/country negotiation headers.
        foreach ($this->localizationHeaders() as $header) {
            $parameters[] = $header;
        }

        return $parameters;
    }

    /**
     * Header lokalisasi global (aditif, tidak mengubah spec existing).
     *
     * @return array<int, array<string,mixed>>
     */
    private function localizationHeaders(): array
    {
        return [
            [
                'name' => 'Accept-Language',
                'in' => 'header',
                'required' => false,
                'description' => 'Locale: "id-ID" (Bahasa Indonesia) atau "en-US" (English). Contoh: Accept-Language: id-ID. / Locale negotiation, e.g. Accept-Language: en-US.',
                'schema' => ['type' => 'string', 'examples' => ['id-ID', 'en-US'], 'default' => 'id-ID'],
            ],
            [
                'name' => 'X-Currency',
                'in' => 'header',
                'required' => false,
                'description' => 'Mata uang ISO 4217 (mis. IDR, USD). / Currency ISO 4217, e.g. IDR, USD.',
                'schema' => ['type' => 'string', 'pattern' => '^[A-Z]{3}$', 'examples' => ['IDR', 'USD'], 'default' => 'IDR'],
            ],
            [
                'name' => 'X-Country',
                'in' => 'header',
                'required' => false,
                'description' => 'Negara ISO 3166-1 alpha-2 (mis. ID, US). / Country ISO 3166-1 alpha-2, e.g. ID, US.',
                'schema' => ['type' => 'string', 'pattern' => '^[A-Z]{2}$', 'examples' => ['ID', 'US'], 'default' => 'ID'],
            ],
        ];
    }

    private function requestBody(array $meta): ?array
    {
        $required = $meta['bodyRequired'] ?? false;
        $schema = $meta['body'] ?? ['type' => 'object', 'additionalProperties' => true];

        if (! $required) {
            $schema = ['oneOf' => [$schema, ['type' => 'object', 'additionalProperties' => true]]];
        }

        return [
            'required' => $required,
            'content' => ['application/json' => ['schema' => $schema]],
        ];
    }

    private function operationResponses(array $meta, bool $writing, bool $secured): array
    {
        $success = $meta['status'] ?? ($writing ? 201 : 200);
        $schema = $meta['response'] ?? ['$ref' => '#/components/schemas/Envelope'];

        $responses = [
            (string) $success => [
                'description' => $meta['message'] ?? 'Success.',
                'content' => ['application/json' => ['schema' => $schema]],
            ],
        ];

        foreach (['400' => 'bad_request', '404' => 'resource_not_found', '409' => 'conflict', '422' => 'validation_failed', '429' => 'rate_limited', '500' => 'internal_error'] as $status => $code) {
            $responses[$status] = [
                'description' => self::ERROR_CODES[$code] ?? 'Error.',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorEnvelope']]],
            ];
        }

        if ($secured) {
            $responses['401'] = [
                'description' => self::ERROR_CODES['unauthenticated'],
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorEnvelope']]],
            ];
            $responses['403'] = [
                'description' => self::ERROR_CODES['forbidden'],
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorEnvelope']]],
            ];
        }

        ksort($responses);

        return $responses;
    }

    private function paginationParameters(string $version): array
    {
        if ($version === 'v4') {
            return [
                [
                    'name' => 'limit',
                    'in' => 'query',
                    'required' => false,
                    'description' => 'Page size, 1-100. v4 always uses cursor pagination.',
                    'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                ],
                [
                    'name' => 'cursor',
                    'in' => 'query',
                    'required' => false,
                    'description' => 'Opaque cursor from meta.next_cursor.',
                    'schema' => ['type' => 'string'],
                ],
                [
                    'name' => 'sort',
                    'in' => 'query',
                    'required' => false,
                    'description' => 'Comma separated, prefix with - for descending. Only whitelisted keys are honoured.',
                    'schema' => ['type' => 'string'],
                ],
                [
                    'name' => 'include',
                    'in' => 'query',
                    'required' => false,
                    'description' => 'Comma separated relation names. Values outside the whitelist are ignored.',
                    'schema' => ['type' => 'string'],
                ],
            ];
        }

        return [
            [
                'name' => 'page',
                'in' => 'query',
                'required' => false,
                'description' => 'Offset page number.',
                'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            ],
            [
                'name' => 'per_page',
                'in' => 'query',
                'required' => false,
                'description' => 'Page size, 1-100.',
                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            ],
            [
                'name' => 'sort',
                'in' => 'query',
                'required' => false,
                'description' => 'Comma separated, prefix with - for descending. Only whitelisted keys are honoured.',
                'schema' => ['type' => 'string'],
            ],
            [
                'name' => 'include',
                'in' => 'query',
                'required' => false,
                'description' => 'Comma separated relation names. Values outside the whitelist are ignored.',
                'schema' => ['type' => 'string'],
            ],
        ];
    }

    private function schemas(): array
    {
        $money = ['type' => 'string', 'pattern' => '^-?\\d+\\.\\d{2}$', 'examples' => ['125000.00']];
        $nullableMoney = ['type' => ['string', 'null'], 'pattern' => '^-?\\d+\\.\\d{2}$'];
        $iso = ['type' => ['string', 'null'], 'format' => 'date-time'];

        return [
            'Envelope' => [
                'type' => 'object',
                'required' => ['success', 'data', 'meta', 'message', 'request_id'],
                'properties' => [
                    'success' => ['type' => 'boolean'],
                    'data' => ['description' => 'Resource, list or null.'],
                    'meta' => ['$ref' => '#/components/schemas/Meta'],
                    'message' => ['type' => 'string'],
                    'errors' => ['type' => ['object', 'null'], 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]],
                    'request_id' => ['type' => 'string', 'description' => 'Matches the X-Request-Id response header and the log line.'],
                ],
            ],
            'Meta' => [
                'type' => 'object',
                'properties' => [
                    'page' => ['type' => ['integer', 'null']],
                    'per_page' => ['type' => ['integer', 'null']],
                    'total' => ['type' => ['integer', 'null']],
                    'total_pages' => ['type' => ['integer', 'null']],
                    'sort' => ['type' => ['string', 'null']],
                    'filters' => ['type' => 'object', 'additionalProperties' => true],
                    'next_cursor' => ['type' => ['string', 'null']],
                    'previous_cursor' => ['type' => ['string', 'null']],
                    'include' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'pagination' => ['type' => 'string', 'enum' => ['offset', 'cursor']],
                ],
            ],
            'ErrorEnvelope' => [
                'type' => 'object',
                'required' => ['success', 'code', 'message', 'request_id'],
                'properties' => [
                    'success' => ['type' => 'boolean', 'const' => false],
                    'code' => ['type' => 'string', 'enum' => array_keys(self::ERROR_CODES)],
                    'message' => ['type' => 'string'],
                    'data' => ['type' => 'null'],
                    'meta' => ['$ref' => '#/components/schemas/Meta'],
                    'errors' => ['type' => ['object', 'null'], 'additionalProperties' => true],
                    'request_id' => ['type' => 'string'],
                ],
            ],
            'Product' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'sku' => ['type' => ['string', 'null']],
                    'price' => $money,
                    'special_price' => $nullableMoney,
                    'effective_price' => $money,
                    'stock_available' => ['type' => 'integer'],
                    'product_type' => ['type' => ['string', 'null']],
                    'shop' => ['$ref' => '#/components/schemas/Shop'],
                    'category' => ['type' => ['object', 'null']],
                    'brand' => ['type' => ['object', 'null']],
                    'created_at' => $iso,
                ],
            ],
            'Shop' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'rating' => $nullableMoney,
                    'products_count' => ['type' => ['integer', 'null']],
                    'is_on_vacation' => ['type' => 'boolean'],
                ],
            ],
            'Order' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'order_number' => ['type' => 'string'],
                    'status' => ['type' => 'string'],
                    'payment_status' => ['type' => 'string'],
                    'subtotal' => $money,
                    'tax' => $money,
                    'shipping_cost' => $money,
                    'discount' => $money,
                    'total' => $money,
                    'currency' => ['type' => 'string'],
                    'created_at' => $iso,
                ],
            ],
            'Customer' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'email' => ['type' => ['string', 'null'], 'description' => 'Only populated for the authenticated customer.'],
                    'phone' => ['type' => ['string', 'null']],
                    'wallet_balance' => $nullableMoney,
                ],
            ],
            'PaginationMeta' => ['$ref' => '#/components/schemas/Meta'],
        ];
    }

    private function responses(): array
    {
        $out = [];

        foreach (self::ERROR_CODES as $code => $description) {
            $out[$code] = [
                'description' => $description,
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorEnvelope']]],
            ];
        }

        return $out;
    }

    private function servers(): array
    {
        $out = [];
        $host = (string) (config('app.url') ?? 'http://localhost');

        foreach (self::VERSIONS as $version) {
            $out[] = [
                'url' => rtrim($host, '/').'/api/'.$version,
                'description' => match ($version) {
                    'v1' => 'Customer public and account API.',
                    'v2' => 'Vendor operating system API.',
                    'v3' => 'Courier delivery API.',
                    default => 'Stable public read API with cursor pagination.',
                },
            ];
        }

        return $out;
    }

    private function tags(): array
    {
        return [
            ['name' => 'v1-auth', 'description' => 'Tokens, sessions and API keys.'],
            ['name' => 'v1-catalog', 'description' => 'Public catalogue reads.'],
            ['name' => 'v1-account', 'description' => 'Authenticated customer resources.'],
            ['name' => 'v1-search', 'description' => 'Cross entity search.'],
            ['name' => 'v1-shipping', 'description' => 'Couriers, services and rates.'],
            ['name' => 'v2-legacy', 'description' => 'Original vendor endpoints, unchanged.'],
            ['name' => 'v2-portal', 'description' => 'Vendor operating system.'],
            ['name' => 'v3-legacy', 'description' => 'Original courier endpoints, unchanged.'],
            ['name' => 'v3-courier', 'description' => 'Courier assignment and earnings.'],
            ['name' => 'v4-public', 'description' => 'Cursor paginated public read API.'],
        ];
    }

    private function tag(array $segments): string
    {
        $version = $segments[0] ?? 'v1';
        $rest = array_slice($segments, 1);
        $section = $rest[0] ?? null;

        return match (true) {
            $version === 'v4' => 'v4-public',
            $version === 'v3' => str_starts_with((string) $section, 'catalog') ? 'v3-legacy' : 'v3-courier',
            $version === 'v2' => str_starts_with((string) $section, 'catalog') ? 'v2-portal' : 'v2-legacy',
            in_array($section, ['auth'], true) => 'v1-auth',
            $section === 'search' => 'v1-search',
            $section === 'shipping' => 'v1-shipping',
            $section === 'catalog' => 'v1-catalog',
            default => 'v1-account',
        };
    }

    private function summary(Route $route, array $segments): string
    {
        $name = $route->getName();

        if (is_string($name) && $name !== '') {
            return Str::of($name)->afterLast('.')->replace(['-', '_'], ' ')->ucfirst()->toString();
        }

        $tail = end($segments) ?: 'index';

        return Str::of($tail)->replace(['-', '_'], ' ')->ucfirst()->toString();
    }

    private function descriptionFor(Route $route, bool $writing, bool $idempotent, bool $secured): string
    {
        $parts = [];

        $parts[] = $secured
            ? 'Requires a bearer token or X-Api-Key with the matching scope.'
            : 'Public read endpoint, no credential required.';

        if ($writing) {
            $parts[] = 'Mutating endpoint.';
        }

        if ($idempotent) {
            $parts[] = 'Send an Idempotency-Key header to make retries safe.';
        }

        $throttle = $this->throttleName($route->gatherMiddleware());

        if ($throttle !== null) {
            $parts[] = 'Rate limited by the `'.$throttle.'` limiter; a 429 carries Retry-After.';
        }

        $parts[] = 'Client supplied prices are never trusted; totals are recalculated server side.';

        return implode(' ', $parts);
    }

    private function throttleName(array $middleware): ?string
    {
        foreach ($middleware as $item) {
            if (is_string($item) && str_starts_with($item, 'throttle:')) {
                return substr($item, 8);
            }
        }

        return null;
    }

    private function hasMiddleware(array $middleware, array $needles): bool
    {
        foreach ($middleware as $item) {
            if (! is_string($item)) {
                continue;
            }

            foreach ($needles as $needle) {
                if ($item === $needle) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isNumericParameter(Route $route, string $name): bool
    {
        $wheres = $route->wheres;

        return isset($wheres[$name]) && str_contains((string) $wheres[$name], '0-9');
    }

    private function meta(string $key): array
    {
        static $cache = null;

        if ($cache === null) {
            $cache = self::curated();
        }

        return $cache[$key] ?? [];
    }

    private static function curated(): array
    {
        $authBody = [
            'bodyRequired' => true,
            'body' => [
                'type' => 'object',
                'required' => ['email', 'password'],
                'properties' => [
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'password' => ['type' => 'string'],
                    'device_name' => ['type' => 'string', 'maxLength' => 120],
                    'scopes' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['read', 'write']]],
                ],
            ],
            'status' => 200,
            'message' => 'A bearer token is issued. Send it as Authorization: Bearer <token>.',
        ];

        $registerBody = [
            'bodyRequired' => true,
            'body' => [
                'type' => 'object',
                'required' => ['name', 'email', 'password'],
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 255],
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'password' => ['type' => 'string', 'minLength' => 8, 'maxLength' => 72],
                    'phone' => ['type' => ['string', 'null'], 'maxLength' => 20],
                    'referral_code' => ['type' => ['string', 'null']],
                ],
            ],
            'status' => 201,
            'message' => 'Account created and a bearer token is issued.',
        ];

        $apiKeyBody = [
            'bodyRequired' => true,
            'body' => [
                'type' => 'object',
                'required' => ['name'],
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 120],
                    'scopes' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['read', 'write', 'vendor', 'admin']]],
                    'expires_in_days' => ['type' => ['integer', 'null'], 'minimum' => 1, 'maximum' => 3650],
                ],
            ],
            'status' => 201,
            'message' => 'The plaintext key is present exactly once in this response. It is never retrievable again.',
        ];

        $searchQuery = [
            'q' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 100],
            'type' => ['type' => 'string', 'enum' => ['all', 'products', 'categories', 'stores'], 'default' => 'all'],
        ];

        $productQuery = [
            'shop_id' => ['type' => 'integer'],
            'category_id' => ['type' => 'integer'],
            'brand_id' => ['type' => 'integer'],
            'min_price' => ['type' => 'number'],
            'max_price' => ['type' => 'number'],
            'in_stock' => ['type' => 'boolean'],
            'featured' => ['type' => 'boolean'],
            'search' => ['type' => 'string', 'maxLength' => 100],
        ];

        $orderQuery = [
            'order_status' => ['type' => 'string'],
            'payment_status' => ['type' => 'string'],
            'from' => ['type' => 'string', 'format' => 'date-time'],
            'to' => ['type' => 'string', 'format' => 'date-time'],
        ];

        $cancelBody = [
            'body' => ['type' => 'object', 'properties' => ['reason' => ['type' => 'string', 'maxLength' => 500]]],
            'message' => 'The order is cancelled and reserved stock is released.',
        ];

        $apiKeyParam = ['query' => ['product_id' => ['type' => 'integer']]];

        return [
            'POST /v1/auth/login' => array_merge($authBody, ['summary' => 'Exchange credentials for an API token']),
            'POST /v1/auth/register' => array_merge($registerBody, ['summary' => 'Register a customer and issue a token']),
            'POST /v1/login' => array_merge($authBody, ['summary' => 'Legacy customer login (unchanged contract)']),
            'POST /v1/register' => array_merge($registerBody, ['summary' => 'Legacy customer registration (unchanged contract)']),
            'GET /v1/catalog/products' => ['summary' => 'List published products', 'query' => $productQuery],
            'GET /v1/catalog/products/{slug}' => ['summary' => 'Fetch one published product'],
            'GET /v1/catalog/products/{slug}/variants' => ['summary' => 'List variants of a product'],
            'GET /v1/catalog/products/{slug}/reviews' => ['summary' => 'List approved reviews of a product'],
            'GET /v1/catalog/categories' => ['summary' => 'List root categories'],
            'GET /v1/catalog/categories/{slug}' => ['summary' => 'Fetch one category with children'],
            'GET /v1/catalog/brands' => ['summary' => 'List brands'],
            'GET /v1/catalog/brands/{slug}' => ['summary' => 'Fetch one brand'],
            'GET /v1/catalog/stores' => ['summary' => 'List active stores'],
            'GET /v1/catalog/stores/{slug}' => ['summary' => 'Fetch one active store'],
            'GET /v1/catalog/stores/{slug}/products' => ['summary' => 'List a store catalogue', 'query' => $productQuery],
            'GET /v1/catalog/vendors' => ['summary' => 'List vendor accounts'],
            'GET /v1/catalog/vendors/{vendor}' => ['summary' => 'Fetch one vendor'],
            'GET /v1/search' => ['summary' => 'Search products, categories and stores', 'query' => $searchQuery],
            'GET /v1/shipping/rates' => [
                'summary' => 'Quote live shipping rates',
                'query' => [
                    'shop_id' => ['type' => 'integer'],
                    'destination' => ['type' => 'string'],
                    'courier' => ['type' => 'string'],
                    'weight' => ['type' => 'integer'],
                ],
            ],
            'GET /v1/account' => ['summary' => 'Current customer profile'],
            'PUT /v1/account' => [
                'summary' => 'Update the current customer profile',
                'body' => ['type' => 'object', 'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 255],
                    'phone' => ['type' => ['string', 'null'], 'maxLength' => 20],
                    'avatar' => ['type' => ['string', 'null']],
                ]],
            ],
            'GET /v1/account/api-keys' => ['summary' => 'List API keys, masked'],
            'POST /v1/account/api-keys' => array_merge($apiKeyBody, ['summary' => 'Create an API key, revealed once']),
            'GET /v1/account/addresses' => ['summary' => 'List delivery addresses'],
            'POST /v1/account/addresses' => [
                'summary' => 'Create a delivery address',
                'bodyRequired' => true,
                'body' => ['type' => 'object', 'required' => ['receiver_name', 'receiver_phone', 'address', 'city', 'province'], 'properties' => [
                    'label' => ['type' => 'string'],
                    'receiver_name' => ['type' => 'string'],
                    'receiver_phone' => ['type' => 'string'],
                    'address' => ['type' => 'string'],
                    'city' => ['type' => 'string'],
                    'province' => ['type' => 'string'],
                    'postal_code' => ['type' => ['string', 'null']],
                    'is_default' => ['type' => 'boolean'],
                ]],
                'status' => 201,
            ],
            'GET /v1/account/cart' => ['summary' => 'Current cart with server side totals'],
            'POST /v1/account/cart' => [
                'summary' => 'Add a product or variant to the cart',
                'bodyRequired' => true,
                'body' => ['type' => 'object', 'required' => ['product_id', 'quantity'], 'properties' => [
                    'product_id' => ['type' => 'integer'],
                    'variant_id' => ['type' => ['integer', 'null']],
                    'quantity' => ['type' => 'integer', 'minimum' => 1],
                ]],
                'status' => 201,
            ],
            'GET /v1/account/orders' => ['summary' => 'List the caller orders', 'query' => $orderQuery],
            'GET /v1/account/orders/{order}' => ['summary' => 'Fetch one owned order'],
            'GET /v1/account/orders/{order}/shipments' => ['summary' => 'Shipments of an owned order'],
            'GET /v1/account/orders/{order}/refunds' => ['summary' => 'Refunds of an owned order'],
            'POST /v1/account/orders/{order}/cancel' => array_merge($cancelBody, ['summary' => 'Cancel an owned order']),
            'GET /v1/account/track/{number}' => ['summary' => 'Track an owned order by order number'],
            'POST /v1/account/checkout/preview' => [
                'summary' => 'Recalculate checkout totals without writing',
                'bodyRequired' => true,
                'body' => self::checkoutBody(),
            ],
            'POST /v1/account/checkout' => [
                'summary' => 'Place an order and open a payment',
                'bodyRequired' => true,
                'body' => array_merge_recursive(self::checkoutBody(), ['required' => ['payment_provider_id']]),
                'status' => 201,
                'message' => 'Order created. Client prices are ignored and recalculated from locked product rows.',
            ],
            'GET /v1/account/wallet' => ['summary' => 'Wallet balance'],
            'GET /v1/account/loyalty' => ['summary' => 'Loyalty point balance and tier'],
            'POST /v1/account/loyalty/redeem' => [
                'summary' => 'Redeem loyalty points into the wallet',
                'bodyRequired' => true,
                'body' => ['type' => 'object', 'required' => ['points'], 'properties' => ['points' => ['type' => 'integer', 'minimum' => 1]]],
            ],
            'GET /v1/account/coupons' => ['summary' => 'List currently valid coupons'],
            'POST /v1/account/coupons/validate' => [
                'summary' => 'Validate a coupon against a cart total',
                'bodyRequired' => true,
                'body' => ['type' => 'object', 'required' => ['code', 'order_total'], 'properties' => [
                    'code' => ['type' => 'string'],
                    'order_total' => ['type' => 'number'],
                ]],
            ],
            'GET /v1/account/payments' => ['summary' => 'List payment groups'],
            'GET /v1/account/reviews' => ['summary' => 'List reviews written by the caller'],
            'POST /v1/account/reviews' => [
                'summary' => 'Review a delivered item',
                'bodyRequired' => true,
                'body' => ['type' => 'object', 'required' => ['product_id', 'rating'], 'properties' => [
                    'product_id' => ['type' => 'integer'],
                    'order_item_id' => ['type' => ['integer', 'null']],
                    'rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                    'comment' => ['type' => ['string', 'null'], 'maxLength' => 2000],
                    'images' => ['type' => 'array', 'items' => ['type' => 'string']],
                ]],
                'status' => 201,
            ],
            'GET /v1/account/notifications' => ['summary' => 'List in-app notifications'],
            'GET /v1/account/conversations' => ['summary' => 'List conversations the caller participates in'],
            'POST /v1/account/conversations/{conversation}/messages' => [
                'summary' => 'Send a message in a conversation',
                'bodyRequired' => true,
                'body' => ['type' => 'object', 'required' => ['body'], 'properties' => [
                    'body' => ['type' => 'string', 'maxLength' => 5000],
                    'attachments' => ['type' => 'array', 'items' => ['type' => 'string']],
                ]],
                'status' => 201,
            ],
            'GET /v1/account/support/tickets' => ['summary' => 'List support tickets'],
            'POST /v1/account/support/tickets' => [
                'summary' => 'Open a support ticket',
                'bodyRequired' => true,
                'body' => ['type' => 'object', 'required' => ['subject', 'description'], 'properties' => [
                    'subject' => ['type' => 'string'],
                    'type' => ['type' => 'string'],
                    'priority' => ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent']],
                    'description' => ['type' => 'string'],
                ]],
                'status' => 201,
            ],
            'GET /v2/vendor/dashboard' => ['summary' => 'Legacy vendor counters (unchanged contract)'],
            'GET /v2/vendor/portal/profile' => ['summary' => 'Vendor profile and shop'],
            'GET /v2/vendor/portal/inventory' => ['summary' => 'Inventory snapshot for the shop'],
            'GET /v2/vendor/portal/catalog/products' => ['summary' => 'List the shop products', 'query' => $productQuery],
            'PUT /v2/vendor/portal/catalog/products/{product}' => [
                'summary' => 'Update a shop product',
                'body' => ['type' => 'object', 'properties' => [
                    'name' => ['type' => 'string'],
                    'price' => ['type' => 'number'],
                    'current_stock' => ['type' => 'integer'],
                    'published' => ['type' => 'boolean'],
                ]],
            ],
            'GET /v2/vendor/portal/payouts' => ['summary' => 'Settlement ledger for the shop'],
            'GET /v3/delivery/courier/summary' => ['summary' => 'Courier workload and earnings summary'],
            'GET /v3/delivery/courier/earnings' => ['summary' => 'Courier earning ledger'],
            'GET /v4/products' => ['summary' => 'Cursor paginated product list', 'query' => $productQuery],
            'GET /v4/products/{slug}' => ['summary' => 'Fetch one product'],
            'GET /v4/categories' => ['summary' => 'Cursor paginated category list'],
            'GET /v4/brands' => ['summary' => 'Cursor paginated brand list'],
            'GET /v4/stores' => ['summary' => 'Cursor paginated store list'],
            'GET /v4/search' => ['summary' => 'Public search', 'query' => ['q' => ['type' => 'string', 'minLength' => 2]]],
        ] + [
            'GET /v1/products' => ['summary' => 'Legacy product list (unchanged contract)'],
            'GET /v1/products/{slug}' => ['summary' => 'Legacy product detail (unchanged contract)'],
            'POST /v1/cart' => $apiKeyParam + ['summary' => 'Legacy cart add (unchanged contract)'],
            'POST /v1/reviews' => ['summary' => 'Legacy review submit (unchanged contract)'],
        ];
    }

    private static function checkoutBody(): array
    {
        return ['type' => 'object', 'required' => [], 'properties' => [
            'address_id' => ['type' => ['integer', 'null']],
            'new_receiver_name' => ['type' => ['string', 'null']],
            'new_receiver_phone' => ['type' => ['string', 'null']],
            'new_address' => ['type' => ['string', 'null']],
            'new_city' => ['type' => ['string', 'null']],
            'new_province' => ['type' => ['string', 'null']],
            'new_shipping_destination_id' => ['type' => ['string', 'null']],
            'shipping_methods' => [
                'type' => 'object',
                'description' => 'Keyed by shop id.',
                'additionalProperties' => ['type' => 'object', 'properties' => [
                    'provider_id' => ['type' => ['integer', 'null']],
                    'courier' => ['type' => ['string', 'null']],
                    'service' => ['type' => ['string', 'null']],
                    'destination' => ['type' => ['string', 'null']],
                ]],
            ],
            'payment_provider_id' => ['type' => ['integer', 'null']],
            'payment_channel' => ['type' => 'object', 'additionalProperties' => true],
            'coupon_code' => ['type' => ['string', 'null']],
            'note' => ['type' => ['string', 'null']],
            'idempotency_key' => ['type' => ['string', 'null'], 'maxLength' => 80],
        ]];
    }

    private function description(): string
    {
        return implode("\n", [
            'Every response uses one envelope: `{ success, data, meta, message, errors, request_id }`.',
            '',
            '- **Money** is always a decimal string such as `"125000.00"`, never a JSON number.',
            '- **Dates** are ISO-8601 strings, or `null` when absent.',
            '- **Nulls** are explicit; a key is never silently omitted to mean "unknown".',
            '- `meta` always carries `page`, `per_page`, `total`, `total_pages`, `sort` and `filters`.',
            '- Errors carry a machine readable `code` plus a `request_id` that matches the `X-Request-Id`',
            '  response header and the server log. Internal messages and stack traces are never returned',
            '  unless `APP_DEBUG` is on, and even then only under `error.debug`.',
            '',
            '**Authentication.** `POST /api/v1/auth/login` returns a Sanctum bearer token.',
            'Machine integrations should create an API key and send it as `X-Api-Key`; the plaintext is',
            'revealed once and only a SHA-256 hash is stored. Both credential types carry scopes:',
            '`read`, `write`, `vendor`, `admin`.',
            '',
            '**Idempotency.** Every mutating endpoint accepts an `Idempotency-Key` header. Replaying a key',
            'with the same payload returns the original response instead of repeating the effect; replaying',
            'it with a different payload answers `409 idempotency_key_reused`.',
            '',
            '**Rate limits.** `api` (global, 120/min), `api:auth` (10/min), `api:write` (60/min) and',
            '`api:search` (60/min). A 429 carries `Retry-After`.',
            '',
            '**Versioning.** `v1` is the customer API, `v2` the vendor OS, `v3` the courier API and `v4` a',
            'thin public read API using cursor pagination. The original v1/v2/v3 endpoints are preserved',
            'unchanged for existing consumers.',
            '',
            '**Localization (aditif, kontrak existing tidak berubah).** Kirim `Accept-Language: id-ID`',
            'atau `Accept-Language: en-US` untuk bahasa, `X-Currency: IDR|USD|...` (ISO 4217) untuk',
            'mata uang, dan `X-Country: ID|US|...` (ISO 3166-1 alpha-2) untuk negara. / Send',
            '`Accept-Language: id-ID` or `Accept-Language: en-US`, `X-Currency` and `X-Country` headers.',
            'Responses echo `meta.locale`, `meta.currency` plus `X-Locale` / `X-Currency` headers.',
        ]);
    }

    public function dumpYaml(mixed $value, int $indent): string
    {
        $pad = str_repeat('  ', $indent);

        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return $this->scalar($value);
        }

        if ($value instanceof \stdClass) {
            $value = (array) $value;
        }

        if (! is_array($value)) {
            return $this->scalar((string) $value);
        }

        if ($value === []) {
            return $value === [] ? '{}' : '[]';
        }

        $isList = array_is_list($value);
        $lines = [];

        foreach ($value as $key => $item) {
            $rendered = $this->dumpYaml($item, $indent + 1);

            if ($isList) {
                $lines[] = $pad.'- '.($rendered === '' ? '' : $rendered);

                continue;
            }

            $prefix = $pad.$this->key((string) $key).':';
            $lines[] = $rendered === '' || $rendered === '{}' || $rendered === '[]'
                ? $prefix.' '.$rendered
                : $prefix."\n".$rendered;
        }

        return implode("\n", $lines);
    }

    private function key(string $key): string
    {
        return preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.-]*$/', $key) === 1 ? $key : $this->quote($key);
    }

    private function scalar(string $value): string
    {
        if ($value === '') {
            return "''";
        }

        if (str_contains($value, "\n")) {
            return '|'.str_repeat("\n", 0)."\n".$this->indentedBlock($value);
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9 _.\/:@+-]*$/', $value) === 1
            && ! in_array(strtolower($value), ['true', 'false', 'null', 'yes', 'no', 'on', 'off', 'y', 'n'], true)
            && ! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            return $value;
        }

        return $this->quote($value);
    }

    private function indentedBlock(string $value): string
    {
        $lines = array_map(static fn (string $line): string => '    '.$line, explode("\n", rtrim($value, "\n")));

        return implode("\n", $lines);
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value).'"';
    }
}
