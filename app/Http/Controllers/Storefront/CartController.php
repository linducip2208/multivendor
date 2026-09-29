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
        $customerId = auth()->id();
        $cartItems = Cart::where('customer_id', $customerId)
            ->with(['product.shop', 'variant'])
            ->get()
            ->filter(fn (Cart $item) => $item->product !== null)
            ->each(function (Cart $item): void {
                $item->price = $item->variant?->getEffectivePrice() ?? $item->product?->getEffectivePrice();
            });

        // Simpan-untuk-nanti memakai sesi (tanpa migrasi): id cart yang diparkir.
        $savedIds = array_values(array_filter(array_map('intval', (array) session('saved_for_later', []))));
        $saved = $cartItems->whereIn('id', $savedIds);
        $active = $cartItems->whereNotIn('id', $savedIds);

        $grouped = $active->groupBy(fn ($item) => $item->product?->shop_id);

        $shops = [];
        foreach ($grouped as $shopId => $items) {
            $shop = $items->first()->product->shop;
            if ($shop === null) {
                continue;
            }
            $subtotal = Money::sum(array_map(
                static fn (Cart $item) => Money::of($item->price)->multiply((int) $item->quantity),
                $items->all()
            ));
            $weight = $items->sum(fn ($item) => max(1, (int) ($item->product->weight ?? 1000)) * (int) $item->quantity);
            $shops[] = ['shop' => $shop, 'items' => $items, 'subtotal' => $subtotal->toFloat(), 'weight' => $weight];
        }

        $total = Money::sum(array_map(static fn (array $group) => Money::of($group['subtotal']), $shops))->toFloat();

        $this->trackAbandoned($customerId, $active, (float) $total);

        $repeatSchedules = $this->repeatSchedules($customerId);

        return view('storefront.cart.index', compact('shops', 'total', 'saved', 'repeatSchedules'));
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
        try {
            $price = app(\App\Services\B2b\B2bPricingService::class)->unitPriceFor($product, (int) $quantity);
        } catch (\Throwable) {
        }
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

        // Aksi simpan-untuk-nanti / kembalikan memakai rute update existing.
        $action = (string) $request->input('action', '');

        if ($action === 'save_for_later') {
            $saved = array_values(array_unique([...(array) session('saved_for_later', []), $cart->id]));
            session(['saved_for_later' => $saved]);

            return back()->with('success', 'Item dipindahkan ke simpan-untuk-nanti.');
        }

        if ($action === 'move_to_cart') {
            session(['saved_for_later' => array_values(array_diff((array) session('saved_for_later', []), [$cart->id]))]);

            return back()->with('success', 'Item dikembalikan ke keranjang.');
        }

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

    /**
     * Daftarkan repeat-order langganan: jadwal ulang otomatis yang membuat
     * draf cart (bukan order langsung). Dieksekusi via Cart::runDueRepeats().
     */
    public function scheduleRepeat(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1|max:1000',
            'frequency' => 'required|string|in:daily,weekly,monthly',
        ]);

        Cart::scheduleRepeat([
            'customer_id' => auth()->id(),
            'product_id' => $validated['product_id'],
            'product_variant_id' => $validated['variant_id'] ?? null,
            'quantity' => $validated['quantity'],
            'frequency' => $validated['frequency'],
        ]);

        return back()->with('success', 'Jadwal repeat-order dibuat. Draf cart akan dibuat otomatis sesuai jadwal.');
    }

    /** Daftar jadwal repeat-order aktif milik pelanggan (kosong bila tabel belum ada). */
    private function repeatSchedules(int $customerId): array
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('order_repeat_schedules')) {
                return [];
            }

            return \Illuminate\Support\Facades\DB::table('order_repeat_schedules')
                ->join('products', 'products.id', '=', 'order_repeat_schedules.product_id')
                ->where('order_repeat_schedules.customer_id', $customerId)
                ->where('order_repeat_schedules.is_active', true)
                ->orderBy('order_repeat_schedules.next_run_at')
                ->select('order_repeat_schedules.*', 'products.name as product_name')
                ->get()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function assertCartable(Product $product, int $quantity, ?ProductVariant $variant = null): void
    {
        if ($product->status !== 'approved' || ! $product->published || $product->shop === null || $product->shop->status !== 'active') abort(422, 'Produk tidak tersedia.');
        if ($product->shop->vacation_mode) abort(422, 'Toko sedang libur.');
        if ($quantity < $product->min_qty || ($product->max_qty && $quantity > $product->max_qty)) abort(422, 'Kuantitas tidak memenuhi batas pembelian.');
        $stock = $variant?->stock ?? $product->current_stock;
        if ($quantity > $stock) abort(422, 'Stok tidak mencukupi.');
    }

    /** Catat keranjang terbengkalai untuk pengingat (tabel abandoned_carts existing). */
    private function trackAbandoned(int $customerId, $activeItems, float $total): void
    {
        try {
            if ($activeItems->isEmpty()) {
                return;
            }

            \Illuminate\Support\Facades\DB::table('abandoned_carts')->updateOrInsert(
                ['customer_id' => $customerId, 'recovered_at' => null],
                [
                    'item_count' => $activeItems->count(),
                    'amount' => $total,
                    'items' => json_encode($activeItems->map(fn (Cart $i) => [
                        'product_id' => $i->product_id, 'quantity' => $i->quantity, 'price' => $i->price,
                    ])->all()),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        } catch (\Throwable) {
        }
    }
}
