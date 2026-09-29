<?php

declare(strict_types=1);

use App\Http\Controllers\Api\DeliveryApiController;
use App\Http\Controllers\Api\MarketplaceApiController;
use App\Http\Controllers\Api\OpenApiController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\ApiKeyController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\CompareController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\LoyaltyController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\ShippingController;
use App\Http\Controllers\Api\V1\SupportController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WishlistController;
use App\Http\Controllers\Api\V2\VendorPortalController;
use App\Http\Controllers\Api\V3\CourierController;
use App\Http\Controllers\Api\V4\PublicReadController;
use App\Http\Controllers\Api\VendorApiController;
use App\Http\Middleware\ApiAuthenticate;
use App\Http\Middleware\ApiIdempotency;
use App\Http\Middleware\ApiRequestId;
use App\Http\Middleware\ApiScope;
use App\Http\Middleware\ResponseEnvelope;
use App\Services\Api\RateLimitRegistry;
use Illuminate\Support\Facades\Route;

RateLimitRegistry::ensure();

$envelope = [
    ApiRequestId::class,
    ResponseEnvelope::class,
    'throttle:api',
];

$authenticated = [
    'throttle:60,1',
    ApiAuthenticate::class,
    ApiScope::class.':read',
];

$mutation = [
    'throttle:60,1',
    ApiAuthenticate::class,
    ApiScope::class.':write',
    'throttle:api:write',
    ApiIdempotency::class,
];

