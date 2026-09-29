<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Provider;

/**
 * Generator deskripsi produk + judul SEO.
 *
 * Selalu mengembalikan hasil: bila provider AI tidak dikonfigurasi (tanpa
 * kredensial) atau panggilan AI gagal, dipakai template Bahasa Indonesia
 * deterministik sehingga pemanggil tidak pernah menerima exception.
 *
 * @phpstan-type HasilDeskripsi array{judul_seo: string, deskripsi: string, meta_description: string, source: string}
 */
final class DeskripsiProduk
{
    public static function providerReady(?Provider $provider): bool
    {
        if ($provider === null) {
            return false;
        }

        try {
            return $provider->type === 'ai'
                && (bool) $provider->is_active
                && $provider->getApiKeyAttribute() !== null
                && trim((string) $provider->getApiKeyAttribute()) !== '';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $spesifikasi
     * @param  array<string, mixed>  $options
     * @return array{judul_seo: string, deskripsi: string, meta_description: string, source: string}
     */
    public function generate(string $nama, array $spesifikasi = [], ?Provider $provider = null, array $options = []): array
    {
        $namaBersih = trim(preg_replace('/\s+/u', ' ', $nama) ?? '');
        $namaBersih = mb_substr($namaBersih, 0, 160);

        if ($namaBersih === '') {
            $namaBersih = 'Produk';
        }

        $spesifikasi = $this->normalisasiSpesifikasi($spesifikasi);

        try {
            if (self::providerReady($provider)) {
                $hasil = $this->viaAi($namaBersih, $spesifikasi, $provider, $options);

                if ($hasil !== null) {
                    return $hasil;
                }
            }
        } catch (\Throwable) {
            // Jatuh ke fallback lokal di bawah.
        }

        return self::fallback($namaBersih, $spesifikasi);
    }

    /**
     * Template Bahasa Indonesia deterministik (dipakai bila AI mati).
     *
     * @param  array<string, string>  $spesifikasi
     * @return array{judul_seo: string, deskripsi: string, meta_description: string, source: string}
     */
    public static function fallback(string $nama, array $spesifikasi = []): array
    {
        $nama = trim($nama) !== '' ? trim($nama) : 'Produk';

        $fitur = array_values(array_filter([
            $spesifikasi['bahan'] ?? $spesifikasi['material'] ?? null,
            $spesifikasi['kategori'] ?? $spesifikasi['jenis'] ?? null,
            $spesifikasi['warna'] ?? null,
        ], static fn ($nilai): bool => is_string($nilai) && trim($nilai) !== ''));

        $judul = $nama.' Original Berkualitas'.($fitur !== [] ? ' — '.implode(', ', array_slice($fitur, 0, 2)) : '');
        $judulSeo = mb_substr(trim($judul), 0, 60);

        $baris = [];
        foreach (array_slice($spesifikasi, 0, 8, true) as $kunci => $nilai) {
            $label = trim((string) ucwords(str_replace(['_', '-'], ' ', (string) $kunci)));
            $nilaiBersih = trim((string) $nilai);

            if ($label !== '' && $nilaiBersih !== '') {
                $baris[] = '- '.$label.': '.$nilaiBersih;
            }
        }

        $paragraf = [$nama.' adalah pilihan tepat untuk kebutuhan harian Anda. Produk original dengan kualitas terjamin, cocok untuk pemakaian pribadi maupun hadiah.'];

        if ($baris !== []) {
            $paragraf[] = "Spesifikasi utama:\n".implode("\n", $baris);
        }

        $paragraf[] = 'Stok terbatas. Pesan sekarang dan nikmati pengiriman cepat serta garansi kepuasan dari toko kami.';

        $deskripsi = implode("\n\n", $paragraf);

        $meta = mb_substr('Beli '.$nama.' original berkualitas. '.($baris !== [] ? strip_tags(implode(', ', $baris)).'. ' : '').'Pengiriman cepat dan garansi kepuasan.', 0, 160);

        return [
            'judul_seo' => $judulSeo,
            'deskripsi' => $deskripsi,
            'meta_description' => $meta,
            'source' => 'fallback',
        ];
    }

    /**
     * @param  array<string, mixed>  $spesifikasi
     * @return array<string, string>
     */
    private function normalisasiSpesifikasi(array $spesifikasi): array
    {
        $keluar = [];

        foreach ($spesifikasi as $kunci => $nilai) {
            if (! is_scalar($nilai)) {
                continue;
            }

            $bersih = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $nilai) ?? '');

            if ($bersih !== '') {
                $keluar[mb_substr(trim((string) $kunci), 0, 60)] = mb_substr($bersih, 0, 300);
            }

            if (count($keluar) >= 12) {
                break;
            }
        }

        return $keluar;
    }

    /**
     * @param  array<string, string>  $spesifikasi
     * @param  array<string, mixed>  $options
     * @return array{judul_seo: string, deskripsi: string, meta_description: string, source: string}|null
     */
    private function viaAi(string $nama, array $spesifikasi, Provider $provider, array $options): ?array
    {
        $konteks = json_encode(['nama' => $nama, 'spesifikasi' => $spesifikasi], JSON_UNESCAPED_UNICODE);

        $prompt = 'Tulis deskripsi produk marketplace berbahasa Indonesia untuk produk berikut (JSON): '.$konteks."\n"
            .'Kembalikan persis tiga bagian dengan format:\nJUDUL: <judul SEO maksimal 60 karakter>\nDESKRIPSI: <2-3 paragraf singkat + daftar spesifikasi>\nMETA: <meta description maksimal 160 karakter>';

        $hasil = app(AiService::class)->chat(
            $provider,
            $prompt,
            AdminPrompts::systemPrompt('product_copy'),
            is_string($options['model'] ?? null) ? $options['model'] : null,
            ['temperature' => 0.5, 'max_tokens' => 900],
        );

        if (! (bool) ($hasil['success'] ?? false)) {
            return null;
        }

        $konten = trim((string) ($hasil['content'] ?? ''));

        if ($konten === '') {
            return null;
        }

        $judul = $this->ekstrakBagian($konten, 'JUDUL');
        $deskripsi = $this->ekstrakBagian($konten, 'DESKRIPSI');
        $meta = $this->ekstrakBagian($konten, 'META');

        $fallback = self::fallback($nama, $spesifikasi);

        return [
            'judul_seo' => mb_substr($judul !== '' ? $judul : $fallback['judul_seo'], 0, 70),
            'deskripsi' => $deskripsi !== '' ? $deskripsi : $konten,
            'meta_description' => mb_substr($meta !== '' ? $meta : $fallback['meta_description'], 0, 170),
            'source' => 'ai',
        ];
    }

    private function ekstrakBagian(string $konten, string $penanda): string
    {
        if (preg_match('/^'.$penanda.'\s*:\s*(.+?)(?=^(?:JUDUL|DESKRIPSI|META)\s*:|\z)/mui', $konten."\nMETA:", $cocok) === 1) {
            return trim($cocok[1]);
        }

        return '';
    }
}
