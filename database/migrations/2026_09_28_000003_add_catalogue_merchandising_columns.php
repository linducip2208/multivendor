<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandising + catalogue columns used by the storefront, search ranking,
 * PSEO and analytics. All additive, all portable (no raw DDL), all guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addColumns('products', [
            ['rating_average', 'decimal', ['total' => 3, 'places' => 2], ['default' => 0]],
            ['rating_count', 'integer', ['unsigned' => true], ['default' => 0]],
            ['sold_count', 'integer', ['unsigned' => true], ['default' => 0]],
            ['view_count', 'integer', ['unsigned' => true], ['default' => 0]],
            ['search_keywords', 'text', [], ['nullable' => true]],
            ['warranty', 'string', ['length' => 255], ['nullable' => true]],
            ['warranty_unit', 'string', ['length' => 30], ['nullable' => true]],
            ['condition', 'string', ['length' => 20], ['default' => 'new']],
            ['low_stock_threshold', 'integer', ['unsigned' => true], ['default' => 5]],
            ['seo_score', 'integer', ['unsigned' => true, 'tiny' => true], ['default' => 0]],
        ]);

        $this->addColumns('categories', [
            ['description', 'text', [], ['nullable' => true]],
            ['image', 'string', ['length' => 255], ['nullable' => true]],
            ['icon', 'string', ['length' => 255], ['nullable' => true]],
            ['banner_image', 'string', ['length' => 255], ['nullable' => true]],
            ['meta_title', 'string', ['length' => 255], ['nullable' => true]],
            ['meta_description', 'string', ['length' => 500], ['nullable' => true]],
            ['is_featured', 'boolean', [], ['default' => false]],
        ]);

        $this->addColumns('brands', [
            ['description', 'text', [], ['nullable' => true]],
            ['logo', 'string', ['length' => 255], ['nullable' => true]],
            ['meta_title', 'string', ['length' => 255], ['nullable' => true]],
            ['meta_description', 'string', ['length' => 500], ['nullable' => true]],
            ['is_featured', 'boolean', [], ['default' => false]],
        ]);

        $this->addColumns('shops', [
            ['banner', 'string', ['length' => 255], ['nullable' => true]],
            ['description', 'text', [], ['nullable' => true]],
            ['city', 'string', ['length' => 100], ['nullable' => true]],
            ['province', 'string', ['length' => 100], ['nullable' => true]],
            ['postal_code', 'string', ['length' => 12], ['nullable' => true]],
            ['shipping_destination_id', 'string', ['length' => 12], ['nullable' => true]],
            ['rating_average', 'decimal', ['total' => 3, 'places' => 2], ['default' => 0]],
            ['rating_count', 'integer', ['unsigned' => true], ['default' => 0]],
            ['product_count', 'integer', ['unsigned' => true], ['default' => 0]],
            ['sold_count', 'integer', ['unsigned' => true], ['default' => 0]],
            ['meta_title', 'string', ['length' => 255], ['nullable' => true]],
            ['meta_description', 'string', ['length' => 500], ['nullable' => true]],
        ]);

        $this->addColumns('product_variants', [
            ['low_stock_threshold', 'integer', ['unsigned' => true], ['default' => 3]],
            ['sku', 'string', ['length' => 120], ['nullable' => true]],
        ]);

        $this->addColumns('blog_posts', [
            ['author_name', 'string', ['length' => 120], ['nullable' => true]],
            ['author_avatar', 'string', ['length' => 255], ['nullable' => true]],
            ['updated_at', 'timestamp', [], ['nullable' => true]],
        ]);

        $this->addIndex('products', ['status', 'published', 'rating_average'], 'products_relevance_idx');
        $this->addIndex('products', ['status', 'published', 'sold_count'], 'products_popular_idx');
        $this->addIndex('categories', ['status', 'sort_order'], 'categories_status_sort_idx');
        $this->addIndex('shops', ['status', 'rating_average'], 'shops_status_rating_idx');
    }

    public function down(): void
    {
    }

    private function addColumns(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as [$name, $type, $args, $modifiers]) {
            if (Schema::hasColumn($table, $name)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($name, $type, $args, $modifiers) {
                $t->addColumn($type, $name, $args + $modifiers);
            });
        }
    }

    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        try {
            Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
        } catch (\Throwable) {
        }
    }
};
