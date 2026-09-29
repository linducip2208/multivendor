@extends('layouts.admin')

@section('title', 'Pengaturan Basis Data')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Sistem', ['label' => 'Database']]" />
@endsection

@section('content')
    <x-admin.page-header title="Pengaturan Database" subtitle="Ukuran tabel dan status koneksi.">
        <x-slot:actions>
            <form method="POST" action="{{ route('admin.system.db-optimize') }}">
                @csrf
                <button type="submit" class="btn btn-outline-warning btn-sm">Optimasi Tabel</button>
            </form>
        </x-slot:actions>
    </x-admin.page-header>

    @if ($driver !== 'mysql' && $driver !== 'mariadb')
        <x-admin.alert type="warning" :title="'Optimasi hanya tersedia untuk MySQL atau MariaDB. Driver saat ini: '.$driver" />
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Driver" :value="strtoupper($driver)" icon="database" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total Ukuran" :value="number_format($dbSize, 2, ',', '.').' MB'" icon="save" color="info" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Jumlah Tabel" :value="count($tables)" icon="layers" color="secondary" />
        </div>
    </div>

    <x-admin.card title="Tabel" icon="table" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Tabel</th>
                        <th scope="col" class="text-end">Baris</th>
                        <th scope="col" class="text-end">Data</th>
                        <th scope="col" class="text-end">Index</th>
                        <th scope="col" class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tables as $table)
                        <tr>
                            <td><code class="small">{{ $table['Name'] }}</code></td>
                            <td class="text-end">{{ number_format((int) $table['Rows'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format(((int) $table['Data_length']) / 1048576, 2, ',', '.') }} MB</td>
                            <td class="text-end">{{ number_format(((int) $table['Index_length']) / 1048576, 2, ',', '.') }} MB</td>
                            <td class="text-end fw-semibold">{{ number_format(((int) $table['total']) / 1048576, 2, ',', '.') }} MB</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.card>
@endsection
