<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Impor/ekspor CSV produk: template, validasi per baris, dan laporan hasil.
 *
 * Kolom yang dipakai hanya yang ada di migrasi products
 * (2026_06_09_000005 + merchandising 2026_09_28_000003): name, slug, sku,
 * barcode, price, special_price, current_stock, low_stock_threshold,
 * category_id, brand_id, unit, min_qty, max_qty, weight, published,
 * search_keywords, short_description, description, condition.
 *
 * Layanan ini murni (tidak menulis ke database) sehingga aman dipakai dari
 * mana saja dan mudah diuji; penulisan tetap menjadi tugas controller
 * vendor/admin yang sudah ada.
 */
final class ProductCsvService
{
    public const HEADERS = [
        'name', 'slug', 'sku', 'barcode', 'price', 'special_price',
        'current_stock', 'low_stock_threshold', 'category_id', 'brand_id',
        'unit', 'min_qty', 'max_qty', 'weight', 'published',
        'search_keywords', 'short_description', 'description', 'condition',
    ];

    /**
     * Baris contoh untuk template CSV yang bisa diunduh vendor.
     *
     * @return list<array<string, string>>
     */
    public function contohTemplate(): array
    {
        return [[
            'name' => 'Contoh Kaos Polos Hitam',
            'slug' => '',
            'sku' => 'KAOS-HITAM-M',
            'barcode' => '',
            'price' => '99000',
            'special_price' => '79000',
            'current_stock' => '50',
            'low_stock_threshold' => '5',
            'category_id' => '',
            'brand_id' => '',
            'unit' => 'pcs',
            'min_qty' => '1',
            'max_qty' => '10',
            'weight' => '0.25',
            'published' => '1',
            'search_keywords' => 'kaos polos hitam katun',
            'short_description' => 'Kaos polos katun 30s yang adem.',
            'description' => 'Deskripsi lengkap produk contoh.',
            'condition' => 'new',
        ]];
    }

    public function templateCsv(): string
    {
        return $this->keCsv($this->contohTemplate());
    }

    /**
     * @param  iterable<array<string, mixed>>  $rows
     */
    public function keCsv(iterable $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fputcsv($handle, self::HEADERS);

        foreach ($rows as $row) {
            $line = [];
            foreach (self::HEADERS as $header) {
                $line[] = (string) ($row[$header] ?? '');
            }
            fputcsv($handle, $line);
        }

        rewind($handle);
        $out = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $out;
    }

    /**
     * Validasi satu baris CSV. Mengembalikan pesan berbahasa Indonesia.
     *
     * @param  array<string, mixed>  $baris
     * @param  array<string, bool>  $skuTerlihat  SKU yang sudah muncul di file yang sama
     * @return list<string>
     */
    public function validasiBaris(array $baris, array $skuTerlihat = []): array
    {
        $errors = [];
        $nama = trim((string) ($baris['name'] ?? ''));

        if ($nama === '') {
            $errors[] = 'Kolom "name" wajib diisi.';
        } elseif (mb_strlen($nama) > 255) {
            $errors[] = 'Kolom "name" maksimal 255 karakter.';
        }

        $harga = $baris['price'] ?? null;
        if ($harga === null || $harga === '' || ! is_numeric($harga) || (float) $harga < 0) {
            $errors[] = 'Kolom "price" harus berupa angka minimal 0.';
        }

        $coret = trim((string) ($baris['special_price'] ?? ''));
        if ($coret !== '') {
            if (! is_numeric($coret) || (float) $coret <= 0) {
                $errors[] = 'Kolom "special_price" harus berupa angka lebih dari 0 bila diisi.';
            } elseif (is_numeric($harga) && (float) $coret >= (float) $harga) {
                $errors[] = 'Kolom "special_price" (harga jual) harus lebih kecil dari "price" (harga coret).';
            }
        }

        foreach (['current_stock' => 'stok', 'low_stock_threshold' => 'ambang stok', 'min_qty' => 'pembelian minimum', 'max_qty' => 'pembelian maksimum'] as $kolom => $label) {
            $nilai = trim((string) ($baris[$kolom] ?? ''));
            if ($nilai !== '' && (! ctype_digit($nilai) || (int) $nilai < 0)) {
                $errors[] = 'Kolom "'.$kolom.'" ('.$label.') harus berupa bilangan bulat minimal 0 bila diisi.';
            }
        }

        $berat = trim((string) ($baris['weight'] ?? ''));
        if ($berat !== '' && (! is_numeric($berat) || (float) $berat < 0)) {
            $errors[] = 'Kolom "weight" harus berupa angka minimal 0 bila diisi.';
        }

        $sku = trim((string) ($baris['sku'] ?? ''));
        if ($sku !== '' && isset($skuTerlihat[$sku])) {
            $errors[] = 'SKU "'.$sku.'" muncul lebih dari satu kali di file ini.';
        }

        $kondisi = trim((string) ($baris['condition'] ?? ''));
        if ($kondisi !== '' && ! in_array(strtolower($kondisi), ['new', 'used', 'refurbished'], true)) {
            $errors[] = 'Kolom "condition" hanya boleh berisi: new, used, atau refurbished.';
        }

        $terbit = trim((string) ($baris['published'] ?? ''));
        if ($terbit !== '' && ! in_array($terbit, ['0', '1', 'true', 'false', 'ya', 'tidak'], true)) {
            $errors[] = 'Kolom "published" hanya boleh berisi 1/0 (tayang/tidak).';
        }

        return $errors;
    }

