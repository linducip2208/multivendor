<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['shop', 'category'])->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->shop_id);
        }

        $products = $query->paginate(15)->withQueryString();
        return view('admin.products.index', compact('products'));
    }

    public function show(Product $product)
    {
        $product->load(['shop', 'category', 'brand', 'variants', 'reviews.customer']);
        return view('admin.products.show', compact('product'));
    }

    public function updateStatus(Request $request, Product $product)
    {
        $request->validate([
            'status' => 'required|in:pending,approved,suspended',
        ]);

        $product->update([
            'status' => $request->status,
            'approved_by' => auth('admin')->id(),
            'approved_at' => $request->status === 'approved' ? now() : null,
        ]);

        $labels = ['pending' => 'Pending', 'approved' => 'Disetujui', 'suspended' => 'Ditangguhkan'];
        return back()->with('success', 'Status produk diubah: ' . $labels[$request->status]);
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil dihapus.');
    }
}
