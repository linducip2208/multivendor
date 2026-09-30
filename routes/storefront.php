<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Storefront routes
|--------------------------------------------------------------------------
|
| Customer-facing catalogue, cart, checkout, account and content.
| Loaded from routes/web.php so every existing URL keeps working.
|
*/

use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\AuthController as SocialAuthController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CatalogController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\CompareController;
use App\Http\Controllers\Storefront\DeliveryRatingController;
use App\Http\Controllers\Storefront\DigitalDownloadController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\InnovativeController;
use App\Http\Controllers\Storefront\LoyaltyController;
use App\Http\Controllers\Storefront\OrderController as StoreOrderController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Storefront\ProfileController;
use App\Http\Controllers\Storefront\ReviewController as StoreReviewController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Storefront\ShopController as StoreShopController;
use App\Http\Controllers\Storefront\TicketController;
use App\Http\Controllers\Storefront\TrackOrderController;
use App\Http\Controllers\Storefront\WishlistController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * Legacy root routes kept verbatim for backward compatibility.
 * The old homepage marketing page is preserved at /landing.
 */
Route::view('/landing', 'storefront.landing')->name('landing');

Route::get('/', [HomeController::class, 'index'])->name('home');

/* ---- Authentication ---- */
Route::get('/login', [HomeController::class, 'loginForm'])->name('login');
Route::post('/login', [HomeController::class, 'login'])->middleware('throttle:auth');
Route::get('/register', [HomeController::class, 'registerForm'])->name('register');
Route::post('/register', [HomeController::class, 'register'])->middleware('throttle:auth');
Route::post('/logout', [HomeController::class, 'logout'])->name('logout');
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');

/* ---- Catalogue (public) ---- */
Route::get('/products', [CatalogController::class, 'products'])->name('products.index');
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');
Route::get('/deals', [CatalogController::class, 'deals'])->name('deals');
Route::get('/flash-sale', [CatalogController::class, 'flashSale'])->name('flash-sale');
Route::get('/new-arrivals', [CatalogController::class, 'newArrivals'])->name('new-arrivals');
Route::get('/best-sellers', [CatalogController::class, 'bestSellers'])->name('best-sellers');
Route::get('/categories', [CatalogController::class, 'categoryIndex'])->name('categories.index');
Route::get('/brands', [CatalogController::class, 'brandIndex'])->name('brands.index');
Route::get('/stores', [CatalogController::class, 'storeIndex'])->name('stores.index');

/* ---- Content (public) ---- */
Route::get('/blog', [PageController::class, 'blogIndex'])->name('blog.index');
Route::get('/blog/{slug}', [PageController::class, 'blogShow'])->name('blog.show');
Route::get('/blog/feed.xml', [PageController::class, 'blogFeed'])->name('blog.feed');
Route::get('/docs', [PageController::class, 'docs'])->name('docs');
Route::get('/page/{slug}', [PageController::class, 'show'])->name('page.show');
Route::post('/cms-forms/{key}', [PageController::class, 'submitForm'])->middleware('throttle:10,1')->name('cms-forms.submit');

/* Named aliases for the static pages the footer and storefront link to. */
Route::get('/about', [PageController::class, 'show'])->defaults('slug', 'about')->name('page.about');
Route::get('/terms', [PageController::class, 'show'])->defaults('slug', 'terms')->name('page.terms');
Route::get('/privacy', [PageController::class, 'show'])->defaults('slug', 'privacy')->name('page.privacy');
Route::get('/return-policy', [PageController::class, 'show'])->defaults('slug', 'return')->name('page.return');
Route::get('/faq', [PageController::class, 'show'])->defaults('slug', 'faq')->name('page.faq');
Route::get('/seller', [PageController::class, 'show'])->defaults('slug', 'seller')->name('page.seller');

/* ---- Guest-accessible customer actions ---- */
Route::get('/track-order', [TrackOrderController::class, 'show'])->name('track-order');
Route::post('/track-order', [TrackOrderController::class, 'lookup'])->middleware('throttle:10,1')->name('track-order.lookup');

