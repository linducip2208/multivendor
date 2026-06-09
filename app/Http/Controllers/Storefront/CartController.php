<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index()
    {
        $cartItems = Cart::where('customer_id', auth()->id())
            ->with(['product.shop', 'variant'])
            ->get()
            ->groupBy(fn ($item) => $item->product->shop_id);

        $shops = [];
        foreach ($cartItems as $shopId => $items) {
            $shop = $items->first()->product->shop;
            $subtotal = $items->sum(fn ($i) => $i->price * $i->quantity);
            $shops[] = ['shop' => $shop, 'items' => $items, 'subtotal' => $subtotal];
        }

        $total = collect($shops)->sum('subtotal');

        return view('storefront.cart.index', compact('shops', 'total'));
    }

    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($request->product_id);
        $quantity = $request->quantity;
        $price = $product->getEffectivePrice();

        if ($request->variant_id) {
            $variant = ProductVariant::find($request->variant_id);
            if ($variant && $variant->product_id === $product->id) {
                $price = $variant->special_price && $variant->discount_start <= now() && $variant->discount_end >= now()
                    ? $variant->special_price : $variant->price;
            }
        }

        $existingCart = Cart::where('customer_id', auth()->id())
            ->where('product_id', $product->id)
            ->where('product_variant_id', $request->variant_id)
            ->first();

        if ($existingCart) {
            $existingCart->increment('quantity', $quantity);
            $existingCart->update(['price' => $price]);
        } else {
            Cart::create([
                'customer_id' => auth()->id(),
                'product_id' => $product->id,
                'product_variant_id' => $request->variant_id,
                'quantity' => $quantity,
                'price' => $price,
                'tax' => $product->tax,
            ]);
        }

        return redirect()->route('cart.index')->with('success', 'Produk ditambahkan ke keranjang.');
    }

    public function update(Request $request, Cart $cart)
    {
        if ($cart->customer_id !== auth()->id()) abort(403);

        $request->validate(['quantity' => 'required|integer|min:1']);
        $cart->update(['quantity' => $request->quantity]);
        return back()->with('success', 'Keranjang diperbarui.');
    }

    public function remove(Cart $cart)
    {
        if ($cart->customer_id !== auth()->id()) abort(403);
        $cart->delete();
        return back()->with('success', 'Item dihapus dari keranjang.');
    }
}
