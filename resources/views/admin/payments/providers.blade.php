@extends('layouts.admin')

@section('title', 'Provider Pembayaran / Payment Providers')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Finance', ['label' => 'Providers']]" />
@endsection

@section('content')
    <x-admin.page-header title="Provider Pembayaran" subtitle="Payment providers — ID + internasional via manifest plugin. / Daftar semua provider beserta kapabilitas dan health." />

    <x-admin.alert type="info" :dismissible="false" title="Tanpa kredensial live / No live credentials" icon="lock">
        Health check hanya memakai cek lokal (kelas gateway, <code>isEnabled()</code>, baris provider aktif).
        Status <code>unknown</code> berarti belum dikonfigurasi — bukan error.
        / Health uses local checks only. <code>unknown</code> means not configured yet.
    </x-admin.alert>

    <x-admin.card title="Daftar Provider / Provider List" icon="server" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Provider</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col">Country</th>
                        <th scope="col">Currency</th>
                        <th scope="col">Methods</th>
                        <th scope="col" class="text-end">Priority</th>
                        <th scope="col" class="text-center">Test-mode</th>
                        <th scope="col" class="text-center">Health</th>
                        <th scope="col" class="text-end">Aksi / Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($providers as $provider)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $provider['name'] }}</span>
                                <small class="d-block text-secondary"><code>{{ $provider['code'] }}</code> · v{{ $provider['version'] }}</small>
                            </td>
                            <td class="text-center">
                                @if ($provider['plugin_enabled'])
                                    <x-admin.badge text="ENABLED" color="success" pill />
                                @else
                                    <x-admin.badge text="DISABLED" color="secondary" pill />
                                @endif
                            </td>
                            <td class="small">{{ implode(', ', $provider['countries']) }}</td>
                            <td class="small">{{ implode(', ', $provider['currencies']) }}</td>
                            <td class="small">{{ implode(', ', $provider['methods']) }}</td>
                            <td class="text-end">{{ $provider['priority'] }}</td>
                            <td class="text-center">
                                <x-admin.badge :text="$provider['test_mode_supported'] ? 'YA / YES' : 'TIDAK / NO'" :color="$provider['test_mode_supported'] ? 'info' : 'secondary'" pill />
                            </td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="strtoupper($provider['health'])"
                                    :color="match($provider['health']) { 'configured' => 'success', 'disabled' => 'secondary', 'error' => 'danger', default => 'warning' }"
                                    pill
                                />
                                <small class="d-block text-secondary mt-1">{{ $provider['health_id'] }}</small>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.providers.index', ['type' => 'payment']) }}" class="btn btn-outline-secondary btn-sm">Configure</a>
                                <a href="{{ route('admin.system.health') }}" class="btn btn-outline-info btn-sm">Test</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-admin.empty-state icon="inbox" title="Belum ada provider / No providers" text="Tambahkan manifest di app/Plugins/*/plugin.json." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <x-admin.card class="mt-3" title="Log Webhook Terakhir / Recent Webhook Log" icon="activity">
        @if (empty($log ?? []))
            <p class="small text-secondary mb-0">Belum ada callback webhook. / No webhook callbacks yet.</p>
        @else
            <div class="table-responsive">
                <table class="table admin-table mb-0 table-sm">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Provider</th>
                            <th scope="col">Transaksi</th>
                            <th scope="col">Hasil</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($log as $row)
                            <tr>
                                <td><code>{{ $row['id'] }}</code></td>
                                <td>{{ $row['provider_id'] }}</td>
                                <td><code class="small">{{ $row['gateway_transaction_id'] }}</code></td>
                                <td><code class="small">{{ $row['processing_result'] }}</code></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <p class="small text-secondary mt-2 mb-0">
            Enable/disable plugin via setting <code>plugins.enabled</code>. Configure kredensial via
            <a href="{{ route('admin.providers.index', ['type' => 'payment']) }}">Providers</a>
            (nilai rahasia tidak pernah ditampilkan).
        </p>
    </x-admin.card>
@endsection
