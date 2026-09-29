@extends('layouts.admin')

@section('title', 'Retur')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Fulfillment', ['label' => 'Retur']]" />
@endsection

@section('content')
    <x-admin.page-header title="Retur" subtitle="Permintaan retur yang menunggu keputusan operator." />

    <div class="row g-3 mb-3">
        @foreach ($counts as $key => $count)
            <div class="col-6 col-xl">
                <x-admin.stat
                    :label="$statuses[$key] ?? ucfirst($key)"
                    :value="number_format($count, 0, ',', '.')"
                    :icon="$key === 'all' ? 'rotate-ccw' : 'inbox'"
                    :color="match($key) { 'approved' => 'success', 'rejected' => 'danger', 'requested' => 'warning', 'all' => 'primary', default => 'secondary' }"
                />
            </div>
        @endforeach
        <div class="col-6 col-xl">
            <x-admin.stat label="Nilai Menunggu" :value="$pending_amount" money icon="cash" color="info" />
        </div>
    </div>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.returns.index')"
            :filters="[
                ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Nomor RMA atau nomor pesanan'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => array_merge(['' => 'Semua status'], $statuses)],
            ]"
        />
    </x-admin.card>

    <x-admin.card title="Permintaan Retur" icon="rotate-ccw" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">RMA</th>
                        <th scope="col">Pesanan</th>
                        <th scope="col">Pelanggan</th>
                        <th scope="col">Produk</th>
                        <th scope="col">Alasan</th>
                        <th scope="col" class="text-end">Nilai</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col">Diajukan</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td><code>{{ $row['rma_number'] }}</code></td>
                            <td><a href="{{ route('admin.orders.show', $row['order_id']) }}">{{ $row['order_number'] }}</a></td>
                            <td>{{ $row['customer'] }}</td>
                            <td>{{ $row['product'] }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($row['reason'], 60) }}</td>
                            <td class="text-end fw-semibold">{{ $row['amount_formatted'] }}</td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="$row['status_label']"
                                    :color="match($row['status']) { 'approved' => 'success', 'rejected' => 'danger', 'received' => 'info', default => 'warning' }"
                                    pill
                                />
                            </td>
                            <td class="text-nowrap">{{ $row['created_at'] }}</td>
                            <td class="text-end">
                                @if ($row['status'] === 'requested')
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#decide-{{ $row['id'] }}"
                                        aria-label="Putuskan retur {{ $row['rma_number'] }}"
                                        aria-haspopup="dialog"
                                    >
                                        Putuskan
                                    </button>
                                @else
                                    <small class="text-secondary d-block">
                                        {{ $row['decider'] !== '' ? $row['decider'] : '-' }}<br>
                                        <span class="text-nowrap">{{ $row['decided_at'] }}</span>
                                    </small>
                                @endif
                            </td>
                        </tr>

                        <x-admin.modal :id="'decide-'.$row['id']" :title="'Keputusan '.$row['rma_number']" icon="check" size="sm">
                            <form method="POST" action="{{ route('admin.returns.update', $row['id']) }}">
                                @csrf
                                @method('PUT')
                                <x-admin.alert type="warning" :dismissible="false" title="Keputusan tidak dapat diubah">
                                        Setelah disimpan, <code>status</code>, <code>decided_by</code>, dan <code>decided_at</code> dikunci serta dicatat di jejak audit.
                                    </x-admin.alert>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label" for="decision-{{ $row['id'] }}">Keputusan</label>
                                            <select class="form-select" id="decision-{{ $row['id'] }}" name="decision" required>
                                                <option value="approved">Setujui</option>
                                                <option value="rejected">Tolak</option>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <x-admin.form-field
                                                name="amount"
                                                label="Nilai yang Disetujui"
                                                type="number"
                                                :value="$row['amount']"
                                                :min="0"
                                                :step="1000"
                                                help="Diabaikan bila keputusan menolak."
                                            />
                                        </div>
                                        <div class="col-12">
                                            <x-admin.form-field
                                                name="admin_note"
                                                label="Catatan Admin"
                                                type="textarea"
                                                :rows="3"
                                                :maxlength="1000"
                                                help="Wajib diisi bila menolak."
                                            />
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end gap-2 mt-3">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-primary">Simpan Keputusan</button>
                                    </div>
                                </form>
                            </x-admin.modal>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-admin.empty-state icon="rotate-ccw" title="Belum ada permintaan retur" text="Permintaan retur dari pelanggan akan muncul di sini." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div class="mt-3">
        <x-admin.pagination :paginator="\App\Support\AdminPaginator::fromArray($pagination, $pagination['total'], $pagination['per_page'], $pagination['current_page'])" size="sm" />
    </div>
@endsection
