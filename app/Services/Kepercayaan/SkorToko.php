<?php

declare(strict_types=1);

namespace App\Services\Kepercayaan;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Skor kepercayaan toko publik + KYC bertahap vendor + helper galeri video & Q&A.
 *
 * Pure-computation first: semua metode inti menerima data polos sehingga mudah
 * diuji tanpa migrasi baru. Varian `*DariShop()` membungkus query DB dengan
 * try/catch agar blade tidak pernah fatal bila tabel pendukung belum ada.
 */
final class SkorToko
{
    public const KYC_LEVELS = ['email', 'identitas', 'rekening', 'verifikasi'];

    /** @return array<string, string> */
    public static function kycLabels(): array
    {
        return [
            'email' => 'Email terdaftar',
            'identitas' => 'Identitas terverifikasi',
            'rekening' => 'Rekening pencairan',
            'verifikasi' => 'Verifikasi admin',
        ];
    }

    /**
     * Hitung progres KYC dari satu baris vendor_applications (+ dokumen opsional).
     *
     * @param  object|array  $application  Baris vendor_applications (id, email, bank_*, status, ...).
     * @param  array<int, mixed>  $documents  Baris/dokumen (kolom `kind` bila ada).
     * @return array{level: string, level_index: int, percent: int, done: int, steps: list<array{key: string, label: string, done: bool}>}
     */
    public function kyc(object|array $application, array $documents = []): array
    {
        $app = is_array($application) ? (object) $application : $application;

        $emailOk = trim((string) ($app->email ?? '')) !== '';

        $kinds = [];
        foreach ($documents as $doc) {
            $kind = is_array($doc) ? ($doc['kind'] ?? null) : ($doc->kind ?? null);
            if (is_string($kind) && $kind !== '') {
                $kinds[] = Str::lower($kind);
            }
        }
        // Bila dokumen tidak disuntik, coba baca dari tabel (aman bila tabel hilang).
        if ($kinds === [] && isset($app->id) && is_numeric($app->id)) {
            try {
                $kinds = DB::table('vendor_application_documents')
                    ->where('vendor_application_id', (int) $app->id)
                    ->pluck('kind')->map(fn ($k) => Str::lower((string) $k))->all();
            } catch (\Throwable) {
                $kinds = [];
            }
        }

        $identitasOk = in_array('identity', $kinds, true)
            || in_array('selfie', $kinds, true)
            || in_array((string) ($app->status ?? ''), ['under_review', 'resubmitted', 'approved'], true);

        $rekeningOk = trim((string) ($app->bank_name ?? '')) !== ''
            && trim((string) ($app->bank_account_number ?? '')) !== ''
            && trim((string) ($app->bank_account_name ?? '')) !== '';

        $verifikasiOk = in_array((string) ($app->status ?? ''), ['approved'], true);

        $labels = self::kycLabels();
        $flags = ['email' => $emailOk, 'identitas' => $identitasOk, 'rekening' => $rekeningOk, 'verifikasi' => $verifikasiOk];

        $steps = [];
        foreach (self::KYC_LEVELS as $key) {
            $steps[] = ['key' => $key, 'label' => $labels[$key], 'done' => (bool) $flags[$key]];
        }

        // Level = langkah pertama yang belum selesai (sekuensial email → verifikasi).
        $level = 'verifikasi';
        $done = 0;
        foreach ($steps as $i => $step) {
            if ($step['done']) {
                $done++;
                continue;
            }
            $level = $step['key'];
            break;
        }
        if ($done === count($steps)) {
            $level = 'verifikasi';
        }

        return [
            'level' => $level,
            'level_index' => array_search($level, self::KYC_LEVELS, true) ?: 0,
            'percent' => (int) round(($done / max(1, count($steps))) * 100),
            'done' => $done,
            'steps' => $steps,
        ];
    }

