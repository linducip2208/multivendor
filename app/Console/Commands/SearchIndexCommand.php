<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Search\Drivers\MeilisearchDriver;
use App\Search\Drivers\TypesenseDriver;
use App\Search\Exceptions\SearchUnavailable;
use App\Search\SpellCorrector;
use App\Search\SynonymRepository;
use App\Services\Search\SearchIndexer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SearchIndexCommand extends Command
{
    protected $signature = 'search:index
        {--rebuild : Delete the remote index and rebuild it from scratch}
        {--chunk= : Rows read per database round trip}
        {--batch= : Documents sent per upsert request}
        {--product= : Reindex a single product id}
        {--driver= : Override the configured search driver}
        {--stats : Print index statistics without writing}
        {--dry-run : Build the documents but never call the remote engine}';

    protected $description = 'Build and push the denormalised search document index';

    public function handle(SearchIndexer $indexer): int
    {
        $driver = strtolower((string) ($this->option('driver') ?: config('search.default', 'database')));

        if ($this->option('stats')) {
            return $this->stats($indexer);
        }

        $chunk = (int) ($this->option('chunk') ?: config('search.index.chunk', 500));
        $batch = max(1, (int) ($this->option('batch') ?: config('search.index.batch', 200)));
        $productId = $this->option('product');

        if ($productId !== null && $productId !== '') {
            $documents = $indexer->documentsForIds([(int) $productId]);

            if ($documents === []) {
                $this->components->error('Product not found or not indexable.');

                return self::FAILURE;
            }

            return $this->push($driver, $documents);
        }

        $rebuild = (bool) $this->option('rebuild');
        $updatedAfter = $rebuild
            ? null
            : now()->subSeconds((int) config('search.index.updated_after', 900))->startOfMinute();

        $total = $indexer->countIndexable($updatedAfter?->toDateTimeString());
        $this->components->info(sprintf('Indexing %d products with the [%s] driver (chunk %d, batch %d).', $total, $driver, $chunk, $batch));

        if ($this->option('dry-run')) {
            $seen = 0;
            $sample = null;

            foreach ($indexer->documents($chunk, $updatedAfter?->toDateTimeString()) as $document) {
                $seen++;
                $sample ??= $document;
            }

            $this->components->info(sprintf('Dry run complete: %d documents built.', $seen));
            $this->line(json_encode($this->redactSample($sample ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($rebuild && ! $this->option('dry-run')) {
            $this->resetIndex($driver);
        }

        $started = microtime(true);
        $buffer = [];
        $indexed = 0;
        $pushed = 0;

        $progress = $this->output->createProgressBar(max(1, $total));
        $progress->start();

        try {
            foreach ($indexer->documents($chunk, $updatedAfter?->toDateTimeString(), function (int $count) use ($progress): void {
                $progress->advance($count);
            }) as $document) {
                $buffer[] = $document;
                $indexed++;

                if (count($buffer) < $batch) {
                    continue;
                }

                $this->pushDocuments($driver, $buffer);
                $pushed += count($buffer);
                $buffer = [];
            }

            if ($buffer !== []) {
                $this->pushDocuments($driver, $buffer);
                $pushed += count($buffer);
            }
        } catch (SearchUnavailable $exception) {
            $progress->clear();
            $this->newLine(2);
            $this->components->error($exception->getMessage());
            $this->components->warn(sprintf('%d document(s) were built before the engine became unreachable.', $indexed));

            return self::FAILURE;
        } finally {
            $progress->finish();
        }

        $this->newLine(2);

        $this->components->info(sprintf(
            '%d documents indexed in %.2fs (%d pushed, %d batched).',
            $pushed,
            microtime(true) - $started,
            $pushed,
            $batch,
        ));

        Cache::forget((string) config('search.synonyms.cache_key', 'search:synonyms:v1'));
        Cache::forget('search:category_tree');
        Cache::forget('search:vocabulary:v1');
        SynonymRepository::flush();
        SpellCorrector::flush();

        return self::SUCCESS;
    }

    private function push(string $driver, array $documents): int
    {
        if ($this->option('dry-run')) {
            $this->components->info(sprintf('Dry run: %d document(s) ready.', count($documents)));

            return self::SUCCESS;
        }

        try {
            $this->pushDocuments($driver, $documents);
        } catch (SearchUnavailable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function pushDocuments(string $driver, array $documents): void
    {
        if ($documents === []) {
            return;
        }

        $driverInstance = $this->resolveDriver($driver);

        if ($driverInstance instanceof MeilisearchDriver) {
            $driverInstance->pushDocuments($documents);

            return;
        }

        if ($driverInstance instanceof TypesenseDriver) {
            $lines = [];
            foreach ($documents as $document) {
                $lines[] = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $driverInstance->importDocuments(implode("\n", $lines));

            return;
        }

        throw SearchUnavailable::misconfigured($driver, 'search.default');
    }

    private function resolveDriver(string $driver)
    {
        return match ($driver) {
            'meilisearch' => app(MeilisearchDriver::class),
            'typesense' => app(TypesenseDriver::class),
            default => null,
        };
    }

    private function resetIndex(string $driver): void
    {
        try {
            $instance = $this->resolveDriver($driver);

            if ($instance instanceof MeilisearchDriver) {
                $host = rtrim((string) config('search.meilisearch.host', ''), '/');
                $index = (string) config('search.meilisearch.index', 'products');
                $key = (string) config('search.meilisearch.key', '');

                \Illuminate\Support\Facades\Http::acceptJson()
                    ->timeout(5)
                    ->withHeaders($key === '' ? [] : ['Authorization' => 'Bearer '.$key])
                    ->delete($host.'/indexes/'.$index.'/documents');

                $this->components->info(sprintf('Cleared Meilisearch index [%s].', $index));
                $instance->settings();
            }

            if ($instance instanceof TypesenseDriver) {
                $this->components->info('Typesense collections are replaced by the next full import.');
            }
        } catch (Throwable $exception) {
            $this->components->warn('Index reset skipped: '.$exception->getMessage());
        }
    }

    private function stats(SearchIndexer $indexer): int
    {
        $indexable = $indexer->countIndexable();
        $incremental = $indexer->countIndexable(now()->subSeconds((int) config('search.index.updated_after', 900))->toDateTimeString());

        $this->table(['metric', 'value'], [
            ['indexable_products', $indexable],
            ['changed_recently', $incremental],
            ['configured_driver', (string) config('search.default', 'database')],
            ['meilisearch_host', (string) config('search.meilisearch.host', '')],
            ['typesense_host', (string) config('search.typesense.host', '')],
        ]);

        return self::SUCCESS;
    }

    private function redactSample(array $document): array
    {
        foreach (['description', 'short_description'] as $key) {
            if (isset($document[$key]) && is_string($document[$key])) {
                $document[$key] = mb_substr($document[$key], 0, 80).'…';
            }
        }

        return $document;
    }
}
