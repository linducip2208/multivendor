<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::where('status', 'approved')
            ->where('published', true)
            ->with(['shop', 'category']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->first();
            if ($category) {
                $ids = collect([$category->id]);
                $ids = $ids->merge($category->children->pluck('id'));
                $query->whereIn('category_id', $ids);
            }
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        $sort = $request->get('sort', 'latest');
        match ($sort) {
            'price_low' => $query->orderBy('price', 'asc'),
            'price_high' => $query->orderBy('price', 'desc'),
            'name' => $query->orderBy('name', 'asc'),
            default => $query->latest(),
        };

        $products = $query->paginate(24)->withQueryString();
        $categories = Category::whereNull('parent_id')->where('status', true)->with('children')->get();

        return view('storefront.products.index', compact('products', 'categories'));
    }

    public function show(Product $product)
    {
        if ($product->status !== 'approved' || !$product->published) {
            abort(404);
        }

        $product->load(['shop', 'category', 'brand', 'variants', 'reviews.customer']);
        $relatedProducts = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('status', 'approved')
            ->where('published', true)
            ->take(8)
            ->get();

        return view('storefront.products.show', compact('product', 'relatedProducts'));
    }
}