    /**
     * Progres KYC untuk toko yang sudah aktif (dasbor/pengaturan vendor).
     *
     * @return array{level: string, level_index: int, percent: int, done: int, steps: list<array{key: string, label: string, done: bool}>}
     */
    public function kycDariShop(Shop $shop): array
    {
        $pseudo = (object) [
            'id' => null,
            'email' => $shop->email ?: ($shop->vendor?->email ?? ''),
            'status' => $shop->status === 'active' ? 'approved' : 'pending',
            'bank_name' => $shop->bank_name,
            'bank_account_number' => $shop->bank_account_number,
            'bank_account_name' => $shop->bank_account_name,
        ];

        $result = $this->kyc($pseudo, []);

        // Untuk toko aktif, "identitas" = profil toko terisi (logo/banner/deskripsi).
        $profilOk = trim((string) ($shop->logo ?? '')) !== ''
            || trim((string) ($shop->banner ?? '')) !== ''
            || trim((string) ($shop->description ?? '')) !== '';
        foreach ($result['steps'] as $i => $step) {
            if ($step['key'] === 'identitas') {
                $result['steps'][$i]['done'] = $profilOk;
                $result['steps'][$i]['label'] = 'Profil toko lengkap';
            }
        }
        $done = count(array_filter($result['steps'], fn (array $s): bool => $s['done']));
        $result['done'] = $done;
        $result['percent'] = (int) round(($done / max(1, count($result['steps']))) * 100);
        foreach ($result['steps'] as $step) {
            if (! $step['done']) {
                $result['level'] = $step['key'];
                $result['level_index'] = (int) array_search($step['key'], self::KYC_LEVELS, true);
                break;
            }
            $result['level'] = 'verifikasi';
            $result['level_index'] = 3;
        }

        return $result;
    }

    /**
     * Skor kepercayaan 0–100: rating 30% + fulfillment 30% + respons 25% + umur toko 15%.
     *
     * @param  array{rating?: float, fulfillment_rate?: float, response_rate?: float, umur_bulan?: float}  $input
     * @return array{skor: int, label: string, badge: string, sf_badge: string, rincian: array<string, float>}
     */
    public function skor(array $input): array
    {
        $rating = max(0.0, min(5.0, (float) ($input['rating'] ?? 0.0)));
        $fulfillment = max(0.0, min(100.0, (float) ($input['fulfillment_rate'] ?? 0.0)));
        $response = max(0.0, min(100.0, (float) ($input['response_rate'] ?? 0.0)));
        $umurBulan = max(0.0, (float) ($input['umur_bulan'] ?? 0.0));

        $pRating = ($rating / 5) * 30;
        $pFulfillment = ($fulfillment / 100) * 30;
        $pResponse = ($response / 100) * 25;
        $pUmur = min(1.0, $umurBulan / 12) * 15;

        $skor = (int) round(max(0, min(100, $pRating + $pFulfillment + $pResponse + $pUmur)));

        return [
            'skor' => $skor,
            'label' => $this->labelSkor($skor),
            'badge' => $this->badgeTabler($skor),
            'sf_badge' => $this->badgeStorefront($skor),
            'rincian' => [
                'rating' => round($pRating, 1),
                'fulfillment' => round($pFulfillment, 1),
                'respons' => round($pResponse, 1),
                'umur_toko' => round($pUmur, 1),
            ],
        ];
    }

    /** Skor dari model Shop (query aman, fallback 0 bila data tak tersedia). */
    public function skorDariShop(Shop $shop): array
    {
        $shopId = (int) $shop->getKey();
        $since = now()->subDays(30);

        $response = 100.0;
        try {
            $threads = (int) DB::table('conversations')->where('shop_id', $shopId)->where('created_at', '>=', $since)->count();
            $replied = (int) DB::table('conversations')->where('shop_id', $shopId)->where('created_at', '>=', $since)->whereNotNull('first_reply_at')->count();
            $response = $threads > 0 ? round(($replied / $threads) * 100, 1) : 100.0;
        } catch (\Throwable) {
            $response = 0.0;
        }

        $fulfillment = 100.0;
        try {
            $total = (int) DB::table('orders')->where('shop_id', $shopId)->where('created_at', '>=', $since)->count();
            $done = (int) DB::table('orders')->where('shop_id', $shopId)->where('created_at', '>=', $since)->where('fulfillment_status', 'fulfilled')->count();
            $fulfillment = $total > 0 ? round(($done / $total) * 100, 1) : 100.0;
        } catch (\Throwable) {
            $fulfillment = 0.0;
        }

        $umurBulan = 0.0;
        try {
            $created = $shop->created_at ?? null;
            if ($created !== null) {
                $umurBulan = max(0.0, $created->diffInMonths(now()));
            }
        } catch (\Throwable) {
            $umurBulan = 0.0;
        }

        return $this->skor([
            'rating' => (float) ($shop->rating_average ?? 0.0),
            'fulfillment_rate' => $fulfillment,
            'response_rate' => $response,
            'umur_bulan' => $umurBulan,
        ]);
    }

    public function labelSkor(int $skor): string
    {
        return match (true) {
            $skor >= 85 => 'Sangat Terpercaya',
            $skor >= 70 => 'Terpercaya',
            $skor >= 50 => 'Cukup',
            default => 'Toko Baru',
        };
    }

