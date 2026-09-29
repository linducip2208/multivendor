<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Models\AbandonedCart;
use App\Models\Banner;
use App\Models\Coupon;
use App\Models\CustomerSegmentMember;
use App\Models\FlashDeal;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Perdalaman pemasaran existing: retensi dan personalisasi.
 *
 * - Pengingat abandoned cart bertahap (maks 3x, hormati reminder_count /
 *   last_reminder_at, berhenti saat recovered, kupon pemulih di tahap akhir).
 * - Voucher ulang tahun otomatis (tanggal lahir bila kolom tersedia,
 *   fallback bulan registrasi; idempoten per pelanggan per tahun).
 * - Banner personalisasi per segmen (aturan tampil berbasis keanggotaan
 *   segmen existing; pemetaan disimpan di system_settings tanpa migrasi).
 * - "Ingatkan saya" flash sale (langganan sebelum deal mulai, notifikasi
 *   sekali saat deal start; langganan disimpan di system_settings).
 *
 * SegmentationService hanya dibaca (keanggotaan segmen), tidak diubah.
 * Notifikasi ditulis ke user_notifications kategori marketing.
 */
final class RetentionService
{
    public const MAX_REMINDERS = 3;

    /** Jeda minimum sejak keranjang dibuat sebelum pengingat tahap 1. */
    public const FIRST_DELAY_HOURS = 1;

    /** Jeda minimum antar pengingat (jam). */
    public const GAP_HOURS = 20;

    public const SETTING_BANNER_SEGMENTS = 'marketing.banner_segments';

    public const SETTING_FLASH_REMINDERS = 'marketing.flash_reminders';

    /* ------------------------------------------------------------------ */
    /* 1. Pengingat abandoned cart bertahap                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Keranjang yang layak diingatkan sekarang: belum pulih, di bawah batas,
     * cukup umur, dan jeda sejak pengingat terakhir terpenuhi.
     *
     * @return EloquentCollection<int, AbandonedCart>
     */
    public function dueCarts(int $limit = 100): EloquentCollection
    {
        $batas = now()->subHours(self::GAP_HOURS);
        $umurMinimum = now()->subHours(self::FIRST_DELAY_HOURS);

        return AbandonedCart::query()
            ->whereNull('recovered_at')
            ->where('reminder_count', '<', self::MAX_REMINDERS)
            ->where('created_at', '<=', $umurMinimum)
            ->where(function ($q) use ($batas): void {
                $q->whereNull('last_reminder_at')->orWhere('last_reminder_at', '<=', $batas);
            })
            ->orderBy('created_at')
            ->limit(max(1, min(500, $limit)))
            ->get();
    }

    /**
     * Kirim satu tahap pengingat berikutnya untuk sebuah keranjang.
     *
     * @return array{queued: bool, stage: int, reason: string, coupon_code: string|null}
     */
    public function sendStagedReminder(AbandonedCart $cart, ?int $actorId = null, bool $force = false): array
    {
        if ($cart->recovered_at !== null) {
            return ['queued' => false, 'stage' => 0, 'reason' => 'Keranjang ini sudah dipulihkan.', 'coupon_code' => null];
        }

        $sudah = (int) $cart->reminder_count;
        if ($sudah >= self::MAX_REMINDERS) {
            return ['queued' => false, 'stage' => 0, 'reason' => 'Batas pengingat sudah tercapai.', 'coupon_code' => null];
        }

        if (! $force) {
            if ($cart->created_at !== null && $cart->created_at->greaterThan(now()->subHours(self::FIRST_DELAY_HOURS))) {
                return ['queued' => false, 'stage' => 0, 'reason' => 'Keranjang masih terlalu baru untuk diingatkan.', 'coupon_code' => null];
            }

            if ($cart->last_reminder_at !== null && $cart->last_reminder_at->greaterThan(now()->subHours(self::GAP_HOURS))) {
                return ['queued' => false, 'stage' => 0, 'reason' => 'Jeda minimum antar pengingat belum terpenuhi.', 'coupon_code' => null];
            }
        }

        $tahap = $sudah + 1;
        $kupon = null;

        // Tahap akhir menyertakan kupon pemulih personal.
        if ($tahap >= self::MAX_REMINDERS) {
            $kupon = Coupon::buatKuponPemulih($cart->customer_id);
        }

        $this->notifyCustomer(
            $cart->customer_id,
            $this->stageTitle($tahap, $cart),
            $this->stageBody($tahap, $cart, $kupon?->code),
            'abandoned:'.$cart->getKey().':tahap'.$tahap,
            ['abandoned_cart_id' => (int) $cart->getKey(), 'stage' => $tahap, 'coupon_code' => $kupon?->code],
        );

        $cart->forceFill([
            'reminder_count' => $tahap,
            'last_reminder_at' => now(),
        ])->save();

        $this->audit('abandoned_cart.reminded_staged', $cart, ['reminder_count' => $sudah], [
            'reminder_count' => $tahap,
            'stage' => $tahap,
            'coupon_code' => $kupon?->code,
        ], $actorId);

        return [
            'queued' => true,
            'stage' => $tahap,
            'reason' => $tahap >= self::MAX_REMINDERS
                ? 'Pengingat tahap akhir terkirim beserta kupon pemulih '.($kupon?->code ?? '').'.'
                : 'Pengingat tahap '.$tahap.' dari '.self::MAX_REMINDERS.' terkirim.',
            'coupon_code' => $kupon?->code,
        ];
    }

