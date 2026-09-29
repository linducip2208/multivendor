<?php

declare(strict_types=1);

namespace App\Services\B2b;

use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Termin/tagihan B2B di atas mekanisme existing:
 * - memakai `orders.payment_status = 'partial'` (nilai existing) untuk order
 *   yang dicicil, dan 'paid' saat semua termin lunas;
 * - pelunasan per termin dicatat di `b2b_termins` sehingga cocok dengan
 *   pembayaran offline existing (transfer/bukti di luar gateway).
 *
 * Tidak menyentuh OrderWorkflowService / CheckoutCalculator — hanya
 * menulis tabel baru + kolom `payment_status` pada order yang dijadwalkan.
 */
final class B2bTerminService
{
    /**
     * Buat jadwal termin untuk sebuah order. Total nominal termin harus
     * pas dengan total order (toleransi 1 sen).
     *
     * @param  list<array{label?: ?string, amount: float|int|string, due_at?: mixed, note?: ?string}>  $termins
     */
    public function buatJadwal(Order $order, array $termins): Collection
    {
        $this->assertTabelAda();

        if ($termins === []) {
            throw ValidationException::withMessages(['termins' => 'Isi minimal satu termin pembayaran.']);
        }

        $total = (float) ($order->total ?? 0);
        $jumlah = round(array_sum(array_map(fn ($t) => (float) ($t['amount'] ?? 0), $termins)), 2);

        if (abs($jumlah - round($total, 2)) > 0.01) {
            throw ValidationException::withMessages([
                'termins' => 'Total termin (Rp ' . number_format($jumlah, 0, ',', '.') . ') harus sama dengan total pesanan (Rp ' . number_format($total, 0, ',', '.') . ').',
            ]);
        }

        foreach (array_values($termins) as $i => $t) {
            if ((float) ($t['amount'] ?? 0) <= 0) {
                throw ValidationException::withMessages(["termins.{$i}.amount" => 'Nominal termin harus lebih besar dari nol.']);
            }
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($order, $termins) {
            B2bTermin::query()->where('order_id', $order->getKey())->delete();

            $hasil = collect();
            foreach (array_values($termins) as $i => $t) {
                $hasil->push(B2bTermin::query()->create([
                    'order_id' => $order->getKey(),
                    'shop_id' => $order->shop_id,
                    'sequence' => $i + 1,
                    'label' => isset($t['label']) && $t['label'] !== '' ? (string) $t['label'] : 'Termin ' . ($i + 1),
                    'amount' => round((float) $t['amount'], 2),
                    'paid_amount' => 0,
                    'status' => B2bTermin::SCHEDULED,
                    'due_at' => $t['due_at'] ?? null,
                    'note' => isset($t['note']) && $t['note'] !== '' ? (string) $t['note'] : null,
                ]));
            }

            if (in_array((string) ($order->payment_status ?? ''), ['unpaid', ''], true)) {
                $order->forceFill(['payment_status' => 'partial'])->save();
            }

            return $hasil;
        });
    }

    /**
     * Bagi total order menjadi N termin sama besar (penyesuaian sen terakhir).
     *
     * @return Collection<int, B2bTermin>
     */
    public function buatJadwalOtomatis(Order $order, int $jumlahTermin, ?string $jatuhTempoPertama = null, int $jarakHari = 30): Collection
    {
        if ($jumlahTermin < 1 || $jumlahTermin > 24) {
            throw ValidationException::withMessages(['jumlah' => 'Jumlah termin antara 1 sampai 24.']);
        }

        $total = round((float) ($order->total ?? 0), 2);
        $perTermin = floor(($total / $jumlahTermin) * 100) / 100;
        $terakhir = round($total - ($perTermin * ($jumlahTermin - 1)), 2);

        $awal = $jatuhTempoPertama !== null ? new \DateTimeImmutable($jatuhTempoPertama) : new \DateTimeImmutable('+30 days');
        $termins = [];
        for ($i = 0; $i < $jumlahTermin; $i++) {
            $termins[] = [
                'label' => 'Termin ' . ($i + 1) . '/' . $jumlahTermin,
                'amount' => $i === $jumlahTermin - 1 ? $terakhir : $perTermin,
                'due_at' => $awal->modify('+' . ($i * $jarakHari) . ' days')->format('Y-m-d H:i:s'),
            ];
        }

        return $this->buatJadwal($order, $termins);
    }

