<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Provider;

/**
 * Ringkasan ulasan produk: pro/kontra + skor agregat.
 *
 * Bekerja murni dari teks ulasan yang sudah ada (tanpa API eksternal bila
 * provider tidak dikonfigurasi): ekstraksi aspek berbasis kata kunci Bahasa
 * Indonesia yang deterministik.
 *
 * @phpstan-type BarisUlasan array{rating: mixed, comment: mixed}
 * @phpstan-type Ringkasan array{total: int, rata_rata: float, distribusi: array<int, int>, pro: list<string>, kontra: list<string>, ringkasan: string, source: string}
 */
final class RingkasanUlasan
{
    private const PRO = [
        'Kualitas awet' => ['awet', 'tahan lama', 'kuat', 'kokoh', 'tahan'],
        'Respons/pengiriman cepat' => ['cepat', 'kilat', 'responsif', 'sat set'],
        'Kemasan rapi' => ['kemasan rapi', 'packing rapi', 'rapi', 'aman'],
        'Produk original' => ['original', 'ori', 'asli'],
        'Harga sepadan' => ['murah', 'worth', 'sesuai harga', 'sepadan', 'hemat'],
        'Kepuasan umum' => ['bagus', 'mantap', 'puas', 'rekomend', 'enak', 'nyaman', 'suka'],
        'Pelayanan ramah' => ['ramah', 'pelayanan baik', 'fast respon', 'fast respons'],
    ];

    private const KONTRA = [
        'Pengiriman lambat' => ['lama', 'lambat', 'telat', 'tak sampai', 'tidak sampai'],
        'Barang rusak/cacat' => ['rusak', 'cacat', 'pecah', 'patah', 'sobek'],
        'Tidak sesuai deskripsi' => ['tidak sesuai', 'tidak cocok', 'beda', 'palsu', 'kw', 'lain'],
        'Harga dirasa mahal' => ['mahal', 'kemahalan', 'overprice'],
        'Kemasan kurang baik' => ['kemasan jelek', 'penyek', 'bocor', 'packing jelek'],
        'Respons penjual lambat' => ['tidak dibalas', 'slow respon', 'slow respons', 'tidak respons'],
        'Kekecewaan umum' => ['kecewa', 'buruk', 'jelek', 'parah', 'zonk'],
    ];

    /**
     * @param  iterable<mixed>  $ulasan  Daftar array{rating, comment} atau model dengan atribut rating/comment.
     * @return array{total: int, rata_rata: float, distribusi: array<int, int>, pro: list<string>, kontra: list<string>, ringkasan: string, source: string}
     */
    public function summarize(iterable $ulasan, string $namaProduk = '', ?Provider $provider = null): array
    {
        $baris = self::normalisasi($ulasan);
        $nama = mb_substr(trim($namaProduk), 0, 120);

        try {
            if (DeskripsiProduk::providerReady($provider) && $baris !== []) {
                $hasil = $this->viaAi($baris, $nama, $provider);

                if ($hasil !== null) {
                    return $hasil;
                }
            }
        } catch (\Throwable) {
            // Jatuh ke fallback lokal di bawah.
        }

        $ringkasan = self::fallbackSummarize($baris, $nama);
        $ringkasan['source'] = 'fallback';

        return $ringkasan;
    }

