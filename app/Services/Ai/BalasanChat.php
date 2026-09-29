<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Provider;

/**
 * Saran balasan chat vendor: selalu 3 opsi Bahasa Indonesia.
 *
 * Bila provider AI tidak dikonfigurasi atau gagal, dipakai template
 * deterministik berdasarkan topik pesan terakhir + konteks pesanan terakhir,
 * sehingga UI chat tidak pernah menerima exception.
 *
 * @phpstan-type SaranBalasan array{options: list<string>, topik: string, source: string}
 */
final class BalasanChat
{
    private const TOPIK = [
        'pengiriman' => ['kirim', 'paket', 'resi', 'ekspedisi', 'sampai', 'datang', 'lacak', 'tracking', 'kurir', 'antar'],
        'stok' => ['stok', 'habis', 'tersedia', 'ready', 'restok', 'kosong'],
        'pembayaran' => ['bayar', 'pembayaran', 'transfer', 'lunas', 'bayarnya', 'qris', 'va', 'virtual', 'cod'],
        'komplain' => ['rusak', 'cacat', 'pecah', 'patah', 'komplain', 'kecewa', 'salah', 'tidak sesuai', 'retur', 'refund', 'kembali'],
        'harga' => ['harga', 'diskon', 'murah', 'nego', 'voucher', 'promo', 'ongkir'],
    ];

    /**
     * @param  array<string, mixed>  $konteks  Contoh: ['nama_pelanggan' => ..., 'nomor_pesanan' => ..., 'status_pesanan' => ...]
     * @return array{options: list<string>, topik: string, source: string}
     */
    public function suggest(string $pesanTerakhir, array $konteks = [], ?Provider $provider = null): array
    {
        $pesan = trim(mb_substr($pesanTerakhir, 0, 1000));
        $konteks = $this->normalisasiKonteks($konteks);

        try {
            if (DeskripsiProduk::providerReady($provider)) {
                $hasil = $this->viaAi($pesan, $konteks, $provider);

                if ($hasil !== null) {
                    return $hasil;
                }
            }
        } catch (\Throwable) {
            // Jatuh ke fallback lokal di bawah.
        }

        return [
            'options' => self::fallbackOptions($pesan, $konteks),
            'topik' => self::deteksiTopik($pesan),
            'source' => 'fallback',
        ];
    }

    /**
     * Tiga opsi deterministik: empatik+solusi, informatif+langkah, singkat+penutup.
     *
     * @param  array<string, string>  $konteks
     * @return list<string>
     */
    public static function fallbackOptions(string $pesanTerakhir, array $konteks = []): array
    {
        $sapaan = ($konteks['nama_pelanggan'] ?? '') !== '' ? 'Halo kak '.$konteks['nama_pelanggan'].', ' : 'Halo kak, ';
        $pesanan = ($konteks['nomor_pesanan'] ?? '') !== '' ? ' untuk pesanan '.$konteks['nomor_pesanan'] : '';
        $status = ($konteks['status_pesanan'] ?? '') !== '' ? ' Status saat ini: '.$konteks['status_pesanan'].'.' : '';
        $topik = self::deteksiTopik($pesanTerakhir);

        $solusi = match ($topik) {
            'pengiriman' => 'Pesanan kakak sudah kami cek dan sedang dalam proses pengiriman. Nomor resi akan kami informasikan segera setelah paket diserahkan ke ekspedisi.',
            'stok' => 'Untuk ketersediaan stok, kami cek langsung dari gudang dan kabari kakak maksimal 1x24 jam. Bila kosong, kami tawarkan varian pengganti yang setara.',
            'pembayaran' => 'Mohon kakak cek kembali status pembayaran pada halaman pesanan. Bila sudah bayar namun status belum berubah, kirimkan bukti bayar agar kami bantu verifikasi manual.',
            'komplain' => 'Mohon maaf atas ketidaknyamanannya. Silakan kirimkan foto/video kendalanya agar kami proses retur atau penggantian sesuai kebijakan toko.',
            'harga' => 'Harga yang tertera sudah termasuk penawaran terbaik saat ini. Kakak bisa manfaatkan voucher toko yang tersedia sebelum checkout agar lebih hemat.',
            default => 'Terima kasih atas pesannya. Kami sudah mencatat kebutuhan kakak dan akan segera menindaklanjutinya.',
        };

        return [
            $sapaan.'terima kasih sudah menghubungi kami'.$pesanan.'. '.$solusi.$status,
            'Baik kak, terkait "'.$solusi.'" Mohon informasikan nomor pesanan dan foto pendukung bila ada agar kami proses lebih cepat ya kak.',
            'Siap kak, kami tindak lanjuti segera. Jangan ragu menghubungi kami lagi bila ada hal lain yang bisa kami bantu. Terima kasih.',
        ];
    }

    public static function deteksiTopik(string $pesan): string
    {
        $normal = mb_strtolower($pesan);

        foreach (self::TOPIK as $topik => $kataKunci) {
            foreach ($kataKunci as $kata) {
                if ($kata !== '' && str_contains($normal, $kata)) {
                    return $topik;
                }
            }
        }

        return 'umum';
    }

    /**
     * @param  array<string, mixed>  $konteks
     * @return array<string, string>
     */
    private function normalisasiKonteks(array $konteks): array
    {
        $keluar = [];

        foreach (['nama_pelanggan', 'nomor_pesanan', 'status_pesanan', 'nama_produk'] as $kunci) {
            $nilai = $konteks[$kunci] ?? null;

            if (is_scalar($nilai) && trim((string) $nilai) !== '') {
                $keluar[$kunci] = mb_substr(trim((string) $nilai), 0, 120);
            }
        }

        return $keluar;
    }

    /**
     * @param  array<string, string>  $konteks
     * @return array{options: list<string>, topik: string, source: string}|null
     */
    private function viaAi(string $pesan, array $konteks, Provider $provider): ?array
    {
        $prompt = 'Buat TEPAT 3 opsi balasan penjual (Bahasa Indonesia, sopan, masing-masing 1-3 kalimat) untuk pesan pelanggan berikut: "'
            .$pesan.'" Konteks pesanan (JSON): '.json_encode($konteks, JSON_UNESCAPED_UNICODE)
            .'. Pisahkan ketiga opsi dengan baris berisi "---". Jangan menjanjikan hal di luar konteks.';

        $hasil = app(AiService::class)->chat(
            $provider,
            $prompt,
            AdminPrompts::systemPrompt('chat_assist'),
            null,
            ['temperature' => 0.6, 'max_tokens' => 700],
        );

        if (! (bool) ($hasil['success'] ?? false)) {
            return null;
        }

        $konten = trim((string) ($hasil['content'] ?? ''));

        if ($konten === '') {
            return null;
        }

        $opsi = array_values(array_filter(
            array_map(static fn (string $bagian): string => trim($bagian), preg_split('/^\s*---\s*$/mu', $konten) ?: []),
            static fn (string $bagian): bool => $bagian !== '',
        ));

        while (count($opsi) < 3) {
            $opsi[] = self::fallbackOptions($pesan, $konteks)[count($opsi) % 3];
        }

        return [
            'options' => array_values(array_slice($opsi, 0, 3)),
            'topik' => self::deteksiTopik($pesan),
            'source' => 'ai',
        ];
    }
}
