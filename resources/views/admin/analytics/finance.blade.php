@extends('layouts.admin')

@section('title', 'Analitik Keuangan')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Analitik', ['label' => 'Keuangan']]" />
@endsection

@section('content')
    <x-admin.page-header title="Keuangan" subtitle="Arus dana, komisi, pajak, dan buku besar.">
        <x-slot:actions>
            <a href="{{ route('admin.ledger.index', request()->query()) }}" class="btn btn-outline-secondary btn-sm">
                <x-admin.icon name="book" :size="14" /> Buku Besar
            </a>
            <a href="{{ route('admin.payments.reconciliation') }}" class="btn btn-primary btn-sm">
                <x-admin.icon name="refresh" :size="14" /> Rekonsiliasi
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card mb-3">
        <div class="card-body">
            @include('admin.partials.date-range', ['range' => $range])
        </div>
    </div>

    <x-admin.tabs :tabs="$tabs" class="mb-3" />

    <div class="row g-3 mb-3">
        @foreach ($report['kpis'] as $key => $kpi)
            <div class="col-6 col-md-4 col-xl-2">
                <x-admin.stat
                    :label="$kpi['label']"
                    :value="$kpi['value']"
                    :money="$kpi['money'] ?? false"
                    :icon="match($key) { 'gross' => 'cash', 'refunds' => 'undo', 'net' => 'trending-up', 'commission' => 'percent', 'vendor' => 'store', 'tax' => 'file-text', 'shipping' => 'truck', 'discount' => 'ticket', 'payouts' => 'download', default => 'wallet' }"
                    :color="match($key) { 'refunds' => 'danger', 'net' => 'success', 'payout_queue' => 'warning', default => 'primary' }"
                />
            </div>
        @endforeach
    </div>

    <x-admin.card title="Arus Dana" subtitle="Pembayaran masuk, refund keluar, dan komisi platform per hari." icon="cash" class="mb-3">
        <x-admin.chart
            id="finance-flow"
            type="line"
            :labels="$report['flow']['labels'] ?? []"
            :data="[
                ['label' => 'Masuk', 'data' => $report['flow']['inflow'] ?? []],
                ['label' => 'Refund', 'data' => $report['flow']['refund'] ?? []],
                ['label' => 'Komisi', 'data' => $report['flow']['commission'] ?? []],
            ]"
            :height="300"
        />
    </x-admin.card>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <x-admin.card title="Saldo Buku Besar" subtitle="Per akun, dari jurnal yang telah diposting." icon="book" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Akun</th>
                                <th scope="col">Jenis</th>
                                <th scope="col" class="text-end">Debit</th>
                                <th scope="col" class="text-end">Kredit</th>
                                <th scope="col" class="text-end">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['accounts'] as $account)
                                <tr>
                                    <td>
                                        <code class="small">{{ $account['code'] }}</code>
                                        <small class="d-block">{{ $account['name'] }}</small>
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::headline($account['type']) }}</td>
                                    <td class="text-end">{{ \App\Support\Currency::format($account['debit']) }}</td>
                                    <td class="text-end">{{ \App\Support\Currency::format($account['credit']) }}</td>
                                    <td class="text-end fw-semibold">{{ \App\Support\Currency::format($account['balance']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <x-admin.empty-state compact icon="book" title="Belum ada jurnal" text="Buku besar belum memiliki entri yang diposting." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-7">
            <x-admin.card title="Jurnal Terakhir" icon="receipt" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Akun</th>
                                <th scope="col">Jenis Entri</th>
                                <th scope="col" class="text-center">Arah</th>
                                <th scope="col" class="text-end">Nilai</th>
                                <th scope="col">Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['entries']['rows'] as $entry)
                                <tr>
                                    <td>
                                        <code class="small">{{ $entry['account_code'] }}</code>
                                        <small class="d-block">{{ $entry['account_name'] }}</small>
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::headline($entry['entry_type']) }}</td>
                                    <td class="text-center">
                                        <x-admin.badge
                                            :text="$entry['direction'] === 'debit' ? 'Debit' : 'Kredit'"
                                            :color="$entry['direction'] === 'debit' ? 'primary' : 'secondary'"
                                            pill
                                        />
                                    </td>
                                    <td class="text-end fw-semibold">{{ $entry['amount_formatted'] ?? \App\Support\Currency::format($entry['amount']) }}</td>
                                    <td class="text-nowrap">{{ $entry['posted_at'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <x-admin.empty-state compact icon="receipt" title="Belum ada entri" text="Tidak ada jurnal pada periode ini." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
    </div>

    <x-admin.pagination :paginator="\App\Support\AdminPaginator::fromArray($report['entries'], $report['entries']['total'], $report['entries']['per_page'], $report['entries']['current_page'])" size="sm" />
@endsection