    /**
     * @param  list<array{rating: float|null, comment: string}>  $baris
     * @return array{total: int, rata_rata: float, distribusi: array<int, int>, pro: list<string>, kontra: list<string>, ringkasan: string, source: string}
     */
    public static function fallbackSummarize(array $baris, string $namaProduk = ''): array
    {
        $distribusi = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $jumlahSkor = 0.0;
        $terhitung = 0;
        $hitungPro = [];
        $hitungKontra = [];

        foreach ($baris as $row) {
            $rating = $row['rating'];

            if (is_numeric($rating)) {
                $bintang = max(1, min(5, (int) round((float) $rating)));
                $distribusi[$bintang]++;
                $jumlahSkor += (float) $rating;
                $terhitung++;
            }

            $teks = mb_strtolower($row['comment']);

            if ($teks === '') {
                continue;
            }

            foreach (self::PRO as $aspek => $kataKunci) {
                foreach ($kataKunci as $kata) {
                    if (str_contains($teks, $kata)) {
                        $hitungPro[$aspek] = ($hitungPro[$aspek] ?? 0) + 1;
                        break;
                    }
                }
            }

            foreach (self::KONTRA as $aspek => $kataKunci) {
                foreach ($kataKunci as $kata) {
                    if (str_contains($teks, $kata)) {
                        $hitungKontra[$aspek] = ($hitungKontra[$aspek] ?? 0) + 1;
                        break;
                    }
                }
            }
        }

        arsort($hitungPro);
        arsort($hitungKontra);

        $pro = array_slice(array_keys($hitungPro), 0, 3);
        $kontra = array_slice(array_keys($hitungKontra), 0, 3);
        $rata = $terhitung > 0 ? round($jumlahSkor / $terhitung, 1) : 0.0;
        $total = count($baris);
        $subjek = $namaProduk !== '' ? ' untuk '.$namaProduk : '';

        if ($total === 0) {
            $kalimat = 'Belum ada ulasan'.$subjek.' yang dapat diringkas.';
        } else {
            $kalimat = 'Dari '.$total.' ulasan'.$subjek.', rata-rata skor '.$rata.'/5. ';
            $kalimat .= $pro !== [] ? 'Kelebihan utama: '.implode(', ', $pro).'. ' : 'Belum ada kelebihan yang menonjol dari teks ulasan. ';
            $kalimat .= $kontra !== [] ? 'Perlu diperbaiki: '.implode(', ', $kontra).'.' : 'Tidak ada keluhan berulang yang terdeteksi.';
        }

        return [
            'total' => $total,
            'rata_rata' => $rata,
            'distribusi' => $distribusi,
            'pro' => array_values($pro),
            'kontra' => array_values($kontra),
            'ringkasan' => $kalimat,
            'source' => 'fallback',
        ];
    }

    /**
     * @param  iterable<mixed>  $ulasan
     * @return list<array{rating: float|null, comment: string}>
     */
    public static function normalisasi(iterable $ulasan): array
    {
        $keluar = [];

        foreach ($ulasan as $item) {
            if (is_array($item)) {
                $rating = $item['rating'] ?? $item['bintang'] ?? null;
                $komentar = $item['comment'] ?? $item['komentar'] ?? $item['ulasan'] ?? '';
            } elseif (is_object($item)) {
                $rating = $item->rating ?? null;
                $komentar = $item->comment ?? $item->komentar ?? '';
            } else {
                continue;
            }

            $keluar[] = [
                'rating' => is_numeric($rating) ? (float) $rating : null,
                'comment' => mb_substr(trim((string) $komentar), 0, 2000),
            ];

            if (count($keluar) >= 200) {
                break;
            }
        }

        return $keluar;
    }

    /**
     * @param  list<array{rating: float|null, comment: string}>  $baris
     * @return array{total: int, rata_rata: float, distribusi: array<int, int>, pro: list<string>, kontra: list<string>, ringkasan: string, source: string}|null
     */
    private function viaAi(array $baris, string $namaProduk, Provider $provider): ?array
    {
        $sampel = array_slice(array_map(static fn (array $row): string => 'Rating '.$row['rating'].': '.$row['comment'], $baris), 0, 40);

        $prompt = 'Ringkas ulasan produk berikut'.($namaProduk !== '' ? ' untuk "'.$namaProduk.'"' : '')
            .' menjadi pro (maks 3), kontra (maks 3), dan satu paragraf ringkasan Bahasa Indonesia. Data: '.implode("\n", $sampel);

        $hasil = app(AiService::class)->chat(
            $provider,
            $prompt,
            AdminPrompts::systemPrompt('review_summary'),
            null,
            ['temperature' => 0.3, 'max_tokens' => 800],
        );

        if (! (bool) ($hasil['success'] ?? false) || trim((string) ($hasil['content'] ?? '')) === '') {
            return null;
        }

        $dasar = self::fallbackSummarize($baris, $namaProduk);
        $dasar['ringkasan'] = trim((string) $hasil['content']);
        $dasar['source'] = 'ai';

        return $dasar;
    }
}
