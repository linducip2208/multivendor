@extends('layouts.admin')

@section('title', 'Refund '.$refund['refund_number'])

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Finance', ['label' => 'Refund', 'href' => route('admin.refunds.index')], ['label' => $refund['refund_number']]]" />
@endsection

@section('content')
    <x-admin.page-header :title="$refund['refund_number']" :subtitle="$refund['order_number'].' · '.$refund['customer']">
        <x-slot:actions>
            <x-admin.badge :text="strtoupper($refund['status'])" :color="match($refund['status']) { 'succeeded' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'rejected' => 'secondary', default => 'info' }" pill />
            <a href="{{ route('admin.refunds.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if ($refund['status'] === 'failed')
        <x-admin.alert type="danger" :title="'Refund gagal: '.($refund['failure_reason'] !== '' ? $refund['failure_reason'] : 'alasan tidak dicatat gateway')" />
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Nilai Refund" :value="$refund['amount']" money icon="undo" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total Pesanan" :value="$order_total" money icon="cash" color="info" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Porsi dari Pesanan" :value="number_format($refunded_ratio, 1, ',', '.').'%'" icon="percent" color="warning" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Mata Uang" :value="$refund['currency']" icon="globe" color="secondary" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <x-admin.card title="Detail Refund" icon="info" class="mb-3">
                <dl class="row small mb-0">
                    <dt class="col-5 text-secondary">Nomor pesanan</dt>
                    <dd class="col-7 text-end">{{ $refund['order_number'] }}</dd>
                    <dt class="col-5 text-secondary">Pelanggan</dt>
                    <dd class="col-7 text-end">{{ $refund['customer'] }}</dd>
                    <dt class="col-5 text-secondary">Diajukan oleh</dt>
                    <dd class="col-7 text-end">{{ \Illuminate\Support\Str::headline($refund['requested_by_type']) }}</dd>
                    <dt class="col-5 text-secondary">Nomor pembayaran</dt>
                    <dd class="col-7 text-end"><code>{{ $refund['payment_number'] }}</code></dd>
                    <dt class="col-5 text-secondary">Provider</dt>
                    <dd class="col-7 text-end">{{ $refund['provider'] }}</dd>
                    <dt class="col-5 text-secondary">ID refund gateway</dt>
                    <dd class="col-7 text-end"><code>{{ $refund['gateway_refund_id'] !== '' ? $refund['gateway_refund_id'] : '-' }}</code></dd>
                    <dt class="col-5 text-secondary">Idempotency key</dt>
                    <dd class="col-7 text-end text-break"><code class="small">{{ $refund['idempotency_key'] !== '' ? $refund['idempotency_key'] : '-' }}</code></dd>
                    <dt class="col-5 text-secondary">Dibuat</dt>
                    <dd class="col-7 text-end">{{ $refund['created_at'] }}</dd>
                    <dt class="col-5 text-secondary">Selesai</dt>
                    <dd class="col-7 text-end">{{ $refund['succeeded_at'] !== '' ? $refund['succeeded_at'] : '-' }}</dd>
                </dl>
            </x-admin.card>

            <x-admin.card title="Item Pesanan" icon="package" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Produk</th>
                                <th scope="col" class="text-end">Jumlah</th>
                                <th scope="col" class="text-end">Subtotal</th>
                                <th scope="col" class="text-end">Refund Item</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $item)
                                <tr>
                                    <td>
                                        {{ $item['name'] }}
                                        <small class="d-block text-secondary">{{ $item['sku'] }}</small>
                                    </td>
                                    <td class="text-end">{{ number_format($item['quantity'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ $item['sub_total_formatted'] }}</td>
                                    <td class="text-end">{{ \App\Support\Currency::format($item['refund_amount']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <x-admin.empty-state compact icon="package" title="Item tidak ditemukan" text="Detail item pesanan tidak dapat dimuat." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-6">
            <x-admin.card title="Alasan" icon="message-square" class="mb-3">
                <p class="mb-0">{{ $refund['reason'] !== '' ? $refund['reason'] : 'Tidak dicatat.' }}</p>
            </x-admin.card>

            <x-admin.card title="Respons Gateway" icon="code">
                @if ($gateway_response !== '')
                    <pre class="small mb-0" style="max-height: 420px; overflow-y: auto; white-space: pre-wrap;">{{ $gateway_response }}</pre>
                @else
                    <x-admin.empty-state compact icon="code" title="Tidak ada respons tersimpan" text="Gateway tidak menyimpan respons untuk refund ini." />
                @endif
            </x-admin.card>
        </div>
    </div>
@endsection
