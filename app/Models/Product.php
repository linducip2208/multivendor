<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

#[Fillable([
    'shop_id', 'category_id', 'brand_id', 'name', 'slug', 'description',
    'short_description', 'thumbnail', 'images', 'unit', 'min_qty', 'max_qty',
    'current_stock', 'weight', 'sku', 'barcode', 'product_type', 'refundable', 'featured',
    'published', 'created_by', 'price', 'special_price', 'discount_type',
    'discount_start', 'discount_end', 'tax', 'tax_type', 'shipping_cost',
    'shipping_cost_type', 'multiply_qty', 'meta_title', 'meta_description',
    'meta_image', 'video_url', 'digital_file', 'request_status', 'approved_by',
    'approved_at', 'status',
    'rating_average', 'rating_count', 'sold_count', 'view_count', 'search_keywords',
    'warranty', 'warranty_unit', 'condition', 'low_stock_threshold', 'seo_score',
    'is_preorder', 'preorder_lead_days', 'preorder_dp_percent',
])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'images' => 'array',
            'refundable' => 'boolean',
            'featured' => 'boolean',
            'published' => 'boolean',
            'multiply_qty' => 'boolean',
            'price' => 'decimal:2',
            'special_price' => 'decimal:2',
            'tax' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'rating_average' => 'decimal:2',
            'rating_count' => 'integer',
            'sold_count' => 'integer',
            'view_count' => 'integer',
            'low_stock_threshold' => 'integer',
            'seo_score' => 'integer',
            'is_preorder' => 'boolean',
            'preorder_lead_days' => 'integer',
            'preorder_dp_percent' => 'decimal:2',
            'discount_start' => 'datetime',
            'discount_end' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function movementHistory(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class, 'coupon_product');
    }

    public function flashDeals(): BelongsToMany
    {
        return $this->belongsToMany(FlashDeal::class, 'flash_deal_products');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ProductTag::class, 'product_tag_pivot', 'product_id', 'product_tag_id');
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (empty($this->thumbnail)) {
            return null;
        }

        return url('img/'.ltrim((string) $this->thumbnail, '/'));
    }

    public function getStorefrontUrlAttribute(): string
    {
        try {
            return route('products.show', $this->slug);
        } catch (Throwable) {
            return url('products/'.$this->slug);
        }
    }

    public function getIsOutOfStockAttribute(): bool
    {
        return (int) $this->current_stock <= 0;
    }

    public function getIsLowStockAttribute(): bool
    {
        $stock = (int) $this->current_stock;

        return $stock > 0 && $stock <= (int) $this->low_stock_threshold;
    }

    // ── Pre-order (aditif) ──

    /** Flag pre-order: produk dijual dengan ETA + uang muka (DP). */
    public function isPreorder(): bool
    {
        return (bool) ($this->getAttribute('is_preorder') ?? false);
    }

    /** Estimasi tanggal ready (ETA) dari lead time hari. */
    public function preorderEtaDate(): ?\Carbon\CarbonInterface
    {
        if (! $this->isPreorder()) {
            return null;
        }

        $days = (int) ($this->getAttribute('preorder_lead_days') ?? 0);

        return now()->addDays(max(0, $days))->startOfDay();
    }

    /** Uang muka (DP) untuk nominal tertentu, dibatasi 0–100%. */
    public function downPaymentFor(float $amount): float
    {
        if (! $this->isPreorder() || $amount <= 0) {
            return 0.0;
        }

        $percent = min(100.0, max(0.0, (float) ($this->getAttribute('preorder_dp_percent') ?? 0)));

        return round($amount * $percent / 100, 2);
    }

    /**
     * Gandakan produk beserta variannya memakai kolom yang sudah ada.
     * Salinan selalu berstatus menunggu persetujuan dan tidak tayang,
     * sehingga tidak ada produk ganda yang lolos tanpa moderasi.
     */
    public function duplikasikan(): Product
    {
        $salinan = $this->replicate([
            'slug', 'sku', 'barcode', 'sold_count', 'view_count',
            'rating_average', 'rating_count', 'approved_by', 'approved_at',
        ]);

        $dasar = trim((string) $this->name).' (Salinan)';
        $salinan->name = $dasar;
        $salinan->slug = static::slugUnik(\Illuminate\Support\Str::slug($dasar) ?: 'produk');
        $salinan->sku = null;
        $salinan->barcode = null;
        $salinan->status = 'pending';
        $salinan->published = false;
        $salinan->approved_by = null;
        $salinan->approved_at = null;
        $salinan->sold_count = 0;
        $salinan->view_count = 0;
        $salinan->rating_average = 0;
        $salinan->rating_count = 0;
        $salinan->save();

        foreach ($this->relationLoaded('variants') ? $this->variants : $this->variants()->get() as $varian) {
            $salinanVarian = $varian->replicate(['sku']);
            $salinanVarian->product_id = $salinan->id;
            $salinanVarian->sku = null;
            $salinanVarian->save();
        }

        return $salinan->refresh();
    }

    public static function slugUnik(string $dasar): string
    {
        $slug = $dasar;
        $angka = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $angka++;
            $slug = $dasar.'-'.$angka;
        }

        return $slug;
    }

    /**
     * Arsip ringan memakai kolom existing: tidak tayang + ditangguhkan.
     * Penjadwalan tayang otomatis (published_at) belum didukung model,
     * jadi arsip selalu manual lewat metode ini.
     */
    public function arsipkan(): bool
    {
        return (bool) $this->forceFill([
            'published' => false,
            'status' => 'suspended',
        ])->save();
    }

    public function pulihkanDariArsip(): bool
    {
        return (bool) $this->forceFill([
            'published' => false,
            'status' => 'pending',
        ])->save();
    }

    public function diarsipkan(): bool
    {
        return ! (bool) $this->published && (string) $this->status === 'suspended';
    }

    public function hasActiveSpecialPrice(): bool
    {
        if ($this->special_price === null) {
            return false;
        }

        if ($this->discount_start !== null && $this->discount_start > now()) {
            return false;
        }

        if ($this->discount_end !== null && $this->discount_end < now()) {
            return false;
        }

        return true;
    }

    public function isOnSale(): bool
    {
        return $this->hasActiveSpecialPrice() && (float) $this->special_price < (float) $this->price;
    }

    public function flashDealPrice(): ?float
    {
        if ($this->id === null) {
            return null;
        }

        $base = $this->hasActiveSpecialPrice() ? (float) $this->special_price : (float) $this->price;

        try {
            $query = DB::table('flash_deal_products as fdp')
                ->join('flash_deals as fd', 'fd.id', '=', 'fdp.flash_deal_id')
                ->where('fdp.product_id', $this->id)
                ->where('fd.status', true)
                ->where('fd.start_date', '<=', now())
                ->where('fd.end_date', '>=', now());

            if (static::hasDiscountedPriceColumn()) {
                $value = (float) $query->min('fdp.discounted_price');

                return $value > 0 ? min($value, $base) : null;
            }

            $best = null;

            foreach ($query->get(['fdp.discount_type', 'fdp.discount_value']) as $row) {
                $price = $this->priceFromFlashDealRow($row, $base);

                if ($price !== null && $price > 0 && ($best === null || $price < $best)) {
                    $best = $price;
                }
            }

            return $best === null ? null : min($best, $base);
        } catch (Throwable) {
            return null;
        }
    }

    public function getEffectivePrice(): float
    {
        $base = $this->hasActiveSpecialPrice() ? (float) $this->special_price : (float) $this->price;

        $flash = $this->flashDealPrice();

        if ($flash !== null && $flash > 0 && $flash < $base) {
            return $flash;
        }

        return $base;
    }

    public function getDiscountPercentage(): ?int
    {
        if (! $this->hasActiveSpecialPrice()) {
            return null;
        }

        if ((float) $this->price <= 0) {
            return null;
        }

        return (int) round(((float) $this->price - (float) $this->special_price) / (float) $this->price * 100);
    }

    private function priceFromFlashDealRow(object $row, float $base): ?float
    {
        $value = (float) ($row->discount_value ?? 0);

        if ($value <= 0) {
            return null;
        }

        if (($row->discount_type ?? null) === 'flat') {
            return max(0.0, $base - $value);
        }

        return max(0.0, $base - ($base * $value / 100));
    }

    private static function hasDiscountedPriceColumn(): bool
    {
        static $exists = null;

        if ($exists === null) {
            try {
                $exists = Schema::hasColumn('flash_deal_products', 'discounted_price');
            } catch (Throwable) {
                $exists = false;
            }
        }

        return $exists;
    }

    // ===== Kapabilitas B2B/grosir (aditif — tidak mengubah logika existing) =====

    /**
     * Tier harga grosir (min. qty → harga) dari tabel `b2b_price_tiers`.
     * Koleksi kosong bila tabel belum termigrasi — aman untuk semua pemanggil.
     *
     * @return HasMany<\App\Services\B2b\B2bPriceTier, $this>
     */
    public function b2bPriceTiers(): HasMany
    {
        return $this->hasMany(\App\Services\B2b\B2bPriceTier::class)->orderBy('min_qty');
    }

    /** Harga satuan B2B untuk qty tertentu (fallback ke harga ecer efektif). */
    public function b2bUnitPrice(int $qty): float
    {
        try {
            return app(\App\Services\B2b\B2bPricingService::class)->unitPriceFor($this, $qty);
        } catch (Throwable) {
            return $this->getEffectivePrice();
        }
    }

    /** True bila produk punya minimal satu tier grosir. */
    public function hasB2bTiers(): bool
    {
        try {
            if (! Schema::hasTable('b2b_price_tiers') || $this->getKey() === null) {
                return false;
            }

            return $this->b2bPriceTiers()->exists();
        } catch (Throwable) {
            return false;
        }
    }

    // ===== Galeri per varian (aditif — fallback ke thumbnail produk) =====

    /** Normalisasi satu path gambar menjadi URL tampil. */
    public static function urlGambar(string $path): string
    {
        $path = trim($path);

        return str_starts_with($path, 'http') ? $path : url('img/'.ltrim($path, '/'));
    }

    /** Galeri tingkat produk (thumbnail + images), sebagai URL tampil. */
    public function galeriDasar(): array
    {
        try {
            $images = $this->getAttribute('images');

            if (is_string($images)) {
                $images = json_decode($images, true) ?: [];
            }

            $paths = collect((array) $images)->map(fn ($p) => trim((string) $p))->filter();

            if (trim((string) $this->getAttribute('thumbnail')) !== '') {
                $paths = $paths->prepend(trim((string) $this->getAttribute('thumbnail')));
            }

            return $paths->map(fn ($p) => static::urlGambar($p))->unique()->values()->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Galeri untuk varian tertentu; fallback ke galeri produk bila varian
     * tidak punya gambar sendiri atau kolom belum termigrasi.
     *
     * @return list<string>
     */
    public function galeriUntukVarian(?int $variantId = null): array
    {
        $dasar = $this->galeriDasar();

        if ($variantId === null) {
            return $dasar;
        }

        try {
            $varian = $this->relationLoaded('variants')
                ? $this->variants->firstWhere('id', $variantId)
                : $this->variants()->find($variantId);

            if ($varian === null || ! method_exists($varian, 'urlGaleri')) {
                return $dasar;
            }

            $milikVarian = $varian->urlGaleri();

            return $milikVarian !== [] ? $milikVarian : $dasar;
        } catch (Throwable) {
            return $dasar;
        }
    }

    /** Peta id varian → daftar URL gambar (untuk galeri reaktif di PDP). */
    public function petaGaleriVarian(): array
    {
        try {
            $varians = $this->relationLoaded('variants') ? $this->variants : $this->variants()->get();
            $peta = [];

            foreach ($varians as $varian) {
                if (! method_exists($varian, 'urlGaleri')) {
                    continue;
                }

                $urls = $varian->urlGaleri();

                if ($urls !== []) {
                    $peta[(int) $varian->id] = $urls;
                }
            }

            return $peta;
        } catch (Throwable) {
            return [];
        }
    }

    // ===== Panduan terstruktur per kategori (aditif, dari atribut existing) =====

    /**
     * Panduan terstruktur untuk PDP: panduan ukuran (fashion) atau info
     * nutrisi (makanan) yang dirangkai dari atribut existing produk +
     * kategorinya. Kosong bila tidak ada atribut yang relevan.
     *
     * @return array{judul: string, jenis: string, baris: list<array{label: string, nilai: string}>, catatan: ?string}
     */
    public function panduanKategori(): array
    {
        try {
            $kategori = mb_strtolower(trim((string) (($this->category?->name ?? '').' '.($this->category?->slug ?? ''))));

            // Nilai atribut dibaca lewat join (tabel product_attributes hanya
            // menyimpan id referensi; tidak ada kolom value di sana).
            $baris = [];

            if ($this->getKey() !== null
                && Schema::hasTable('product_attributes')
                && Schema::hasTable('attributes')) {
                $query = DB::table('product_attributes as pa')
                    ->join('attributes as a', 'a.id', '=', 'pa.attribute_id')
                    ->where('pa.product_id', $this->getKey());

                if (Schema::hasTable('attribute_values')) {
                    $query->leftJoin('attribute_values as av', 'av.id', '=', 'pa.attribute_value_id');
                    $rows = $query->get(['a.name as nama', 'av.value as nilai']);
                } else {
                    $rows = $query->get(['a.name as nama']);
                }

                foreach ($rows as $row) {
                    $nama = mb_strtolower(trim((string) ($row->nama ?? '')));
                    $nilai = trim((string) ($row->nilai ?? ''));

                    if ($nama === '' || $nilai === '') {
                        continue;
                    }

                    $baris[] = ['label' => (string) ($row->nama ?? 'Atribut'), 'nilai' => $nilai, 'kunci' => $nama];
                }
            }

            $isFashion = $this->teksMengandung($kategori, ['fashion', 'pakaian', 'baju', 'kaos', 'kemeja', 'celana', 'jaket', 'sepatu', 'sandal', 'tas', 'hijab', 'dress', 'fashion']);
            $isMakanan = $this->teksMengandung($kategori, ['makanan', 'minuman', 'kuliner', 'snack', 'food', 'beverage', 'kopi', 'teh', 'kue', 'roti']);

            $kunciUkuran = ['ukuran', 'size', 'lingkar', 'panjang', 'lebar', 'tinggi', 'panjang badan', 'panjang lengan', 'bust', 'pinggang'];
            $kunciNutrisi = ['kalori', 'energi', 'protein', 'lemak', 'karbohidrat', 'gula', 'garam', 'natrium', 'serat', 'nutrition', 'takaran', 'berat bersih', 'komposisi', 'alergen', 'kedaluwarsa', 'expired'];

            if ($isMakanan) {
                $relevan = array_values(array_filter($baris, fn ($b) => $this->teksMengandung($b['kunci'], $kunciNutrisi)));

                if ($relevan === []) {
                    return [];
                }

                return [
                    'judul' => 'Informasi Nilai Gizi',
                    'jenis' => 'nutrisi',
                    'baris' => array_map(fn ($b) => ['label' => $b['label'], 'nilai' => $b['nilai']], $relevan),
                    'catatan' => 'Nilai gizi per kemasan sesuai label produsen. Konsultasikan ke ahli gizi bila perlu.',
                ];
            }

            if ($isFashion) {
                $relevan = array_values(array_filter($baris, fn ($b) => $this->teksMengandung($b['kunci'], $kunciUkuran)));

                if ($relevan === []) {
                    return [];
                }

                return [
                    'judul' => 'Panduan Ukuran',
                    'jenis' => 'ukuran',
                    'baris' => array_map(fn ($b) => ['label' => $b['label'], 'nilai' => $b['nilai']], $relevan),
                    'catatan' => 'Ukur badan Anda lalu cocokkan dengan tabel di atas. Bila di antara dua ukuran, pilih yang lebih besar.',
                ];
            }

            // Kategori umum: tampilkan atribut terstruktur apa adanya bila ada.
            if ($baris === []) {
                return [];
            }

            return [
                'judul' => 'Detail Atribut',
                'jenis' => 'umum',
                'baris' => array_map(fn ($b) => ['label' => $b['label'], 'nilai' => $b['nilai']], $baris),
                'catatan' => null,
            ];
        } catch (Throwable) {
            return [];
        }
    }

    private function teksMengandung(string $teks, array $kataKunci): bool
    {
        foreach ($kataKunci as $kata) {
            if ($kata !== '' && str_contains($teks, mb_strtolower((string) $kata))) {
                return true;
            }
        }

        return false;
    }

    // ===== Koleksi tematik terkurasi (aditif, baca-saja untuk PDP) =====

    /**
     * Koleksi aktif yang memuat produk ini dan sedang dalam jadwal tampil
     * (starts_at/ends_at dihormati bila terisi).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function koleksiTematikAktif(): \Illuminate\Support\Collection
    {
        try {
            if (! Schema::hasTable('thematic_collections')
                || ! Schema::hasTable('thematic_collection_product')
                || $this->getKey() === null) {
                return collect();
            }

            $sekarang = now();

            return DB::table('thematic_collections as tc')
                ->join('thematic_collection_product as tcp', 'tcp.thematic_collection_id', '=', 'tc.id')
                ->where('tcp.product_id', $this->getKey())
                ->where('tc.is_active', true)
                ->where(fn ($q) => $q->whereNull('tc.starts_at')->orWhere('tc.starts_at', '<=', $sekarang))
                ->where(fn ($q) => $q->whereNull('tc.ends_at')->orWhere('tc.ends_at', '>=', $sekarang))
                ->orderBy('tc.sort_order')
                ->orderBy('tc.name')
                ->get(['tc.id', 'tc.name', 'tc.slug', 'tc.description', 'tc.banner', 'tc.starts_at', 'tc.ends_at']);
        } catch (Throwable) {
            return collect();
        }
    }

    /** Semua koleksi yang sedang tayang (untuk landing kurasi). */
    public static function koleksiTematikTayang(int $batas = 12): \Illuminate\Support\Collection
    {
        try {
            if (! Schema::hasTable('thematic_collections')) {
                return collect();
            }

            $sekarang = now();

            return DB::table('thematic_collections')
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $sekarang))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $sekarang))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->limit(max(1, $batas))
                ->get();
        } catch (Throwable) {
            return collect();
        }
    }

    /** Produk tayang anggota satu koleksi (untuk landing koleksi). */
    public static function produkKoleksi(string $slugKoleksi, int $batas = 24): \Illuminate\Support\Collection
    {
        try {
            if (! Schema::hasTable('thematic_collections')
                || ! Schema::hasTable('thematic_collection_product')) {
                return collect();
            }

            $koleksi = DB::table('thematic_collections')->where('slug', $slugKoleksi)->first();

            if (! $koleksi) {
                return collect();
            }

            return DB::table('thematic_collection_product as tcp')
                ->join('products as p', 'p.id', '=', 'tcp.product_id')
                ->where('tcp.thematic_collection_id', $koleksi->id)
                ->where('p.status', 'approved')
                ->where('p.published', true)
                ->orderBy('tcp.sort_order')
                ->limit(max(1, $batas))
                ->get(['p.id', 'p.name', 'p.slug', 'p.thumbnail', 'p.price', 'p.special_price']);
        } catch (Throwable) {
            return collect();
        }
    }

    // ===== Lisensi digital otomatis (aditif, pakai digital_file existing) =====

    /** True bila produk digital dan sudah punya berkas unduhan. */
    public function butuhLisensiDigital(): bool
    {
        return (string) ($this->getAttribute('product_type') ?? '') === 'digital'
            && trim((string) ($this->getAttribute('digital_file') ?? '')) !== '';
    }

    /**
     * Terbitkan kunci lisensi unik untuk satu pembelian (satu order item →
     * satu kunci). Idempoten: pembelian yang sama tidak digandakan.
     */
    public function buatLisensiDigital(?int $orderItemId = null, int $maksUnduh = 5, ?\DateTimeInterface $kedaluwarsa = null): ?object
    {
        try {
            if (! Schema::hasTable('digital_licenses') || $this->getKey() === null) {
                return null;
            }

            if ($orderItemId !== null) {
                $ada = DB::table('digital_licenses')->where('order_item_id', $orderItemId)->first();

                if ($ada) {
                    return $ada;
                }
            }

            for ($i = 0; $i < 10; $i++) {
                $kunci = 'LIC-'.implode('-', [
                    strtoupper(\Illuminate\Support\Str::random(4)),
                    strtoupper(\Illuminate\Support\Str::random(4)),
                    strtoupper(\Illuminate\Support\Str::random(4)),
                ]);

                if (DB::table('digital_licenses')->where('license_key', $kunci)->exists()) {
                    continue;
                }

                $id = DB::table('digital_licenses')->insertGetId([
                    'product_id' => $this->getKey(),
                    'order_item_id' => $orderItemId,
                    'license_key' => $kunci,
                    'max_downloads' => max(1, $maksUnduh),
                    'download_count' => 0,
                    'revoked' => false,
                    'expires_at' => $kedaluwarsa?->format('Y-m-d H:i:s'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return DB::table('digital_licenses')->where('id', $id)->first();
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Ambil lisensi milik satu order item, bila ada. */
    public function lisensiUntukOrderItem(int $orderItemId): ?object
    {
        try {
            if (! Schema::hasTable('digital_licenses')) {
                return null;
            }

            return DB::table('digital_licenses')->where('order_item_id', $orderItemId)->first();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Catat satu unduhan; tolak bila lisensi dicabut, kedaluwarsa,
     * atau batas unduh tercapai. Mengembalikan status berbahasa Indonesia.
     *
     * @return array{ok: bool, pesan: string, sisa: int}
     */
    public static function catatUnduhanLisensi(string $kunci, ?string $ip = null, ?string $agen = null): array
    {
        try {
            if (! Schema::hasTable('digital_licenses') || ! Schema::hasTable('digital_license_downloads')) {
                return ['ok' => false, 'pesan' => 'Fitur lisensi digital belum tersedia.', 'sisa' => 0];
            }

            $lisensi = DB::table('digital_licenses')->where('license_key', $kunci)->first();

            if (! $lisensi) {
                return ['ok' => false, 'pesan' => 'Kunci lisensi tidak ditemukan.', 'sisa' => 0];
            }

            if ((bool) $lisensi->revoked) {
                return ['ok' => false, 'pesan' => 'Lisensi ini telah dicabut.', 'sisa' => 0];
            }

            if ($lisensi->expires_at !== null && now()->greaterThan($lisensi->expires_at)) {
                return ['ok' => false, 'pesan' => 'Masa berlaku lisensi sudah berakhir.', 'sisa' => 0];
            }

            $sisa = (int) $lisensi->max_downloads - (int) $lisensi->download_count;

            if ($sisa <= 0) {
                return ['ok' => false, 'pesan' => 'Batas unduh lisensi sudah habis.', 'sisa' => 0];
            }

            DB::table('digital_license_downloads')->insert([
                'digital_license_id' => $lisensi->id,
                'downloaded_at' => now(),
                'ip_hash' => $ip !== null ? hash('sha256', $ip) : null,
                'user_agent' => $agen !== null ? mb_substr($agen, 0, 255) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('digital_licenses')->where('id', $lisensi->id)->increment('download_count');

            return ['ok' => true, 'pesan' => 'Unduhan dicatat.', 'sisa' => $sisa - 1];
        } catch (Throwable) {
            return ['ok' => false, 'pesan' => 'Gagal mencatat unduhan.', 'sisa' => 0];
        }
    }

    /** Riwayat unduhan satu kunci lisensi, terbaru dulu. */
    public static function riwayatUnduhanLisensi(string $kunci): array
    {
        try {
            if (! Schema::hasTable('digital_licenses') || ! Schema::hasTable('digital_license_downloads')) {
                return [];
            }

            $lisensi = DB::table('digital_licenses')->where('license_key', $kunci)->first();

            if (! $lisensi) {
                return [];
            }

            return DB::table('digital_license_downloads')
                ->where('digital_license_id', $lisensi->id)
                ->orderByDesc('downloaded_at')
                ->get()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** Cabut lisensi (unduhan berikutnya ditolak). */
    public static function cabutLisensi(string $kunci): bool
    {
        try {
            if (! Schema::hasTable('digital_licenses')) {
                return false;
            }

            return (bool) DB::table('digital_licenses')->where('license_key', $kunci)->update([
                'revoked' => true,
                'updated_at' => now(),
            ]);
        } catch (Throwable) {
            return false;
        }
    }
}
