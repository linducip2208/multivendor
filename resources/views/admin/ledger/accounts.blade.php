@extends('layouts.admin')

@section('title', 'Saldo Akun')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Finance', ['label' => 'Buku Besar', 'href' => route('admin.ledger.index')], ['label' => 'Saldo Akun']]" />
@endsection

@section('content')
    <x-admin.page-header title="Saldo Akun" subtitle="Saldo berjalan tiap akun dari jurnal yang telah diposting.">
        <x-slot:actions>
            <a href="{{ route('admin.ledger.index') }}" class="btn btn-outline-secondary btn-sm">Buku Besar</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.alert
        :type="$totals['balanced'] ? 'success' : 'danger'"
        :title="$totals['balanced'] ? 'Seluruh jurnal seimbang' : 'Terdapat jurnal yang tidak seimbang'"
    >
        Total debit {{ $totals['debit_formatted'] }} berbanding kredit {{ $totals['credit_formatted'] }}.
    </x-admin.alert>

    <x-admin.card title="Bagan Akun" icon="book" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Kode</th>
                        <th scope="col">Nama Akun</th>
                        <th scope="col">Jenis</th>
                        <th scope="col">Normal</th>
                        <th scope="col" class="text-end">Debit</th>
                        <th scope="col" class="text-end">Kredit</th>
                        <th scope="col" class="text-end">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr>
                            <td><code class="small">{{ $account['code'] }}</code></td>
                            <td>{{ $account['name'] }}</td>
                            <td>{{ \Illuminate\Support\Str::headline($account['type']) }}</td>
                            <td>{{ $account['normal_balance'] === 'debit' ? 'Debit' : 'Kredit' }}</td>
                            <td class="text-end">{{ $account['debit_formatted'] }}</td>
                            <td class="text-end">{{ $account['credit_formatted'] }}</td>
                            <td class="text-end fw-semibold">{{ $account['balance_formatted'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty-state icon="book" title="Belum ada akun" text="Bagan akun sistem belum dibuat di database ini." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>
@endsection