Route::middleware($envelope)->group(function () use ($authenticated, $mutation): void {
    Route::prefix('v1')->controller(MarketplaceApiController::class)->group(function () use ($authenticated, $mutation): void {
        Route::get('products', 'products');
        Route::get('products/{slug}', 'product');
        Route::get('categories', 'categories');
        Route::get('shops', 'shops');
        Route::get('shops/{slug}', 'shop');
        Route::post('login', 'login')->middleware('throttle:auth');
        Route::post('register', 'register')->middleware('throttle:auth');
        Route::middleware($authenticated)->group(function () use ($mutation): void {
            Route::get('profile', 'profile');
            Route::put('profile', 'updateProfile')->middleware($mutation);
            Route::get('orders', 'orders');
            Route::get('orders/{order}', 'order');
            Route::post('orders/{order}/cancel', 'cancel')->middleware($mutation);
            Route::post('reviews', 'review')->middleware([...$mutation, 'throttle:10,1']);
            Route::get('track/{number}', 'track');
            Route::get('cart', 'cart');
            Route::post('cart', 'addCart')->middleware($mutation);
            Route::put('cart/{cart}', 'updateCart')->middleware($mutation);
            Route::delete('cart/{cart}', 'removeCart')->middleware($mutation);
        });
    });

    Route::prefix('v2/vendor')->controller(VendorApiController::class)->group(function () use ($authenticated, $mutation): void {
        Route::post('login', 'login')->middleware('throttle:auth');
        Route::middleware([...$authenticated, 'vendor.api'])->group(function () use ($mutation): void {
            Route::get('dashboard', 'dashboard');
            Route::get('products', 'products');
            Route::get('orders', 'orders');
            Route::put('orders/{order}/status', 'updateOrder')->middleware($mutation);
        });
    });

    Route::prefix('v3/delivery')->controller(DeliveryApiController::class)->group(function () use ($authenticated, $mutation): void {
        Route::post('login', 'login')->middleware('throttle:auth');
        Route::middleware([...$authenticated, 'delivery.api'])->group(function () use ($mutation): void {
            Route::get('orders', 'orders');
            Route::put('orders/{order}/status', 'updateOrder')->middleware($mutation);
        });
    });

    Route::prefix('v1')->name('api.v1.')->group(function () use ($authenticated, $mutation): void {
        Route::prefix('auth')->name('auth.')->group(function (): void {
            Route::post('register', [AuthController::class, 'register'])->middleware('throttle:api:auth')->name('register');
            Route::post('login', [AuthController::class, 'login'])->middleware('throttle:api:auth')->name('login');
        });

        Route::middleware($authenticated)->prefix('auth')->name('auth.')->group(function () use ($mutation): void {
            Route::post('logout', [AuthController::class, 'logout'])->middleware($mutation)->name('logout');
            Route::post('logout-all', [AuthController::class, 'logoutAll'])->middleware($mutation)->name('logout-all');
            Route::get('tokens', [AuthController::class, 'tokens'])->name('tokens.index');
            Route::delete('tokens/{token}', [AuthController::class, 'revokeToken'])->middleware($mutation)->name('tokens.destroy');
        });

        Route::prefix('catalog')->name('catalog.')->group(function (): void {
            Route::get('products', [CatalogController::class, 'products'])->name('products.index');
            Route::get('products/{slug}', [CatalogController::class, 'product'])->name('products.show');
            Route::get('products/{slug}/variants', [CatalogController::class, 'productVariants'])->name('products.variants');
            Route::get('products/{slug}/reviews', [CatalogController::class, 'productReviews'])->name('products.reviews');
            Route::get('categories', [CatalogController::class, 'categories'])->name('categories.index');
            Route::get('categories/{slug}', [CatalogController::class, 'category'])->name('categories.show');
            Route::get('brands', [CatalogController::class, 'brands'])->name('brands.index');
            Route::get('brands/{slug}', [CatalogController::class, 'brand'])->name('brands.show');
            Route::get('stores', [CatalogController::class, 'stores'])->name('stores.index');
            Route::get('stores/{slug}', [CatalogController::class, 'store'])->name('stores.show');
            Route::get('stores/{slug}/products', [CatalogController::class, 'storeProducts'])->name('stores.products');
            Route::get('vendors', [CatalogController::class, 'vendors'])->name('vendors.index');
            Route::get('vendors/{vendor}', [CatalogController::class, 'vendor'])->whereNumber('vendor')->name('vendors.show');
        });

        Route::get('search', SearchController::class)->middleware('throttle:api:search')->name('search');
        Route::get('shipping/methods', [ShippingController::class, 'methods'])->name('shipping.methods');
        Route::get('shipping/providers', [ShippingController::class, 'providers'])->name('shipping.providers');
        Route::get('shipping/rates', [ShippingController::class, 'rates'])->name('shipping.rates');

        Route::middleware($authenticated)->prefix('account')->name('account.')->group(function () use ($mutation): void {
            Route::get('', [AccountController::class, 'show'])->name('show');
            Route::put('', [AccountController::class, 'update'])->middleware($mutation)->name('update');
            Route::delete('', [AccountController::class, 'destroy'])->middleware($mutation)->name('destroy');

            Route::get('addresses', [AddressController::class, 'index'])->name('addresses.index');
            Route::post('addresses', [AddressController::class, 'store'])->middleware($mutation)->name('addresses.store');
            Route::get('addresses/{address}', [AddressController::class, 'show'])->whereNumber('address')->name('addresses.show');
            Route::put('addresses/{address}', [AddressController::class, 'update'])->whereNumber('address')->middleware($mutation)->name('addresses.update');
            Route::delete('addresses/{address}', [AddressController::class, 'destroy'])->whereNumber('address')->middleware($mutation)->name('addresses.destroy');

            Route::get('api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
            Route::post('api-keys', [ApiKeyController::class, 'store'])->middleware($mutation)->name('api-keys.store');
            Route::delete('api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->whereNumber('apiKey')->middleware($mutation)->name('api-keys.destroy');

            Route::get('cart', [CartController::class, 'index'])->name('cart.index');
            Route::post('cart', [CartController::class, 'store'])->middleware($mutation)->name('cart.store');
            Route::put('cart/{cart}', [CartController::class, 'update'])->whereNumber('cart')->middleware($mutation)->name('cart.update');
            Route::delete('cart', [CartController::class, 'clear'])->middleware($mutation)->name('cart.clear');
            Route::delete('cart/{cart}', [CartController::class, 'destroy'])->whereNumber('cart')->middleware($mutation)->name('cart.destroy');

            Route::get('wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
            Route::get('wishlist/ids', [WishlistController::class, 'ids'])->name('wishlist.ids');
            Route::post('wishlist', [WishlistController::class, 'store'])->middleware($mutation)->name('wishlist.store');
            Route::delete('wishlist', [WishlistController::class, 'clear'])->middleware($mutation)->name('wishlist.clear');
            Route::delete('wishlist/{product}', [WishlistController::class, 'destroy'])->whereNumber('product')->middleware($mutation)->name('wishlist.destroy');

            Route::get('compare', [CompareController::class, 'index'])->name('compare.index');
            Route::post('compare/compare', [CompareController::class, 'compare'])->middleware($mutation)->name('compare.compare');
            Route::post('compare', [CompareController::class, 'store'])->middleware($mutation)->name('compare.store');
            Route::delete('compare', [CompareController::class, 'clear'])->middleware($mutation)->name('compare.clear');
            Route::delete('compare/{product}', [CompareController::class, 'destroy'])->whereNumber('product')->middleware($mutation)->name('compare.destroy');

            Route::get('wallet', [WalletController::class, 'show'])->name('wallet.show');
            Route::get('wallet/transactions', [WalletController::class, 'transactions'])->name('wallet.transactions');

            Route::get('loyalty', [LoyaltyController::class, 'show'])->name('loyalty.show');
            Route::get('loyalty/transactions', [LoyaltyController::class, 'transactions'])->name('loyalty.transactions');
            Route::post('loyalty/redeem', [LoyaltyController::class, 'redeem'])->middleware($mutation)->name('loyalty.redeem');
            Route::get('loyalty/missions', [LoyaltyController::class, 'missions'])->name('loyalty.missions');
            Route::post('loyalty/missions/claim', [LoyaltyController::class, 'claimMission'])->middleware($mutation)->name('loyalty.missions.claim');
            Route::post('loyalty/checkin', [LoyaltyController::class, 'checkin'])->middleware($mutation)->name('loyalty.checkin');

            Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index');
            Route::get('coupons/{code}', [CouponController::class, 'show'])->name('coupons.show');
            Route::post('coupons/validate', [CouponController::class, 'validateCode'])->name('coupons.validate');

            Route::post('checkout/preview', [CheckoutController::class, 'preview'])->middleware($mutation)->name('checkout.preview');
            Route::post('checkout', [CheckoutController::class, 'store'])->middleware($mutation)->name('checkout.store');

            Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order')->name('orders.show');
            Route::get('orders/{order}/shipments', [OrderController::class, 'shipments'])->whereNumber('order')->name('orders.shipments');
            Route::get('orders/{order}/refunds', [OrderController::class, 'refunds'])->whereNumber('order')->name('orders.refunds');
            Route::get('track/{number}', [OrderController::class, 'track'])->name('orders.track');
            Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->whereNumber('order')->middleware($mutation)->name('orders.cancel');
            Route::post('orders/{order}/confirm', [OrderController::class, 'confirmReceipt'])->whereNumber('order')->middleware($mutation)->name('orders.confirm');

            Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
            Route::get('payments/{payment}', [PaymentController::class, 'show'])->whereNumber('payment')->name('payments.show');
            Route::get('payments/{payment}/orders', [PaymentController::class, 'orders'])->whereNumber('payment')->name('payments.orders');

            Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
            Route::post('reviews', [ReviewController::class, 'store'])->middleware([...$mutation, 'throttle:10,1'])->name('reviews.store');

            Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread');
            Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->middleware($mutation)->name('notifications.read-all');
            Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->middleware($mutation)->name('notifications.read');
            Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->middleware($mutation)->name('notifications.destroy');

            Route::get('conversations', [ChatController::class, 'index'])->name('conversations.index');
            Route::post('conversations', [ChatController::class, 'store'])->middleware($mutation)->name('conversations.store');
            Route::get('conversations/{conversation}', [ChatController::class, 'show'])->whereNumber('conversation')->name('conversations.show');
            Route::get('conversations/{conversation}/messages', [ChatController::class, 'messages'])->whereNumber('conversation')->name('conversations.messages');
            Route::post('conversations/{conversation}/messages', [ChatController::class, 'storeMessage'])->whereNumber('conversation')->middleware($mutation)->name('conversations.messages.store');
            Route::post('conversations/{conversation}/read', [ChatController::class, 'markRead'])->whereNumber('conversation')->middleware($mutation)->name('conversations.read');

            Route::get('support/tickets', [SupportController::class, 'index'])->name('support.index');
            Route::post('support/tickets', [SupportController::class, 'store'])->middleware($mutation)->name('support.store');
            Route::get('support/tickets/{ticket}', [SupportController::class, 'show'])->whereNumber('ticket')->name('support.show');
            Route::post('support/tickets/{ticket}/replies', [SupportController::class, 'reply'])->whereNumber('ticket')->middleware($mutation)->name('support.reply');
            Route::post('support/tickets/{ticket}/close', [SupportController::class, 'close'])->whereNumber('ticket')->middleware($mutation)->name('support.close');
        });
    });

    Route::prefix('v2/vendor')->name('api.v2.vendor.')->group(function () use ($authenticated, $mutation): void {
        Route::middleware([...$authenticated, 'vendor.api'])->name('portal.')->group(function () use ($mutation): void {
            Route::get('profile', [VendorPortalController::class, 'profile'])->name('profile');
            Route::get('inventory', [VendorPortalController::class, 'inventory'])->name('inventory');
            Route::post('vacation', [VendorPortalController::class, 'toggleVacation'])->middleware($mutation)->name('vacation');

            Route::get('catalog/products', [VendorPortalController::class, 'products'])->name('products.index');
            Route::get('catalog/products/{product}', [VendorPortalController::class, 'product'])->whereNumber('product')->name('products.show');
            Route::put('catalog/products/{product}', [VendorPortalController::class, 'updateProduct'])->whereNumber('product')->middleware($mutation)->name('products.update');

            Route::get('orders/{order}', [VendorPortalController::class, 'order'])->whereNumber('order')->name('orders.show');
            Route::get('shipments', [VendorPortalController::class, 'shipments'])->name('shipments');
            Route::get('payouts', [VendorPortalController::class, 'payouts'])->name('payouts');
            Route::get('withdrawals', [VendorPortalController::class, 'withdrawRequests'])->name('withdrawals');
            Route::get('reviews', [VendorPortalController::class, 'reviews'])->name('reviews');
        });
    });

    Route::prefix('v3/delivery')->name('api.v3.delivery.')->group(function () use ($authenticated): void {
        Route::middleware([...$authenticated, 'delivery.api'])->name('courier.')->group(function (): void {
            Route::get('profile', [CourierController::class, 'profile'])->name('profile');
            Route::get('summary', [CourierController::class, 'summary'])->name('summary');
            Route::get('earnings', [CourierController::class, 'earnings'])->name('earnings');
            Route::get('orders/{order}', [CourierController::class, 'order'])->whereNumber('order')->name('orders.show');
        });
    });

    Route::prefix('v4')->name('api.v4.')->group(function (): void {
        Route::get('products', [PublicReadController::class, 'products'])->name('products.index');
        Route::get('products/{slug}', [PublicReadController::class, 'product'])->name('products.show');
        Route::get('categories', [PublicReadController::class, 'categories'])->name('categories.index');
        Route::get('categories/{slug}', [PublicReadController::class, 'category'])->name('categories.show');
        Route::get('brands', [PublicReadController::class, 'brands'])->name('brands.index');
        Route::get('stores', [PublicReadController::class, 'stores'])->name('stores.index');
        Route::get('stores/{slug}', [PublicReadController::class, 'store'])->name('stores.show');
        Route::get('search', [PublicReadController::class, 'search'])->middleware('throttle:api:search')->name('search');
    });
});

app()->booted(static function (): void {
    Route::get('/docs/api', [OpenApiController::class, 'ui'])->name('docs.api');
    Route::get('/docs/api/openapi.yaml', [OpenApiController::class, 'yaml'])->name('docs.api.openapi');
    Route::get('/docs/api/openapi.json', [OpenApiController::class, 'json'])->name('docs.api.json');
});
