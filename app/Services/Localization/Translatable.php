<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Models\Redirect;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Helper konten multibahasa (copy BI/EN, Tabler-ready).
 *
 * Chain fallback: locale diminta -> base (id-ID=>id) -> id -> en.
 * Tabel induk tetap canonical; tabel *_translations hanya overlay.
 * Slug lokal + canonical + redirect slug lama memakai model Redirect
 * existing (from_path -> to_path, 301) — tidak membuat tabel redirect baru.
 */
trait HasTranslations
{
    public function translationFor(string $locale): ?Model
    {
        $chain = Translatable::fallbackChain($locale);

        foreach ($chain as $candidate) {
            $row = $this->translations()->where('locale', $candidate)->first();
            if ($row) {
                return $row;
            }
        }

        return null;
    }
}

class Translatable
{
    public const LOCALES = ['id', 'en'];

    public const FALLBACK = 'id';

    /** Chain: id-ID -> id -> en (umum: locale -> base -> id -> en). */
    public static function fallbackChain(string $locale): array
    {
        $locale = static::normalize($locale);
        $chain = [$locale];

        $base = strtolower(explode('-', str_replace('_', '-', $locale))[0]);
        if ($base !== '' && ! in_array($base, $chain, true)) {
            $chain[] = $base;
        }
        if (! in_array(static::FALLBACK, $chain, true)) {
            $chain[] = static::FALLBACK;
        }
        if (! in_array('en', $chain, true)) {
            $chain[] = 'en';
        }

        return array_values(array_unique($chain));
    }

    public static function normalize(string $locale): string
    {
        $locale = trim($locale) !== '' ? trim($locale) : static::FALLBACK;

        return \App\Models\Language::canonicalize($locale);
    }

    /** Locale penerima: users.locale / preferred_locale, fallback id. */
    public static function recipientLocale(mixed $user): string
    {
        try {
            $candidate = $user?->getAttribute('locale') ?? $user?->getAttribute('preferred_locale') ?? null;
            if (is_string($candidate) && trim($candidate) !== '') {
                $base = strtolower(explode('-', str_replace('_', '-', trim($candidate)))[0]);

                return $base === 'en' ? 'en' : 'id';
            }
        } catch (\Throwable) {
        }

        return static::FALLBACK;
    }

    /**
     * Overlay atribut terjemahan ke model tanpa mengubah query inti.
     * $fields: [attrModel => attrTranslation], mis. ['name'=>'name'].
     */
    public static function applyToModel(Model $model, string $locale, array $fields): void
    {
        try {
            // Jalur 1: relasi translations() bila model sudah memilikinya.
            if (method_exists($model, 'translations')) {
                $rows = $model->getRelation('translations');
                if ($rows === null) {
                    $rows = $model->translations()->get();
                    $model->setRelation('translations', $rows);
                }
                $byLocale = [];
                foreach ($rows as $row) {
                    $byLocale[(string) $row->getAttribute('locale')] = $row;
                }
                foreach (static::fallbackChain($locale) as $candidate) {
                    if (! isset($byLocale[$candidate])) {
                        continue;
                    }
                    $tr = $byLocale[$candidate];
                    foreach ($fields as $modelAttr => $trAttr) {
                        $value = $tr->getAttribute($trAttr);
                        if ($value !== null && $value !== '') {
                            $model->setAttribute($modelAttr, $value);
                        }
                    }

                    return;
                }

                return;
            }

            // Jalur 2 (tanpa ubah model induk): lookup DB langsung via peta tabel.
            $map = static::tableForModel($model);
            if ($map === null || $model->getKey() === null) {
                return;
            }
            if (! Schema::hasTable($map['table'])) {
                return;
            }
            foreach (static::fallbackChain($locale) as $candidate) {
                $row = DB::table($map['table'])
                    ->where($map['fk'], $model->getKey())
                    ->where('locale', $candidate)
                    ->first();
                if (! $row) {
                    continue;
                }
                foreach ($fields as $modelAttr => $trAttr) {
                    $value = $row->{$trAttr} ?? null;
                    if ($value !== null && $value !== '') {
                        $model->setAttribute($modelAttr, (string) $value);
                    }
                }

                return;
            }
        } catch (\Throwable) {
        }
    }

    /** Field overlay default per tipe konten. */
    public static function fieldsFor(string $type): array
    {
        return match ($type) {
            'product' => ['name' => 'name', 'slug' => 'slug', 'short_description' => 'short_description', 'description' => 'description', 'meta_title' => 'meta_title', 'meta_description' => 'meta_description'],
            'blog' => ['title' => 'title', 'slug' => 'slug', 'excerpt' => 'excerpt', 'content' => 'content', 'meta_title' => 'meta_title', 'meta_description' => 'meta_description'],
            default => ['name' => 'name', 'slug' => 'slug', 'description' => 'description', 'meta_title' => 'meta_title', 'meta_description' => 'meta_description'],
        };
    }

