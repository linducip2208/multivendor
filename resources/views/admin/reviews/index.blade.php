@extends('layouts.admin')

@section('title', 'Moderasi Ulasan')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Commerce', ['label' => 'Ulasan']]" />
@endsection

@section('content')
    <x-admin.page-header title="Moderasi Ulasan" subtitle="Tinjau ulasan pelanggan sebelum tampil di storefront.">
        <x-slot:actions>
            <div class="input-group input-group-sm" style="width: 280px;">
                <form method="GET" action="{{ route('admin.reviews.index') }}" class="d-flex" role="search">
                    @foreach (request()->except(['search', 'page']) as $key => $value)
                        @if (! is_array($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <input type="search" name="search" class="form-control" value="{{ request()->query('search') }}" placeholder="Cari ulasan atau produk" aria-label="Cari ulasan">
                </form>
            </div>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total Ulasan" :value="$counts['all']" icon="star" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Menunggu Moderasi" :value="$counts['pending']" icon="clock" color="warning" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Disetujui" :value="$counts['approved']" icon="check" color="success" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Rata-rata Rating" :value="number_format($average_rating, 2, ',', '.')" icon="award" color="info" hint="Hanya ulasan yang disetujui." />
        </div>
    </div>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.reviews.index')"
            :filters="[
                ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Isi ulasan atau nama produk'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['' => 'Semua', 'approved' => 'Disetujui', 'pending' => 'Menunggu']],
            ]"
        />
    </x-admin.card>

    <x-admin.card title="Daftar Ulasan" icon="star" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Produk</th>
                        <th scope="col">Pelanggan</th>
                        <th scope="col" class="text-center" style="width: 160px">Rating</th>
                        <th scope="col">Ulasan</th>
                        <th scope="col">Waktu</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row['product'] }}</td>
                            <td>
                                {{ $row['customer'] }}
                                <small class="d-block text-secondary">{{ $row['email'] }}</small>
                            </td>
                            <td class="text-center" aria-label="Rating {{ $row['rating'] }} dari 5">
                                <x-admin.badge
                                    :text="$row['rating'].' / 5'"
                                    :color="match(true) { $row['rating'] >= 4 => 'success', $row['rating'] >= 3 => 'warning', default => 'danger' }"
                                    pill
                                />
                            </td>
                            <td>
                                {{ $row['comment'] !== '' ? \Illuminate\Support\Str::limit($row['comment'], 160) : '<em class="text-secondary">Tanpa teks</em>' }}
                                @if ($row['images'] > 0)
                                    <small class="d-block text-secondary">{{ $row['images'] }} foto dilampirkan</small>
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $row['at'] }}</td>
                            <td class="text-center">
                                <x-admin.badge :text="$row['approved'] ? 'Tampil' : 'Disembunyikan'" :color="$row['approved'] ? 'success' : 'warning'" pill />
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group" aria-label="A moderatedasi {{ $row['product'] }}">
                                    <form method="POST" action="{{ route('admin.reviews.update', $row['id']) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="approved" value="{{ $row['approved'] ? 0 : 1 }}">
                                        <button type="submit" class="btn btn-outline-{{ $row['approved'] ? 'warning' : 'success' }}">
                                            {{ $row['approved'] ? 'Sembunyikan' : 'Setujui' }}
                                        </button>
                                    </form>
                                    <x-admin.confirmation-form
                                        :action="route('admin.reviews.destroy', $row['id'])"
                                        message="Ulasan akan dihapus permanen dari katalog. Lanjutkan?"
                                        label="Hapus"
                                        variant="outline-danger"
                                        icon="trash"
                                        size="btn-sm"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty-state icon="star" title="Belum ada ulasan" text="Ulasan pelanggan akan muncul di sini setelah dikirim." />
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
