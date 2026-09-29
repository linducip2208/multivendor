@props([
    'striped' => false,
    'hover' => true,
    'responsive' => true,
    'empty' => 'Belum ada data',
    'table' => null,
    'caption' => null,
    'dense' => false,
    'id' => null,
])

@php
    $builder = $table instanceof \App\Support\TableBuilder
        ? $table
        : new \App\Support\TableBuilder();

    $columns = $builder->getColumns();
    $rows = $builder->getRows();
    $span = max(1, count($columns));
    $emptyMessage = $builder->getEmptyMessage() !== '' ? $builder->getEmptyMessage() : $empty;
@endphp

<div {{ $attributes->merge(['class' => $responsive ? 'table-responsive' : '']) }}>
    <table
        @if ($id) id="{{ $id }}" @endif
        class="table admin-table mb-0 {{ $striped ? 'table-striped' : '' }} {{ $hover ? 'table-hover' : '' }} {{ $dense ? 'table-sm' : '' }}"
    >
        @if ($caption)
            <caption class="caption-top text-secondary small">{{ $caption }}</caption>
        @endif
        <thead>
            <tr>
                @foreach ($columns as $key => $column)
                    <th
                        class="text-{{ $column['align'] }} {{ $column['class'] }}"
                        @if ($column['width']) style="width: {{ $column['width'] }}" @endif
                        scope="col"
                    >{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                @php $rowAttributes = $builder->attributesForRow($row, (int) $index); @endphp
                <tr @foreach ($rowAttributes as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach>
                    @foreach ($columns as $key => $column)
                        <td class="text-{{ $column['align'] }} {{ $column['class'] }}">{!! $builder->renderCell($row, (string) $key) !!}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $span }}" class="text-center py-5 text-secondary">
                        <x-admin.empty-state
                            icon="inbox"
                            title="Tidak ada data"
                            :text="$emptyMessage"
                        />
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if (isset($tfoot) && ! \Illuminate\Support\Str::of((string) $tfoot)->trim()->isEmpty())
            <tfoot>
                {{ $tfoot }}
            </tfoot>
        @endif
    </table>
</div>
