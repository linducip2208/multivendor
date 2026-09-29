@extends('layouts.admin')

@section('title', 'Inventori')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Commerce', ['label' => 'Inventori']]" />
@endsection

@section('content')
    <x-admin.page-header title="Inventori" subtitle="Posisi stok per produk dan gudang, dengan jejak audit setiap perubahan.">
        <x-slot:actions>
            <a href="{{ route('admin.inventory.warehouses') }}" class="btn btn-outline-secondary btn-sm">
                <x-admin.icon name="building" :size="14" /> Gudang
            </a>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#opname-modal" aria-haspopup="dialog">
                <x-admin.icon name="clipboard-check" :size="14" /> Opname
            </button>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#adjust-modal" aria-haspopup="dialog">
                <x-admin.icon name="sliders" :size="14" /> Penyesuaian
            </button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3 mb-3">
        @foreach ($report['kpis'] as $kpi)
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

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.inventory.index')"
            :filters="array_merge(
                [
                    ['name' => 'search', 'label' => 'Cari produk', 'placeholder' => 'Nama atau SKU'],
                    ['name' => 'state', 'label' => 'Kondisi', 'type' => 'select', 'options' => array_column($states, 'label', 'value')],
                ],
                array_map(fn (array $warehouse): array => [
                    'name' => 'warehouse',
                    'label' => 'Gudang',
                    'type' => 'select',
                    'options' => array_reduce(
                        $warehouses,
                        fn (array $carry, array $row): array => $carry + [$row['id'] => $row['name']],
                        ['Semua gudang' => 'all'],
                    ),
                ], $warehouses),
            )"
        />
    </x-admin.card>

    <x-admin.card title="Posisi Stok" icon="package" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Produk</th>
                        <th scope="col">Gudang</th>
                        <th scope="col" class="text-end">Tersedia</th>
                        <th scope="col" class="text-end">Reservasi</th>
                        <th scope="col" class="text-end">Masuk</th>
                        <th scope="col" class="text-end">Aman</th>
                        <th scope="col" class="text-center">Kondisi</th>
                        <th scope="col" class="text-end">Nilai</th>
                        <th scope="col">Hitung Terakhir</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['rows'] as $row)
                        <tr>
                            <td>
                                {{ $row['product'] }}
                                <small class="d-block text-secondary">{{ $row['sku'] }}</small>
                            </td>
                            <td>{{ $row['warehouse'] }} <small class="text-secondary">{{ $row['warehouse_code'] }}</small></td>
                            <td class="text-end">{{ number_format($row['on_hand'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($row['reserved'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($row['incoming'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($row['safety_stock'], 0, ',', '.') }}</td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="match($row['state']) { 'out_of_stock' => 'Habis', 'low_stock' => 'Menipis', default => 'Aman' }"
                                    :color="match($row['state']) { 'out_of_stock' => 'danger', 'low_stock' => 'warning', default => 'success' }"
                                    pill
                                />
                            </td>
                            <td class="text-end fw-semibold">{{ \App\Support\Currency::format($row['value']) }}</td>
                            <td class="text-nowrap">{{ $row['last_counted_at'] !== '' ? $row['last_counted_at'] : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-admin.empty-state
                                    icon="boxes"
                                    title="Belum ada baris stok"
                                    text="Posisi stok muncul setelah produk pertama diberi stok pada sebuah gudang."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div class="mt-3">
        <x-admin.pagination :paginator="\App\Support\AdminPaginator::fromArray($report['pagination'], $report['pagination']['total'], $report['pagination']['per_page'], $report['pagination']['current_page'])" size="sm" />
    </div>

    <x-admin.modal id="adjust-modal" title="Penyesuaian Stok" icon="sliders" size="sm">
        <form method="POST" action="{{ route('admin.inventory.adjust') }}">
            @csrf
            <x-admin.alert type="info" :dismissible="false" title="Setiap penyesuaian menulis satu baris movements">
                Selisih antara stok sistem dan hasil hitung dicatat sebagai <code>adjustment</code> beserta alasan dan pelaku, lalu <code>products.current_stock</code> dihitung ulang dari seluruh gudang.
            </x-admin.alert>
            <div class="row g-3 mt-1">
                <div class="col-12">
                    <x-admin.form-field name="product_id" label="ID Produk" type="number" required :min="1" help="Tempelkan ID produk dari halaman detail produk." />
                </div>
                <div class="col-12">
                    <label class="form-label" for="adjust-warehouse">Gudang</label>
                    <select class="form-select" id="adjust-warehouse" name="warehouse_id" required>
                        <option value="">Pilih gudang</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse['id'] }}">{{ $warehouse['name'] }} ({{ $warehouse['code'] }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <x-admin.form-field name="product_variant_id" label="ID Varian" type="number" :min="1" help="Kosongkan bila produk tanpa varian." />
                </div>
                <div class="col-6">
                    <x-admin.form-field name="on_hand" label="Stok Aktual" type="number" required :min="0" />
                </div>
                <div class="col-12">
                    <x-admin.form-field name="note" label="Alasan Penyesuaian" type="text" :maxlength="255" placeholder="Contoh: Hasil hitung fisik gudang utama" required />
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Penyesuaian</button>
            </div>
        </form>
    </x-admin.modal>

    <x-admin.modal id="opname-modal" title="Stok Opname (Hitung Ulang Massal)" icon="clipboard-check" size="lg">
        <form method="POST" action="{{ route('admin.inventory.opname') }}">
            @csrf
            <x-admin.alert type="warning" :dismissible="false" title="Hitung fisik menang atas catatan sistem">
                Untuk setiap baris, jumlah hasil hitung menjadi stok baru dan selisihnya dicatat sebagai movements <code>opname</code>. Maksimal 200 baris persubmission.
            </x-admin.alert>
            <div class="table-responsive">
                <table class="table admin-table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">ID Produk</th>
                            <th scope="col">Gudang</th>
                            <th scope="col" style="width: 180px">Jumlah Hasil Hitung</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for ($i = 0; $i < 5; $i++)
                            <tr>
                                <td>
                                    <input type="number" class="form-control form-control-sm" name="lines[{{ $i }}][product_id]" min="1" required aria-label="ID produk baris {{ $i + 1 }}">
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" name="lines[{{ $i }}][warehouse_id]" required aria-label="Gudang baris {{ $i + 1 }}">
                                        <option value="">Pilih</option>
                                        @foreach ($warehouses as $warehouse)
                                            <option value="{{ $warehouse['id'] }}">{{ $warehouse['name'] }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm" name="lines[{{ $i }}][quantity]" min="0" required aria-label="Jumlah baris {{ $i + 1 }}">
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-warning">Terapkan Opname</button>
            </div>
        </form>
    </x-admin.modal>
@endsection
