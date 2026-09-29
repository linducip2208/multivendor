<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id', 'sku', 'variant', 'variant_attributes', 'images', 'price',
    'special_price', 'discount_type', 'discount_start', 'discount_end', 'stock',
    'low_stock_threshold',
])]
class ProductVariant extends Model
{
    protected function casts(): array
    {
        return [
            'variant_attributes' => 'json',
            'images' => 'array',
            'price' => 'decimal:2',
            'special_price' => 'decimal:2',
            'discount_start' => 'datetime',
            'discount_end' => 'datetime',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getEffectivePrice(): float
    {
        if ($this->special_price && (!$this->discount_start || $this->discount_start <= now()) && (!$this->discount_end || $this->discount_end >= now())) {
            return (float) $this->special_price;
        }

        return (float) $this->price;
    }

    public function isOutOfStock(): bool
    {
        return (int) $this->stock <= 0;
    }

    public function isLowStock(): bool
    {
        $stock = (int) $this->stock;

        return $stock > 0 && $stock <= (int) ($this->low_stock_threshold ?? 3);
    }

    // ===== Galeri per varian (aditif — fallback ke thumbnail produk) =====

    /** Daftar path gambar milik varian ini (kosong bila belum diunggah). */
    public function galeri(): array
    {
        $raw = $this->getAttribute('images');

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        return array_values(array_filter(array_map(
            fn ($p) => trim((string) $p),
            (array) $raw
        )));
    }

    /** URL galeri varian, siap tampil di PDP maupun panel vendor. */
    public function urlGaleri(): array
    {
        return array_map(
            fn (string $p) => str_starts_with($p, 'http') ? $p : url('img/'.ltrim($p, '/')),
            $this->galeri()
        );
    }

    /**
     * Gambar utama varian; fallback ke gambar cadangan (thumbnail produk)
     * bila varian belum punya gambar sendiri.
     */
    public function gambarUtama(?string $cadangan = null): ?string
    {
        $urls = $this->urlGaleri();

        if ($urls !== []) {
            return $urls[0];
        }

        if (is_string($cadangan) && trim($cadangan) !== '') {
            $cadangan = trim($cadangan);

            return str_starts_with($cadangan, 'http') ? $cadangan : url('img/'.ltrim($cadangan, '/'));
        }

        return null;
    }

    /** True bila varian sudah punya minimal satu gambar sendiri. */
    public function punyaGambarSendiri(): bool
    {
        return $this->galeri() !== [];
    }

    /**
     * Harga coret (price) vs harga jual (special_price) konsisten bila:
     * special_price kosong, atau 0 < special_price < price,
     * dan jendela diskon tidak terbalik.
     */
    public function hasConsistentPricing(): bool
    {
        if ($this->special_price === null || (float) $this->special_price <= 0) {
            return true;
        }

        if ((float) $this->special_price >= (float) $this->price) {
            return false;
        }

        if ($this->discount_start !== null && $this->discount_end !== null
            && $this->discount_start > $this->discount_end) {
            return false;
        }

        return true;
    }

    /**
     * Kunci kombinasi atribut yang dinormalisasi (urutan kunci diabaikan,
     * perbandingan tanpa memperhatikan huruf besar/kecil dan spasi).
     *
     * @return array<string, string>
     */
    public function normalizedAttributes(): array
    {
        $raw = $this->variant_attributes;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $key => $value) {
            $key = mb_strtolower(trim((string) $key));
            $value = mb_strtolower(trim((string) $value));

            if ($key !== '' && $value !== '') {
                $out[$key] = $value;
            }
        }

        ksort($out);

        return $out;
    }

    public function combinationKey(): string
    {
        return json_encode($this->normalizedAttributes(), JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<ProductVariant>  $query
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0)
            ->whereColumn('stock', '<=', 'low_stock_threshold');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<ProductVariant>  $query
     */
    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('stock', '<=', 0);
    }

    /**
     * Apakah kombinasi atribut sudah dipakai varian lain dalam produk yang sama.
     */
    public static function kombinasiDuplikat(int $productId, mixed $attributes, ?int $ignoreId = null): bool
    {
        $probe = new static(['variant_attributes' => $attributes]);
        $key = $probe->combinationKey();

        if ($key === '{}') {
            return false;
        }

        return static::query()
            ->where('product_id', $productId)
            ->when($ignoreId !== null, fn (Builder $q) => $q->where('id', '!=', $ignoreId))
            ->get(['id', 'variant_attributes'])
            ->contains(fn (ProductVariant $row) => $row->combinationKey() === $key);
    }

    /**
     * SKU unik per toko: SKU yang sama boleh dipakai toko lain,
     * tetapi tidak boleh ganda di dalam satu toko (produk + varian).
     */
    public static function skuSudahDipakaiDiToko(?string $sku, int $productId, ?int $ignoreId = null): bool
    {
        $sku = trim((string) $sku);

        if ($sku === '') {
            return false;
        }

        $shopId = Product::query()->whereKey($productId)->value('shop_id');

        if ($shopId === null) {
            return false;
        }

        $produkGanda = Product::query()
            ->where('shop_id', $shopId)
            ->where('sku', $sku)
            ->where('id', '!=', $productId)
            ->exists();

        if ($produkGanda) {
            return true;
        }

        return static::query()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.shop_id', $shopId)
            ->where('product_variants.sku', $sku)
            ->when($ignoreId !== null, fn ($q) => $q->where('product_variants.id', '!=', $ignoreId))
            ->exists();
    }

    /**
     * Validasi satu baris varian. Mengembalikan daftar pesan kesalahan
     * berbahasa Indonesia (kosong bila valid).
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function validasiBaris(array $data, int $productId, ?int $ignoreId = null): array
    {
        $errors = [];

        $probe = new static([
            'price' => $data['price'] ?? 0,
            'special_price' => $data['special_price'] ?? null,
            'discount_start' => $data['discount_start'] ?? null,
            'discount_end' => $data['discount_end'] ?? null,
            'stock' => $data['stock'] ?? 0,
            'low_stock_threshold' => $data['low_stock_threshold'] ?? 3,
            'variant_attributes' => $data['variant_attributes'] ?? $data['attributes'] ?? [],
        ]);

        if (! is_numeric($data['price'] ?? null) || (float) ($data['price'] ?? 0) < 0) {
            $errors[] = 'Harga varian harus berupa angka minimal 0.';
        }

        if (! $probe->hasConsistentPricing()) {
            $errors[] = 'Harga coret harus lebih besar dari harga jual, dan tanggal mulai diskon tidak boleh lewat tanggal selesai.';
        }

        if (isset($data['stock']) && (! is_numeric($data['stock']) || (int) $data['stock'] < 0)) {
            $errors[] = 'Stok varian harus berupa bilangan bulat minimal 0.';
        }

        if (isset($data['low_stock_threshold'])
            && (! is_numeric($data['low_stock_threshold']) || (int) $data['low_stock_threshold'] < 0)) {
            $errors[] = 'Ambang stok menipis harus berupa bilangan bulat minimal 0.';
        }

        if (static::kombinasiDuplikat($productId, $data['variant_attributes'] ?? $data['attributes'] ?? [], $ignoreId)) {
            $errors[] = 'Kombinasi atribut varian ini sudah dipakai varian lain pada produk yang sama.';
        }

        if (isset($data['sku']) && static::skuSudahDipakaiDiToko((string) $data['sku'], $productId, $ignoreId)) {
            $errors[] = 'SKU "'.$data['sku'].'" sudah dipakai produk atau varian lain di toko yang sama.';
        }

        return $errors;
    }
}
