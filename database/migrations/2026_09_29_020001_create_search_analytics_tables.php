<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Advanced search storage: query analytics, editable synonyms and the
 * nullable tenant discriminator carried by every indexed document.
 *
 * Guarded and additive so the migration is a no-op on a database that already
 * carries the structures. Index names are kept well under the 64 character
 * MySQL identifier limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('search_queries')) {
            Schema::create('search_queries', function (Blueprint $table) {
                $table->id();
                $table->string('query', 255)->default('');
                $table->string('normalized_query', 255)->default('')->index();
                $table->string('query_hash', 64)->index();
                $table->unsignedSmallInteger('token_count')->default(0);
                $table->unsignedInteger('result_count')->default(0);
                $table->unsignedInteger('results_shown')->default(0);
                $table->boolean('has_term')->default(false);
                $table->boolean('zero_result')->default(false)->index();
                $table->boolean('used_typo_tolerance')->default(false);
                $table->string('source', 20)->default('search')->index();
                $table->string('driver', 30)->default('database');
                $table->unsignedSmallInteger('took_ms')->default(0);
                $table->json('filters')->nullable();
                $table->string('ip_hash', 64)->nullable()->index();
                $table->string('visitor_hash', 64)->nullable()->index();
                $table->string('locale', 10)->nullable();
                $table->string('device', 20)->nullable();
                $table->timestamps();

                $table->index(['source', 'created_at'], 'search_q_source_created_idx');
                $table->index(['zero_result', 'created_at'], 'search_q_zero_created_idx');
                $table->index(['normalized_query', 'created_at'], 'search_q_norm_created_idx');
            });
        }

        if (! Schema::hasTable('search_synonyms')) {
            Schema::create('search_synonyms', function (Blueprint $table) {
                $table->id();
                $table->string('term', 120);
                $table->json('synonyms');
                $table->string('group_key', 120)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_system')->default(false);
                $table->unsignedTinyInteger('weight')->default(1);
                $table->timestamps();

                $table->unique('term');
                $table->index(['is_active', 'term'], 'search_syn_active_term_idx');
            });
        }

        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'tenant_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            });

            Schema::table('products', function (Blueprint $table) {
                $table->index('tenant_id', 'products_tenant_idx');
            });
        }

        $this->seedSynonyms();
    }

    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'tenant_id')) {
            try {
                Schema::table('products', function (Blueprint $table) {
                    $table->dropIndex('products_tenant_idx');
                });
            } catch (Throwable) {
            }

            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }

        Schema::dropIfExists('search_synonyms');
        Schema::dropIfExists('search_queries');
    }

    private function seedSynonyms(): void
    {
        if (! Schema::hasTable('search_synonyms')) {
            return;
        }

        $equivalents = $this->equivalents();
        if ($equivalents === []) {
            return;
        }

        $groups = $this->groupEquivalents($equivalents);
        $now = now();
        $rows = [];
        $seen = DB::table('search_synonyms')->pluck('term')->all();
        $seen = array_fill_keys(array_map(static fn ($v) => mb_strtolower((string) $v), $seen), true);

        foreach ($groups as $key => $group) {
            $term = \App\Support\TextNormalizer::normalize($group['primary']);
            if ($term === '' || isset($seen[$term])) {
                continue;
            }

            $rows[] = [
                'term' => mb_substr($term, 0, 120),
                'synonyms' => json_encode(array_values($group['others']), JSON_UNESCAPED_UNICODE),
                'group_key' => mb_substr($group['key'], 0, 120),
                'is_active' => true,
                'is_system' => true,
                'weight' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('search_synonyms')->insert($chunk);
        }
    }

    private function equivalents(): array
    {
        try {
            $reflection = new ReflectionClass(\App\Support\TextNormalizer::class);
            $value = $reflection->getConstant('EQUIVALENTS');

            return is_array($value) ? $value : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function groupEquivalents(array $equivalents): array
    {
        $parent = [];

        $find = static function (string $item) use (&$parent, &$find): string {
            if (! isset($parent[$item])) {
                $parent[$item] = $item;
            }
            if ($parent[$item] === $item) {
                return $item;
            }

            return $parent[$item] = $find($parent[$item]);
        };

        $union = static function (string $a, string $b) use (&$parent, $find): void {
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[$rb] = $ra;
            }
        };

        foreach ($equivalents as $from => $to) {
            $union((string) $from, (string) $to);
        }

        $buckets = [];
        foreach (array_keys($equivalents) as $item) {
            $buckets[$find((string) $item)][] = (string) $item;
        }

        $groups = [];
        foreach ($buckets as $key => $members) {
            $tokens = [];
            foreach ($members as $member) {
                foreach (preg_split('/\s+/u', \App\Support\TextNormalizer::normalize($member)) ?: [] as $token) {
                    if ($token !== '') {
                        $tokens[$token] = true;
                    }
                }
            }

            $tokens = array_keys($tokens);
            if ($tokens === []) {
                continue;
            }

            $groupKey = 'grp_'.substr(md5(implode('|', $tokens)), 0, 12);
            $groups[$groupKey] = [
                'key' => $groupKey,
                'primary' => $tokens[0],
                'others' => array_values(array_diff($tokens, [$tokens[0]])),
            ];
        }

        return $groups;
    }
};
