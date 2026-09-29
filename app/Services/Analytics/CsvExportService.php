<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Support\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streaming CSV export.
 *
 * Rows are read in chunks and flushed as they are produced, so exporting a
 * million orders uses the same memory as exporting a hundred. Values are
 * formatted here, not in the browser, so a spreadsheet never contains a raw
 * float such as `1.0E+6` for an IDR amount.
 */
final class CsvExportService
{
    public const CHUNK = 500;

    /**
     * @param  list<string>  $columns
     * @param  list<callable(Model, array<string, mixed>): string|null>  $mappers
     */
    public function stream(
        string $filename,
        array $columns,
        Builder $query,
        ?callable $mapper = null,
        array $mappers = [],
        int $chunk = self::CHUNK,
    ): StreamedResponse {
        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '-', $filename) ?: 'export';

        return response()->streamDownload(function () use ($columns, $query, $mapper, $mappers, $chunk): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $columns);

            $query->clone()->chunkById($chunk, function ($rows) use ($handle, $mapper, $mappers): bool {
                foreach ($rows as $row) {
                    $mapped = $mapper !== null ? $mapper($row) : [];
                    $line = [];

                    foreach ($mappers as $index => $callable) {
                        $line[] = $callable($row, $mapped);
                    }

                    fputcsv($handle, $line);
                }

                if (function_exists('ob_get_level') && ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                return true;
            }, column: $this->qualifiedKey($query));

            fclose($handle);
        }, $safeName.'-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * @param  list<string>  $columns
     * @param  list<callable(object, array<string, mixed>): string|null>  $mappers
     */
    public function streamRaw(
        string $filename,
        array $columns,
        \Illuminate\Support\Facades\Query\Builder $query,
        array $mappers,
        int $chunk = self::CHUNK,
    ): StreamedResponse {
        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '-', $filename) ?: 'export';

        return response()->streamDownload(function () use ($columns, $query, $mappers, $chunk): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $columns);

            $query->clone()->chunk($chunk, function ($rows) use ($handle, $mappers): void {
                foreach ($rows as $row) {
                    $line = [];
                    foreach ($mappers as $callable) {
                        $line[] = $callable($row);
                    }
                    fputcsv($handle, $line);
                }

                if (function_exists('ob_get_level') && ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            });

            fclose($handle);
        }, $safeName.'-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function money(mixed $value): string
    {
        return Currency::format((float) ($value ?? 0));
    }

    public function raw(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'ya' : 'tidak';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return (string) $value;
    }

    private function qualifiedKey(Builder $query): string
    {
        $model = $query->getModel();
        $table = $model->getTable();

        return $model->getKeyName() === 'id' && str_contains($query->toSql(), '.')
            ? $table.'.'.$model->getKeyName()
            : $model->getKeyName();
    }
}
