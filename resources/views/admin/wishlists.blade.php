@extends('layouts.admin')

@section('title', 'Favorit Pelanggan')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Pelanggan', ['label' => 'Favorit']]" />
@endsection

@section('content')
    <x-admin.page-header title="Favorit" subtitle="Produk yang ditandai pelanggan, berguna untuk menyusun merchandising." />

    <div class="row g-3 mb-3">
        @foreach ($kpis as $kpi)
            <div class="col-6 col-xl-3">
                <x-admin.stat :label="$kpi['label']" :value="$kpi['value']" :money="$kpi['money'] ?? false" :icon="$kpi['icon']" :color="$kpi['color']" :hint="$kpi['hint']" />
            </div>
        @endforeach
    </div>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.wishlists.index')"
            :filters="[['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Nama pelanggan atau produk']]"
        />
    </x-admin.card>

    <div class="row g-3">
        <div class="col-lg-8">
            <x-admin.card title="Produk Favorit" icon="heart" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Produk</th>
                                <th scope="col">Pelanggan</th>
                                <th scope="col" class="text-end">Harga</th>
                                <th scope="col" class="text-center">Stok</th>
                                <th scope="col">Ditandai</th>
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
                                    <td class="text-end fw-semibold">{{ $row['price_formatted'] }}</td>
                                    <td class="text-center">
                                        <x-admin.badge
                                            :text="number_format($row['stock'], 0, ',', '.')"
                                            :color="$row['stock'] <= 0 ? 'danger' : 'success'"
                                            pill
                                        />
                                    </td>
                                    <td class="text-nowrap">{{ $row['at'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <x-admin.empty-state icon="heart" title="Belum ada favorit" text="Tidak ada produk yang ditandai pelanggan." />
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
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Produk Paling Disukai" icon="award">
                <x-admin.chart
                    type="bar"
                    horizontal
                    :labels="array_map(fn (array $row): string => \Illuminate\Support\Str::limit($row['name'], 26), $top_products)"
                    :data="[['label' => 'Favorit', 'data' => array_column($top_products, 'count')]]"
                    :height="300"
                />
            </x-admin.card>
        </div>
    </div>
@endsection