    /**
     * Parse isi CSV dan kembalikan laporan hasil per baris.
     *
     * @return array{total: int, valid: int, gagal: int, baris: list<array{nomor: int, data: array<string, string>, errors: list<string>}>}
     */
    public function laporanImpor(string $isiCsv, int $maksBaris = 5000): array
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return ['total' => 0, 'valid' => 0, 'gagal' => 0, 'baris' => []];
        }

        fwrite($handle, $isiCsv);
        rewind($handle);

        $header = fgetcsv($handle);
        if ($header === false || $header === []) {
            fclose($handle);

            return ['total' => 0, 'valid' => 0, 'gagal' => 0, 'baris' => []];
        }

        $header = array_map(static fn ($h): string => Str::slug(trim((string) $h), '_'), $header);

        $baris = [];
        $skuTerlihat = [];
        $nomor = 0;

        while (($row = fgetcsv($handle)) !== false && $nomor < $maksBaris) {
            $nomor++;

            if ($row === [null] || $row === []) {
                continue;
            }

            $data = [];
            foreach ($header as $i => $namaKolom) {
                if (in_array($namaKolom, self::HEADERS, true)) {
                    $data[$namaKolom] = trim((string) ($row[$i] ?? ''));
                }
            }

            // Baris kosong total dilewati tanpa dihitung gagal.
            if (collect($data)->filter(fn ($v) => $v !== '')->isEmpty()) {
                $nomor--;
                continue;
            }

            $errors = $this->validasiBaris($data, $skuTerlihat);
            $sku = trim((string) ($data['sku'] ?? ''));
            if ($sku !== '') {
                $skuTerlihat[$sku] = true;
            }

            $baris[] = ['nomor' => $nomor + 1, 'data' => $data, 'errors' => $errors];
        }

        fclose($handle);

        $gagal = collect($baris)->filter(fn (array $b): bool => $b['errors'] !== [])->count();

        return [
            'total' => count($baris),
            'valid' => count($baris) - $gagal,
            'gagal' => $gagal,
            'baris' => $baris,
        ];
    }

    /**
     * Ekspor koleksi produk menjadi CSV siap unduh.
     *
     * @param  iterable<mixed>  $produk
     */
    public function ekspor(iterable $produk): string
    {
        $rows = [];

        foreach ($produk as $item) {
            $p = $item instanceof \Illuminate\Database\Eloquent\Model ? $item->toArray() : (array) $item;
            $row = [];
            foreach (self::HEADERS as $header) {
                $row[$header] = (string) ($p[$header] ?? '');
            }
            $rows[] = $row;
        }

        return $this->keCsv($rows);
    }
}
