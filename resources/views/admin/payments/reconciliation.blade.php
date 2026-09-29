@extends('layouts.admin')

@section('title', 'Rekonsiliasi Pembayaran')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Finance', ['label' => 'Rekonsiliasi']]" />
@endsection

@section('content')
    <x-admin.page-header title="Rekonsiliasi Pembayaran" subtitle="Kelompok pembayaran yang masih tertunda setelah ambang waktu tertentu." />

    <div class="row g-3 mb-3">
        @foreach ($kpis as $kpi)
            <div class="col-6 col-xl-3">
                <x-admin.stat
                    :label="$kpi['label']"
                    :value="$kpi['value']"
                    :money="$kpi['money'] ?? false"
                    :icon="$kpi['icon']"
                    :color="$kpi['color']"
                    :hint="$kpi['hint']"
                />
            </div>
        @endforeach
    </div>

    <x-admin.alert type="info" :dismissible="false" title="Apa yang dilakukan rekonsiliasi" icon="refresh">
        Proses ini menandai kelompok pembayaran yang sudah diperiksa beserta jumlah pemeriksaan, sehingga operator bisa membedakan "belum pernah dicek" dari "sudah dicek dan memang belum ada jawaban gateway".
    </x-admin.alert>

    <x-admin.card class="mb-3" title="Jalankan Rekonsiliasi" icon="play">
        <form method="POST" action="{{ route('admin.payments.reconciliation.run') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-4">
                <x-admin.form-field
                    name="minutes"
                    label="Ambang Usia (menit)"
                    type="number"
                    :value="$stale_minutes"
                    :min="1"
                    :max="1440"
                    required
                    help="Kelompok yang lebih tua dari nilai ini akan diperiksa."
                />
            </div>
            <div class="col-md-4">
                <p class="small text-secondary mb-2">
                    Batas bawah yang dipakai: {{ $cutoff }}<br>
                    Driver antrean: <code>{{ config('queue.default') }}</code>
                </p>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">
                    <x-admin.icon name="refresh" :size="14" /> Jalankan Rekonsiliasi
                </button>
            </div>
        </form>
    </x-admin.card>

    <x-admin.card title="Kelompok Tertunda" icon="inbox" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Nomor Pembayaran</th>
                        <th scope="col">Pelanggan</th>
                        <th scope="col">Provider</th>
                        <th scope="col" class="text-end">Nilai</th>
                        <th scope="col" class="text-center">Pesanan Belum Dicek</th>
                        <th scope="col" class="text-center">Callback</th>
                        <th scope="col" class="text-end">Usia</th>
                        <th scope="col">Terakhir Dicek</th>
                        <th scope="col" class="text-center">Tingkat</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <code>{{ $row['payment_number'] }}</code>
                                <small class="d-block text-secondary">{{ $row['gateway_reference'] !== '' ? $row['gateway_reference'] : '-' }}</small>
                            </td>
                            <td>{{ $row['customer'] }}</td>
                            <td>{{ $row['provider'] }}</td>
                            <td class="text-end fw-semibold">{{ $row['grand_total_formatted'] }}</td>
                            <td class="text-end">
                                {{ number_format($row['unreconciled_orders'], 0, ',', '.') }} / {{ number_format($row['orders'], 0, ',', '.') }}
                            </td>
                            <td class="text-center">
                                <x-admin.badge :text="$row['has_callback'] ? 'Ada' : 'Belum'" :color="$row['has_callback'] ? 'warning' : 'secondary'" pill />
                            </td>
                            <td class="text-end">{{ number_format($row['age_minutes'], 0, ',', '.') }} mnt</td>
                            <td class="text-nowrap">
                                {{ $row['last_reconciled_at'] !== '' ? $row['last_reconciled_at'] : '-' }}
                                @if ($row['attempts'] > 0)
                                    <small class="d-block text-secondary">{{ $row['attempts'] }}×</small>
                                @endif
                            </td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="strtoupper($row['severity'])"
                                    :color="match($row['severity']) { 'high' => 'danger', 'medium' => 'warning', default => 'secondary' }"
                                    pill
                                />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-admin.empty-state
                                    icon="inbox"
                                    title="Tidak ada kelompok tertunda"
                                    text="Semua kelompok pembayaran sudah melewati prosesnya atau berstatus final."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    @if ($pending_refunds > 0)
        <div class="mt-3">
            <x-admin.alert type="warning" :dismissible="false" title="Ada refund yang masih menunggu">
                {{ number_format($pending_refunds, 0, ',', '.') }} refund berstatus <code>pending</code>.
                <a href="{{ route('admin.refunds.index', ['status' => 'pending']) }}">Lihat daftar refund</a>.
            </x-admin.alert>
        </div>
    @endif
@endsection
