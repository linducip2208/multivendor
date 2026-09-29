@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Pergerakan stok')
@section('subtitle', 'Jejak audit setiap perubahan stok')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Inventori', 'href' => route('vendor.inventory.index')],
    ['label' => 'Pergerakan'],
])

@section('actions')
    <a href="{{ route('vendor.inventory.index') }}" class="btn btn-outline-secondary">
        <x-admin.icon name="arrow-left" :size="16" class="me-1" />
        <span>Inventori</span>
    </a>
@endsection

@section('content')
    <x-admin.filters
        :action="route('vendor.inventory.movements')"
        :filters="[
            ['name' => 'product_id', 'label' => 'Produk', 'type' => 'select', 'value' => $selected['product_id'], 'options' => ['' => 'Semua produk'] + $products->all()],
            ['name' => 'type', 'label' => 'Jenis', 'type' => 'select', 'value' => $selected['type'], 'options' => ['' => 'Semua'] + $types],
        ]"
    />

    <x-admin.card :padding="false">
        <x-admin.table dense>
            <x-slot:table>
                \App\Support\TableBuilder::make()
                    ->columns([
                        'date' => ['label' => 'Waktu', 'width' => '150px'],
                        'product' => ['label' => 'Produk'],
                        'type' => ['label' => 'Jenis'],
                        'quantity' => ['label' => 'Jumlah', 'align' => 'end'],
                        'balance' => ['label' => 'Saldo', 'align' => 'end'],
                        'note' => ['label' => 'Catatan'],
                        'actor' => ['label' => 'Oleh', 'align' => 'end'],
                    ])
                    ->rows(
                        $movements->map(fn ($movement) => [
                            'date' => e($movement->created_at?->format('d/m/Y H:i') ?? '-'),
                            'product' => '<span class="fw-medium d-block text-truncate">'.e($movement->product?->name ?? 'Produk dihapus').'</span><span class="text-secondary small">'.e($movement->product?->sku).'</span>',
                            'type' => $__status($movement->type, [
                                'in' => ['Barang masuk', 'success'],
                                'out' => ['Barang keluar', 'warning'],
                                'adjustment' => ['Penyesuaian', 'info'],
                            ]),
                            'quantity' => $movement->type === 'out'
                                ? '<span class="text-danger fw-medium">-'.e(Currency::number($movement->quantity)).'</span>'
                                : '<span class="text-success fw-medium">+'.e(Currency::number($movement->quantity)).'</span>',
                            'balance' => e(Currency::number($movement->balance_after ?? 0)),
                            'note' => e(\Illuminate\Support\Str::limit((string) $movement->note, 60)),
                            'actor' => e($movement->creator?->name ?? 'Sistem'),
                        ])->all()
                    )
                    ->empty('Belum ada pergerakan stok.')
            </x-slot:table>
        </x-admin.table>
    </x-admin.card>

    <div class="mt-3"><x-admin.pagination :paginator="movements" /></div>
@endsection
