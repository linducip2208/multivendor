@extends('layouts.admin')

@section('title', 'Buku Besar')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Finance', ['label' => 'Buku Besar']]" />
@endsection

@section('content')
    <x-admin.page-header title="Buku Besar" subtitle="Jurnal debit dan kredit yang saling menyeimbangkan." />

    <x-admin.alert
        :type="$totals['balanced'] ? 'success' : 'danger'"
        :title="$totals['balanced'] ? 'Jurnal seimbang' : 'Jurnal tidak seimbang'"
    >
        Total debit {{ $totals['debit_formatted'] }} berbanding kredit {{ $totals['credit_formatted'] }},
        selisih {{ \App\Support\Currency::format($totals['difference']) }}. Setiap entri pada {@see \App\Services\Finance\LedgerService} menolak transaksi yang tidak balance.
    </x-admin.alert>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total Debit" :value="$totals['debit']" money icon="arrow-down" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total Kredit" :value="$totals['credit']" money icon="arrow-up" color="info" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Selisih" :value="$totals['difference']" money icon="calculator" :color="$totals['balanced'] ? 'success' : 'danger'" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Jumlah Entri" :value="number_format($pagination['total'], 0, ',', '.')" icon="receipt" color="secondary" />
        </div>
    </div>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.ledger.index')"
            :filters="[
                ['name' => 'account', 'label' => 'Akun', 'type' => 'select', 'options' => array_merge(['' => 'Semua akun'], array_column($accounts, 'name', 'code'))],
                ['name' => 'entry_type', 'label' => 'Jenis Entri', 'type' => 'select', 'options' => array_merge(['' => 'Semua jenis'], array_combine($entry_types, array_map('ucfirst', $entry_types)))],
                ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Memo atau grup transaksi'],
            ]"
        />
    </x-admin.card>

    <x-admin.card class="mb-3" title="Saldo per Akun" icon="book" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Kode</th>
                        <th scope="col">Nama Akun</th>
                        <th scope="col">Jenis</th>
                        <th scope="col" class="text-end">Debit</th>
                        <th scope="col" class="text-end">Kredit</th>
                        <th scope="col" class="text-end">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr @class(['table-active' => request()->query('account') === $account['code']])>
                            <td><code class="small">{{ $account['code'] }}</code></td>
                            <td>{{ $account['name'] }}</td>
                            <td>{{ \Illuminate\Support\Str::headline($account['type']) }}</td>
                            <td class="text-end">{{ $account['debit_formatted'] }}</td>
                            <td class="text-end">{{ $account['credit_formatted'] }}</td>
                            <td class="text-end fw-semibold">{{ $account['balance_formatted'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-admin.empty-state icon="book" title="Belum ada akun" text="Bagan akun sistem belum dibuat di database ini." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <x-admin.card title="Jurnal" icon="receipt" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Waktu</th>
                        <th scope="col">Grup</th>
                        <th scope="col">Akun</th>
                        <th scope="col">Jenis Entri</th>
                        <th scope="col" class="text-center">Arah</th>
                        <th scope="col" class="text-end">Nilai</th>
                        <th scope="col">Memo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $entry)
                        <tr>
                            <td class="text-nowrap">{{ $entry['posted_at'] }}</td>
                            <td><code class="small">{{ \Illuminate\Support\Str::limit($entry['group'], 12) }}</code></td>
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
                            <td class="text-end fw-semibold">{{ $entry['amount_formatted'] }}</td>
                            <td>{{ $entry['memo'] !== '' ? \Illuminate\Support\Str::limit($entry['memo'], 60) : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty-state icon="receipt" title="Belum ada entri" text="Tidak ada jurnal yang cocok dengan filter ini." />
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
