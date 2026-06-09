<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FlashDeal;
use App\Models\Product;
use Illuminate\Http\Request;

class FlashDealController extends Controller
{
    public function index()
    {
        $flashDeals = FlashDeal::withCount('products')->latest()->paginate(10);
        return view('admin.flashdeals.index', compact('flashDeals'));
    }

    public function create()
    {
        $products = Product::where('status', 'approved')->get();
        return view('admin.flashdeals.create', compact('products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'boolean',
            'featured' => 'boolean',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.discount_type' => 'required|in:flat,percentage',
            'products.*.discount_value' => 'required|numeric|min:0',
        ]);

        $deal = FlashDeal::create([
            'title' => $validated['title'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => $request->boolean('status'),
            'featured' => $request->boolean('featured'),
        ]);

        foreach ($validated['products'] as $p) {
            $deal->dealProducts()->create([
                'product_id' => $p['id'],
                'discount_type' => $p['discount_type'],
                'discount_value' => $p['discount_value'],
            ]);
        }

        return redirect()->route('admin.flashdeals.index')->with('success', 'Flash deal dibuat.');
    }

    public function edit(FlashDeal $flashdeal)
    {
        $flashdeal->load('dealProducts.product');
        $products = Product::where('status', 'approved')->get();
        return view('admin.flashdeals.edit', compact('flashdeal', 'products'));
    }

    public function update(Request $request, FlashDeal $flashdeal)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'boolean',
            'featured' => 'boolean',
        ]);

        $flashdeal->update($validated);

        if ($request->has('products')) {
            $flashdeal->dealProducts()->delete();
            foreach ($request->products as $p) {
                $flashdeal->dealProducts()->create([
                    'product_id' => $p['id'],
                    'discount_type' => $p['discount_type'] ?? 'percentage',
                    'discount_value' => $p['discount_value'] ?? 0,
                ]);
            }
        }

        return redirect()->route('admin.flashdeals.index')->with('success', 'Flash deal diperbarui.');
    }

    public function destroy(FlashDeal $flashdeal)
    {
        $flashdeal->delete();
        return back()->with('success', 'Flash deal dihapus.');
    }
}
