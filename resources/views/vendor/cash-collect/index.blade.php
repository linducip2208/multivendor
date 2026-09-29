@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Serah terima COD')
@section('subtitle', 'Catatan atenuasi uang tunai yang dipegang kurir')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'COD'],
])

@section('content')
    <x-admin.alert type="info" title="COD adalah catatan atenuasi, bukan pendapatan">
        Uang COD dipegang kurir dan belum menjadi pendapatan toko. Saldo toko bertambah secara otomatis
        ketika pesanan berstatus <strong>Diterima</strong> atau <strong>Selesai</strong>.
        Menandai lunas di sini hanya mencatat bahwa serah terima antara kurir dan platform sudah beres,
        dan tidak menambah saldo satu rupiah pun.
    </x-admin.alert>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl">
            <x-admin.stat label="Belum diterima" :value="$totalPending->toFloat()" icon="clock" color="warning" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Sudah diterima" :value="$totalCollected->toFloat()" icon="check-circle" color="success" />
        </div>
        <div class="col-12 col-xl">
            <x-admin.stat label="Total catatan" :value="$total" icon="list" color="primary" />
        </div>
    </div>

    <x-admin.filters
        :action="route('vendor.cash-collect.index')"
        :filters="[['name' => 'status', 'label' => 'Status', 'type' => 'select', 'value' => $status, 'options' => [
            '' => 'Semua',
            'pending' => 'Belum diterima',
            'collected' => 'Sudah diterima',
        ]]]"
    />

    <x-admin.card :padding="false">
        <x-admin.table>
            <x-slot:table>
                \App\Support\TableBuilder::make()
                    ->columns([
                        'order' => ['label' => 'Pesanan', 'width' => '22%'],
                        'courier' => ['label' => 'Kurir'],
                        'amount' => ['label' => 'Nominal', 'align' => 'end'],
                        'status' => ['label' => 'Serah terima'],
                        'collected_at' => ['label' => 'Diterima pada', 'align' => 'end'],
                        'actions' => ['label' => '', 'align' => 'end', 'width' => '160px'],
                    ])
                    ->rows(
                        $collects->map(fn ($collect) => [
                            'order' => '<a href="'.route('vendor.orders.show', $collect->order_id).'" class="fw-medium">'.e($collect->order?->order_number ?? '#'.$collect->order_id).'</a>',
                            'courier' => '<span class="text-secondary small">'.e($collect->deliveryMan?->name ?? '—').'</span>',
                            'amount' => '<span class="fw-medium">'.e(Currency::format($collect->amount)).'</span>',
                            'status' => $__status($collect->collected ? 'collected' : 'pending', [
                                'collected' => ['Diterima', 'success'],
                                'pending' => ['Menunggu', 'warning'],
                            ]),
                            'collected_at' => '<span class="text-secondary small">'.e($collect->collected_at ? $collect->collected_at->format('d/m/Y H:i') : '—').'</span>',
                            'actions' => $collect->collected
                                ? '<span class="text-secondary small">'.e($collect->collected_at?->format('d/m/Y H:i') ?? '—').'</span>'
                                : '<form method="POST" action="'.route('vendor.cash-collect.mark', $collect->id).'" data-confirm="Catat serah terima COD untuk pesanan ini?">'
                                    .csrf()
                                    .'<button type="submit" class="btn btn-sm btn-success"><x-admin.icon name="check" :size="14" class="me-1" />Tandai diterima</button></form>',
                        ])->all()
                    )
                    ->empty('Belum ada catatan COD untuk toko Anda.')
            </x-slot:table>
        </x-admin.table>
    </x-admin.card>

    <x-admin.pagination :paginator="$collects" class="mt-3" />
@endsection
