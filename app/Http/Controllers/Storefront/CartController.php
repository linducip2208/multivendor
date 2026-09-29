<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index()
    {
        $cartItems = Cart::where('customer_id', auth()->id())
            ->with(['product.shop', 'variant'])
            ->get()
            ->filter(fn (Cart $item) => $item->product !== null)
            ->each(function (Cart $item): void {
                $item->price = $item->variant?->getEffectivePrice() ?? $item->product?->getEffectivePrice();
            })
            ->groupBy(fn ($item) => $item->product?->shop_id);

        $shops = [];
        foreach ($cartItems as $shopId => $items) {
            $shop = $items->first()->product->shop;
            if ($shop === null) {
                continue;
            }
            $subtotal = Money::sum(array_map(
                static fn (Cart $item) => Money::of($item->price)->multiply((int) $item->quantity),
                $items->all()
            ));
            $shops[] = ['shop' => $shop, 'items' => $items, 'subtotal' => $subtotal->toFloat()];
        }

        $total = Money::sum(array_map(static fn (array $group) => Money::of($group['subtotal']), $shops))->toFloat();

        return view('storefront.cart.index', compact('shops', 'total'));
    }

    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1|max:1000',
        ]);

        $product = Product::with('shop')->findOrFail($request->product_id);
        $quantity = $request->quantity;
        $price = $product->getEffectivePrice();
        $variant = null;

        $this->assertCartable($product, $quantity);

        if ($request->variant_id) {
            $variant = ProductVariant::whereKey($request->variant_id)->where('product_id', $product->id)->first();
            if (!$variant) return back()->withInput()->with('error', 'Varian tidak sesuai dengan produk.');
            if ($variant->stock < $quantity) return back()->withInput()->with('error', 'Stok varian tidak mencukupi.');
            $price = $variant->getEffectivePrice();
        }

        $existingCart = Cart::where('customer_id', auth()->id())
            ->where('product_id', $product->id)
            ->when(
                $request->variant_id,
                fn ($query) => $query->where('product_variant_id', $request->variant_id),
                fn ($query) => $query->whereNull('product_variant_id')
            )
            ->first();

        if ($existingCart) {
            $newQuantity = $existingCart->quantity + $quantity;
            $this->assertCartable($product, $newQuantity, $variant);
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

        $request->validate(['quantity' => 'required|integer|min:1|max:1000']);
        $cart->load('product.shop', 'variant');
        if ($cart->product === null) {
            $cart->delete();

            return back()->with('error', 'Produk sudah tidak tersedia dan telah dihapus dari keranjang.');
        }
        $this->assertCartable($cart->product, (int) $request->quantity, $cart->variant);
        $cart->update([
            'quantity' => $request->quantity,
            'price' => $cart->variant?->getEffectivePrice() ?? $cart->product->getEffectivePrice(),
        ]);
        return back()->with('success', 'Keranjang diperbarui.');
    }

    public function remove(Cart $cart)
    {
        if ($cart->customer_id !== auth()->id()) abort(403);
        $cart->delete();
        return back()->with('success', 'Item dihapus dari keranjang.');
    }

    public function clear()
    {
        Cart::where('customer_id', auth()->id())->delete();
        return back()->with('success', 'Keranjang dikosongkan.');
    }

    private function assertCartable(Product $product, int $quantity, ?ProductVariant $variant = null): void
    {
        if ($product->status !== 'approved' || ! $product->published || $product->shop === null || $product->shop->status !== 'active') abort(422, 'Produk tidak tersedia.');
        if ($product->shop->vacation_mode) abort(422, 'Toko sedang libur.');
        if ($quantity < $product->min_qty || ($product->max_qty && $quantity > $product->max_qty)) abort(422, 'Kuantitas tidak memenuhi batas pembelian.');
        $stock = $variant?->stock ?? $product->current_stock;
        if ($quantity > $stock) abort(422, 'Stok tidak mencukupi.');
    }
}
