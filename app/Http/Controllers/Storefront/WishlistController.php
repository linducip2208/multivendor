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

    /**
     * Legacy compare page alias — delegates to the canonical
     * CompareController::index so both URLs render identically.
     */
    public function compare(Request $request)
    {
        return app(CompareController::class)->index($request);
    }

    /**
     * Legacy compare.add alias — delegates to the canonical
     * CompareController::add. Route kept for backwards compatibility.
     */
    public function addCompare(Request $request)
    {
        return app(CompareController::class)->add($request);
    }

    /**
     * Legacy compare.remove alias — delegates to the canonical
     * CompareController::remove. Route kept for backwards compatibility.
     */
    public function removeCompare(Request $request, CompareList $item)
    {
        return app(CompareController::class)->remove($request, $item);
    }
}
