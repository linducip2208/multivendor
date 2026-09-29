@props([
    'id' => null,
    'type' => 'line',
    'data' => [],
    'labels' => [],
    'height' => 260,
    'title' => null,
    'legend' => true,
    'stacked' => false,
    'filled' => false,
    'horizontal' => false,
    'tooltipSuffix' => '',
])

@php
    $chartId = $id ?: 'chart-'.substr(md5(json_encode([$type, $data, $labels])), 0, 10);
    $type = in_array($type, ['line', 'bar', 'doughnut', 'pie', 'radar'], true) ? $type : 'line';

    $series = [];
    foreach ((array) $data as $index => $set) {
        if (is_array($set) && array_is_list($set)) {
            $series[] = ['label' => 'Seri '.($index + 1), 'data' => array_values($set)];
            continue;
        }
        if (is_array($set)) {
            $label = (string) ($set['label'] ?? 'Seri '.($index + 1));
            $values = (array) ($set['data'] ?? []);
            $series[] = ['label' => $label, 'data' => array_values($values)];
            continue;
        }
        $series[] = ['label' => (string) $index, 'data' => array_values((array) $set)];
    }

    $chartLabels = array_values((array) $labels);
    $hasData = $chartLabels !== [];
    foreach ($series as $set) {
        if ($set['data'] !== []) {
            $hasData = true;
            break;
        }
    }
@endphp

<div {{ $attributes->merge(['class' => 'card admin-card admin-chart']) }}>
    @if ($title)
        <div class="card-header admin-card__header">
            <h3 class="card-title mb-0">{{ $title }}</h3>
        </div>
    @endif
    <div class="card-body">
        <div style="position: relative; height: {{ (int) $height }}px;">
            <canvas
                id="{{ $chartId }}"
                role="img"
                aria-label="{{ $title ?? 'Grafik' }}"
                data-chart
                data-chart-type="{{ $type }}"
                data-chart-labels="{{ $hasData ? base64_encode(json_encode($chartLabels)) : '' }}"
                data-chart-series="{{ $hasData ? base64_encode(json_encode($series)) : '' }}"
                data-chart-legend="{{ $legend ? '1' : '0' }}"
                data-chart-stacked="{{ $stacked ? '1' : '0' }}"
                data-chart-filled="{{ $filled ? '1' : '0' }}"
                data-chart-horizontal="{{ $horizontal ? '1' : '0' }}"
                data-chart-suffix="{{ $tooltipSuffix }}"
            ></canvas>
        </div>
        @unless ($hasData)
            <p class="text-secondary small text-center mb-0 mt-2">Belum ada data untuk ditampilkan.</p>
        @endunless
    </div>
</div>
