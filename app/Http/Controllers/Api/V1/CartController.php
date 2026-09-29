<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\CartItemResource;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Api\InsufficientStockException;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $items = Cart::where('customer_id', $request->user()->id)
            ->with(['product.shop', 'product.category', 'product.brand', 'variant'])
            ->orderBy('id')
            ->get();

        $subtotal = 0.0;
        $tax = 0.0;

        foreach ($items as $item) {
            $subtotal += (float) $item->price * (int) $item->quantity;
            $tax += (float) $item->tax * (int) $item->quantity;
        }

        $meta = ApiResponse::meta(['count' => $items->count()]);
        $meta['totals'] = [
            'subtotal' => ApiResponse::money($subtotal),
            'tax' => ApiResponse::money($tax),
            'total' => ApiResponse::money($subtotal + $tax),
            'currency' => 'IDR',
        ];

        return $this->ok(CartItemResource::collection($items)->resolve($request), 'OK', $meta);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
            'quantity' => 'required|integer|min:1|max:10000',
        ]);

        $product = Product::with('shop')->findOrFail($data['product_id']);
        $variant = $this->variantFor($product, $data['variant_id'] ?? null);
        $existing = Cart::where([
            'customer_id' => $request->user()->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
        ])->first();

        $quantity = (int) $data['quantity'] + (int) ($existing?->quantity ?? 0);
        $this->assertCartable($product, $variant, $quantity);

        $cart = Cart::updateOrCreate(
            [
                'customer_id' => $request->user()->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
            ],
            [
                'quantity' => $quantity,
                'price' => $variant?->getEffectivePrice() ?? $product->getEffectivePrice(),
                'tax' => $product->tax,
            ]
        );

        $this->markResource($request, 'cart', (int) $cart->id);

        return $this->created(new CartItemResource($cart->load(['product.shop', 'variant'])), 'Produk ditambahkan');
    }

    public function update(Request $request, int $cart): JsonResponse
    {
        $model = $this->owned($request, $cart);
        $data = $request->validate(['quantity' => 'required|integer|min:1|max:10000']);

        $model->load(['product.shop', 'variant']);
        $this->assertCartable($model->product, $model->variant, (int) $data['quantity']);
        $model->fill($data)->save();

        return $this->ok(new CartItemResource($model->fresh(['product.shop', 'variant'])), 'Keranjang diperbarui');
    }

    public function destroy(Request $request, int $cart): JsonResponse
    {
        $this->owned($request, $cart)->delete();

        return $this->ok(null, 'Item dihapus');
    }

    public function clear(Request $request): JsonResponse
    {
        $count = Cart::where('customer_id', $request->user()->id)->delete();

        return $this->ok(['removed' => $count], 'Keranjang dikosongkan');
    }

    private function owned(Request $request, int $cart): Cart
    {
        $model = Cart::where('customer_id', $request->user()->id)->whereKey($cart)->first();

        $this->abortUnlessOwned($model !== null, 'cart_item_not_found');

        return $model;
    }

    private function variantFor(Product $product, ?int $variantId): ?ProductVariant
    {
        if ($variantId === null) {
            return null;
        }

        $variant = ProductVariant::whereKey($variantId)->where('product_id', $product->id)->first();

        $this->abortUnlessOwned($variant !== null, 'variant_not_found');

        return $variant;
    }

    private function assertCartable(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        $shop = $product->shop;

        if ($product->status !== 'approved' || ! $product->published || $shop === null || $shop->status !== 'active' || $shop->vacation_mode) {
            throw new \DomainException('Produk tidak tersedia.');
        }

        if ($quantity < (int) $product->min_qty || ((int) $product->max_qty > 0 && $quantity > (int) $product->max_qty)) {
            throw new \DomainException('Kuantitas tidak valid.');
        }

        $stock = $variant === null ? (int) $product->current_stock : (int) $variant->stock;

        if ($quantity > $stock) {
            throw new \App\Services\Api\InsufficientStockException($stock);
        }
    }
}