    private function stageTitle(int $tahap, AbandonedCart $cart): string
    {
        return match ($tahap) {
            1 => 'Keranjang belanja Anda menunggu',
            2 => 'Jangan sampai kehabisan — keranjang Anda masih tersimpan',
            default => 'Kami simpan kupon khusus untuk keranjang Anda',
        };
    }

    private function stageBody(int $tahap, AbandonedCart $cart, ?string $kodeKupon): string
    {
        $nilai = number_format((float) $cart->amount, 0, ',', '.');

        return match ($tahap) {
            1 => 'Anda meninggalkan '.(int) $cart->item_count.' produk senilai Rp '.$nilai.'. Selesaikan pesanan kapan saja — keranjang tersimpan otomatis.',
            2 => 'Keranjang senilai Rp '.$nilai.' masih menunggu. Stok produk bisa berubah sewaktu-waktu, sebaiknya segera checkout.',
            default => 'Sebagai apresiasi, gunakan kode '.($kodeKupon ?? '').' untuk potongan khusus keranjang senilai Rp '.$nilai.'. Berlaku 7 hari, satu kali pakai.',
        };
    }

    /* ------------------------------------------------------------------ */
    /* 2. Voucher ulang tahun otomatis                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Kolom tanggal lahir pada tabel users bila tersedia (tanpa migrasi:
     * ikuti apa pun yang sudah ada), null bila tidak ada.
     */
    public function birthdateColumn(): ?string
    {
        try {
            foreach (['birthdate', 'tanggal_lahir', 'date_of_birth', 'dob'] as $kolom) {
                if (Schema::hasColumn('users', $kolom)) {
                    return $kolom;
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * Pelanggan yang berulang tahun pada tanggal acuan.
     * Memakai tanggal lahir bila kolom tersedia, fallback bulan registrasi.
     *
     * @return EloquentCollection<int, User>
     */
    public function birthdayCandidates(CarbonInterface $tanggal): EloquentCollection
    {
        $query = User::query()->where('role', 'customer');

        if (($kolom = $this->birthdateColumn()) !== null) {
            $bulan = $tanggal->month;
            $hari = $tanggal->day;

            return $query
                ->whereMonth($kolom, $bulan)
                ->whereDay($kolom, $hari)
                ->get();
        }

        // Fallback: pelanggan yang mendaftar pada bulan yang sama (tahun berapa pun).
        return $query
            ->whereMonth('created_at', $tanggal->month)
            ->get();
    }

    /**
     * Terbitkan (idempoten) voucher ulang tahun untuk satu pelanggan.
     *
     * @return array{coupon: Coupon, created: bool}
     */
    public function generateBirthdayVoucher(User $user, ?CarbonInterface $tanggal = null): array
    {
        $tanggal ??= now();
        $tahun = (int) $tanggal->format('Y');

        $sudahAda = Coupon::query()->where('code', Coupon::kodeUltah((int) $user->getKey(), $tahun))->exists();
        $kupon = Coupon::buatVoucherUltah((int) $user->getKey(), $tahun);

        if (! $sudahAda) {
            $this->notifyCustomer(
                (int) $user->getKey(),
                'Selamat ulang tahun! Ini hadiah untuk Anda 🎂',
                'Nikmati potongan 15% (maks. Rp 50.000) dengan kode '.$kupon->code.'. Berlaku 30 hari.',
                'ultah:'.$user->getKey().':'.$tahun,
                ['coupon_code' => $kupon->code, 'year' => $tahun],
            );
        }

        return ['coupon' => $kupon, 'created' => ! $sudahAda];
    }

    /**
     * Terbitkan voucher untuk seluruh kandidat pada tanggal acuan.
     *
     * @return array{issued: int, skipped: int, coupons: list<string>}
     */
    public function issueBirthdayVouchers(CarbonInterface $tanggal, ?int $actorId = null): array
    {
        $issued = 0;
        $skipped = 0;
        $codes = [];

        foreach ($this->birthdayCandidates($tanggal) as $user) {
            try {
                $hasil = $this->generateBirthdayVoucher($user, $tanggal);
                $codes[] = $hasil['coupon']->code;

                if ($hasil['created']) {
                    $issued++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable) {
                $skipped++;
            }
        }

        if ($issued > 0) {
            $this->audit('coupon.birthday_issued', null, [], [
                'date' => $tanggal->format('Y-m-d'),
                'issued' => $issued,
            ], $actorId);
        }

        return ['issued' => $issued, 'skipped' => $skipped, 'coupons' => $codes];
    }

    /* ------------------------------------------------------------------ */
    /* 3. Banner personalisasi per segmen                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Pemetaan banner → segmen: [banner_id => [segment_id, ...]].
     * Banner tanpa entri berarti publik (tampil untuk semua).
     *
     * @return array<int, list<int>>
     */
    public function bannerSegmentMap(): array
    {
        try {
            $raw = SystemSetting::get(self::SETTING_BANNER_SEGMENTS, '');
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        } catch (\Throwable) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $bannerId => $segmentIds) {
            $out[(int) $bannerId] = array_values(array_unique(array_map(
                'intval',
                is_array($segmentIds) ? $segmentIds : [$segmentIds],
            )));
        }

        return $out;
    }

    /**
     * Atur segmen sasaran sebuah banner ([] = publik untuk semua).
     *
     * @param  list<int>  $segmentIds
     * @return array<int, list<int>> pemetaan terbaru
     */
    public function setBannerSegments(int $bannerId, array $segmentIds, ?int $actorId = null): array
    {
        $map = $this->bannerSegmentMap();
        $bersih = array_values(array_unique(array_map('intval', $segmentIds)));
        $bersih = array_values(array_filter($bersih, fn (int $id): bool => $id > 0));

        if ($bersih === []) {
            unset($map[$bannerId]);
        } else {
            $map[$bannerId] = $bersih;
        }

        SystemSetting::set(self::SETTING_BANNER_SEGMENTS, json_encode($map, JSON_UNESCAPED_UNICODE));

        $banner = Banner::query()->find($bannerId);
        $this->audit('banner.segments_updated', $banner, [], [
            'banner_id' => $bannerId,
            'segment_ids' => $bersih,
        ], $actorId);

        return $map;
    }

    /**
     * Banner aktif pada posisi tertentu yang boleh dilihat pelanggan.
     * Tamu (null) hanya melihat banner publik.
     *
     * @return EloquentCollection<int, Banner>
     */
    public function visibleBannersForCustomer(?int $customerId, string $position = 'hero', int $limit = 3): EloquentCollection
    {
        $query = Banner::query()
            ->where('status', true)
            ->where('position', $position)
            ->orderBy('sort_order')
            ->limit(max(1, min(24, $limit)));

        // Hormati penjadwalan bila kolom tersedia (tanpa migrasi baru).
        try {
            if (Schema::hasColumn('banners', 'starts_at')) {
                $query->where(function ($q): void {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                });
            }
            if (Schema::hasColumn('banners', 'ends_at')) {
                $query->where(function ($q): void {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                });
            }
        } catch (\Throwable) {
        }

        $banners = $query->get();
        $map = $this->bannerSegmentMap();

        $tersegment = array_filter($map, fn (array $ids): bool => $ids !== []);
        if ($tersegment === []) {
            return $banners;
        }

        $milik = $customerId !== null ? $this->segmentIdsOfCustomer($customerId) : [];

        return $banners
            ->filter(function (Banner $banner) use ($map, $milik): bool {
                $target = $map[(int) $banner->getKey()] ?? [];

                if ($target === []) {
                    return true;
                }

                return array_intersect($target, $milik) !== [];
            })
            ->values();
    }

    /**
     * ID segmen yang diikuti pelanggan (hanya baca).
     *
     * @return list<int>
     */
    public function segmentIdsOfCustomer(int $customerId): array
    {
        try {
            return CustomerSegmentMember::query()
                ->where('customer_id', $customerId)
                ->pluck('customer_segment_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /* ------------------------------------------------------------------ */
    /* 4. "Ingatkan saya" flash sale                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Langganan pengingat: [deal_id => [user_id, ...]].
     *
     * @return array<int, list<int>>
     */
    public function flashSubscriptions(): array
    {
        try {
            $raw = SystemSetting::get(self::SETTING_FLASH_REMINDERS, '');
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        } catch (\Throwable) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $dealId => $userIds) {
            $daftar = array_values(array_unique(array_map('intval', is_array($userIds) ? $userIds : [$userIds])));
            $daftar = array_values(array_filter($daftar, fn (int $id): bool => $id > 0));
            if ($daftar !== []) {
                $out[(int) $dealId] = $daftar;
            }
        }

        return $out;
    }

    /**
     * @return array{subscribed: bool, reason: string}
     */
    public function subscribeFlashReminder(int $dealId, int $userId): array
    {
        $deal = FlashDeal::query()->find($dealId);
        if (! $deal instanceof FlashDeal) {
            return ['subscribed' => false, 'reason' => 'Flash deal tidak ditemukan.'];
        }

        if (! User::query()->whereKey($userId)->exists()) {
            return ['subscribed' => false, 'reason' => 'Pelanggan tidak ditemukan.'];
        }

        if ($deal->start_date !== null && ! $deal->start_date->isFuture()) {
            return ['subscribed' => false, 'reason' => 'Deal sudah dimulai — pengingat tidak diperlukan lagi.'];
        }

        $subs = $this->flashSubscriptions();
        $daftar = $subs[$dealId] ?? [];

        if (in_array($userId, $daftar, true)) {
            return ['subscribed' => true, 'reason' => 'Anda sudah terdaftar untuk pengingat deal ini.'];
        }

        if (count($daftar) >= 10000) {
            return ['subscribed' => false, 'reason' => 'Kuota pengingat deal ini penuh.'];
        }

        $daftar[] = $userId;
        $subs[$dealId] = $daftar;
        SystemSetting::set(self::SETTING_FLASH_REMINDERS, json_encode($subs, JSON_UNESCAPED_UNICODE));

        return ['subscribed' => true, 'reason' => 'Pengingat didaftarkan. Kami beri tahu saat deal dimulai.'];
    }

    /**
     * @return array{subscribed: bool, reason: string}
     */
    public function unsubscribeFlashReminder(int $dealId, int $userId): array
    {
        $subs = $this->flashSubscriptions();
        $daftar = array_values(array_filter(
            $subs[$dealId] ?? [],
            fn (int $id): bool => $id !== $userId,
        ));

        if ($daftar === []) {
            unset($subs[$dealId]);
        } else {
            $subs[$dealId] = $daftar;
        }

        SystemSetting::set(self::SETTING_FLASH_REMINDERS, json_encode($subs, JSON_UNESCAPED_UNICODE));

        return ['subscribed' => false, 'reason' => 'Pendaftaran pengingat dibatalkan.'];
    }

    /**
     * @return list<int>
     */
    public function subscribersForDeal(int $dealId): array
    {
        return $this->flashSubscriptions()[$dealId] ?? [];
    }

    /**
     * Kirim notifikasi mulai-deal ke seluruh pelanggan (sekali per user,
     * via dedupe_key), lalu bersihkan langganan deal tersebut.
     *
     * @return array{sent: int, skipped: int, reason: string}
     */
    public function notifyFlashDealStarted(FlashDeal $deal, ?int $actorId = null): array
    {
        if ($deal->start_date !== null && $deal->start_date->isFuture()) {
            return ['sent' => 0, 'skipped' => 0, 'reason' => 'Deal belum dimulai.'];
        }

        $dealId = (int) $deal->getKey();
        $subs = $this->flashSubscriptions();
        $daftar = $subs[$dealId] ?? [];

        $sent = 0;
        $skipped = 0;

        foreach ($daftar as $userId) {
            $ok = $this->notifyCustomer(
                $userId,
                'Flash sale dimulai: '.(string) $deal->title,
                'Deal yang Anda nantikan sudah tayang. Stok terbatas — segera checkout sebelum berakhir.',
                'flash:'.$dealId.':mulai:'.$userId,
                ['flash_deal_id' => $dealId],
                (string) ($deal->banner ?? ''),
            );

            if ($ok) {
                $sent++;
            } else {
                $skipped++;
            }
        }

        unset($subs[$dealId]);
        SystemSetting::set(self::SETTING_FLASH_REMINDERS, json_encode($subs, JSON_UNESCAPED_UNICODE));

        if ($sent > 0) {
            $this->audit('flash_deal.reminder_sent', $deal, [], [
                'sent' => $sent,
                'skipped' => $skipped,
            ], $actorId);
        }

        return [
            'sent' => $sent,
            'skipped' => $skipped,
            'reason' => $sent > 0
                ? $sent.' pelanggan diberi tahu bahwa deal dimulai.'
                : 'Tidak ada pelanggan baru yang perlu diberi tahu.',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Ringkasan + pembantu                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Ringkasan retensi untuk panel admin (dihitung ulang dari data nyata).
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        try {
            $pending = (int) AbandonedCart::query()->whereNull('recovered_at')->count();
            $jatuhTempo = (int) $this->dueCarts(500)->count();
            $nilai = (float) AbandonedCart::query()->whereNull('recovered_at')->sum('amount');
        } catch (\Throwable) {
            $pending = 0;
            $jatuhTempo = 0;
            $nilai = 0.0;
        }

        try {
            $voucherUltah = (int) Coupon::query()->where('code', 'like', 'ULTAH-%')->count();
        } catch (\Throwable) {
            $voucherUltah = 0;
        }

        $langgananFlash = array_sum(array_map('count', $this->flashSubscriptions()));
        $bannerTertarget = count(array_filter($this->bannerSegmentMap(), fn (array $ids): bool => $ids !== []));

        return [
            'abandoned_pending' => $pending,
            'abandoned_jatuh_tempo' => $jatuhTempo,
            'abandoned_nilai' => $nilai,
            'voucher_ultah' => $voucherUltah,
            'langganan_flash' => $langgananFlash,
            'banner_tertarget' => $bannerTertarget,
        ];
    }

    /**
     * Jejak audit best-effort: tidak boleh menggagalkan alur retensi
     * bila tabel audit belum tersedia (mis. konteks uji minimal).
     */
    private function audit(string $aksi, mixed $entitas, array $lama = [], array $baru = [], ?int $aktor = null): void
    {
        try {
            app(AuditLogger::class)->log($aksi, $entitas, $lama, $baru, $aktor);
        } catch (\Throwable) {
        }
    }

    /**
     * Tulis satu notifikasi marketing. Idempoten via dedupe_key.
     * Mengembalikan false bila pelanggan tak dikenal / sudah pernah dikirim.
     */
    private function notifyCustomer(
        ?int $customerId,
        string $title,
        string $body,
        string $dedupeKey,
        array $data = [],
        string $actionUrl = '',
    ): bool {
        if ($customerId === null || $customerId <= 0) {
            return false;
        }

        try {
            if (! User::query()->whereKey($customerId)->exists()) {
                return false;
            }

            if (UserNotification::query()->where('dedupe_key', $dedupeKey)->exists()) {
                return false;
            }

            UserNotification::query()->create([
                'uuid' => (string) Str::uuid(),
                'notifiable_type' => User::class,
                'notifiable_id' => $customerId,
                'channel' => 'database',
                'category' => 'marketing',
                'title' => $title,
                'body' => $body,
                'action_url' => $actionUrl !== '' ? $actionUrl : null,
                'action_label' => $actionUrl !== '' ? 'Lihat' : null,
                'data' => $data !== [] ? $data : null,
                'dedupe_key' => $dedupeKey,
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
