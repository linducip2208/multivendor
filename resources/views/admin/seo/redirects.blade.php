@extends('layouts.admin')

@section('title', 'Redirects')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Content', ['label' => 'SEO', 'href' => route('admin.seo.index')], ['label' => 'Redirect']]" />
@endsection

@section('content')
    <x-admin.page-header title="Redirect" subtitle="Aturan pengalihan URL lama ke URL baru." />

    {{-- Tab locale ID/EN (tampilan saja; slug lama dialihkan via tabel redirects, fallback ID) --}}
    <ul class="nav nav-tabs mb-3" data-locale-tabs role="tablist">
        <li class="nav-item" role="presentation"><button type="button" class="nav-link active" data-locale-tab="id" role="tab">ID</button></li>
        <li class="nav-item" role="presentation"><button type="button" class="nav-link" data-locale-tab="en" role="tab">EN</button></li>
        <li class="nav-item ms-auto d-flex align-items-center"><span class="text-muted small">Fallback: ID</span></li>
    </ul>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total Aturan" :value="number_format($pagination['total'], 0, ',', '.')" icon="corner-up-right" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Aktif" :value="number_format($active_count, 0, ',', '.')" icon="check" color="success" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total Hit" :value="number_format($total_hits, 0, ',', '.')" icon="activity" color="info" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Halaman Ini" :value="count($rows)" icon="list" color="secondary" />
        </div>
    </div>

    <x-admin.card class="mb-3" title="Tambah Redirect" icon="plus">
        <form method="POST" action="{{ route('admin.seo.redirects.store') }}">
            @csrf
            <div class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <x-admin.form-field name="from_path" label="Dari Path" required placeholder="/produk-lama" :maxlength="255" help="Harus diawali / dan tanpa spasi." />
                </div>
                <div class="col-lg-4">
                    <x-admin.form-field name="to_path" label="Ke Path atau URL" required placeholder="/produk-baru" :maxlength="255" />
                </div>
                <div class="col-lg-2">
                    <label class="form-label" for="redirect-status">Kode Status</label>
                    <select class="form-select" id="redirect-status" name="status_code">
                        @foreach ([301, 302, 307, 308] as $code)
                            <option value="{{ $code }}" @selected($code === 301)>{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <div class="form-check mb-2">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="redirect-active" checked>
                        <label class="form-check-label" for="redirect-active">Aktif</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Simpan</button>
                </div>
            </div>
        </form>
    </x-admin.card>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.seo.redirects')"
            :filters="[['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Path sumber atau tujuan']]"
        />
    </x-admin.card>

    <x-admin.card title="Aturan Redirect" icon="corner-up-right" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Dari</th>
                        <th scope="col">Ke</th>
                        <th scope="col" class="text-center">Kode</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-end">Hit</th>
                        <th scope="col">Terakhir Dipakai</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td><code>{{ $row['from_path'] }}</code></td>
                            <td><code>{{ $row['to_path'] }}</code></td>
                            <td class="text-center"><x-admin.badge :text="(string) $row['status_code']" color="info" pill /></td>
                            <td class="text-center">
                                <x-admin.badge :text="$row['is_active'] ? 'Aktif' : 'Nonaktif'" :color="$row['is_active'] ? 'success' : 'secondary'" pill />
                            </td>
                            <td class="text-end">{{ number_format($row['hit_count'], 0, ',', '.') }}</td>
                            <td class="text-nowrap">{{ $row['last_hit_at'] !== '' ? $row['last_hit_at'] : '-' }}</td>
                            <td class="text-end">
                                <x-admin.confirmation-form
                                    :action="route('admin.seo.redirects.destroy', $row['id'])"
                                    message="Aturan redirect ini akan dihapus. Lanjutkan?"
                                    label="Hapus"
                                    variant="outline-danger"
                                    icon="trash"
                                    size="btn-sm"
                                />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty-state icon="corner-up-right" title="Belum ada redirect" text="Tambahkan aturan pertama di atas." />
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
