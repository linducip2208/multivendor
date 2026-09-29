<?php

declare(strict_types=1);

use App\Http\Controllers\PseoController;
use App\Http\Controllers\PseoSitemapController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Storefront\PageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SEO routes — sitemaps, robots, redirects
|--------------------------------------------------------------------------
*/

/* ---- Sitemaps ---- */
Route::get('/sitemap.xml', [PseoSitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-main.xml', [SitemapController::class, 'main'])->name('sitemap.main');
Route::get('/sitemap-products.xml', [SitemapController::class, 'products'])->name('sitemap.products');
Route::get('/sitemap-products-{num}.xml', [SitemapController::class, 'productChunk'])->where('num', '[0-9]+')->name('sitemap.products.chunk');
Route::get('/sitemap-categories.xml', [SitemapController::class, 'categories'])->name('sitemap.categories');
Route::get('/sitemap-brands.xml', [SitemapController::class, 'brands'])->name('sitemap.brands');
Route::get('/sitemap-stores.xml', [SitemapController::class, 'stores'])->name('sitemap.stores');
Route::get('/sitemap-blog.xml', [SitemapController::class, 'blog'])->name('sitemap.blog');
Route::get('/sitemap-pages.xml', [SitemapController::class, 'pages'])->name('sitemap.pages');
Route::get('/sitemap-pseo-{num}.xml', [PseoSitemapController::class, 'chunk'])->where('num', '[0-9]+')->name('sitemap.pseo.chunk');

/* ---- Legacy sitemap route names (kept, now pointing at the same builders) ---- */
Route::get('/sitemap-products-all.xml', [SitemapController::class, 'products'])->name('sitemap.products.all');

/* ---- Robots ---- */
Route::get('/robots.txt', function () {
    $lines = [
        'User-agent: *',
        'Allow: /',
        'Disallow: /admin',
        'Disallow: /vendor',
        'Disallow: /delivery',
        'Disallow: /api/',
        'Disallow: /account',
        'Disallow: /cart',
        'Disallow: /checkout',
        'Disallow: /orders',
        'Disallow: /wishlist',
        'Disallow: /compare',
        'Disallow: /webhook/',
        'Disallow: /__pair',
        'Disallow: /search',
        'Disallow: /*?sort=',
        'Disallow: /*?min_price=',
        'Disallow: /*?max_price=',
        '',
        'User-agent: GPTBot',
        'Allow: /',
        'Disallow: /admin',
        'Disallow: /vendor',
        'Disallow: /api/',
        '',
        'Sitemap: '.url('/sitemap.xml'),
    ];

    return response(implode("\n", $lines), 200, [
        'Content-Type' => 'text/plain; charset=utf-8',
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->name('robots');

/* ---- Blog RSS ---- */
Route::get('/blog/feed.xml', [PageController::class, 'blogFeed'])->name('blog.feed');
