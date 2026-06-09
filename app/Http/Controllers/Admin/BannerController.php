<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::latest()->paginate(10);
        return view('admin.banners.index', compact('banners'));
    }

    public function create()
    {
        return view('admin.banners.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image' => 'required|string|max:500',
            'link' => 'nullable|string|max:500',
            'position' => 'required|in:hero,sidebar,footer,popup',
            'sort_order' => 'integer|min:0',
            'status' => 'boolean',
        ]);
        Banner::create($validated);
        return redirect()->route('admin.banners.index')->with('success', 'Banner dibuat.');
    }

    public function edit(Banner $banner)
    {
        return view('admin.banners.edit', compact('banner'));
    }

    public function update(Request $request, Banner $banner)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image' => 'nullable|string|max:500',
            'link' => 'nullable|string|max:500',
            'position' => 'required|in:hero,sidebar,footer,popup',
            'sort_order' => 'integer|min:0',
            'status' => 'boolean',
        ]);
        $banner->update($validated);
        return redirect()->route('admin.banners.index')->with('success', 'Banner diperbarui.');
    }

    public function destroy(Banner $banner)
    {
        $banner->delete();
        return back()->with('success', 'Banner dihapus.');
    }
}
