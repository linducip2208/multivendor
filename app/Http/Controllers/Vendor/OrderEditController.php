<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Vendor\OrderEditService;
use App\Support\Currency;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderEditController extends Controller
{
    public function __construct(private readonly OrderEditService $editor) {}

    public function edit(Request $request, Order $order): View
    {
        $this->assertOwned($order);

        $shop = auth('vendor')->user()->shop;

        $products = $shop->products()
            ->where('status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'price', 'tax', 'tax_type', 'thumbnail', 'current_stock']);

        $order->load(['items.product', 'items.variant']);

        return view('vendor.order-edit.index', [
            'order' => $order,
            'products' => $products,
            'currency' => Currency::config(),
            'editableStatuses' => OrderEditService::editableStatuses(),
        ]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $this->assertOwned($order);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.product_variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'shipping_tracking_id' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->editor->apply($order, $validated['items'], $validated);

        return redirect()->route('vendor.orders.show', $order)
            ->with('success', 'Pesanan diperbarui. Subtotal dan pajak dihitung ulang.');
    }

    private function assertOwned(Order $order): void
    {
        abort_if((int) $order->shop_id !== (int) auth('vendor')->user()->shop_id, 403);
    }
}