    /**
     * Cari model via slug lokal (terjemahan) lalu slug induk.
     * Mengembalikan [model|null, canonicalSlug|null].
     * Tidak mengubah query inti pemanggil selain lookup aditif ini.
     */
    public static function resolveBySlug(string $type, string $slug, string $locale): array
    {
        $map = [
            'product' => [\App\Models\Product::class, 'product_translations', 'product_id'],
            'category' => [\App\Models\Category::class, 'categories', null],
            'brand' => [\App\Models\Brand::class, 'brands', null],
        ];
        // Category/brand/shop memakai tabel induk + tabel terjemahan masing-masing.
        $translationTables = [
            'product' => ['table' => 'product_translations', 'fk' => 'product_id', 'model' => \App\Models\Product::class],
            'category' => ['table' => 'category_translations', 'fk' => 'category_id', 'model' => \App\Models\Category::class],
            'brand' => ['table' => 'brand_translations', 'fk' => 'brand_id', 'model' => \App\Models\Brand::class],
            'shop' => ['table' => 'shop_translations', 'fk' => 'shop_id', 'model' => \App\Models\Shop::class],
            'blog' => ['table' => 'blog_post_translations', 'fk' => 'blog_post_id', 'model' => \App\Models\BlogPost::class],
        ];

        try {
            if (! isset($translationTables[$type]) || ! Schema::hasTable($translationTables[$type]['table'])) {
                return [null, null];
            }
            $conf = $translationTables[$type];
            foreach (static::fallbackChain($locale) as $candidate) {
                $row = DB::table($conf['table'])->where('locale', $candidate)->where('slug', $slug)->first();
                if ($row) {
                    $model = ($conf['model'])::query()->whereKey($row->{$conf['fk']})->first();
                    if ($model) {
                        return [$model, (string) ($model->getAttribute('slug') ?? $slug)];
                    }
                }
            }
        } catch (\Throwable) {
        }

        return [null, null];
    }

    /** Status per-locale untuk page/blog: draft|published (default published). */
    public static function tableForModel(Model $model): ?array
    {
        return match (true) {
            $model instanceof \App\Models\Product => ['table' => 'product_translations', 'fk' => 'product_id'],
            $model instanceof \App\Models\Category => ['table' => 'category_translations', 'fk' => 'category_id'],
            $model instanceof \App\Models\Brand => ['table' => 'brand_translations', 'fk' => 'brand_id'],
            $model instanceof \App\Models\Shop => ['table' => 'shop_translations', 'fk' => 'shop_id'],
            $model instanceof \App\Models\BlogPost => ['table' => 'blog_post_translations', 'fk' => 'blog_post_id'],
            default => null,
        };
    }

