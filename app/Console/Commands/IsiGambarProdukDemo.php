<?php

namespace App\Console\Commands;

use App\Models\Product;
use Database\Seeders\Support\DemoProductImage;
use Illuminate\Console\Command;

/**
 * Isi ulang gambar produk demo yang masih placeholder/kosong dengan
 * ilustrasi sesuai kategori + nama produk. Idempoten: produk yang sudah
 * bergambar demo dilewati. Hanya menyentuh kolom thumbnail/images.
 */
class IsiGambarProdukDemo extends Command
{
    protected $signature = 'demo:isi-gambar-produk {--limit=0 : Batasi jumlah produk (0 = semua)}';

    protected $description = 'Generate ilustrasi kategori untuk produk demo tanpa gambar';

    public function handle(): int
    {
        $query = Product::query()->with(['category', 'brand'])
            ->where(function ($q): void {
                $q->whereNull('thumbnail')->orWhere('thumbnail', '')
                    ->orWhere('thumbnail', 'like', '%placeholder%');
            })
            ->orderBy('id');

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Semua produk sudah bergambar. Tidak ada yang diubah.');

            return self::SUCCESS;
        }

        $this->info("Mengisi gambar untuk {$total} produk...");
        $bar = $this->output->createProgressBar($total);
        $diisi = 0;

        foreach ($query->cursor() as $product) {
            $kategori = $product->category;
            $slugKategori = (string) ($kategori->slug ?? 'lainnya');
            $namaKategori = (string) ($kategori->name ?? 'Lainnya');
            $brand = (string) ($product->brand?->name ?? 'Tanpa Brand');
            $seed = (string) ($product->slug ?: 'produk-'.$product->getKey());

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

            if ($gambar !== []) {
                $product->forceFill([
                    'thumbnail' => $gambar[0],
                    'images' => json_encode($gambar),
                ])->save();
                $diisi++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->info("\nSelesai: {$diisi} produk diisi gambar.");

        return self::SUCCESS;
    }
}
