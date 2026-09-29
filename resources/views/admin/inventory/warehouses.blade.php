@extends('layouts.admin')

@section('title', 'Gudang')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Commerce', ['label' => 'Inventori', 'href' => route('admin.inventory.index')], ['label' => 'Gudang']]" />
@endsection

@section('content')
    <x-admin.page-header title="Gudang" subtitle="Lokasi penyimpanan dan transfer antar gudang.">
                        <x-slot:actions>
            <a href="{{ route('admin.inventory.index') }}" class="btn btn-outline-secondary btn-sm">
                <x-admin.icon name="package" :size="14" /> Posisi Stok
            </a>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#transfer-modal" aria-haspopup="dialog">
                <x-admin.icon name="refresh" :size="14" /> Transfer
            </button>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#warehouse-modal" aria-haspopup="dialog">
                <x-admin.icon name="plus" :size="14" /> Tambah Gudang
            </button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.card class="mb-3" title="Daftar Gudang" icon="building" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Gudang</th>
                        <th scope="col">Lokasi</th>
                        <th scope="col">Pengelola</th>
                        <th scope="col" class="text-end">SKU</th>
                        <th scope="col" class="text-end">Unit</th>
                        <th scope="col" class="text-end">Nilai</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                {{ $row['name'] }}
                                <code class="small d-block text-secondary">{{ $row['code'] }}</code>
                            </td>
                            <td>{{ trim($row['city'] . ($row['province'] !== '' ? ', '.$row['province'] : '')) }}</td>
                            <td>
                                {{ $row['manager_name'] !== '' ? $row['manager_name'] : '-' }}
                                @if ($row['phone'] !== '')
                                    <small class="d-block text-secondary">{{ $row['phone'] }}</small>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format($row['skus'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($row['units'], 0, ',', '.') }}</td>
                            <td class="text-end fw-semibold">{{ \App\Support\Currency::format($row['value']) }}</td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="$row['is_active'] ? 'Aktif' : 'Nonaktif'"
                                    :color="$row['is_active'] ? 'success' : 'secondary'"
                                    pill
                                />
                                @if ($row['is_default'])
                                    <x-admin.badge text="Utama" color="primary" pill />
                                @endif
                            </td>
                            <td class="text-end">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#edit-warehouse-{{ $row['id'] }}"
                                    aria-label="Ubah gudang {{ $row['name'] }}"
                                    aria-haspopup="dialog"
                                >
                                    <x-admin.icon name="pencil" :size="14" />
                                </button>
                            </td>
                        </tr>

                        <x-admin.modal :id="'edit-warehouse-'.$row['id']" :title="'Ubah '.$row['name']" icon="pencil" size="sm">
                            <form method="POST" action="{{ route('admin.inventory.warehouses.update', $row['id']) }}">
                                @csrf
                                @method('PUT')
                                <div class="row g-3">
                                    <div class="col-12">
                                        <x-admin.form-field name="name" label="Nama Gudang" :value="$row['name']" required :maxlength="160" />
                                    </div>
                                    <div class="col-6">
                                        <x-admin.form-field name="code" label="Kode" :value="$row['code']" :maxlength="40" />
                                    </div>
                                    <div class="col-6">
                                        <x-admin.form-field name="manager_name" label="Pengelola" :value="$row['manager_name']" :maxlength="160" />
                                    </div>
                                    <div class="col-12">
                                        <x-admin.form-field name="address" label="Alamat" :value="$row['address']" :maxlength="255" />
                                    </div>
                                    <div class="col-6">
                                        <x-admin.form-field name="city" label="Kota" :value="$row['city']" :maxlength="100" />
                                    </div>
                                    <div class="col-6">
                                        <x-admin.form-field name="province" label="Provinsi" :value="$row['province']" :maxlength="100" />
                                    </div>
                                    <div class="col-6">
                                        <x-admin.form-field name="postal_code" label="Kode Pos" :value="$row['postal_code']" :maxlength="12" />
                                    </div>
                                    <div class="col-6">
                                        <x-admin.form-field name="country" label="Negara" :value="$row['country']" :maxlength="2" />
                                    </div>
                                    <div class="col-6">
                                        <x-admin.form-field name="phone" label="Telepon" :value="$row['phone']" :maxlength="30" />
                                    </div>
                                    <div class="col-6 d-flex align-items-end gap-3">
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="is_active" value="0">
                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="wh-active-{{ $row['id'] }}" @checked($row['is_active'])>
                                            <label class="form-check-label" for="wh-active-{{ $row['id'] }}">Aktif</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="is_default" value="0">
                                            <input class="form-check-input" type="checkbox" name="is_default" value="1" id="wh-default-{{ $row['id'] }}" @checked($row['is_default'])>
                                            <label class="form-check-label" for="wh-default-{{ $row['id'] }}">Gudang utama</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary">Simpan</button>
                                </div>
                            </form>
                        </x-admin.modal>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-admin.empty-state
                                    icon="building"
                                    title="Belum ada gudang"
                                    text="Tambahkan gudang pertama agar stok dapat dilacak per lokasi."
                                    action-label="Tambah Gudang"
                                    action-url="#"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <x-admin.card class="mb-3" title="Transfer Antar Gudang" icon="refresh" :subtitle="'Status: '.implode(' · ', array_map('ucfirst', $transfers['statuses']))" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Nomor</th>
                        <th scope="col">Asal</th>
                        <th scope="col">Tujuan</th>
                        <th scope="col" class="text-center">Item</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col">Dibuat</th>
                        <th scope="col">Diterima</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transfers['rows'] as $transfer)
                        <tr>
                            <td><code>{{ $transfer['transfer_number'] }}</code></td>
                            <td>{{ $transfer['from'] }}</td>
                            <td>{{ $transfer['to'] }}</td>
                            <td class="text-center">{{ count($transfer['items']) }}</td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="match($transfer['status']) { 'in_transit' => 'Dalam Perjalanan', 'received' => 'Diterima', 'cancelled' => 'Dibatalkan', default => 'Draf' }"
                                    :color="match($transfer['status']) { 'in_transit' => 'warning', 'received' => 'success', 'cancelled' => 'secondary', default => 'info' }"
                                    pill
                                />
                            </td>
                            <td class="text-nowrap">{{ $transfer['created_at'] }}</td>
                            <td class="text-nowrap">{{ $transfer['received_at'] !== '' ? $transfer['received_at'] : '-' }}</td>
                            <td class="text-end">
                                @if ($transfer['status'] === 'in_transit')
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#receive-{{ $transfer['id'] }}"
                                        aria-label="Terima transfer {{ $transfer['transfer_number'] }}"
                                        aria-haspopup="dialog"
                                    >
                                        <x-admin.icon name="check" :size="14" /> Terima
                                    </button>
                                @endif
                            </td>
                        </tr>

                        <x-admin.modal :id="'receive-'.$transfer['id']" :title="'Terima '.$transfer['transfer_number']" icon="check" size="sm">
                            <form method="POST" action="{{ route('admin.inventory.transfers.receive', $transfer['id']) }}">
                                @csrf
                                <x-admin.alert type="info" :dismissible="false" title="Penerimaan bisa sebagian">
                                    Isi 0 untuk barang yang belum sampai. Stok tujuan bertambah sesuai jumlah yang benar-benar diterima.
                                </x-admin.alert>
                                <div class="table-responsive">
                                    <table class="table admin-table mb-0">
                                        <thead>
                                            <tr>
                                                <th scope="col">Item</th>
                                                <th scope="col" class="text-end">Terkirim</th>
                                                <th scope="col" class="text-end">Sudah Diterima</th>
                                                <th scope="col" style="width: 130px">Diterima Sekarang</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($transfer['items'] as $item)
                                                <tr>
                                                    <td>#{{ $item['id'] }}</td>
                                                    <td class="text-end">{{ number_format($item['quantity'], 0, ',', '.') }}</td>
                                                    <td class="text-end">{{ number_format($item['received_quantity'], 0, ',', '.') }}</td>
                                                    <td>
                                                        <input
                                                            type="number"
                                                            class="form-control form-control-sm"
                                                            name="received[{{ $item['id'] }}]"
                                                            min="0"
                                                            max="{{ $item['pending'] }}"
                                                            value="{{ $item['pending'] }}"
                                                            aria-label="Jumlah diterima item {{ $item['id'] }}"
                                                        >
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary">Konfirmasi Penerimaan</button>
                                </div>
                            </form>
                        </x-admin.modal>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-admin.empty-state compact icon="refresh" title="Belum ada transfer" text="Buat transfer untuk memindahkan stok antar gudang." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div class="mt-3">
        <x-admin.pagination :paginator="\App\Support\AdminPaginator::fromArray($transfers['pagination'], $transfers['pagination']['total'], $transfers['pagination']['per_page'], $transfers['pagination']['current_page'])" size="sm" />
    </div>

    <x-admin.modal id="warehouse-modal" title="Tambah Gudang" icon="plus" size="sm">
        <form method="POST" action="{{ route('admin.inventory.warehouses.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-12">
                    <x-admin.form-field name="name" label="Nama Gudang" required :maxlength="160" />
                </div>
                <div class="col-6">
                    <x-admin.form-field name="code" label="Kode" :maxlength="40" help="Dibuat otomatis dari nama bila dikosongkan." />
                </div>
                <div class="col-6">
                    <x-admin.form-field name="manager_name" label="Pengelola" :maxlength="160" />
                </div>
                <div class="col-12">
                    <x-admin.form-field name="address" label="Alamat" :maxlength="255" />
                </div>
                <div class="col-6">
                    <x-admin.form-field name="city" label="Kota" :maxlength="100" />
                </div>
                <div class="col-6">
                    <x-admin.form-field name="province" label="Provinsi" :maxlength="100" />
                </div>
                <div class="col-6">
                    <x-admin.form-field name="postal_code" label="Kode Pos" :maxlength="12" />
                </div>
                <div class="col-6">
                    <x-admin.form-field name="country" label="Negara" value="ID" :maxlength="2" />
                </div>
                <div class="col-6">
                    <x-admin.form-field name="phone" label="Telepon" :maxlength="30" />
                </div>
                <div class="col-6 d-flex align-items-end gap-3">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="wh-new-active" checked>
                        <label class="form-check-label" for="wh-new-active">Aktif</label>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_default" value="0">
                        <input class="form-check-input" type="checkbox" name="is_default" value="1" id="wh-new-default">
                        <label class="form-check-label" for="wh-new-default">Gudang utama</label>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Gudang</button>
            </div>
        </form>
    </x-admin.modal>

    <x-admin.modal id="transfer-modal" title="Buat Transfer" icon="refresh" size="sm">
        <form method="POST" action="{{ route('admin.inventory.transfers.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label" for="transfer-from">Dari Gudang</label>
                    <select class="form-select" id="transfer-from" name="from_warehouse_id" required>
                        <option value="">Pilih</option>
                        @foreach ($rows as $warehouse)
                            <option value="{{ $warehouse['id'] }}">{{ $warehouse['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label" for="transfer-to">Ke Gudang</label>
                    <select class="form-select" id="transfer-to" name="to_warehouse_id" required>
                        <option value="">Pilih</option>
                        @foreach ($rows as $warehouse)
                            <option value="{{ $warehouse['id'] }}">{{ $warehouse['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <x-admin.form-field name="note" label="Catatan" type="textarea" :rows="2" :maxlength="500" />
                </div>
            </div>
            <h6 class="fw-semibold mt-3 mb-2">Barang yang dipindahkan</h6>
            <div class="table-responsive">
                <table class="table admin-table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">ID Produk</th>
                            <th scope="col" style="width: 140px">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for ($i = 0; $i < 3; $i++)
                            <tr>
                                <td>
                                    <input type="number" class="form-control form-control-sm" name="items[{{ $i }}][product_id]" min="1" aria-label="ID produk {{ $i + 1 }}">
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm" name="items[{{ $i }}][quantity]" min="1" value="1" aria-label="Jumlah {{ $i + 1 }}">
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Buat Transfer</button>
            </div>
        </form>
    </x-admin.modal>
@endsection
