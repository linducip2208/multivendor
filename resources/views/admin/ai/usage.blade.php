@extends('layouts.admin')

@section('title', 'Pemakaian AI')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['AI', ['label' => 'Pemakaian']]" />
@endsection

@section('content')
    <x-admin.page-header title="Pemakaian AI" subtitle="Token dan biaya per panggilan, dicatat untuk setiap tugas." />

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total Panggilan" :value="number_format($totals['calls'], 0, ',', '.')" icon="sparkles" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total Token" :value="number_format($totals['tokens'], 0, ',', '.')" icon="bar-chart" color="info" :hint="number_format($totals['prompt_tokens'], 0, ',', '.').' prompt + '.number_format($totals['completion_tokens'], 0, ',', '.').' completion'" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Estimasi Biaya" :value="$totals['cost_formatted']" icon="cash" color="success" help="Dihitung dari tarif per 1k token di pengaturan." />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat
                label="Tingkat Keberhasilan"
                :value="number_format($totals['success_rate'], 1, ',', '.').'%'"
                icon="check"
                :color="$totals['success_rate'] >= 90 ? 'success' : 'warning'"
            />
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <x-admin.card title="Pemakaian per Tugas" icon="bar-chart">
                @forelse ($by_feature as $row)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small">
                            <span class="text-secondary">{{ $row['label'] }}</span>
                            <span class="fw-semibold">{{ number_format($row['calls'], 0, ',', '.') }}×</span>
                        </div>
                        <div class="progress" role="progressbar" aria-label="{{ $row['label'] }}" aria-valuenow="{{ $row['calls'] }}" aria-valuemin="0" aria-valuemax="{{ max(1, $totals['calls']) }}">
                            <div class="progress-bar" style="width: {{ min(100, $row['calls'] / max(1, $totals['calls']) * 100) }}%"></div>
                        </div>
                        <small class="text-secondary">{{ number_format($row['tokens'], 0, ',', '.') }} token</small>
                    </div>
                @empty
                    <x-admin.empty-state compact icon="bar-chart" title="Belum ada pemakaian" text="Riwayat panggilan akan tampil di sini." />
                @endforelse
            </x-admin.card>
        </div>

        <div class="col-lg-7">
            <x-admin.card title="Filter" icon="filter" class="mb-3">
                <x-admin.filters
                    :action="route('admin.ai.usage')"
                    :filters="[['name' => 'feature', 'label' => 'Tugas', 'type' => 'select', 'options' => array_merge(['' => 'Semua tugas'], array_column($by_feature, 'label', 'feature'))]]"
                />
            </x-admin.card>
        </div>
    </div>

    <x-admin.card title="Riwayat Panggilan" icon="activity" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Waktu</th>
                        <th scope="col">Tugas</th>
                        <th scope="col">Model</th>
                        <th scope="col">Pengguna</th>
                        <th scope="col" class="text-end">Token</th>
                        <th scope="col" class="text-end">Durasi</th>
                        <th scope="col" class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="text-nowrap">{{ $row['at'] }}</td>
                            <td>{{ $row['feature_label'] }}</td>
                            <td><code class="small">{{ $row['model'] }}</code></td>
                            <td>{{ $row['user'] }}</td>
                            <td class="text-end">{{ number_format($row['total_tokens'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($row['duration_ms'], 0, ',', '.') }} ms</td>
                            <td class="text-center">
                                <x-admin.badge :text="$row['success'] ? 'Berhasil' : 'Gagal'" :color="$row['success'] ? 'success' : 'danger'" pill />
                                @if ($row['error'] !== '')
                                    <small class="d-block text-secondary">{{ $row['error'] }}</small>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty-state icon="activity" title="Belum ada riwayat" text="Panggilan AI akan tercatat otomatis di sini." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div class="mt-3">
        <x-admin.pagination :paginator="\App\Support\AdminPaginator::fromArray($pagination, $pagination['total'], $pagination['per_page'], $pagination['current_page'])" size="sm" />
    </div>
@endsection
