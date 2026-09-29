<?php

declare(strict_types=1);

namespace App\Services\Loyalitas;

use App\Models\LoyaltyPoint;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\ProductReview;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Misi harian + check-in beruntun (streak).
 *
 * Tanpa tabel/migrasi baru: status misi diturunkan dari tabel existing
 * (orders, product_reviews, users) dan klaim dicatat memakai transaksi
 * loyalitas existing dengan reference_type `misi_harian:{kunci}` /
 * `checkin` + reference_id tanggal Ymd sehingga idempoten per hari.
 */
class MisiHarian
{
    public const KEY_LOGIN = 'login';

    public const KEY_CHECKIN = 'checkin';

    public const KEY_REVIEW = 'review';

    public const KEY_SHARE = 'share';

    public const KEY_BELANJA = 'belanja';

    /** @return array<string, array{label: string, deskripsi: string, poin: int, target: int}> */
    public static function missions(): array
    {
        return [
            self::KEY_LOGIN => [
                'label' => 'Masuk harian',
                'deskripsi' => 'Buka aplikasi dan masuk ke akun Anda hari ini.',
                'poin' => 5,
                'target' => 1,
            ],
            self::KEY_CHECKIN => [
                'label' => 'Check-in beruntun',
                'deskripsi' => 'Check-in setiap hari untuk bonus streak.',
                'poin' => 10,
                'target' => 1,
            ],
            self::KEY_REVIEW => [
                'label' => 'Tulis ulasan',
                'deskripsi' => 'Beri ulasan untuk produk yang sudah dibeli.',
                'poin' => 15,
                'target' => 1,
            ],
            self::KEY_SHARE => [
                'label' => 'Bagikan produk',
                'deskripsi' => 'Bagikan tautan produk/afiliasi ke teman atau media sosial.',
                'poin' => 10,
                'target' => 1,
            ],
            self::KEY_BELANJA => [
                'label' => 'Belanja harian',
                'deskripsi' => 'Buat minimal 1 pesanan hari ini.',
                'poin' => 25,
                'target' => 1,
            ],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::missions());
    }

    public static function missionRef(string $key): string
    {
        return 'misi_harian:'.$key;
    }

    public static function dayId(?\DateTimeInterface $date = null): int
    {
        return (int) ($date ?? now())->format('Ymd');
    }

    /**
     * Status semua misi untuk pengguna (progres + sudah diklaim atau belum).
     *
     * @return list<array{key: string, label: string, deskripsi: string, poin: int, target: int, progress: int, selesai: bool, diklaim: bool}>
     */
    public function statusFor(User $user): array
    {
        $out = [];
        foreach (self::missions() as $key => $def) {
            $progress = $this->progressFor($user, $key);
            $out[] = [
                'key' => $key,
                'label' => $def['label'],
                'deskripsi' => $def['deskripsi'],
                'poin' => $key === self::KEY_CHECKIN ? $this->checkinPoints($this->streak($user) + 1) : $def['poin'],
                'target' => $progress['target'],
                'progress' => $progress['progress'],
                'selesai' => $progress['done'],
                'diklaim' => $this->claimedOn($user, $key, now()),
            ];
        }

        return $out;
    }

    /** @return array{progress: int, target: int, done: bool} */
    public function progressFor(User $user, string $key): array
    {
        $today = now()->toDateString();

        return match ($key) {
            self::KEY_LOGIN => ['progress' => 1, 'target' => 1, 'done' => true],
            self::KEY_CHECKIN => ['progress' => $this->claimedOn($user, $key, now()) ? 1 : 0, 'target' => 1, 'done' => $this->claimedOn($user, $key, now())],
            self::KEY_REVIEW => $this->countProgress(
                (int) ProductReview::where('customer_id', $user->id)->whereDate('created_at', $today)->count()
            ),
            self::KEY_SHARE => ['progress' => 1, 'target' => 1, 'done' => true],
            self::KEY_BELANJA => $this->countProgress(
                (int) Order::where('customer_id', $user->id)->whereDate('created_at', $today)->whereNotIn('order_status', ['canceled', 'failed'])->count()
            ),
            default => ['progress' => 0, 'target' => 1, 'done' => false],
        };
    }

