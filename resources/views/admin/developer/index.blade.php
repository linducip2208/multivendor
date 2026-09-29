@extends('layouts.admin')

@section('title', 'API Platform')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Developers', ['label' => 'API']]" />
@endsection

@section('content')
    <x-admin.page-header title="Dokumentasi API" subtitle="Kontrak endpoint publik dan katalog event webhook.">
        <x-slot:actions>
            <a href="{{ route('admin.api-keys.index') }}" class="btn btn-outline-secondary btn-sm">API Keys</a>
            <a href="{{ route('admin.webhooks.index') }}" class="btn btn-outline-secondary btn-sm">Webhooks</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.alert type="warning" title="Kunci hanya disimpan sebagai hash" icon="lock">
        Nilai penuh kredensial API ditampilkan satu kali saat pembuatan. Setelah itu hanya hash SHA-256 yang tersimpan, sehingga isi database tidak dapat dipakai ulang untuk mengakses API.
    </x-admin.alert>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <x-admin.card title="Base URL" icon="globe">
                <dl class="row small mb-0">
                    <dt class="col-5 text-secondary">Endpoint</dt>
                    <dd class="col-7 text-end text-break"><code>{{ $documentation['base_url'] }}/api/{{ $documentation['version'] }}</code></dd>
                    <dt class="col-5 text-secondary">Versi</dt>
                    <dd class="col-7 text-end">{{ $documentation['version'] }}</dd>
                </dl>
            </x-admin.card>
        </div>
        <div class="col-md-4">
            <x-admin.card title="Autentikasi" icon="key" class="h-100">
                <p class="small mb-2">Kirim kunci pada header:</p>
                <pre class="small mb-0" style="white-space: pre-wrap;">Authorization: Bearer &lt;api-key&gt;</pre>
            </x-admin.card>
        </div>
        <div class="col-md-4">
            <x-admin.card title="Kunci Aktif" icon="key-square" class="h-100">
                <x-admin.stat label="Kunci tidak dicabut" :value="count($apiKeys)" icon="key" color="primary" />
            </x-admin.card>
        </div>
    </div>

    <x-admin.card class="mb-3" title="Endpoint" icon="server" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Metode</th>
                        <th scope="col">Path</th>
                        <th scope="col">Deskripsi</th>
                        <th scope="col">Scope</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($documentation['endpoints'] as $endpoint)
                        <tr>
                            <td>
                                <x-admin.badge
                                    :text="$endpoint['method']"
                                    :color="match($endpoint['method']) { 'GET' => 'info', 'POST' => 'success', 'PUT', 'PATCH' => 'warning', default => 'danger' }"
                                    pill
                                />
                            </td>
                            <td><code class="small">{{ $endpoint['path'] }}</code></td>
                            <td>{{ $endpoint['description'] }}</td>
                            <td class="small text-secondary">
                                {{ str_contains($endpoint['path'], 'orders') ? 'orders:read' : (str_contains($endpoint['path'], 'payments') ? 'payments:read' : (str_contains($endpoint['path'], 'customers') ? 'customers:read' : 'catalog:read')) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div class="row g-3">
        <div class="col-lg-6">
            <x-admin.card title="Scope" icon="key-square" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Scope</th>
                                <th scope="col">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($documentation['scopes'] as $scope => $label)
                                <tr>
                                    <td><code class="small">{{ $scope }}</code></td>
                                    <td>{{ $label }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
        <div class="col-lg-6">
            <x-admin.card title="Catatan" icon="info">
                <ul class="mb-0 small">
                    @foreach ($documentation['notes'] as $note)
                        <li class="mb-1">{{ $note }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('admin.events.index') }}" class="btn btn-outline-secondary btn-sm mt-3">Katalog Event</a>
            </x-admin.card>
        </div>
    </div>
@endsection
