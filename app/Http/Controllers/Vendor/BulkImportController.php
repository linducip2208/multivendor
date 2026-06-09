<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class BulkImportController extends Controller
{
    public function index()
    {
        return view('vendor.bulk-import.index');
    }

    public function store(Request $request)
    {
        $shop = auth('vendor')->user()->shop;
        $request->validate(['file' => 'required|file|mimes:csv,xlsx,xls|max:10240']);

        $path = $request->file('file')->getRealPath();
        $file = fopen($path, 'r');
        $header = fgetcsv($file);

        $imported = 0;
        $errors = [];

        while (($row = fgetcsv($file)) !== false) {
            if (count($row) < 3) continue;

            $data = array_combine($header, $row);

            try {
                $slug = \Illuminate\Support\Str::slug($data['name'] ?? 'product-' . $imported);
                $counter = 1;
                $originalSlug = $slug;
                while (Product::where('slug', $slug)->exists()) { $slug = $originalSlug . '-' . $counter++; }

                Product::create([
                    'shop_id' => $shop->id,
                    'name' => $data['name'] ?? 'Untitled',
                    'slug' => $slug,
                    'price' => (float) ($data['price'] ?? 0),
                    'current_stock' => (int) ($data['stock'] ?? 0),
                    'sku' => $data['sku'] ?? null,
                    'category_id' => $data['category_id'] ?? null,
                    'description' => $data['description'] ?? null,
                    'status' => 'pending',
                    'created_by' => 'vendor',
                    'published' => true,
                ]);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row {$imported}: " . $e->getMessage();
            }
        }

        fclose($file);

        return redirect()->route('vendor.products.index')
            ->with('success', "{$imported} produk berhasil di-import.")
            ->with('error_import', $errors ? implode('; ', $errors) : null);
    }
}
