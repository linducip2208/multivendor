<?php

declare(strict_types=1);

namespace App\Services\B2b;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Dasbor salesman — 100% read-only dari data existing.
 *
 * Sumber: kolom existing `users.referral_code` (kode milik salesman) +
 * `users.referred_by` (customer yang mendaftar lewat kode tersebut).
 * Order yang dihitung = order milik customer yang di-refer.
 *
 * Tidak menulis apa pun; tidak menyentuh skema/loyalty/affiliate.
 */
final class B2bSalesmanService
{
    /**
     * @return array{code: string, pelanggan: int, order: int, omzet: float, komisi: float, tarif_pct: float, order_terbaru: Collection<int, mixed>}
     */
    public function dasbor(User $salesman, ?int $shopId = null, float $tarifPersen = -1.0): array
    {
        $kode = (string) ($salesman->referral_code ?? '');

        if ($kode === '' || ! Schema::hasTable('orders')) {
            return $this->kosong($kode);
        }

        try {
            $pelangganIds = User::query()->where('referred_by', $salesman->getKey())->pluck('id');
        } catch (\Throwable) {
            return $this->kosong($kode);
        }

        if ($pelangganIds->isEmpty()) {
            return $this->kosong($kode);
        }

        try {
            $query = \App\Models\Order::query()
                ->whereIn('customer_id', $pelangganIds)
                ->when($shopId !== null, fn ($q) => $q->where('shop_id', $shopId))
                ->whereNotIn('order_status', ['canceled', 'failed']);

            $order = (clone $query)->count();
            $omzet = (float) (clone $query)->sum('total');
            $terbaru = (clone $query)->latest()->limit(10)->get();
        } catch (\Throwable) {
            return $this->kosong($kode);
        }

        $tarif = $tarifPersen >= 0 ? $tarifPersen : $this->tarifDefault();

        return [
            'code' => $kode,
            'pelanggan' => $pelangganIds->count(),
            'order' => $order,
            'omzet' => $omzet,
            'komisi' => round($omzet * $tarif / 100, 2),
            'tarif_pct' => $tarif,
            'order_terbaru' => $terbaru,
        ];
    }

    /** Tarif komisi default dari pengaturan (fallback 2,5%). */
    public function tarifDefault(): float
    {
        try {
            $nilai = \App\Models\SystemSetting::get('b2b_salesman_commission_pct', 2.5);

            return max(0.0, min(100.0, (float) $nilai));
        } catch (\Throwable) {
            return 2.5;
        }
    }

    /** @return array{code: string, pelanggan: int, order: int, omzet: float, komisi: float, tarif_pct: float, order_terbaru: Collection<int, mixed>} */
    private function kosong(string $kode): array
    {
        return [
            'code' => $kode,
            'pelanggan' => 0,
            'order' => 0,
            'omzet' => 0.0,
            'komisi' => 0.0,
            'tarif_pct' => $this->tarifDefault(),
            'order_terbaru' => collect(),
        ];
    }
}
