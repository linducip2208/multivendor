<?php

declare(strict_types=1);

namespace App\Services\B2b;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Harga tier grosir per produk (min. qty → harga).
 *
 * API bersih untuk integrator (termasuk wiring ke checkout via
 * B2bQuoteService::toCheckoutLines):
 *
 *   $harga = app(B2bPricingService::class)->unitPriceFor($product, $qty);
 *   $baris = app(B2bPricingService::class)->tierTableRows($product);
 *   $html  = app(B2bPricingService::class)->renderTierTableHtml($product);
 *
 * $html adalah tabel Tabler siap tempel di PDP — integrator cukup
 * `echo` di view PDP tanpa mengubah logika pricing/checkout existing.
 * Tidak pernah menulis ke produk/order; murni baca + tabel baru.
 */
final class B2bPricingService
{
    /**
     * Simpan ulang seluruh tier sebuah produk (ganti total, transaksional).
     *
     * @param  list<array{min_qty: int, price: float|int|string, note?: ?string}>  $tiers
     */
    public function simpanTiers(Product $product, array $tiers): Collection
    {
        $bersih = $this->validasi($tiers);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($product, $bersih) {
            B2bPriceTier::query()->where('product_id', $product->getKey())->delete();

            $hasil = collect();
            foreach ($bersih as $baris) {
                $hasil->push(B2bPriceTier::query()->create([
                    'product_id' => $product->getKey(),
                    'shop_id' => $product->shop_id,
                    'min_qty' => $baris['min_qty'],
                    'price' => $baris['price'],
                    'note' => $baris['note'] ?? null,
                ]));
            }

            return $hasil;
        });
    }

    /**
     * @param  list<array{min_qty: mixed, price: mixed, note?: mixed}>  $tiers
     * @return list<array{min_qty: int, price: float, note: ?string}>
     */
    public function validasi(array $tiers): array
    {
        if ($tiers === []) {
            throw ValidationException::withMessages(['tiers' => 'Isi minimal satu tingkat harga grosir.']);
        }

        $bersih = [];
        foreach (array_values($tiers) as $i => $baris) {
            $min = (int) ($baris['min_qty'] ?? 0);
            $harga = (float) ($baris['price'] ?? 0);

            if ($min < 1) {
                throw ValidationException::withMessages(["tiers.{$i}.min_qty" => 'Qty minimum harus minimal 1.']);
            }
            if ($harga <= 0) {
                throw ValidationException::withMessages(["tiers.{$i}.price" => 'Harga tier harus lebih besar dari nol.']);
            }

            $bersih[] = [
                'min_qty' => $min,
                'price' => round($harga, 2),
                'note' => isset($baris['note']) && $baris['note'] !== '' ? (string) $baris['note'] : null,
            ];
        }

        usort($bersih, fn ($a, $b) => $a['min_qty'] <=> $b['min_qty']);

        $dilihat = [];
        foreach ($bersih as $i => $baris) {
            if (isset($dilihat[$baris['min_qty']])) {
                throw ValidationException::withMessages(["tiers.{$i}.min_qty" => 'Qty minimum '.$baris['min_qty'].' duplikat. Gabungkan menjadi satu baris.']);
            }
            $dilihat[$baris['min_qty']] = true;

            if ($i > 0 && $baris['price'] > $bersih[$i - 1]['price']) {
                throw ValidationException::withMessages(["tiers.{$i}.price" => 'Harga grosir harus turun (atau sama) saat qty naik.']);
            }
        }

        return $bersih;
    }

    /** Daftar tier terurut naik menurut min_qty (koleksi kosong bila tabel/belum ada tier). */
    public function tiersFor(Product $product): Collection
    {
        try {
            if (! Schema::hasTable('b2b_price_tiers') || $product->getKey() === null) {
                return collect();
            }

            return B2bPriceTier::query()
                ->where('product_id', $product->getKey())
                ->orderBy('min_qty')
                ->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    /**
     * Harga satuan untuk qty tertentu: tier tertinggi yang min_qty-nya <= qty,
     * fallback ke harga ecer efektif existing (special/flash deal ikut berlaku).
     */
    public function unitPriceFor(Product $product, int $qty): float
    {
        $qty = max(1, $qty);

        $tier = $this->tiersFor($product)
            ->filter(fn ($t) => (int) $t->min_qty <= $qty)
            ->sortByDesc('min_qty')
            ->first();

        if ($tier !== null) {
            return (float) $tier->price;
        }

        try {
            return (float) $product->getEffectivePrice();
        } catch (\Throwable) {
            return (float) ($product->price ?? 0);
        }
    }

    /**
     * Baris siap tampil untuk tabel tier di PDP / dasbor vendor.
     *
     * @return list<array{min_qty: int, price: float, hemat_pct: ?float, note: ?string}>
     */
    public function tierTableRows(Product $product): array
    {
        $tiers = $this->tiersFor($product);
        if ($tiers->isEmpty()) {
            return [];
        }

        try {
            $ecer = (float) $product->getEffectivePrice();
        } catch (\Throwable) {
            $ecer = (float) ($product->price ?? 0);
        }

        return $tiers->map(fn ($t) => [
            'min_qty' => (int) $t->min_qty,
            'price' => (float) $t->price,
            'hemat_pct' => $ecer > 0 && (float) $t->price < $ecer
                ? round(($ecer - (float) $t->price) / $ecer * 100, 1)
                : null,
            'note' => $t->note,
        ])->all();
    }

    /**
     * Tabel tier (markup Tabler) siap tempel di PDP. String kosong bila
     * produk tidak punya tier — PDP existing tidak berubah tampilannya.
     */
    public function renderTierTableHtml(Product $product): string
    {
        $baris = $this->tierTableRows($product);
        if ($baris === []) {
            return '';
        }

        $unit = e((string) ($product->unit ?? 'pcs'));
        $html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
        $html .= '<thead><tr><th>Min. Jumlah</th><th class="text-end">Harga/' . $unit . '</th><th class="text-end">Hemat</th></tr></thead><tbody>';

        foreach ($baris as $r) {
            $hemat = $r['hemat_pct'] !== null
                ? '<span class="badge bg-success-lt text-success rounded-pill">' . e(number_format($r['hemat_pct'], 1, ',', '.')) . '%</span>'
                : '<span class="text-secondary">—</span>';
            $html .= '<tr><td><span class="fw-medium">' . e(number_format($r['min_qty'], 0, ',', '.')) . '+</span></td>'
                . '<td class="text-end fw-medium">Rp ' . e(number_format($r['price'], 0, ',', '.')) . '</td>'
                . '<td class="text-end">' . $hemat . '</td></tr>';
        }

        return $html . '</tbody></table></div>'
            . '<small class="text-muted d-block mt-1">Harga grosir berlaku otomatis saat jumlah memenuhi tingkat pembelian.</small>';
    }
}
