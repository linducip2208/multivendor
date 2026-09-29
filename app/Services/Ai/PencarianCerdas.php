<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Search\QueryParser;
use App\Search\SynonymRepository;
use App\Support\TextNormalizer;

/**
 * Pencarian cerdas: koreksi ejaan Bahasa Indonesia + ekspansi sinonim.
 *
 * Murni lokal (tidak butuh kredensial AI): menggabungkan koreksi ejaan
 * QueryParser dengan kamus sinonim + sinonim database, lalu mengembalikan
 * token yang diperluas agar pemanggil search bisa menaikkan recall tanpa
 * mengubah alur pencarian yang ada.
 *
 * @phpstan-type HasilCerdas array{original: string, corrected: string, dikoreksi: bool, koreksi: list<array{dari: string, ke: string}>, tokens: list<string>, expanded: list<string>, sinonim: array<string, list<string>>}
 */
final class PencarianCerdas
{
    /**
     * @return array{original: string, corrected: string, dikoreksi: bool, koreksi: list<array{dari: string, ke: string}>, tokens: list<string>, expanded: list<string>, sinonim: array<string, list<string>>}
     */
    public function enrich(string $query): array
    {
        $original = trim(preg_replace('/\s+/u', ' ', $query) ?? '');

        try {
            if ($original === '') {
                return $this->kosong($original);
            }

            $smart = QueryParser::parseSmart($original);
            $tokens = array_values(array_unique(array_merge(
                $smart['tokens'],
                TextNormalizer::tokenize($smart['corrected'] !== '' ? $smart['corrected'] : $original, false),
            )));

            if ($tokens === []) {
                $tokens = TextNormalizer::tokenize($smart['corrected']);
            }

            $sinonim = [];
            $expanded = $tokens;

            foreach ($tokens as $token) {
                $daftar = SynonymRepository::expandSmart($token);
                $sinonim[$token] = $daftar;

                foreach ($daftar as $padanan) {
                    if (! in_array($padanan, $expanded, true)) {
                        $expanded[] = $padanan;
                    }
                }
            }

            return [
                'original' => $original,
                'corrected' => $smart['corrected'],
                'dikoreksi' => $smart['corrected'] !== TextNormalizer::normalize($original),
                'koreksi' => $smart['koreksi'],
                'tokens' => array_values($tokens),
                'expanded' => array_values($expanded),
                'sinonim' => $sinonim,
            ];
        } catch (\Throwable) {
            return $this->kosong($original);
        }
    }

    /**
     * Saran kueri "maksud Anda" untuk ditampilkan di UI pencarian.
     *
     * @return list<string>
     */
    public function suggestions(string $query, int $limit = 3): array
    {
        try {
            $hasil = $this->enrich($query);
            $saran = [];

            if ($hasil['dikoreksi']) {
                $saran[] = $hasil['corrected'];
            }

            foreach ($hasil['expanded'] as $token) {
                if (! in_array($token, $hasil['tokens'], true)) {
                    $kandidat = trim($hasil['corrected'].' '.$token);

                    if ($kandidat !== '' && ! in_array($kandidat, $saran, true)) {
                        $saran[] = $kandidat;
                    }
                }

                if (count($saran) >= max(1, $limit)) {
                    break;
                }
            }

            return array_values(array_slice($saran, 0, max(1, $limit)));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{original: string, corrected: string, dikoreksi: bool, koreksi: list<array{dari: string, ke: string}>, tokens: list<string>, expanded: list<string>, sinonim: array<string, list<string>>}
     */
    private function kosong(string $original): array
    {
        return [
            'original' => $original,
            'corrected' => '',
            'dikoreksi' => false,
            'koreksi' => [],
            'tokens' => [],
            'expanded' => [],
            'sinonim' => [],
        ];
    }
}
