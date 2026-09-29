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
                            'product' => '<span class="fw-medium d-block text-truncate">'.e($movement->product?->name ?? 'Produk dihapus').'</span><span class="text-secondary small">'.e($movement->product?->sku ?? '').'</span>',
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

    <div class="row g-3 mt-1">
        <div class="col-12 col-xl-6">
            <x-admin.card>
                <h3 class="h6 mb-2">Transfer gudang + approval</h3>
                <p class="text-secondary small">Buat draft transfer, lalu setujui (kirim) dan terima (tambah stok tujuan). Memakai rute penyesuaian existing dengan <code>action</code>.</p>
                <form method="POST" action="{{ route('vendor.inventory.adjust') }}" class="row g-2">
                    @csrf
                    <input type="hidden" name="action" value="transfer_request">
                    <div class="col-6">
                        <label class="form-label small">Dari gudang</label>
                        <select name="from_warehouse_id" class="form-select form-select-sm" required>
                            @foreach (($warehouses ?? []) as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }} ({{ $warehouse->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Ke gudang</label>
                        <select name="to_warehouse_id" class="form-select form-select-sm" required>
                            @foreach (($warehouses ?? []) as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }} ({{ $warehouse->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Item (ID produk:jumlah, pisahkan koma. Contoh: 1:5, 2:3)</label>
                        <input name="items_text" class="form-control form-control-sm" placeholder="1:5, 2:3">
                        <span class="text-secondary small">Pada API kirim <code>items[][product_id]</code> + <code>items[][quantity]</code>.</span>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm">Buat draft transfer</button>
                    </div>
                </form>
                @if (($transfers ?? collect())->isNotEmpty())
                    <div class="table-responsive mt-3">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>No. Transfer</th><th>Rute</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($transfers as $transfer)
                                    <tr>
                                        <td><code>{{ $transfer->transfer_number }}</code></td>
                                        <td class="small">{{ $transfer->fromWarehouse?->code }} → {{ $transfer->toWarehouse?->code }}</td>
                                        <td>{{ $__status($transfer->status) }}</td>
                                        <td class="text-end">
                                            @if ($transfer->status === 'draft')
                                                <form method="POST" action="{{ route('vendor.inventory.adjust') }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="action" value="transfer_approve">
                                                    <input type="hidden" name="transfer_id" value="{{ $transfer->id }}">
                                                    <button type="submit" class="btn btn-sm btn-success">Setujui</button>
                                                </form>
                                            @elseif ($transfer->status === 'in_transit')
                                                <form method="POST" action="{{ route('vendor.inventory.adjust') }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="action" value="transfer_receive">
                                                    <input type="hidden" name="transfer_id" value="{{ $transfer->id }}">
                                                    <button type="submit" class="btn btn-sm btn-primary">Terima</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.card>
        </div>
        <div class="col-12 col-xl-6">
            <x-admin.card>
                <h3 class="h6 mb-2">Stock opname + laporan selisih</h3>
                <form method="POST" action="{{ route('vendor.inventory.adjust') }}" class="row g-2">
                    @csrf
                    <input type="hidden" name="action" value="opname">
                    <div class="col-6">
                        <label class="form-label small">Produk</label>
                        <select name="product_id" class="form-select form-select-sm" required>
                            @foreach ($products as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-3">
                        <label class="form-label small">Hitung fisik</label>
                        <input type="number" name="quantity" class="form-control form-control-sm" min="0" required>
                    </div>
                    <div class="col-3">
                        <label class="form-label small">Gudang (opsional)</label>
                        <select name="warehouse_id" class="form-select form-select-sm">
                            <option value="">—</option>
                            @foreach (($warehouses ?? []) as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Catatan opname</label>
                        <input type="text" name="reason" class="form-control form-control-sm" maxlength="255" required placeholder="Contoh: opname mingguan rak A">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-warning btn-sm">Catat opname</button>
                    </div>
                </form>
                @if (($variance ?? collect())->isNotEmpty())
                    <div class="table-responsive mt-3">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Waktu</th><th>Produk</th><th class="text-end">Jumlah selisih</th><th>Catatan</th></tr></thead>
                            <tbody>
                                @foreach ($variance as $row)
                                    <tr>
                                        <td class="small">{{ $row->created_at?->format('d/m/Y H:i') }}</td>
                                        <td class="small">{{ $row->product?->name }}</td>
                                        <td class="text-end">{{ $row->quantity }}</td>
                                        <td class="small">{{ \Illuminate\Support\Str::limit((string) $row->note, 60) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.card>
        </div>
    </div>
@endsection
