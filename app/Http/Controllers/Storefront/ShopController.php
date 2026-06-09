<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Shop;

class ShopController extends Controller
{
    public function show(Shop $shop)
    {
        $shop->load(['products' => fn($q) => $q->where('status', 'approved')->where('published', true)->latest()->take(12)]);
        return view('storefront.shop.show', compact('shop'));
    }
}
