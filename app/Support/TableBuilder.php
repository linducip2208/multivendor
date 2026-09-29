<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Declarative table description for the backoffice Blade layer.
 *
 * Views describe *what* the table contains; the component decides how it is
 * rendered. Nothing here touches the database: callers pass an already
 * resolved collection plus a resolver closure, which keeps the markup free of
 * business logic.
 */
final class TableBuilder
{
    /** @var array<string, array{label: string, align: string, class: string, sortable: bool, width: string|null}> */
    private array $columns = [];

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    private string $empty = '';

    /** @var null|callable(mixed, int, array<string, mixed>): string */
    private $rowClass = null;

    /** @var null|callable(mixed, int, array<string, mixed>): array<string, string> */
    private $rowAttributes = null;

    private string $rowIdKey = 'id';

    /**
     * Accepts ['a', 'b'], ['a' => 'Label'] or a keyed definition array.
     *
     * @param  array<array-key, mixed>|string  $columns
     */
    public function columns(array|string $columns): static
    {
        $this->columns = [];

        foreach (is_string($columns) ? func_get_args() : $columns as $key => $definition) {
            if (is_int($key)) {
                $key = (string) $definition;
                $definition = [];
            }

            $this->column((string) $key, $definition);
        }

        return $this;
    }

    /**
     * @param  string|array<string, mixed>  $definition
     */
    public function column(string $key, string|array $definition = []): static
    {
        if (is_string($definition)) {
            $definition = ['label' => $definition];
        }

        $align = (string) ($definition['align'] ?? 'start');

        $this->columns[$key] = [
            'label' => (string) ($definition['label'] ?? \Illuminate\Support\Str::headline($key)),
            'align' => in_array($align, ['start', 'center', 'end'], true) ? $align : 'start',
            'class' => (string) ($definition['class'] ?? ''),
            'sortable' => (bool) ($definition['sortable'] ?? false),
            'width' => isset($definition['width']) ? (string) $definition['width'] : null,
        ];

        return $this;
    }

    /**
     * @param  iterable<mixed>  $records
     * @param  null|callable(mixed, int): array<string, mixed>|list<mixed>  $resolver
     */
    public function rows(iterable $records, ?callable $resolver = null): static
    {
        $this->rows = [];

        foreach ($records as $index => $record) {
            $this->addRow($resolver !== null ? $resolver($record, (int) $index) : (array) $record);
        }

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $cells
     */
    public function addRow(array $cells): static
    {
        $this->rows[] = $cells;

        return $this;
    }

    /**
     * @param  null|callable(mixed, int, array<string, mixed>): string  $callback
     */
    public function rowClass(?callable $callback): static
    {
        $this->rowClass = $callback;

        return $this;
    }

    /**
     * @param  null|callable(mixed, int, array<string, mixed>): array<string, string>  $callback
     */
    public function rowAttributes(?callable $callback): static
    {
        $this->rowAttributes = $callback;

        return $this;
    }

    public function rowIdKey(string $key): static
    {
        $this->rowIdKey = $key;

        return $this;
    }

    public function empty(string $message): static
    {
        $this->empty = $message;

        return $this;
    }

    /**
     * Marks the supplied HTML as already escaped.
     */
    public function raw(string $html): HtmlString
    {
        return new HtmlString($html);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->columns);
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /**
     * @return array<string, array{label: string, align: string, class: string, sortable: bool, width: string|null}>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return list<string>
     */
    public function getColumnKeys(): array
    {
        return array_keys($this->columns);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    public function getEmptyMessage(): string
    {
        return $this->empty;
    }

    public function countColumns(): int
    {
        return count($this->columns);
    }

    public function countRows(): int
    {
        return count($this->rows);
    }

    /**
     * Raw cell lookup used by the rendering component.
     *
     * @param  array<string, mixed>  $row
     */
    public function cell(array $row, string $key): mixed
    {
        if (array_key_exists($key, $row)) {
            return $row[$key];
        }

        $index = array_search($key, array_keys($this->columns), true);

        if ($index !== false && array_key_exists($index, $row)) {
            return $row[$index];
        }

        return null;
    }

    /**
     * Escapes anything that is not already markup so a table cell can never
     * inject HTML by accident.
     *
     * @param  array<string, mixed>  $row
     */
    public function renderCell(array $row, string $key): Htmlable
    {
        return self::toHtmlable($this->cell($row, $key));
    }

    public static function toHtmlable(mixed $value): Htmlable
    {
        if ($value instanceof Htmlable) {
            return $value;
        }

        if ($value === null || $value === false) {
            return new HtmlString('');
        }

        if ($value === true) {
            return new HtmlString('&#10003;');
        }

        if (is_array($value)) {
            if (array_key_exists('raw', $value)) {
                return new HtmlString((string) $value['raw']);
            }

            $text = $value['text'] ?? $value['label'] ?? $value['value'] ?? null;

            if (is_array($value) && isset($value['url']) && $text !== null) {
                return new HtmlString('<a href="'.e((string) $value['url']).'">'.e((string) $text).'</a>');
            }

            return new HtmlString(e((string) ($text ?? '')));
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return new HtmlString(e((string) $value));
        }

        return new HtmlString(e((string) $value));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    public function attributesForRow(array $row, int $index): array
    {
        $attributes = $this->rowAttributes !== null
            ? (array) ($this->rowAttributes)($row, $index, $row)
            : [];

        if ($this->rowClass !== null) {
            $class = trim((string) ($this->rowClass)($row, $index, $row));
            if ($class !== '') {
                $attributes['class'] = trim(($attributes['class'] ?? '').' '.$class);
            }
        }

        if (! isset($attributes['data-row-id']) && isset($row[$this->rowIdKey]) && is_scalar($row[$this->rowIdKey])) {
            $attributes['data-row-id'] = (string) $row[$this->rowIdKey];
        }

        return $attributes;
    }
}
