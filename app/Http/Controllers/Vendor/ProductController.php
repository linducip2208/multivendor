<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $shop = auth('vendor')->user()->shop;
        if (!$shop) return redirect()->route('vendor.dashboard')->with('error', 'Toko belum disetujui.');
        $query = Product::where('shop_id', $shop->id)->with('category')->latest();
        if ($request->filled('search')) $query->where('name', 'like', "%{$request->search}%");
        if ($request->filled('status')) $query->where('status', $request->status);
        $products = $query->paginate(15)->withQueryString();
        return view('vendor.products.index', compact('products', 'shop'));
    }

    public function create()
    {
        $shop = auth('vendor')->user()->shop;
        if (!$shop || $shop->status !== 'active') return redirect()->route('vendor.dashboard')->with('error', 'Toko belum aktif.');
        $categories = \App\Models\Category::where('status', true)->get();
        $brands = \App\Models\Brand::where('status', true)->get();
        return view('vendor.products.create', compact('categories', 'brands'));
    }

    public function store(Request $request)
    {
        $shop = auth('vendor')->user()->shop;
        $validated = $request->validate([
            'name' => 'required|string|max:255', 'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id', 'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500', 'price' => 'required|numeric|min:0',
            'special_price' => 'nullable|numeric|min:0', 'current_stock' => 'required|integer|min:0',
            'unit' => 'nullable|string|max:50', 'sku' => 'nullable|string|max:100',
            'min_qty' => 'integer|min:1', 'max_qty' => 'integer|min:1',
            'product_type' => 'required|in:physical,digital', 'tax' => 'numeric|min:0', 'shipping_cost' => 'numeric|min:0',
            'video_url' => 'nullable|url|max:500', 'discount_type' => 'nullable|in:flat,percentage',
            'discount_end' => 'nullable|date', 'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500', 'tags' => 'nullable|string|max:500',
        ]);
        $validated['shop_id'] = $shop->id;
        $validated['slug'] = Str::slug($validated['name']);
        $originalSlug = $validated['slug']; $counter = 1;
        while (Product::where('slug', $validated['slug'])->exists()) { $validated['slug'] = $originalSlug . '-' . $counter++; }
        $validated['created_by'] = 'vendor'; $validated['status'] = 'pending'; $validated['published'] = true;
        if ($validated['discount_end'] ?? null) $validated['discount_start'] = now();
        $product = Product::create($validated);

        if ($request->hasFile('thumbnail')) {
            $product->update(['thumbnail' => $request->file('thumbnail')->store('products', 'public')]);
        }
        if ($request->hasFile('images')) {
            $paths = []; foreach ($request->file('images') as $i => $img) { if ($i >= 5) break; $paths[] = $img->store('products', 'public'); }
            $product->update(['images' => json_encode($paths)]);
        }
        if ($request->has('variants')) {
            foreach ($request->variants as $v) {
                if (!empty($v['name'])) \App\Models\ProductVariant::create(['product_id'=>$product->id,'variant'=>$v['name'],'sku'=>$v['sku']??null,'price'=>(float)($v['price']??$product->price),'stock'=>(int)($v['stock']??0)]);
            }
        }
        if ($request->filled('tags')) {
            foreach (explode(',', $request->tags) as $tag) {
                $tag = trim($tag); if ($tag) { $pt = \App\Models\ProductTag::firstOrCreate(['name'=>$tag,'slug'=>Str::slug($tag)]); $product->tags()->attach($pt->id); }
            }
        }
        if ($request->hasFile('digital_file') && $validated['product_type'] === 'digital') {
            $product->update(['digital_file' => $request->file('digital_file')->store('digital-products', 'public')]);
        }

        if ($request->hasFile('video_file')) {
            $product->update(['video_url' => $request->file('video_file')->store('videos', 'public')]);
        }

        return redirect()->route('vendor.products.index')->with('success', 'Produk berhasil ditambahkan. Menunggu persetujuan admin.');
    }

    public function show(Product $product)
    {
        $shop = auth('vendor')->user()->shop;
        if ($product->shop_id !== $shop->id) abort(403);
        $product->load(['category', 'brand', 'variants']);
        return view('vendor.products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $shop = auth('vendor')->user()->shop;
        if ($product->shop_id !== $shop->id) abort(403);
        $categories = \App\Models\Category::where('status', true)->get();
        $brands = \App\Models\Brand::where('status', true)->get();
        return view('vendor.products.edit', compact('product', 'categories', 'brands'));
    }

    public function update(Request $request, Product $product)
    {
        $shop = auth('vendor')->user()->shop;
        if ($product->shop_id !== $shop->id) abort(403);
        $validated = $request->validate([
            'name' => 'required|string|max:255', 'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id', 'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500', 'price' => 'required|numeric|min:0',
            'special_price' => 'nullable|numeric|min:0', 'current_stock' => 'required|integer|min:0',
            'unit' => 'nullable|string|max:50', 'sku' => 'nullable|string|max:100',
            'min_qty' => 'integer|min:1', 'max_qty' => 'integer|min:1',
            'product_type' => 'required|in:physical,digital', 'tax' => 'numeric|min:0', 'shipping_cost' => 'numeric|min:0',
            'video_url' => 'nullable|url|max:500',
        ]);
        if ($validated['name'] !== $product->name) {
            $validated['slug'] = Str::slug($validated['name']); $counter = 1;
            $original = $validated['slug'];
            while (Product::where('slug', $validated['slug'])->where('id', '!=', $product->id)->exists()) { $validated['slug'] = $original . '-' . $counter++; }
        }
        $product->update($validated);

        if ($request->hasFile('thumbnail')) {
            $path = $request->file('thumbnail')->store('products', 'public');
            $product->update(['thumbnail' => $path]);
        }

        return redirect()->route('vendor.products.index')->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product)
    {
        $shop = auth('vendor')->user()->shop;
        if ($product->shop_id !== $shop->id) abort(403);
        $product->delete();
        return redirect()->route('vendor.products.index')->with('success', 'Produk berhasil dihapus.');
    }
}
