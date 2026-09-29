<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Vendor\VendorInventoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request, VendorInventoryService $inventory): View
    {
        $data = $inventory->overview(
            VendorScopeRequest::search($request),
            VendorScopeRequest::stockFilter($request),
        );

        return view('vendor.inventory.index', [
            'products' => $data['products'],
            'stats' => $data['stats'],
            'search' => $data['search'],
            'stock' => $data['stock'],
        ]);
    }

    public function movements(Request $request, VendorInventoryService $inventory): View
    {
        return view('vendor.inventory.movements', [
            'movements' => $inventory->movements([
                'product_id' => $request->integer('product_id') ?: null,
                'type' => VendorScopeRequest::movementType($request),
            ]),
            'products' => $inventory->overview('', '')['products']->getCollection()->pluck('name', 'id'),
            'types' => [
                'in' => 'Barang masuk',
                'out' => 'Barang keluar',
                'adjustment' => 'Penyesuaian',
            ],
            'selected' => [
                'product_id' => $request->integer('product_id') ?: '',
                'type' => VendorScopeRequest::movementType($request),
            ],
        ]);
    }

    public function adjust(Request $request, VendorInventoryService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'mode' => ['required', 'in:increase,decrease,set'],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $product = Product::query()->findOrFail($validated['product_id']);
        abort_if((int) $product->shop_id !== (int) auth('vendor')->user()->shop_id, 403);

        $current = (int) $product->current_stock;
        $target = $validated['mode'] === 'set'
            ? (int) $validated['quantity']
            : $current + ($validated['mode'] === 'increase' ? 1 : -1) * (int) $validated['quantity'];

        $delta = $target - $current;

        if ($delta !== 0) {
            $inventory->adjust($product, $delta, 'adjustment', $validated['reason']);
        }

        return back()->with(
            $delta === 0 ? 'warning' : 'success',
            $delta === 0
                ? 'Stok tidak berubah karena nilai baru sama dengan stok saat ini.'
                : 'Stok '.$product->name.' diperbarui menjadi '.$target.' unit.'
        );
    }
}