    /** Catat pembayaran (mis. bukti transfer offline) pada satu termin. */
    public function catatPembayaran(B2bTermin $termin, float $nominal): B2bTermin
    {
        $this->assertTabelAda();

        if ($nominal <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nominal pembayaran harus lebih besar dari nol.']);
        }

        if ($nominal - $termin->sisa() > 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal melebihi sisa termin (Rp ' . number_format($termin->sisa(), 0, ',', '.') . ').',
            ]);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($termin, $nominal) {
            $termin->forceFill([
                'paid_amount' => round((float) $termin->paid_amount + $nominal, 2),
            ])->save();
            $termin->refresh();

            $termin->forceFill([
                'status' => $termin->sisa() <= 0.0 ? B2bTermin::PAID : B2bTermin::PARTIAL,
                'paid_at' => $termin->sisa() <= 0.0 ? now() : $termin->paid_at,
            ])->save();

            $this->sinkronStatusOrder((int) $termin->order_id);

            return $termin->refresh();
        });
    }

    /** Tandai pengingat terkirim (untuk badge "sudah diingatkan" di finance vendor). */
    public function tandaiPengingat(B2bTermin $termin): B2bTermin
    {
        $termin->forceFill(['reminder_sent_at' => now()])->save();

        return $termin->refresh();
    }

    /**
     * Pengingat: termin yang sudah/belum jatuh tempo dalam N hari ke depan.
     *
     * @return array{terlambat: Collection<int, B2bTermin>, segera: Collection<int, B2bTermin>}
     */
    public function pengingat(?int $shopId = null, int $hariKeDepan = 7): array
    {
        if (! Schema::hasTable('b2b_termins')) {
            return ['terlambat' => collect(), 'segera' => collect()];
        }

        $dasar = B2bTermin::query()
            ->whereIn('status', [B2bTermin::SCHEDULED, B2bTermin::PARTIAL])
            ->when($shopId !== null, fn ($q) => $q->where('shop_id', $shopId))
            ->orderBy('due_at');

        $terlambat = (clone $dasar)->whereNotNull('due_at')->where('due_at', '<', now())->get();
        $segera = (clone $dasar)
            ->whereNotNull('due_at')
            ->where('due_at', '>=', now())
            ->where('due_at', '<=', now()->addDays(max(1, $hariKeDepan)))
            ->get();

        return ['terlambat' => $terlambat, 'segera' => $segera];
    }

    /** Ringkasan tagihan per toko untuk tampilan vendor finance (read-only). */
    public function ringkasanVendor(int $shopId): array
    {
        if (! Schema::hasTable('b2b_termins')) {
            return $this->ringkasanKosong();
        }

        $query = B2bTermin::query()->where('shop_id', $shopId);
        $tagihan = (float) (clone $query)->sum('amount');
        $tertagih = (float) (clone $query)->sum('paid_amount');
        $terlambat = (clone $query)
            ->whereIn('status', [B2bTermin::SCHEDULED, B2bTermin::PARTIAL])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        return [
            'tagihan' => $tagihan,
            'tertagih' => $tertagih,
            'sisa' => max(0.0, $tagihan - $tertagih),
            'terlambat' => $terlambat,
        ];
    }

    /** Jadwal termin sebuah order (read-only, aman bila tabel belum ada). */
    public function jadwalOrder(int $orderId): Collection
    {
        if (! Schema::hasTable('b2b_termins')) {
            return collect();
        }

        return B2bTermin::query()->where('order_id', $orderId)->orderBy('sequence')->get();
    }

    private function sinkronStatusOrder(int $orderId): void
    {
        try {
            $sisa = (float) B2bTermin::query()->where('order_id', $orderId)->selectRaw('SUM(amount - paid_amount) as s')->value('s');
        } catch (\Throwable) {
            return;
        }

        try {
            $order = Order::query()->find($orderId);
            if ($order === null) {
                return;
            }

            $order->forceFill(['payment_status' => $sisa <= 0.0 ? 'paid' : 'partial'])->save();
        } catch (\Throwable) {
            // Status order existing tidak boleh merusak pencatatan termin.
        }
    }

    private function assertTabelAda(): void
    {
        if (! Schema::hasTable('b2b_termins')) {
            throw ValidationException::withMessages(['termins' => 'Tabel termin belum tersedia. Jalankan migrasi database dahulu.']);
        }
    }

    /** @return array{tagihan: float, tertagih: float, sisa: float, terlambat: int} */
    private function ringkasanKosong(): array
    {
        return ['tagihan' => 0.0, 'tertagih' => 0.0, 'sisa' => 0.0, 'terlambat' => 0];
    }
}