/* ---- Authenticated customer area ---- */
Route::middleware('customer')->group(function (): void {
    /* Cart */
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
    Route::put('/cart/{cart}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{cart}', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');

    /* Checkout */
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'process'])->middleware('throttle:10,1')->name('checkout.process');
    Route::post('/checkout/shipping-cost', [CheckoutController::class, 'shippingCost'])->middleware('throttle:30,1')->name('checkout.shipping-cost');

    /* Orders */
    Route::get('/orders', [StoreOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [StoreOrderController::class, 'show'])->name('orders.show');
    Route::post('/order-items/{orderItem}/refund', [StoreOrderController::class, 'requestRefund'])
        ->middleware('throttle:10,1')
        ->name('orders.refund.request');

    /* Wishlist & compare */
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::get('/compare', [CompareController::class, 'index'])->name('compare.index');
    Route::post('/compare/add', [WishlistController::class, 'addCompare'])->name('compare.add');
    Route::delete('/compare/{item}', [WishlistController::class, 'removeCompare'])->name('compare.remove');

    /* Account hub */
    Route::get('/account', [AccountController::class, 'dashboard'])->name('account.dashboard');
    Route::get('/account/addresses', [AccountController::class, 'addresses'])->name('account.addresses');
    Route::get('/account/wallet', [AccountController::class, 'wallet'])->name('account.wallet');
    Route::get('/account/notifications', [AccountController::class, 'notifications'])->name('account.notifications');
    Route::post('/account/notifications/{notification}/read', [AccountController::class, 'markRead'])->name('account.notifications.read');
    Route::get('/account/messages', [AccountController::class, 'messages'])->name('account.messages');
    Route::get('/account/security', [AccountController::class, 'security'])->name('account.security');
    Route::put('/account/security', [AccountController::class, 'updateSecurity'])->name('account.security.update');
    Route::get('/account/preferences', [AccountController::class, 'preferences'])->name('account.preferences');
    Route::put('/account/preferences', [AccountController::class, 'updatePreferences'])->name('account.preferences.update');
    Route::get('/account/reviews', [AccountController::class, 'reviews'])->name('account.reviews');

    /* Profile (legacy URL space, unchanged) */
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/address', [ProfileController::class, 'addressStore'])->name('profile.address.store');
    Route::delete('/profile/address/{address}', [ProfileController::class, 'addressDestroy'])->name('profile.address.destroy');

    /* Support */
    Route::resource('tickets', TicketController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');

    /* Loyalty */
    Route::get('/loyalty', [LoyaltyController::class, 'index'])->name('loyalty.index');
    Route::post('/loyalty/redeem', [LoyaltyController::class, 'redeem'])->name('loyalty.redeem');

    /* Reviews */
    Route::post('/reviews', [StoreReviewController::class, 'store'])->name('reviews.store');

    /* Restock / price alerts */
    Route::post('/restock', function (\Illuminate\Http\Request $request) {
        $request->validate(['product_id' => 'required|exists:products,id']);
        \App\Models\RestockRequest::firstOrCreate(
            ['product_id' => $request->product_id, 'customer_id' => auth()->id()],
            ['status' => 'pending']
        );

        return back()->with('success', 'Permintaan restock dikirim. Kami akan mengabari saat stok tersedia.');
    })->name('restock.request');

    /* Innovative features */
    Route::get('/recommendations/{product:slug}', [InnovativeController::class, 'recommendations'])->name('recommendations');
    Route::post('/alerts', [InnovativeController::class, 'setAlert'])->name('alerts.set');
    Route::get('/bundles', [InnovativeController::class, 'bundles'])->name('bundles');
    Route::get('/group-buys', [InnovativeController::class, 'groupBuys'])->name('group-buys');
    Route::post('/group-buys/{groupBuy}/join', [InnovativeController::class, 'joinGroup'])->name('group-buys.join');
    Route::get('/feed', [InnovativeController::class, 'feed'])->name('feed');
    Route::get('/leaderboard', [InnovativeController::class, 'leaderboard'])->name('leaderboard');
    Route::get('/products/{product:slug}/price-history', [InnovativeController::class, 'priceHistory'])->name('products.price-history');

    /* Digital downloads */
    Route::post('/orders/{orderItem}/download/otp', [DigitalDownloadController::class, 'requestOtp'])->name('download.otp');
    Route::post('/orders/{orderItem}/download', [DigitalDownloadController::class, 'verify'])->name('download.verify');

    /* Delivery rating */
    Route::get('/orders/{order}/rate-delivery', [DeliveryRatingController::class, 'create'])->name('delivery.rate');
    Route::post('/orders/{order}/rate-delivery', [DeliveryRatingController::class, 'store'])->name('delivery.rate.store');
});

/* Public lead capture used by the source-code landing page. */
Route::post('/landing/contact', function (Request $request) {
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:120'],
        'email' => ['required', 'email', 'max:180'],
        'phone' => ['nullable', 'string', 'max:32'],
        'company' => ['nullable', 'string', 'max:160'],
        'message' => ['required', 'string', 'max:2000'],
        'consent' => ['accepted'],
        'source' => ['nullable', 'string', 'max:80'],
    ]);

    $key = 'landing-lead:'.$request->ip();

    if (RateLimiter::tooManyAttempts($key, 5)) {
        return back()
            ->withErrors(['email' => 'Terlalu banyak permintaan. Silakan coba beberapa menit lagi.'])
            ->with('error', 'Terlalu banyak permintaan dari perangkat ini.');
    }

    RateLimiter::hit($key, 300);

    $stored = false;

    if (Schema::hasTable('storefront_leads')) {
        try {
            DB::table('storefront_leads')->insert([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'company' => $validated['company'] ?? null,
                'message' => $validated['message'],
                'source' => $validated['source'] ?? 'landing',
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500),
                'is_handled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $stored = true;
        } catch (Throwable $e) {
            report($e);
        }
    }

    return back()->with('success', $stored
        ? 'Permintaan Anda sudah kami terima. Tim kami akan menghubungi Anda segera.'
        : 'Permintaan Anda sudah kami terima.');
})->name('pseo.contact');

