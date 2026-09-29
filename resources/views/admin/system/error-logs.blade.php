@extends('layouts.admin')

@section('title', 'Error Logs')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Sistem', ['label' => 'Error Logs']]" />
@endsection

@section('content')
    <x-admin.page-header title="Error Logs" subtitle="500 baris terakhir dari storage/logs/laravel.log.">
        <x-slot:actions>
            <a href="{{ route('admin.system.db-settings') }}" class="btn btn-outline-secondary btn-sm">Pengaturan DB</a>
            <a href="{{ route('admin.system.env-settings') }}" class="btn btn-outline-secondary btn-sm">.env</a>
            <a href="{{ route('admin.system.software-update') }}" class="btn btn-outline-secondary btn-sm">Perbarui</a>
            <form method="POST" action="{{ route('admin.system.error-logs-clear') }}">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm">Clear Logs</button>
            </form>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.card title="Isi Log" icon="file-code">
        <x-admin.alert type="warning" :dismissible="false" title="Log dapat berisi data sensitif" icon="alert-triangle">
            Isi log ditampilkan apa adanya. Tinjau sebelum membagikan.
        </x-admin.alert>
        <pre style="max-height: 620px; overflow-y: auto; font-size: 12px; font-family: monospace; line-height: 1.6; white-space: pre-wrap; word-wrap: break-word; margin: 0;">@forelse ($logs as $log){{ $log }}

@empty<div class="text-center py-5 text-secondary">Tidak ada error log.</div>
@endforelse</pre>
    </x-admin.card>
@endsection
