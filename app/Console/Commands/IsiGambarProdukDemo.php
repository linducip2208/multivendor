<?php

namespace App\Console\Commands;

use App\Models\Product;
use Database\Seeders\Support\DemoPhotoFetcher;
use Database\Seeders\Support\DemoProductImage;
use Illuminate\Console\Command;

/**
 * Isi ulang gambar produk demo yang masih placeholder/kosong dengan
 * ilustrasi sesuai kategori + nama produk. Idempoten: produk yang sudah
 * bergambar demo dilewati. Hanya menyentuh kolom thumbnail/images.
 */
class IsiGambarProdukDemo extends Command
{
    protected $signature = 'demo:isi-gambar-produk {--limit=0 : Batasi jumlah produk (0 = semua)} {--svg : Paksa ilustrasi SVG, lewati foto asli} {--ganti-svg : Ganti juga ilustrasi SVG demo dengan foto asli}';

    protected $description = 'Isi gambar produk demo: foto asli Wikimedia Commons (fallback ilustrasi SVG)';

    public function handle(): int
    {
        $query = Product::query()->with(['category', 'brand'])
            ->where(function ($q): void {
                $q->whereNull('thumbnail')->orWhere('thumbnail', '')
                    ->orWhere('thumbnail', 'like', '%placeholder%');
                if ($this->option('ganti-svg')) {
                    $q->orWhere('thumbnail', 'like', '%products/demo/%');
                }
            })
            ->orderBy('id');

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = (clone $query)->count();
        $idsHilang = [];
        if ($this->option('ganti-svg')) {
            // Produk yang file fotonya sudah tidak ada di disk ikut diperbaiki.
            foreach (Product::query()->select(['id', 'thumbnail'])->where('thumbnail', 'like', '%products/photo/%')->cursor() as $ringkas) {
                if (! is_file(storage_path('app/public/'.$ringkas->thumbnail))) {
                    $idsHilang[] = $ringkas->id;
                }
            }
            $total += count($idsHilang);
        }
        if ($total === 0) {
            $this->info('Semua produk sudah bergambar. Tidak ada yang diubah.');

            return self::SUCCESS;
        }

        $this->info("Mengisi gambar untuk {$total} produk...");
        $bar = $this->output->createProgressBar($total);
        $diisi = 0;

        $proses = function ($product) use (&$diisi, $bar): void {
            $kategori = $product->category;
            $slugKategori = (string) ($kategori->slug ?? 'lainnya');
            $namaKategori = (string) ($kategori->name ?? 'Lainnya');
            $brand = (string) ($product->brand?->name ?? 'Tanpa Brand');
            $seed = (string) ($product->slug ?: 'produk-'.$product->getKey());

            $gambar = $this->gambarProduk($product, $slugKategori, $namaKategori, $brand, $seed);

            if ($gambar !== []) {
                $product->forceFill([
                    'thumbnail' => $gambar[0],
                    'images' => json_encode($gambar),
                ])->save();
                $diisi++;
            }
            $bar->advance();
        };

        foreach ($query->cursor() as $product) {
            $proses($product);
        }
        if ($idsHilang !== []) {
            foreach (Product::query()->with(['category', 'brand'])->whereIn('id', $idsHilang)->cursor() as $product) {
                $proses($product);
            }
        }

        $bar->finish();
        $this->info("\nSelesai: {$diisi} produk diisi gambar.");

        return self::SUCCESS;
    }

    /** Foto asli dulu (cache per kata kunci), fallback ilustrasi SVG. */
    private function gambarProduk($product, string $slugKategori, string $namaKategori, string $brand, string $seed): array
    {
        if (! $this->option('svg')) {
            try {
                $keyword = DemoPhotoFetcher::keywordFor(
                    (string) $product->name.' '.$brand, $slugKategori, $namaKategori
                );
                $foto = DemoPhotoFetcher::fetch($keyword, 3);
                if (count($foto) >= 1) {
                    return $foto;
                }
            } catch (\Throwable) {
            }
        }

        $gambar = [];
        for ($v = 0; $v < 3; $v++) {
            try {
                $gambar[] = DemoProductImage::forProduct(
                    (string) $product->name, $brand, $slugKategori, $namaKategori, $seed, $v
                );
            } catch (\Throwable) {
                break;
            }
        }

        return $gambar;
    }
}
