@props([
    'columns' => [],
    'sort' => null,
    'direction' => 'asc',
    'search' => null,
    'perPage' => null,
    'selectable' => false,
    'selectAction' => null,
    'striped' => true,
    'hover' => true,
    'searchPlaceholder' => 'Cari...',
    'empty' => 'Belum ada data',
])

@php
    $direction = strtolower((string) $direction) === 'desc' ? 'desc' : 'asc';
    $baseQuery = request()->query();
    $makeUrl = function (array $overrides) use ($baseQuery): string {
        return request()->fullUrlWithQuery(array_merge($baseQuery, $overrides, ['page' => null]));
    };
    $sortUrl = function (string $key) use ($sort, $direction, $makeUrl): string {
        return $makeUrl([
            'sort' => $key,
            'direction' => $sort === $key && $direction === 'asc' ? 'desc' : 'asc',
        ]);
    };
@endphp

<div {{ $attributes->merge(['class' => 'card admin-card admin-datatable']) }}>
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <form action="{{ request()->url() }}" method="GET" class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 26rem;">
            @foreach ($baseQuery as $key => $value)
                @if ($key !== 'search' && $key !== 'sort' && $key !== 'direction' && $key !== 'page' && ! is_array($value))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <div class="input-icon flex-grow-1">
                <x-admin.icon name="search" :size="18" class="input-icon-addon" />
                <input
                    type="search"
                    name="search"
                    class="form-control"
                    value="{{ $search }}"
                    placeholder="{{ $searchPlaceholder }}"
                    aria-label="{{ $searchPlaceholder }}"
                >
            </div>
            @if ($sort)
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
            @endif
            <button type="submit" class="btn btn-outline-secondary">
                <x-admin.icon name="search" :size="16" />
                <span class="d-none d-sm-inline">Cari</span>
            </button>
            @if ($search)
                <a href="{{ $makeUrl(['search' => null, 'page' => null]) }}" class="btn btn-ghost-light" aria-label="Bersihkan pencarian">
                    <x-admin.icon name="x" :size="16" />
                </a>
            @endif
        </form>

        <div class="d-flex align-items-center gap-2">
            @isset($actions)
                {{ $actions }}
            @endisset
            @if ($perPage)
                <form action="{{ request()->url() }}" method="GET" class="d-flex align-items-center gap-2">
                    @foreach ($baseQuery as $key => $value)
                        @if ($key !== 'per_page' && $key !== 'page' && ! is_array($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <label class="form-label small text-secondary mb-0" for="admin-datatable-per-page">Baris</label>
                    <select class="form-select form-select-sm w-auto" name="per_page" id="admin-datatable-per-page" onchange="this.form.submit()">
                        @foreach ((array) $perPage as $optionValue => $optionLabel)
                            @php
                                $isAssoc = is_string($optionValue);
                                $optionKey = $isAssoc ? $optionValue : ($optionLabel['value'] ?? $optionValue);
                                $optionText = $isAssoc ? $optionLabel : ($optionLabel['label'] ?? $optionLabel);
                            @endphp
                            <option value="{{ $optionKey }}" @selected((string) request('per_page', '') === (string) $optionKey)>{{ $optionText }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>
    </div>

    <div class="table-responsive">
        <table class="table admin-table table-vcenter card-table mb-0 {{ $striped ? 'table-striped' : '' }} {{ $hover ? 'table-hover' : '' }}">
            <thead>
                <tr>
                    @if ($selectable)
                        <th class="w-1">
                            <input class="form-check-input m-0 align-middle" type="checkbox" data-bulk-master aria-label="Pilih semua">
                        </th>
                    @endif
                    @foreach ((array) $columns as $key => $column)
                        @php
                            $definition = is_string($key)
                                ? (is_array($column) ? $column : ['label' => $column])
                                : $column;
                            $columnKey = is_string($key) ? $key : ($definition['key'] ?? $key);
                            $label = $definition['label'] ?? \Illuminate\Support\Str::headline((string) $columnKey);
                            $alignRaw = $definition['align'] ?? 'start';
                            $align = in_array($alignRaw, ['start', 'center', 'end'], true) ? $alignRaw : 'start';
                            $sortable = (bool) ($definition['sortable'] ?? false);
                            $isSorted = (string) $sort === (string) $columnKey;
                        @endphp
                        <th class="text-{{ $align }} {{ $definition['class'] ?? '' }}" scope="col">
                            @if ($sortable)
                                <a
                                    href="{{ $sortUrl((string) $columnKey) }}"
                                    class="text-reset text-decoration-none d-inline-flex align-items-center gap-1 {{ $isSorted ? 'fw-bold' : '' }}"
                                    aria-sort="{{ $isSorted ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}"
                                >
                                    {{ $label }}
                                    <x-admin.icon :name="$isSorted ? ($direction === 'asc' ? 'arrow-up' : 'arrow-down') : 'sort'" :size="14" class="text-secondary" />
                                </a>
                            @else
                                {{ $label }}
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @if (\Illuminate\Support\Str::of((string) $slot)->trim()->isEmpty())
                    <tr>
                        <td colspan="{{ max(1, count((array) $columns) + ($selectable ? 1 : 0)) }}" class="text-center py-5 text-secondary">
                            <x-admin.empty-state icon="inbox" title="Tidak ada data" :text="$empty" compact />
                        </td>
                    </tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>

    @isset($footer)
        <div class="card-footer bg-transparent border-top">
            {{ $footer }}
        </div>
    @endisset
</div>
