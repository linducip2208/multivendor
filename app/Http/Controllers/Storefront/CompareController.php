<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CompareList;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Product comparison.
 *
 * Backwards compatible: the legacy `compare_lists` table and the historic
 * `compare.add` / `compare.remove` endpoints are preserved, while the
 * canonical URLs are now `/compare` (page) and `/compare/add` (action).
 */
class CompareController extends Controller
{
    public const MAX_ITEMS = 4;

    public function index(Request $request)
    {
        $ids = $this->selectedIds($request);

        $products = Product::query()
            ->whereIn('id', $ids)
            ->where('status', 'approved')
            ->where('published', true)
            ->with(['shop', 'category', 'brand', 'variants'])
            ->get()
            ->keyBy('id');

        $ordered = collect($ids)->map(fn (int $id) => $products->get($id))->filter()->values();

        return view('storefront.wishlist.compare', [
            'items' => $ordered,
            'products' => $ordered,
            'slots' => max(0, self::MAX_ITEMS - $ordered->count()),
        ]);
    }

    public function add(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $product = Product::where('status', 'approved')->where('published', true)
            ->findOrFail($validated['product_id']);

        $ids = $this->selectedIds($request);

        if (in_array($product->id, $ids, true)) {
            return $this->respond($request, 'Produk sudah ada di daftar perbandingan.', 'info');
        }

        if (count($ids) >= self::MAX_ITEMS) {
            return $this->respond(
                $request,
                'Maksimal '.self::MAX_ITEMS.' produk dapat dibandingkan sekaligus.',
                'error',
                status: 422
            );
        }

        CompareList::firstOrCreate([
            'customer_id' => auth()->id(),
            'product_id' => $product->id,
        ]);

        return $this->respond($request, 'Produk ditambahkan ke perbandingan.', 'success');
    }

    public function remove(Request $request, CompareList $item)
    {
        abort_unless((int) $item->customer_id === (int) auth()->id(), 403);

        $item->delete();

        return $this->respond($request, 'Produk dihapus dari perbandingan.', 'info');
    }

    /** @return list<int> */
    private function selectedIds(Request $request): array
    {
        $raw = $request->input('products', $request->input('ids', []));
        if (is_string($raw)) {
            $raw = array_filter(explode(',', $raw));
        }

        $ids = collect((array) $raw)->map(fn ($v) => (int) $v)->filter()->unique()->values()->all();

        return $ids !== [] ? $ids : CompareList::where('customer_id', auth()->id())
            ->orderByDesc('id')
            ->limit(self::MAX_ITEMS)
            ->pluck('product_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private function respond(Request $request, string $message, string $type, int $status = 200)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => $message, 'type' => $type], $status);
        }

        return back()->with($type === 'error' ? 'error' : 'success', $message);
    }
}
