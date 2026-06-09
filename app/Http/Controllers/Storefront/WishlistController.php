<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\Models\CompareList;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index()
    {
        $items = Wishlist::where('customer_id', auth()->id())->with('product.shop')->latest()->paginate(20);
        return view('storefront.wishlist.index', compact('items'));
    }

    public function toggle(Request $request)
    {
        $request->validate(['product_id' => 'required|exists:products,id']);
        $existing = Wishlist::where('customer_id', auth()->id())->where('product_id', $request->product_id)->first();
        if ($existing) { $existing->delete(); return back()->with('success', 'Dihapus dari wishlist.'); }
        Wishlist::create(['customer_id' => auth()->id(), 'product_id' => $request->product_id]);
        return back()->with('success', 'Ditambahkan ke wishlist.');
    }

    public function compare()
    {
        $items = CompareList::where('customer_id', auth()->id())->with('product')->latest()->take(4)->get();
        return view('storefront.wishlist.compare', compact('items'));
    }

    public function addCompare(Request $request)
    {
        $request->validate(['product_id' => 'required|exists:products,id']);
        $count = CompareList::where('customer_id', auth()->id())->count();
        if ($count >= 4) CompareList::where('customer_id', auth()->id())->oldest()->first()->delete();
        CompareList::firstOrCreate(['customer_id' => auth()->id(), 'product_id' => $request->product_id]);
        return back()->with('success', 'Ditambahkan ke perbandingan.');
    }

    public function removeCompare(CompareList $item)
    {
        if ($item->customer_id !== auth()->id()) abort(403);
        $item->delete();
        return back()->with('success', 'Dihapus dari perbandingan.');
    }
}
