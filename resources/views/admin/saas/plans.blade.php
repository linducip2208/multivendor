@extends('layouts.admin')

@section('title', 'Paket Langganan')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['SaaS', ['label' => 'Paket']]" />
@endsection

@section('content')
    <x-admin.page-header title="Paket Langganan" subtitle="Batas penggunaan yang menjadi dasar meter tenant.">
        <x-slot:actions>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#plan-modal" aria-haspopup="dialog">
                <x-admin.icon name="plus" :size="14" /> Tambah Paket
            </button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.card title="Daftar Paket" icon="credit-card" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Paket</th>
                        <th scope="col" class="text-end">Bulanan</th>
                        <th scope="col" class="text-end">Tahunan</th>
                        <th scope="col" class="text-center">Batas</th>
                        <th scope="col" class="text-end">Langganan</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                {{ $row['name'] }}
                                <small class="d-block text-secondary">{{ $row['slug'] }}</small>
                            </td>
                            <td class="text-end fw-semibold">{{ $row['monthly_price_formatted'] }}</td>
                            <td class="text-end">{{ $row['yearly_price_formatted'] }}</td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="collect($row['limits'])->filter(fn (array $limit): bool => $limit['value'] !== null)->count().' batas aktif'"
                                    color="info"
                                    pill
                                />
                            </td>
                            <td class="text-end">{{ number_format($row['subscriptions'], 0, ',', '.') }}</td>
                            <td class="text-center">
                                <x-admin.badge :text="$row['is_active'] ? 'Aktif' : 'Nonaktif'" :color="$row['is_active'] ? 'success' : 'secondary'" pill />
                                @if ($row['is_featured'])
                                    <x-admin.badge text="Unggulan" color="primary" pill />
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Aksi paket {{ $row['name'] }}">
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#edit-plan-{{ $row['id'] }}"
                                        aria-label="Ubah paket {{ $row['name'] }}"
                                        aria-haspopup="dialog"
                                    >
                                        <x-admin.icon name="pencil" :size="14" />
                                    </button>
                                    <x-admin.confirmation-form
                                        :action="route('admin.saas.plans.destroy', $row['id'])"
                                        message="Paket ini akan dihapus. Paket dengan langganan aktif tidak dapat dihapus. Lanjutkan?"
                                        label="Hapus"
                                        variant="outline-danger"
                                        icon="trash"
                                        size="btn-sm"
                                    />
                                </div>
                            </td>
                        </tr>

                        <x-admin.modal :id="'edit-plan-'.$row['id']" :title="'Ubah '.$row['name']" icon="pencil" size="lg">
                            <form method="POST" action="{{ route('admin.saas.plans.update', $row['id']) }}">
                                @csrf
                                @method('PUT')
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <x-admin.form-field name="name" label="Nama Paket" :value="$row['name']" required :maxlength="120" />
                                    </div>
                                    <div class="col-md-4">
                                        <x-admin.form-field name="slug" label="Slug" :value="$row['slug']" :maxlength="120" />
                                    </div>
                                    <div class="col-12">
                                        <x-admin.form-field name="description" label="Deskripsi" type="textarea" :rows="2" :value="$row['description']" :maxlength="1000" />
                                    </div>
                                    <div class="col-md-6">
                                        <x-admin.form-field name="monthly_price" label="Harga Bulanan" type="number" :value="$row['monthly_price']" :min="0" required />
                                    </div>
                                    <div class="col-md-6">
                                        <x-admin.form-field name="yearly_price" label="Harga Tahunan" type="number" :value="$row['yearly_price']" :min="0" required />
                                    </div>
                                    @foreach (['max_products' => 'Produk', 'max_vendors' => 'Vendor', 'max_staff' => 'Staf', 'max_orders_per_month' => 'Pesanan / bulan', 'max_storage_mb' => 'Penyimpanan (MB)', 'pseo_page_quota' => 'Kuota PSEO', 'ai_request_quota' => 'Permintaan AI'] as $field => $label)
                                        <div class="col-md-6">
                                            <x-admin.form-field
                                                :name="$field"
                                                :label="$label"
                                                type="number"
                                                :value="collect($row['limits'])->firstWhere('label', $label)['value'] ?? null"
                                                :min="0"
                                                help="Kosongkan untuk tidak terbatas."
                                            />
                                        </div>
                                    @endforeach
                                    <div class="col-12 d-flex gap-3">
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="is_active" value="0">
                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="plan-active-{{ $row['id'] }}" @checked($row['is_active'])>
                                            <label class="form-check-label" for="plan-active-{{ $row['id'] }}">Aktif</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="is_featured" value="0">
                                            <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="plan-featured-{{ $row['id'] }}" @checked($row['is_featured'])>
                                            <label class="form-check-label" for="plan-featured-{{ $row['id'] }}">Paket unggulan</label>
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
                            <td colspan="7">
                                <x-admin.empty-state icon="credit-card" title="Belum ada paket" text="Tambahkan paket pertama untuk menetapkan batas penggunaan tenant." />
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

    <x-admin.modal id="plan-modal" title="Tambah Paket" icon="plus" size="lg">
        <form method="POST" action="{{ route('admin.saas.plans.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-8">
                    <x-admin.form-field name="name" label="Nama Paket" required :maxlength="120" />
                </div>
                <div class="col-md-4">
                    <x-admin.form-field name="slug" label="Slug" :maxlength="120" help="Dibuat otomatis dari nama bila dikosongkan." />
                </div>
                <div class="col-12">
                    <x-admin.form-field name="description" label="Deskripsi" type="textarea" :rows="2" :maxlength="1000" />
                </div>
                <div class="col-md-6">
                    <x-admin.form-field name="monthly_price" label="Harga Bulanan" type="number" value="0" :min="0" required />
                </div>
                <div class="col-md-6">
                    <x-admin.form-field name="yearly_price" label="Harga Tahunan" type="number" value="0" :min="0" required />
                </div>
                @foreach (['max_products' => 'Produk', 'max_vendors' => 'Vendor', 'max_staff' => 'Staf', 'max_orders_per_month' => 'Pesanan / bulan', 'max_storage_mb' => 'Penyimpanan (MB)', 'pseo_page_quota' => 'Kuota PSEO', 'ai_request_quota' => 'Permintaan AI'] as $field => $label)
                    <div class="col-md-6">
                        <x-admin.form-field :name="$field" :label="$label" type="number" :min="0" help="Kosongkan untuk tidak terbatas." />
                    </div>
                @endforeach
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="plan-new-active" checked>
                        <label class="form-check-label" for="plan-new-active">Aktif</label>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Paket</button>
            </div>
        </form>
    </x-admin.modal>
@endsection
