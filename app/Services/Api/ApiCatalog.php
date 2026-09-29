<?php

declare(strict_types=1);

namespace App\Services\Api;

use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for what each resource will accept from the query string.
 *
 * Adding a sortable or filterable column means adding it here. Nothing is derived
 * from the request, so a hostile `?sort=password` can never reach a column.
 */
final class ApiCatalog
{
    public static function make(string $resource): ApiFilter
    {
        return match ($resource) {
            'products' => self::products(),
            'categories' => self::categories(),
            'brands' => self::brands(),
            'stores' => self::stores(),
            'vendors' => self::vendors(),
            'variants' => self::variants(),
            'reviews' => self::reviews(),
            'orders' => self::orders(),
            'coupons' => self::coupons(),
            'notifications' => self::notifications(),
            'conversations' => self::conversations(),
            'messages' => self::messages(),
            'shipments' => self::shipments(),
            'refunds' => self::refunds(),
            'wallet_transactions' => self::walletTransactions(),
            'loyalty_transactions' => self::loyaltyTransactions(),
            'support_tickets' => self::supportTickets(),
            'wishlist' => self::wishlist(),
            'addresses' => self::addresses(),
            'search' => self::search(),
            default => new ApiFilter,
        };
    }

    public static function products(): ApiFilter
    {
        return (new ApiFilter)
            ->searchable(['name', 'sku', 'short_description', 'search_keywords'])
            ->filters([
                'shop_id' => 'shop_id',
                'category_id' => 'category_id',
                'brand_id' => 'brand_id',
                'status' => 'status',
                'featured' => 'featured',
                'product_type' => 'product_type',
                'min_price' => static fn (Builder $q, $value) => $q->whereRaw('COALESCE(special_price, price) >= ?', [(float) $value]),
                'max_price' => static fn (Builder $q, $value) => $q->whereRaw('COALESCE(special_price, price) <= ?', [(float) $value]),
                'in_stock' => static fn (Builder $q, $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $q->where('current_stock', '>', 0) : $q->where('current_stock', '<=', 0),
                'on_sale' => static fn (Builder $q, $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $q->whereNotNull('special_price') : null,
                'updated_since' => 'updated_at',
            ])
            ->sorts([
                'name' => 'name',
                'price' => 'price',
                'created_at' => 'created_at',
                'updated_at' => 'updated_at',
                'rating' => 'rating_average',
                'sold' => 'sold_count',
                'popularity' => 'view_count',
                'id' => 'id',
            ])
            ->includes([
                'shop' => 'shop',
                'category' => 'category',
                'brand' => 'brand',
                'variants' => 'variants',
                'reviews' => 'reviews',
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function categories(): ApiFilter
    {
        return (new ApiFilter)
            ->searchable(['name', 'slug'])
            ->filters([
                'parent_id' => 'parent_id',
                'is_featured' => 'is_featured',
                'status' => 'status',
            ])
            ->sorts([
                'name' => 'name',
                'sort_order' => 'sort_order',
                'created_at' => 'created_at',
                'id' => 'id',
            ])
            ->includes([
                'parent' => 'parent',
                'children' => 'children',
            ])
            ->defaultSort('sort_order', 'asc');
    }

    public static function brands(): ApiFilter
    {
        return (new ApiFilter)
            ->searchable(['name', 'slug'])
            ->filters([
                'is_featured' => 'is_featured',
                'status' => 'status',
            ])
            ->sorts([
                'name' => 'name',
                'created_at' => 'created_at',
                'id' => 'id',
            ])
            ->includes(['products' => 'products'])
            ->defaultSort('name', 'asc');
    }

    public static function stores(): ApiFilter
    {
        return (new ApiFilter)
            ->searchable(['name', 'slug', 'city', 'province'])
            ->filters([
                'city' => 'city',
                'province' => 'province',
                'min_rating' => static fn (Builder $q, $value) => $q->where('rating_average', '>=', (float) $value),
                'vacation_mode' => 'vacation_mode',
            ])
            ->sorts([
                'name' => 'name',
                'rating' => 'rating_average',
                'sold' => 'sold_count',
                'products' => 'product_count',
                'created_at' => 'created_at',
                'id' => 'id',
            ])
            ->includes(['products' => 'products', 'vendor' => 'vendor'])
            ->defaultSort('created_at', 'desc');
    }

    public static function vendors(): ApiFilter
    {
        return (new ApiFilter)
            ->searchable(['name', 'email'])
            ->filters([
                'role' => 'role',
                'status' => 'status',
            ])
            ->sorts([
                'name' => 'name',
                'created_at' => 'created_at',
                'id' => 'id',
            ])
            ->includes(['shop' => 'shop', 'wallet' => 'wallet'])
            ->defaultSort('created_at', 'desc');
    }

    public static function variants(): ApiFilter
    {
        return (new ApiFilter)
            ->filters([
                'product_id' => 'product_id',
                'sku' => 'sku',
                'in_stock' => static fn (Builder $q, $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $q->where('stock', '>', 0) : $q->where('stock', '<=', 0),
            ])
            ->sorts([
                'price' => 'price',
                'stock' => 'stock',
                'created_at' => 'created_at',
                'id' => 'id',
            ])
            ->includes(['product' => 'product'])
            ->defaultSort('id', 'desc');
    }

    public static function reviews(): ApiFilter
    {
        return (new ApiFilter)
            ->filters([
                'product_id' => 'product_id',
                'customer_id' => 'customer_id',
                'rating' => 'rating',
                'min_rating' => static fn (Builder $q, $value) => $q->where('rating', '>=', (int) $value),
            ])
            ->sorts([
                'rating' => 'rating',
                'created_at' => 'created_at',
                'id' => 'id',
            ])
            ->includes([
                'product' => 'product',
                'customer' => 'customer',
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function orders(): ApiFilter
    {
        return (new ApiFilter)
            ->filters([
                'order_status' => 'order_status',
                'payment_status' => 'payment_status',
                'shop_id' => 'shop_id',
                'order_number' => 'order_number',
                'from' => static fn (Builder $q, $value) => $q->where('created_at', '>=', $value),
                'to' => static fn (Builder $q, $value) => $q->where('created_at', '<=', $value),
            ])
            ->sorts([
                'created_at' => 'created_at',
                'total' => 'total',
                'order_status' => 'order_status',
                'id' => 'id',
            ])
            ->includes([
                'shop' => 'shop',
                'items' => 'items',
                'shipments' => 'shipments',
                'refunds' => 'refunds',
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function coupons(): ApiFilter
    {
        return (new ApiFilter)
            ->filters([
                'shop_id' => 'shop_id',
                'coupon_type' => 'coupon_type',
                'status' => 'status',
            ])
            ->sorts([
                'code' => 'code',
                'start_date' => 'start_date',
                'end_date' => 'end_date',
                'id' => 'id',
            ])
            ->defaultSort('id', 'desc');
    }

    public static function notifications(): ApiFilter
    {
        return (new ApiFilter)
            ->filters([
                'type' => 'type',
                'unread' => static fn (Builder $q, $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $q->whereNull('read_at') : $q->whereNotNull('read_at'),
            ])
            ->sorts([
                'created_at' => 'created_at',
                'id' => 'id',
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function conversations(): ApiFilter
    {
        return (new ApiFilter)
            ->filters([
                'status' => 'status',
                'shop_id' => 'shop_id',
                'order_id' => 'order_id',
            ])
            ->sorts([
                'last_message_at' => 'last_message_at',
                'created_at' => 'created_at',
                'id' => 'id',
            ])
            ->includes([
                'shop' => 'shop',
                'order' => 'order',
                'messages' => 'messages',
            ])
            ->defaultSort('last_message_at', 'desc');
    }

    public static function messages(): ApiFilter
    {
        return (new ApiFilter)
            ->filters(['conversation_id' => 'conversation_id'])
            ->sorts([
                'created_at' => 'created_at',
                'id' => 'id',
            ])
            ->includes([
                'author' => 'author',
                'conversation' => 'conversation',
            ])
            ->defaultSort('created_at', 'asc');
    }

    public static function shipments(): ApiFilter
    {
        return (new ApiFilter)
            ->filters([
                'order_id' => 'order_id',
                'courier' => 'courier',
                'status' => 'status',
            ])
            ->sorts([
                'shipped_at' => 'shipped_at',
                'delivered_at' => 'delivered_at',
                'id' => 'id',
            ])
            ->includes([
                'order' => 'order',
                'provider' => 'provider',
            ])
            ->defaultSort('id', 'desc');
    }

    public static function refunds(): ApiFilter
    {
        return (new ApiFilter)
            ->filters([
                'order_id' => 'order_id',
                'status' => 'status',
            ])
            ->sorts([
                'created_at' => 'created_at',
                'amount' => 'amount',
                'id' => 'id',
            ])
            ->includes([
                'order' => 'order',
                'orderItem' => 'orderItem',
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function walletTransactions(): ApiFilter
    {
        return (new ApiFilter)
            ->filters([
                'type' => 'type',
                'status' => 'status',
                'reference_type' => 'reference_type',
            ])
            ->sorts([
                'created_at' => 'created_at',
                'amount' => 'amount',
                'id' => 'id',
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function loyaltyTransactions(): ApiFilter
    {
        return (new ApiFilter)
            ->filters([
                'type' => 'type',
                'reference_type' => 'reference_type',
            ])
            ->sorts([
                'created_at' => 'created_at',
                'points' => 'points',
                'id' => 'id',
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function supportTickets(): ApiFilter
    {
        return (new ApiFilter)
            ->filters([
                'status' => 'status',
                'priority' => 'priority',
                'type' => 'type',
            ])
            ->sorts([
                'created_at' => 'created_at',
                'updated_at' => 'updated_at',
                'id' => 'id',
            ])
            ->includes(['replies' => 'replies'])
            ->defaultSort('created_at', 'desc');
    }

    public static function wishlist(): ApiFilter
    {
        return (new ApiFilter)
            ->filters(['product_id' => 'product_id'])
            ->sorts([
                'created_at' => 'created_at',
                'id' => 'id',
            ])
            ->includes(['product' => 'product'])
            ->defaultSort('created_at', 'desc');
    }

    public static function addresses(): ApiFilter
    {
        return (new ApiFilter)
            ->filters([
                'city' => 'city',
                'province' => 'province',
            ])
            ->sorts([
                'is_default' => 'is_default',
                'created_at' => 'created_at',
                'id' => 'id',
            ])
            ->defaultSort('is_default', 'desc');
    }

    public static function search(): ApiFilter
    {
        return (new ApiFilter)
            ->filters([
                'type' => static function (Builder $q, $value): void {
                    $q->where('searchable_type', 'products');
                },
            ])
            ->sorts([
                'relevance' => 'id',
                'created_at' => 'created_at',
                'id' => 'id',
            ])
            ->defaultSort('id', 'desc');
    }
}
