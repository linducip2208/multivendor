<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Meta media: tag/koleksi/alt massal + deteksi tak terpakai.
 *
 * ID: Meta disimpan di SystemSetting `cms_media_meta_json` (path => meta)
 * agar tanpa migrasi baru (jatah 1 migrasi dipakai form builder).
 * Deteksi tak terpakai: pindai referensi URL di page_blocks_*, banners,
 * blog featured_image, homepage_sections, popups — path yang tak
 * dirujuk siapa pun dilaporkan sebagai kandidat hapus (tidak dihapus
 * otomatis).
 *
 * EN: Media tags/collections/bulk-alt + unused detection via settings.
 */
class MediaMetaService
{
    /** @return array<string, array{tags: list<string>, collection: string, alt: string}> */
    public function all(): array
    {
        try {
            $raw = SystemSetting::get('cms_media_meta_json', '');
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param  list<string>  $tags */
    public function tag(string $path, array $tags, string $collection = '', string $alt = ''): array
    {
        $path = $this->cleanPath($path);
        $meta = $this->all();
        $meta[$path] = [
            'tags' => array_values(array_unique(array_filter(array_map(
                fn ($t): string => mb_substr(trim((string) $t), 0, 60),
                array_slice($tags, 0, 10)
            ), fn (string $t): bool => $t !== ''))),
            'collection' => mb_substr(trim($collection), 0, 80),
            'alt' => mb_substr(trim($alt), 0, 200),
        ];
        SystemSetting::set('cms_media_meta_json', json_encode($meta, JSON_UNESCAPED_UNICODE));

        return $meta[$path];
    }

    /** @param  array<string, string>  $alts path => alt */
    public function bulkAlt(array $alts): int
    {
        $meta = $this->all();
        $count = 0;
        foreach ($alts as $path => $alt) {
            $path = $this->cleanPath((string) $path);
            if ($path === '') {
                continue;
            }
            $row = $meta[$path] ?? ['tags' => [], 'collection' => '', 'alt' => ''];
            $row['alt'] = mb_substr(trim((string) $alt), 0, 200);
            $meta[$path] = $row;
            $count++;
        }
        SystemSetting::set('cms_media_meta_json', json_encode($meta, JSON_UNESCAPED_UNICODE));

        return $count;
    }

    /**
     * Daftar media + meta + status pakai.
     *
     * @return array{items: list<array<string, mixed>>, unused: list<string>, collections: list<string>}
     */
    public function inventory(): array
    {
        $meta = $this->all();
        $referenced = $this->referencedPaths();
        $items = [];
        $collections = [];

        try {
            $disk = Storage::disk('public');
            $files = $disk->exists('uploads') ? $disk->allFiles('uploads') : [];
        } catch (\Throwable) {
            $files = [];
        }

        foreach ($files as $file) {
            $row = $meta[$file] ?? ['tags' => [], 'collection' => '', 'alt' => ''];
            if (trim((string) ($row['collection'] ?? '')) !== '') {
                $collections[(string) $row['collection']] = true;
            }
            $items[] = [
                'path' => $file,
                'tags' => (array) ($row['tags'] ?? []),
                'collection' => (string) ($row['collection'] ?? ''),
                'alt' => (string) ($row['alt'] ?? ''),
                'alt_missing' => trim((string) ($row['alt'] ?? '')) === '',
                'used' => isset($referenced[$file]),
                'ref_count' => $referenced[$file] ?? 0,
            ];
        }

        usort($items, fn (array $a, array $b): int => [$a['used'] ? 0 : 1, $a['path']] <=> [$b['used'] ? 0 : 1, $b['path']]);

        return [
            'items' => $items,
            'unused' => array_values(array_map(
                fn (array $i): string => $i['path'],
                array_filter($items, fn (array $i): bool => ! $i['used'])
            )),
            'collections' => array_values(array_keys($collections)),
        ];
    }

    /** @return array<string, int> path => jumlah referensi */
    private function referencedPaths(): array
    {
        $haystacks = [];

        try {
            foreach (SystemSetting::query()->where('key', 'like', 'page\_blocks\_%')->pluck('value') as $v) {
                $haystacks[] = (string) $v;
            }
            foreach (['homepage_sections', 'cms_page_versions'] as $key) {
                $haystacks[] = (string) (SystemSetting::get($key, '') ?? '');
            }
        } catch (\Throwable) {
        }

        try {
            if (Schema::hasTable('banners')) {
                foreach (DB::table('banners')->pluck('image') as $v) {
                    $haystacks[] = (string) $v;
                }
            }
            if (Schema::hasTable('blog_posts')) {
                foreach (DB::table('blog_posts')->pluck('featured_image') as $v) {
                    $haystacks[] = (string) $v;
                }
            }
            if (Schema::hasTable('popups')) {
                foreach (DB::table('popups')->pluck('image') as $v) {
                    $haystacks[] = (string) $v;
                }
            }
        } catch (\Throwable) {
        }

        $joined = implode("\n", $haystacks);
        $counts = [];
        // Cocokkan pola uploads/... atau /img/uploads/... atau nama berkas saja.
        if (preg_match_all('~uploads/[A-Za-z0-9_\-./]{1,180}\.(?:jpg|jpeg|png|webp|gif|svg|pdf)~i', $joined, $m)) {
            foreach ($m[0] as $path) {
                $counts[$path] = ($counts[$path] ?? 0) + 1;
            }
        }

        return $counts;
    }

    private function cleanPath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        $path = preg_replace('~^img/~', '', $path) ?? $path;
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            return '';
        }

        return mb_substr($path, 0, 255);
    }
}
