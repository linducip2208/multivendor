<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTag;
use App\Models\ProductVariant;
use App\Services\HtmlSanitizer;
use App\Services\Vendor\VendorInventoryService;
use App\Services\Vendor\VendorProductPricingService;
use App\Support\Currency;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $shop = auth('vendor')->user()->shop;
        if (! $shop) {
            return redirect()->route('vendor.dashboard')->with('error', 'Toko belum disetujui.');
        }
        $query = Product::where('shop_id', $shop->id)->with('category')->latest();
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $products = $query->paginate(15)->withQueryString();

        return view('vendor.products.index', compact('products', 'shop'));
    }

    public function create()
    {
        $shop = auth('vendor')->user()->shop;
        if (! $shop || $shop->status !== 'active') {
            return redirect()->route('vendor.dashboard')->with('error', 'Toko belum aktif.');
        }
        $categories = Category::where('status', true)->get();
        $brands = Brand::where('status', true)->get();

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
            'product_type' => 'required|in:physical,digital', 'tax' => 'numeric|min:0', 'shipping_cost' => 'numeric|min:0', 'weight' => 'nullable|integer|min:1|max:100000',
            'video_url' => 'nullable|url|max:500', 'discount_type' => 'nullable|in:flat,percentage',
            'discount_end' => 'nullable|date', 'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500', 'tags' => 'nullable|string|max:500',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120', 'images.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'digital_file' => 'nullable|file|max:51200|mimes:zip,pdf,epub,mp3,mp4,webp,png,jpg,jpeg', 'video_file' => 'nullable|file|max:51200|mimetypes:video/mp4,video/webm',
        ]);
        $validated['shop_id'] = $shop->id;
        $validated['description'] = app(HtmlSanitizer::class)->sanitize($validated['description'] ?? null);
        $validated['short_description'] = app(HtmlSanitizer::class)->sanitize($validated['short_description'] ?? null);
        $validated['slug'] = Str::slug($validated['name']);
        $originalSlug = $validated['slug'];
        $counter = 1;
        while (Product::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug.'-'.$counter++;
        }
        $validated['created_by'] = 'vendor';
        $validated['status'] = 'pending';
        $validated['published'] = true;
        if ($validated['discount_end'] ?? null) {
            $validated['discount_start'] = now();
        }
        $product = Product::create($validated);

        if ($request->hasFile('thumbnail')) {
            $product->update(['thumbnail' => $request->file('thumbnail')->store('products', 'public')]);
        }
        if ($request->hasFile('images')) {
            $paths = [];
            foreach ($request->file('images') as $i => $img) {
                if ($i >= 5) {
                    break;
                } $paths[] = $img->store('products', 'public');
            }
            $product->update(['images' => json_encode($paths)]);
        }
        if ($request->has('variants')) {
            foreach ($request->variants as $v) {
                if (! empty($v['name'])) {
                    ProductVariant::create(['product_id' => $product->id, 'variant' => $v['name'], 'sku' => $v['sku'] ?? null, 'price' => (float) ($v['price'] ?? $product->price), 'stock' => (int) ($v['stock'] ?? 0)]);
                }
            }
        }
        if ($request->filled('tags')) {
            foreach (explode(',', (string) $request->input('tags')) as $tag) {
                $tag = trim($tag);
                if ($tag) {
                    $pt = ProductTag::firstOrCreate(['name' => $tag, 'slug' => Str::slug($tag)]);
                    $product->tags()->attach($pt->id);
                }
            }
        }
        if ($request->hasFile('digital_file') && $validated['product_type'] === 'digital') {
            $product->update(['digital_file' => $request->file('digital_file')->store('digital-products', 'private')]);
        }

        if ($request->hasFile('video_file')) {
            $product->update(['video_url' => $request->file('video_file')->store('videos', 'public')]);
        }

        // Harga grosir opsional: saring baris kosong, simpan bila ada isi.
        $tiers = collect((array) $request->input('tiers', []))
            ->filter(fn ($row): bool => (int) ($row['min_qty'] ?? 0) > 0 && (float) ($row['price'] ?? 0) > 0)
            ->values()->all();
        if ($tiers !== []) {
            app(\App\Services\B2b\B2bPricingService::class)->simpanTiers($product->refresh(), $tiers);
        }

        return redirect()->route('vendor.products.index')->with('success', 'Produk berhasil ditambahkan. Menunggu persetujuan admin.');
    }

    public function show(Product $product)
    {
        $shop = auth('vendor')->user()->shop;
        if ($product->shop_id !== $shop->id) {
            abort(403);
        }
        $product->load(['category', 'brand', 'variants']);

        return view('vendor.products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $shop = auth('vendor')->user()->shop;
        if ($product->shop_id !== $shop->id) {
            abort(403);
        }
        $categories = Category::where('status', true)->get();
        $brands = Brand::where('status', true)->get();

        return view('vendor.products.edit', compact('product', 'categories', 'brands'));
    }

    public function update(Request $request, Product $product)
    {
        $shop = auth('vendor')->user()->shop;
        if ($product->shop_id !== $shop->id) {
            abort(403);
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255', 'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id', 'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500', 'price' => 'required|numeric|min:0',
            'special_price' => 'nullable|numeric|min:0', 'current_stock' => 'required|integer|min:0',
            'unit' => 'nullable|string|max:50', 'sku' => 'nullable|string|max:100',
            'min_qty' => 'integer|min:1', 'max_qty' => 'integer|min:1',
            'product_type' => 'required|in:physical,digital', 'tax' => 'numeric|min:0', 'shipping_cost' => 'numeric|min:0', 'weight' => 'nullable|integer|min:1|max:100000',
            'video_url' => 'nullable|url|max:500',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        if ($validated['name'] !== $product->name) {
            $validated['slug'] = Str::slug($validated['name']);
            $counter = 1;
            $original = $validated['slug'];
            while (Product::where('slug', $validated['slug'])->where('id', '!=', $product->id)->exists()) {
                $validated['slug'] = $original.'-'.$counter++;
            }
        }
        $validated['description'] = app(HtmlSanitizer::class)->sanitize($validated['description'] ?? null);
        $validated['short_description'] = app(HtmlSanitizer::class)->sanitize($validated['short_description'] ?? null);
        // Vendor edits to commercial fields are moderated again before storefront changes take effect.
        $validated['status'] = 'pending';
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
        if ($product->shop_id !== $shop->id) {
            abort(403);
        }
        $product->delete();

        return redirect()->route('vendor.products.index')->with('success', 'Produk berhasil dihapus.');
    }

    public function lowStock(Request $request, VendorInventoryService $inventory): View
    {
        $data = $inventory->overview(VendorScopeRequest::search($request), 'low');

        return view('vendor.products.low-stock', [
            'products' => $data['products'],
            'stats' => $data['stats'],
            'search' => $data['search'],
        ]);
    }

    public function bulkPriceUpdate(Request $request, VendorProductPricingService $pricing): RedirectResponse
    {
        $validated = $request->validate([
            'products' => ['required', 'array', 'min:1'],
            'products.*' => ['integer'],
            'mode' => ['required', 'in:increase,decrease,set,margin'],
            'value' => ['required', 'numeric', 'min:0', 'max:1000000000000'],
            'status' => ['nullable', 'in:pending,approved,suspended'],
        ], [
            'products.required' => 'Pilih minimal satu produk untuk diperbarui.',
        ]);

        $result = $pricing->bulkReprice(
            $validated['products'],
            $validated['mode'],
            $validated['value'],
            ['status' => $validated['status'] ?? null],
        );

        return back()->with(
            'success',
            $result['updated'].' produk diperbarui. Total nilai katalog '.Currency::format($result['after']->toFloat()).'.'
        );
    }

    // ── Perdalaman katalog (aksi baru; metode existing tidak diubah) ──
    // Catatan: rute untuk aksi di bawah ini dipasang oleh pemilik routes/*.php;
    // sebelum rute ada, aksi tetap dapat dipanggil terprogram dan teruji.

    /** Simpan/tambah gambar milik satu varian (maks. 5 gambar per varian). */
    public function simpanGambarVarian(Request $request, Product $product, ProductVariant $varian): RedirectResponse
    {
        $this->pastikanMilikToko($product, $varian);

        $validated = $request->validate([
            'gambar' => ['required', 'array', 'max:5'],
            'gambar.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if (! \Illuminate\Support\Facades\Schema::hasColumn('product_variants', 'images')) {
            return back()->with('error', 'Kolom gambar varian belum tersedia. Jalankan migrasi katalog media dahulu.');
        }

        $lama = $varian->galeri();
        $baru = $lama;

        foreach ($request->file('gambar', []) as $berkas) {
            if (count($baru) >= 5) {
                break;
            }

            $baru[] = $berkas->store('products/variants', 'public');
        }

        $varian->forceFill(['images' => array_values($baru)])->save();

        return back()->with('success', 'Gambar varian disimpan. Galeri halaman produk ikut varian yang dipilih pembeli.');
    }

    /** Hapus satu gambar milik varian berdasarkan posisinya. */
    public function hapusGambarVarian(Request $request, Product $product, ProductVariant $varian): RedirectResponse
    {
        $this->pastikanMilikToko($product, $varian);

        $validated = $request->validate([
            'posisi' => ['required', 'integer', 'min:0', 'max:20'],
        ]);

        $daftar = $varian->galeri();

        if (! isset($daftar[$validated['posisi']])) {
            return back()->with('error', 'Gambar tidak ditemukan.');
        }

        unset($daftar[$validated['posisi']]);
        $varian->forceFill(['images' => array_values($daftar)])->save();

        return back()->with('success', 'Gambar varian dihapus. Bila kosong, galeri kembali memakai foto utama produk.');
    }

    /** Terbitkan kunci lisensi digital untuk satu pembelian (mis. pesanan manual). */
    public function terbitkanLisensiDigital(Request $request, Product $product): RedirectResponse
    {
        $shop = auth('vendor')->user()->shop;
        if ($product->shop_id !== $shop->id) {
            abort(403);
        }

        if (! $product->butuhLisensiDigital()) {
            return back()->with('error', 'Produk digital membutuhkan berkas unduhan (digital_file) sebelum lisensi diterbitkan.');
        }

        $validated = $request->validate([
            'order_item_id' => ['nullable', 'integer', 'min:1'],
            'maks_unduh' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $lisensi = $product->buatLisensiDigital(
            $validated['order_item_id'] ?? null,
            (int) ($validated['maks_unduh'] ?? 5)
        );

        if ($lisensi === null) {
            return back()->with('error', 'Lisensi gagal diterbitkan. Pastikan migrasi katalog media sudah berjalan.');
        }

        return back()->with('success', 'Lisensi digital diterbitkan: '.$lisensi->license_key);
    }

    /** Buat/perbarui koleksi tematik terkurasi milik platform (mis. "Back to School"). */
    public function simpanKoleksiTematik(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:190'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'mulai_tampil' => ['nullable', 'date'],
            'selesai_tampil' => ['nullable', 'date', 'after_or_equal:mulai_tampil'],
        ]);

        if (! \Illuminate\Support\Facades\Schema::hasTable('thematic_collections')) {
            return back()->with('error', 'Tabel koleksi tematik belum tersedia. Jalankan migrasi katalog media dahulu.');
        }

        $slug = trim((string) ($validated['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug($validated['nama']) ?: 'koleksi';
        }

        $dasar = $slug;
        $angka = 1;
        while (\Illuminate\Support\Facades\DB::table('thematic_collections')->where('slug', $slug)->exists()) {
            $angka++;
            $slug = $dasar.'-'.$angka;
        }

        \Illuminate\Support\Facades\DB::table('thematic_collections')->insert([
            'name' => $validated['nama'],
            'slug' => $slug,
            'description' => $validated['deskripsi'] ?? null,
            'is_active' => true,
            'starts_at' => $validated['mulai_tampil'] ?? null,
            'ends_at' => $validated['selesai_tampil'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Koleksi "'.$validated['nama'].'" dibuat dan dijadwalkan tampil sesuai tanggal yang diisi.');
    }

    /** Masukkan produk toko sendiri ke sebuah koleksi tematik. */
    public function tambahProdukKeKoleksi(Request $request, Product $product): RedirectResponse
    {
        $shop = auth('vendor')->user()->shop;
        if ($product->shop_id !== $shop->id) {
            abort(403);
        }

        $validated = $request->validate([
            'koleksi_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            \Illuminate\Support\Facades\DB::table('thematic_collection_product')->updateOrInsert(
                ['thematic_collection_id' => $validated['koleksi_id'], 'product_id' => $product->id],
                ['sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]
            );
        } catch (\Throwable) {
            return back()->with('error', 'Produk gagal dimasukkan ke koleksi.');
        }

        return back()->with('success', 'Produk dimasukkan ke koleksi tematik.');
    }

    /** Keluarkan produk toko sendiri dari sebuah koleksi tematik. */
    public function lepasProdukDariKoleksi(Request $request, Product $product): RedirectResponse
    {
        $shop = auth('vendor')->user()->shop;
        if ($product->shop_id !== $shop->id) {
            abort(403);
        }

        $validated = $request->validate([
            'koleksi_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            \Illuminate\Support\Facades\DB::table('thematic_collection_product')
                ->where('thematic_collection_id', $validated['koleksi_id'])
                ->where('product_id', $product->id)
                ->delete();
        } catch (\Throwable) {
            return back()->with('error', 'Produk gagal dilepas dari koleksi.');
        }

        return back()->with('success', 'Produk dilepas dari koleksi tematik.');
    }

    private function pastikanMilikToko(Product $product, ProductVariant $varian): void
    {
        $shop = auth('vendor')->user()->shop;
        if ($product->shop_id !== $shop->id || (int) $varian->product_id !== (int) $product->id) {
            abort(403);
        }
    }
}
