<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Tren harga produk dari riwayat transaksi (order_items + orders).
 *
 * Tidak ada tabel price_histories di migrasi, jadi tren dihitung dari harga
 * jual tercatat — memakai kolom yang terverifikasi ada di
 * 2026_06_09_000010 (order_items.price, order_items.created_at) dan status
 * pesanan yang bukan "canceled". Dipakai PDP dan endpoint priceHistory.
 */
final class TrenHarga
{
    /**
     * @return array{
     *   titik: list<array{tanggal: string, harga: float}>,
     *   jumlah_transaksi: int,
     *   terkini: float|null,
     *   terendah: float|null,
     *   tertinggi: float|null,
     *   rata_rata: float|null,
     *   perubahan_persen: float|null,
     *   arah: string
     * }
     */
    public function untukProduk(int $productId, int $hari = 30): array
    {
        $kosong = [
            'titik' => [], 'jumlah_transaksi' => 0, 'terkini' => null,
            'terendah' => null, 'tertinggi' => null, 'rata_rata' => null,
            'perubahan_persen' => null, 'arah' => 'stabil',
        ];

        if ($productId <= 0) {
            return $kosong;
        }

        try {
            if (! Schema::hasTable('order_items')) {
                return $kosong;
            }

            $query = DB::table('order_items as oi')
                ->where('oi.product_id', $productId)
                ->where('oi.created_at', '>=', now()->subDays(max(1, $hari)));

            if (Schema::hasTable('orders')) {
                $query->join('orders as o', 'o.id', '=', 'oi.order_id')
                    ->where('o.order_status', '!=', 'canceled');
            }

            $rows = $query
                ->selectRaw('DATE(oi.created_at) as tanggal, AVG(oi.price) as harga, COUNT(*) as jumlah')
                ->groupBy('tanggal')
                ->orderBy('tanggal')
                ->limit(90)
                ->get();
        } catch (Throwable) {
            return $kosong;
        }

        if ($rows->isEmpty()) {
            return $kosong;
        }

        $titik = $rows->map(fn ($row): array => [
            'tanggal' => (string) $row->tanggal,
            'harga' => round((float) $row->harga, 2),
        ])->all();

        $harga = array_column($titik, 'harga');
        $pertama = (float) $harga[0];
        $terkini = (float) end($harga);
        $perubahan = $pertama > 0 ? round(($terkini - $pertama) / $pertama * 100, 1) : null;

        return [
            'titik' => $titik,
            'jumlah_transaksi' => (int) $rows->sum('jumlah'),
            'terkini' => $terkini,
            'terendah' => round((float) min($harga), 2),
            'tertinggi' => round((float) max($harga), 2),
            'rata_rata' => round(array_sum($harga) / count($harga), 2),
            'perubahan_persen' => $perubahan,
            'arah' => $perubahan === null ? 'stabil' : ($perubahan > 0.5 ? 'naik' : ($perubahan < -0.5 ? 'turun' : 'stabil')),
        ];
    }
}