    /**
     * Catat redirect slug lama -> slug canonical memakai Redirect existing.
     * Idempoten (updateOrCreate by from_path). Return true bila tercatat.
     */
    public static function rememberSlugRedirect(string $fromPath, string $toPath, int $status = 301): bool
    {
        try {
            if (! Schema::hasTable('redirects')) {
                return false;
            }
            $fromPath = '/'.ltrim(trim($fromPath), '/');
            $toPath = '/'.ltrim(trim($toPath), '/');
            if ($fromPath === '' || $toPath === '' || $fromPath === $toPath) {
                return false;
            }
            Redirect::query()->updateOrCreate(
                ['from_path' => $fromPath],
                ['to_path' => $toPath, 'status_code' => $status, 'is_active' => true]
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function contentStatus(string $subjectType, int|string $subject, string $locale): string
    {
        try {
            if (in_array($subjectType, ['blog'], true) && Schema::hasTable('blog_post_translations') && is_numeric($subject)) {
                $row = DB::table('blog_post_translations')
                    ->where('blog_post_id', (int) $subject)->where('locale', static::normalize($locale))->first();
                if ($row && in_array((string) ($row->status ?? ''), ['draft', 'published'], true)) {
                    return (string) $row->status;
                }
            }
            if (Schema::hasTable('content_translations')) {
                $q = DB::table('content_translations')->where('subject_type', $subjectType)->where('locale', static::normalize($locale));
                if (is_numeric($subject)) {
                    $q->where('subject_id', (int) $subject);
                } else {
                    $q->where('subject_key', (string) $subject);
                }
                $row = $q->first();
                if ($row && in_array((string) ($row->status ?? ''), ['draft', 'published'], true)) {
                    return (string) $row->status;
                }
            }
        } catch (\Throwable) {
        }

        return 'published';
    }

    /**
     * Konten halaman statis per locale: baca content_translations
     * (subject_type=page, subject_key=slug) dengan fallback chain.
     * Mengembalikan null bila tidak ada terjemahan (pemanggil pakai SystemSetting).
     *
     * @return array{title: ?string, body: ?string, status: string}|null
     */
    public static function pageContent(string $slug, string $locale): ?array
    {
        try {
            if (! Schema::hasTable('content_translations')) {
                return null;
            }
            foreach (static::fallbackChain($locale) as $candidate) {
                $row = DB::table('content_translations')
                    ->where('subject_type', 'page')
                    ->where('subject_key', $slug)
                    ->where('locale', $candidate)
                    ->first();
                if ($row) {
                    return [
                        'title' => ($row->title ?? null) !== '' ? (string) $row->title : null,
                        'body' => ($row->body ?? null) !== '' ? (string) $row->body : null,
                        'status' => in_array((string) ($row->status ?? ''), ['draft', 'published'], true) ? (string) $row->status : 'published',
                    ];
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * Laporan coverage konten per locale (aditif di atas TranslationRepository
     * existing untuk string UI). Mengembalikan total/terisi/persen per tipe.
     *
     * @return array{locale: string, per_type: array<string, array{total: int, translated: int, percent: float}>, total: int, translated: int, percent: float}
     */
    public static function contentCoverage(string $locale): array
    {
        $locale = static::normalize($locale);
        $perType = [];
        $tables = [
            'product' => ['table' => 'product_translations', 'fk' => 'product_id', 'parent' => 'products'],
            'category' => ['table' => 'category_translations', 'fk' => 'category_id', 'parent' => 'categories'],
            'brand' => ['table' => 'brand_translations', 'fk' => 'brand_id', 'parent' => 'brands'],
            'shop' => ['table' => 'shop_translations', 'fk' => 'shop_id', 'parent' => 'shops'],
            'blog' => ['table' => 'blog_post_translations', 'fk' => 'blog_post_id', 'parent' => 'blog_posts'],
        ];
        foreach ($tables as $type => $conf) {
            try {
                if (! Schema::hasTable($conf['table']) || ! Schema::hasTable($conf['parent'])) {
                    continue;
                }
                $total = (int) DB::table($conf['parent'])->count();
                $translated = (int) DB::table($conf['table'])->where('locale', $locale)->count();
                $perType[$type] = [
                    'total' => $total,
                    'translated' => min($translated, $total > 0 ? $total : $translated),
                    'percent' => $total > 0 ? round(min($translated, $total) / $total * 100, 2) : 100.0,
                ];
            } catch (\Throwable) {
            }
        }
        $total = array_sum(array_column($perType, 'total'));
        $translated = array_sum(array_column($perType, 'translated'));

        return [
            'locale' => $locale,
            'per_type' => $perType,
            'total' => $total,
            'translated' => $translated,
            'percent' => $total > 0 ? round($translated / $total * 100, 2) : 100.0,
        ];
    }

    /** Export JSON terjemahan konten satu locale. */
    public static function exportContentJson(string $locale): array
    {
        $locale = static::normalize($locale);
        $out = [];
        $tables = [
            'product' => ['table' => 'product_translations', 'fk' => 'product_id'],
            'category' => ['table' => 'category_translations', 'fk' => 'category_id'],
            'brand' => ['table' => 'brand_translations', 'fk' => 'brand_id'],
            'shop' => ['table' => 'shop_translations', 'fk' => 'shop_id'],
            'blog' => ['table' => 'blog_post_translations', 'fk' => 'blog_post_id'],
        ];
        foreach ($tables as $type => $conf) {
            try {
                if (! Schema::hasTable($conf['table'])) {
                    continue;
                }
                foreach (DB::table($conf['table'])->where('locale', $locale)->get() as $row) {
                    $out[$type.'.'.$row->{$conf['fk']}] = (array) $row;
                }
            } catch (\Throwable) {
            }
        }

        return $out;
    }

    /** Import JSON format exportContentJson(); mengembalikan jumlah baris. */
    public static function importContentJson(string $locale, array $data): int
    {
        $locale = static::normalize($locale);
        $count = 0;
        $tables = [
            'product' => ['table' => 'product_translations', 'fk' => 'product_id'],
            'category' => ['table' => 'category_translations', 'fk' => 'category_id'],
            'brand' => ['table' => 'brand_translations', 'fk' => 'brand_id'],
            'shop' => ['table' => 'shop_translations', 'fk' => 'shop_id'],
            'blog' => ['table' => 'blog_post_translations', 'fk' => 'blog_post_id'],
        ];
        foreach ($data as $key => $row) {
            try {
                [$type, $id] = explode('.', (string) $key, 2) + [null, null];
                if (! isset($tables[$type]) || ! is_numeric($id)) {
                    continue;
                }
                $conf = $tables[$type];
                if (! Schema::hasTable($conf['table'])) {
                    continue;
                }
                $payload = is_array($row) ? $row : [];
                unset($payload['id'], $payload[$conf['fk']], $payload['locale'], $payload['created_at']);
                $payload['updated_at'] = now();
                DB::table($conf['table'])->updateOrInsert(
                    [$conf['fk'] => (int) $id, 'locale' => $locale],
                    $payload + ['created_at' => now()]
                );
                $count++;
            } catch (\Throwable) {
            }
        }

        return $count;
    }
}