    public function badgeTabler(int $skor): string
    {
        return match (true) {
            $skor >= 85 => 'success',
            $skor >= 70 => 'info',
            $skor >= 50 => 'warning',
            default => 'secondary',
        };
    }

    public function badgeStorefront(int $skor): string
    {
        return match (true) {
            $skor >= 85 => 'sf-badge--success',
            $skor >= 70 => 'sf-badge--info',
            $skor >= 50 => 'sf-badge--warning',
            default => 'sf-badge--solid-dark',
        };
    }

    /**
     * Galeri video produk: dukung video_url tunggal, multi (koma/baris-baru/JSON),
     * path relatif storage, plus video SocialFeed milik produk (shorts).
     *
     * @return list<array{url: string, embed: string|null, kind: string, label: string}>
     */
    public static function videos(object $product): array
    {
        $raw = $product->video_url ?? null;
        $candidates = [];

        if (is_string($raw) && trim($raw) !== '') {
            $trimmed = trim($raw);
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (is_string($item)) {
                        $candidates[] = trim($item);
                    } elseif (is_array($item) && isset($item['url'])) {
                        $candidates[] = trim((string) $item['url']);
                    }
                }
            } else {
                foreach (preg_split('/[\r\n,;|]+/', $trimmed) ?: [] as $part) {
                    $part = trim((string) $part);
                    if ($part !== '') {
                        $candidates[] = $part;
                    }
                }
            }
        }

        // Shorts-style: video sosial milik produk yang sama (best-effort).
        try {
            if ($product instanceof Product && $product->getKey() !== null) {
                $feeds = DB::table('social_feeds')
                    ->where('product_id', $product->getKey())
                    ->where('is_active', true)
                    ->orderByDesc('id')->limit(6)->pluck('video_url')->all();
                foreach ($feeds as $feed) {
                    if (is_string($feed) && trim($feed) !== '') {
                        $candidates[] = trim($feed);
                    }
                }
            }
        } catch (\Throwable) {
            // Tabel opsional — abaikan.
        }

        $out = [];
        $seen = [];
        foreach ($candidates as $i => $url) {
            $norm = self::normalisasiUrlVideo($url);
            if ($norm === null || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $embed = self::embedYoutube($url);
            $out[] = [
                'url' => $norm,
                'embed' => $embed,
                'kind' => $embed !== null ? 'youtube' : 'file',
                'label' => 'Video '.count($out) + 1,
            ];
            if (count($out) >= 8) {
                break;
            }
        }

        return $out;
    }

    public static function normalisasiUrlVideo(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        try {
            return url('storage/'.ltrim($url, '/'));
        } catch (\Throwable) {
            return $url;
        }
    }

    public static function embedYoutube(string $url): ?string
    {
        $url = trim($url);
        $patterns = [
            '#(?:youtube\.com/(?:watch\?v=|shorts/|embed/|live/)|youtu\.be/)([\w-]{6,})#i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $m)) {
                return 'https://www.youtube.com/embed/'.$m[1];
            }
        }

        return null;
    }

    /**
     * Normalisasi daftar Q&A untuk voting + badge "Dijawab penjual".
     *
     * Mendukung item lama {q,a} sekaligus bentuk kaya
     * {question,answer,votes,answered,is_seller,created_at}.
     *
     * @param  mixed  $items
     * @return list<array{id: string, q: string, a: string|null, votes: int, answered: bool, seller: bool}>
     */
    public static function normalisasiQa(mixed $items): array
    {
        if ($items instanceof \Traversable) {
            $items = iterator_to_array($items);
        }
        if (! is_array($items)) {
            return [];
        }

        $out = [];
        foreach (array_values($items) as $i => $raw) {
            $item = is_array($raw) ? $raw : (array) $raw;
            $q = trim((string) ($item['q'] ?? $item['question'] ?? $item['name'] ?? ''));
            if ($q === '') {
                continue;
            }
            $a = $item['a'] ?? $item['answer'] ?? $item['text'] ?? null;
            $a = $a !== null && trim((string) $a) !== '' ? trim((string) $a) : null;
            $answered = (bool) ($item['answered'] ?? $item['is_answered'] ?? $item['answered_by_seller'] ?? $a !== null);
            $seller = (bool) ($item['seller'] ?? $item['is_seller'] ?? $item['by_seller'] ?? $answered);
            $out[] = [
                'id' => 'qa-'.($i + 1),
                'q' => $q,
                'a' => $a,
                'votes' => max(0, (int) ($item['votes'] ?? $item['helpful'] ?? 0)),
                'answered' => $answered,
                'seller' => $seller,
            ];
        }

        return $out;
    }
}
