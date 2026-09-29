<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
| The route table is split by audience so each surface can be maintained and
| reasoned about independently. Every previously registered route name is
| preserved — see docs/architecture/routing.md for the migration map.
|
|   routes/storefront.php  customer-facing catalogue + account
|   routes/admin.php       admin backoffice
|   routes/vendor.php      seller OS + delivery
|   routes/seo.php         sitemaps, robots, redirects
|   routes/pair-routes.php licence pairing wizard
|
*/

use App\Http\Controllers\PseoController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Webhook\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/* ------------------------------------------------------------------ *
 * Storefront
 * ------------------------------------------------------------------ */
require __DIR__.'/storefront.php';

/* ------------------------------------------------------------------ *
 * Payment callbacks
 *
 * CSRF exempt, throttled, signature verified inside the controller. Kept in the
 * web group for backwards compatibility of the URL.
 * ------------------------------------------------------------------ */
Route::post('/webhook/payment/{provider}', PaymentWebhookController::class)
    ->middleware(['throttle:payment-webhook', 'license.exempt'])
    ->name('webhook.payment');

/* ------------------------------------------------------------------ *
 * SEO / infrastructure
 * ------------------------------------------------------------------ */
require __DIR__.'/seo.php';

/* ------------------------------------------------------------------ *
 * Admin & vendor backoffices
 * ------------------------------------------------------------------ */
require __DIR__.'/admin.php';
require __DIR__.'/vendor.php';

/* ------------------------------------------------------------------ *
 * Programme SEO — quality-gated, not combinatorial
 *
 * The previous implementation generated ~1.3M doorway URLs from a
 * platform x city x feature cross-product. Those URLs have been retired:
 * they had no unique search intent, no real inventory and no editorial
 * review, which is exactly the scaled-content-abuse pattern Google
 * penalises. `/source-code` remains as a genuine commercial landing page.
 * ------------------------------------------------------------------ */
Route::get('/source-code', [PseoController::class, 'sourceCode'])->name('pseo.source-code');

require __DIR__.'/pair-routes.php';

/* ------------------------------------------------------------------ *
 * Media passthrough
 *
 * Product images live under storage/app/public and are served through this
 * route so the deployment does not require a `public/storage` symlink.
 * ------------------------------------------------------------------ */
Route::get('img/{path}', function (string $path) {
    // Reject traversal before touching the filesystem.
    if (str_contains($path, '..') || str_starts_with($path, '/')) {
        abort(404);
    }

    $fullPath = storage_path('app/public/'.$path);
    $real = realpath($fullPath);
    $root = realpath(storage_path('app/public'));

    if ($real === false || $root === false || ! str_starts_with($real, $root) || ! is_file($real)) {
        abort(404);
    }

    return response()->file($real, [
        'Cache-Control' => 'public, max-age=2592000, immutable',
        'X-Content-Type-Options' => 'nosniff',
    ]);
})->where('path', '.*')->name('img.serve');

/*
 * Legacy storefront path aliases. Kept as real redirects so existing links,
 * bookmarks and any acquired inbound SEO equity survive the IA change.
 */
Route::redirect('/shop-all', '/stores', 301)->name('shop-all');
Route::redirect('/promo', '/deals', 301)->name('promo');
Route::redirect('/promotions', '/deals', 301)->name('promotions');
Route::redirect('/terlaris', '/best-sellers', 301);
Route::redirect('/baru', '/new-arrivals', 301);
Route::redirect('/flashsale', '/flash-sale', 301);
Route::redirect('/kategori', '/categories', 301);
Route::redirect('/brand', '/brands', 301);
Route::redirect('/semua-toko', '/stores', 301);
Route::redirect('/akun', '/account', 301);

/*
 * Admin-managed 301/410 redirect table.
 *
 * Registered last so it only ever sees URLs that no real route claimed. The
 * previous implementation used a PSEO catch-all here that answered HTTP 200
 * with a sales page for any unmatched path, which silently swallowed /search,
 * /deals, /stores and every typo. Unknown URLs now correctly 404 unless an
 * administrator has explicitly mapped them.
 */
Route::get('/{path}', [\App\Http\Controllers\RedirectController::class, 'resolve'])
    ->where('path', '.*')
    ->name('redirect.resolve');