Route::post('/landing/newsletter', function (Request $request) {
    $validated = $request->validate([
        'email' => ['required', 'email', 'max:180'],
        'source' => ['nullable', 'string', 'max:80'],
    ]);

    $key = 'landing-newsletter:'.$request->ip();

    if (RateLimiter::tooManyAttempts($key, 10)) {
        return back()->with('error', 'Terlalu banyak permintaan. Silakan coba beberapa menit lagi.');
    }

    RateLimiter::hit($key, 300);

    if (Schema::hasTable('storefront_leads')) {
        try {
            $exists = DB::table('storefront_leads')
                ->where('email', $validated['email'])
                ->where('source', 'newsletter')
                ->exists();

            if (! $exists) {
                DB::table('storefront_leads')->insert([
                    'name' => 'Newsletter',
                    'email' => $validated['email'],
                    'message' => 'Langganan newsletter',
                    'source' => 'newsletter',
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 500),
                    'is_handled' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    return back()->with('success', 'Berhasil berlangganan newsletter.');
})->name('newsletter.subscribe');

/*
 * Product / category / brand / store detail pages.
 * Declared last (before the PSEO catch-all) so a slug that matches both a
 * product and a PSEO pattern always resolves to the real product.
 */
Route::get('/products/{slug}', [CatalogController::class, 'product'])->name('products.show');
/* Legacy singular alias: 301 to the canonical plural URL (no model binding). */
Route::get('/product/{slug}', fn (string $slug) => redirect()->route('products.show', ['slug' => $slug], 301))->name('products.singular');
Route::get('/category/{slug}', [CatalogController::class, 'category'])->name('categories.show');
Route::get('/brand/{slug}', [CatalogController::class, 'brand'])->name('brands.show');
Route::get('/store/{slug}', [StoreShopController::class, 'show'])->name('stores.show');
Route::get('/shop/{shop:slug}', [StoreShopController::class, 'show'])->name('shop.show');
