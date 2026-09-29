<?php

declare(strict_types=1);

return [
    'default' => env('SEARCH_DRIVER', 'database'),
    'fallback' => env('SEARCH_FALLBACK_DRIVER', 'database'),

    'drivers' => [
        'database' => App\Search\Drivers\DatabaseSearchDriver::class,
        'meilisearch' => App\Search\Drivers\MeilisearchDriver::class,
        'typesense' => App\Search\Drivers\TypesenseDriver::class,
    ],

    'failover' => [
        'enabled' => (bool) env('SEARCH_FAILOVER', true),
        'cooldown' => (int) env('SEARCH_FAILOVER_COOLDOWN', 60),
        'cache_key' => 'search:failover:state',
    ],

    'meilisearch' => [
        'host' => rtrim((string) env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'), '/'),
        'key' => (string) env('MEILISEARCH_KEY', ''),
        'timeout' => (int) env('MEILISEARCH_TIMEOUT', 3),
        'connect_timeout' => (int) env('MEILISEARCH_CONNECT_TIMEOUT', 2),
        'index' => (string) env('MEILISEARCH_INDEX', 'products'),
        'suggest_index' => [
            'products' => (string) env('MEILISEARCH_INDEX', 'products'),
            'categories' => (string) env('MEILISEARCH_INDEX', 'products'),
            'brands' => (string) env('MEILISEARCH_INDEX', 'products'),
            'shops' => (string) env('MEILISEARCH_INDEX', 'products'),
        ],
        'searchable' => [
            'name', 'sku', 'barcode', 'short_description', 'description', 'search_keywords',
            'category_names', 'brand_name', 'shop_name', 'tags', 'attribute_values',
        ],
        'filterable' => [
            'category_id', 'category_ids', 'brand_id', 'brand_name', 'shop_id', 'shop_name',
            'price', 'effective_price', 'rating_average', 'current_stock', 'in_stock',
            'status', 'published', 'tenant_id', 'attribute_values',
        ],
        'sortable' => [
            'price', 'effective_price', 'sold_count', 'view_count', 'rating_average',
            'rating_count', 'created_at', 'popularity',
        ],
        'typo_tolerance' => [
            'enabled' => true,
            'min_word_size_for_1_typo' => 5,
            'min_word_size_for_2_typos' => 9,
            'disable_on_attributes' => ['sku', 'barcode', 'shop_name'],
        ],
        'synonyms' => [],
        'stop_words' => ['yang', 'untuk', 'dengan', 'dan', 'dari', 'di', 'ke', 'dari', 'pada', 'yang'],
        'pagination' => ['max_total_hits' => 5000],
    ],

    'typesense' => [
        'host' => rtrim((string) env('TYPESENSE_HOST', 'http://127.0.0.1:8108'), '/'),
        'port' => (int) env('TYPESENSE_PORT', 8108),
        'protocol' => (string) env('TYPESENSE_PROTOCOL', 'http'),
        'api_key' => (string) env('TYPESENSE_KEY', ''),
        'timeout' => (int) env('TYPESENSE_TIMEOUT', 3),
        'connect_timeout' => (int) env('TYPESENSE_CONNECT_TIMEOUT', 2),
        'collection' => (string) env('TYPESENSE_COLLECTION', 'products'),
        'suggest_collections' => [
            'products' => (string) env('TYPESENSE_COLLECTION', 'products'),
            'categories' => (string) env('TYPESENSE_CATEGORY_COLLECTION', 'categories'),
            'brands' => (string) env('TYPESENSE_BRAND_COLLECTION', 'brands'),
            'shops' => (string) env('TYPESENSE_SHOP_COLLECTION', 'shops'),
        ],
        'query_by' => 'name,sku,barcode,short_description,description,search_keywords,category_names,brand_name,shop_name,tags,attribute_values',
        'query_by_weights' => '9,10,10,6,3,5,4,5,3,4,2',
        'filter_by' => 'status := approved && published := true',
        'facet_by' => 'category_id,category_names,brand_id,brand_name,shop_id,shop_name,price, rating_average,in_stock,attribute_values',
        'typo_tolerance' => [
            'min_len_1_typo' => 5,
            'min_len_2_typo' => 9,
            'min_query_len' => 2,
        ],
        'pagination' => ['max_hits' => 5000],
    ],

    'index' => [
        'name' => (string) env('SEARCH_INDEX_NAME', 'products'),
        'tenant_id' => env('SEARCH_TENANT_ID'),
        'chunk' => (int) env('SEARCH_INDEX_CHUNK', 500),
        'batch' => (int) env('SEARCH_INDEX_BATCH', 200),
        'updated_after' => (int) env('SEARCH_INDEX_UPDATED_AFTER', 900),
        'popularity' => [
            'sold_weight' => 1.0,
            'view_weight' => 0.25,
            'review_weight' => 0.5,
            'rating_weight' => 2.0,
            'freshness_half_life_days' => 45.0,
            'stock_bonus' => 6.0,
            'out_of_stock_penalty' => 12.0,
        ],
    ],

    'ranking' => [
        'weights' => [
            'sku_exact' => 1200,
            'barcode_exact' => 1150,
            'name_exact' => 1000,
            'name_prefix' => 700,
            'name_contains' => 480,
            'token_name_prefix' => 220,
            'token_coverage' => 150,
            'phrase_bonus' => 320,
            'category_match' => 120,
            'brand_match' => 110,
            'shop_match' => 90,
            'attribute_match' => 70,
            'popularity' => 26,
            'rating' => 6,
            'freshness' => 24,
            'in_stock' => 45,
            'featured' => 18,
        ],
        'popularity' => [
            'sold_cap' => 500,
            'sold_divisor' => 10.0,
            'view_cap' => 2000,
            'view_divisor' => 100.0,
        ],
        'freshness' => [
            'days' => [7, 30, 90, 365],
            'bonus' => [14, 10, 6, 2],
        ],
        'max_tokens' => 12,
        'max_phrase_tokens' => 6,
    ],

    'typo' => [
        'enabled' => true,
        'similarity_threshold' => 0.74,
        'min_length' => 5,
        'double_edit_length' => 9,
        'max_patterns_per_token' => 18,
        'max_total_patterns' => 60,
        'max_candidates' => 250,
    ],

    'synonyms' => [
        'enabled' => true,
        'cache_ttl' => 3600,
        'cache_key' => 'search:synonyms:v1',
        'max_terms' => 500,
    ],

    'facets' => [
        'enabled' => true,
        'limit' => 12,
        'attribute_limit' => 40,
        'price_buckets' => [
            ['from' => 0, 'to' => 50000],
            ['from' => 50000, 'to' => 100000],
            ['from' => 100000, 'to' => 250000],
            ['from' => 250000, 'to' => 500000],
            ['from' => 500000, 'to' => 1000000],
            ['from' => 1000000, 'to' => null],
        ],
        'rating_buckets' => [
            ['key' => '4', 'label' => '4 ke atas', 'min' => 4, 'max' => null],
            ['key' => '3', 'label' => '3 - 3,9', 'min' => 3, 'max' => 4],
            ['key' => '2', 'label' => '2 - 2,9', 'min' => 2, 'max' => 3],
            ['key' => '1', 'label' => 'Di bawah 2', 'min' => 0, 'max' => 2],
        ],
    ],

    'analytics' => [
        'enabled' => (bool) env('SEARCH_ANALYTICS', true),
        'hash_ip' => true,
        'min_term_length' => 2,
        'log_suggestions' => true,
        'suggest_dedupe_seconds' => 300,
        'max_term_length' => 255,
        'max_occurrences' => 200000,
    ],

    'suggest' => [
        'enabled' => true,
        'ttl' => 180,
        'min_length' => 2,
        'product_limit' => 6,
        'category_limit' => 4,
        'brand_limit' => 4,
        'shop_limit' => 4,
        'term_limit' => 4,
        'did_you_mean_limit' => 3,
        'cache_prefix' => 'search:suggest:v2',
        'vocabulary_ttl' => 900,
        'vocabulary_size' => 400,
        'result_ttl' => 60,
    ],
];
