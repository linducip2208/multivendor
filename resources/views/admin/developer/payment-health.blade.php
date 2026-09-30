@extends('layouts.admin')

@section('title', 'Health Pembayaran / Payment Health')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Developers', ['label' => 'Payment Health']]" />
@endsection

@section('content')
    <x-admin.page-header title="Health Pembayaran" subtitle="Payment health — cek aman tanpa kredensial live. / Safe checks, no live credentials." />

    <x-admin.alert type="warning" :dismissible="false" title="Jujur / Honest" icon="activity">
        <code>unknown</code> = belum dikonfigurasi, bukan error. Tidak ada panggilan HTTP keluar dan nilai rahasia tidak pernah ditampilkan.
        / <code>unknown</code> means not configured. No outbound HTTP; secrets are never shown.
    </x-admin.alert>

    <x-admin.card title="Health per Provider / Per-provider Health" icon="heart" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Provider</th>
                        <th scope="col" class="text-center">Health</th>
                        <th scope="col">Keterangan / Detail</th>
                        <th scope="col" class="text-center">Configured</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($providers as $provider)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $provider['name'] }}</span>
                                <small class="d-block text-secondary"><code>{{ $provider['code'] }}</code></small>
                            </td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="strtoupper($provider['health'])"
                                    :color="match($provider['health']) { 'configured' => 'success', 'disabled' => 'secondary', 'error' => 'danger', default => 'warning' }"
                                    pill
                                />
                            </td>
                            <td class="small">{{ $provider['health_id'] }}<br><span class="text-secondary">{{ $provider['health_en'] }}</span></td>
                            <td class="text-center">
                                <x-admin.badge :text="$provider['configured'] ? 'YA / YES' : 'TIDAK / NO'" :color="$provider['configured'] ? 'success' : 'warning'" pill />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-admin.empty-state icon="heart" title="Belum ada data" text="Belum ada manifest plugin." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <x-admin.card class="mt-3" title="Log Webhook / Webhook Log" icon="list">
        @if (empty($log ?? []))
            <p class="small text-secondary mb-0">Belum ada callback. / No callbacks yet.</p>
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
    </x-admin.card>
@endsection