    /** @return array{progress: int, target: int, done: bool} */
    private function countProgress(int $count, int $target = 1): array
    {
        return ['progress' => min($count, $target), 'target' => $target, 'done' => $count >= $target];
    }

    public function claimedOn(User $user, string $key, \DateTimeInterface $date): bool
    {
        $ref = $key === self::KEY_CHECKIN ? 'checkin' : self::missionRef($key);

        return LoyaltyTransaction::where('customer_id', $user->id)
            ->where('reference_type', $ref)
            ->where('reference_id', self::dayId($date))
            ->exists();
    }

    /**
     * Klaim hadiah misi harian via transaksi loyalitas existing.
     *
     * @return array{ok: bool, poin: int, pesan: string}
     */
    public function claim(User $user, string $key): array
    {
        $missions = self::missions();
        if (! isset($missions[$key])) {
            return ['ok' => false, 'poin' => 0, 'pesan' => 'Misi tidak dikenal.'];
        }
        if ($key === self::KEY_CHECKIN) {
            return $this->checkin($user);
        }
        if ($this->claimedOn($user, $key, now())) {
            return ['ok' => false, 'poin' => 0, 'pesan' => 'Hadiah misi hari ini sudah diklaim.'];
        }
        $progress = $this->progressFor($user, $key);
        if (! $progress['done']) {
            return ['ok' => false, 'poin' => 0, 'pesan' => 'Selesaikan dulu misi "'.$missions[$key]['label'].'" hari ini.'];
        }

        $points = (int) $missions[$key]['poin'];
        LoyaltyPoint::earn(
            $user,
            $points,
            'Misi harian: '.$missions[$key]['label'].' ('.now()->toDateString().')',
            self::missionRef($key),
            self::dayId()
        );

        return ['ok' => true, 'poin' => $points, 'pesan' => $points.' poin misi "'.$missions[$key]['label'].'" masuk ke saldo Anda.'];
    }

    /**
     * Check-in harian + bonus streak.
     *
     * @return array{ok: bool, poin: int, streak: int, pesan: string}
     */
    public function checkin(User $user): array
    {
        if ($this->claimedOn($user, self::KEY_CHECKIN, now())) {
            return ['ok' => false, 'poin' => 0, 'streak' => $this->streak($user), 'pesan' => 'Anda sudah check-in hari ini. Kembali lagi besok!'];
        }

        $streak = $this->streak($user) + 1;
        $points = $this->checkinPoints($streak);
        LoyaltyPoint::earn(
            $user,
            $points,
            'Check-in harian beruntun '.$streak.' hari ('.now()->toDateString().')',
            'checkin',
            self::dayId()
        );

        return ['ok' => true, 'poin' => $points, 'streak' => $streak, 'pesan' => 'Check-in hari ke-'.$streak.' berhasil! +'.$points.' poin.'];
    }

    /** Poin check-in: basis 10 + bonus Rp—poin 2/hari beruntun, maks 7 hari. */
    public function checkinPoints(int $streak): int
    {
        return 10 + min(max($streak - 1, 0), 6) * 2;
    }

    /** Hitung streak check-in beruntun hingga kemarin (atau termasuk hari ini bila sudah check-in). */
    public function streak(User $user): int
    {
        $streak = 0;
        $cursor = CarbonImmutable::now()->startOfDay();
        // Jika belum check-in hari ini, streak dihitung dari kemarin.
        if (! $this->claimedOn($user, self::KEY_CHECKIN, now())) {
            $cursor = $cursor->subDay();
        }
        for ($i = 0; $i < 365; $i++) {
            if ($this->claimedOn($user, self::KEY_CHECKIN, $cursor)) {
                $streak++;
                $cursor = $cursor->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }
}
