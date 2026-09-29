@extends('layouts.admin')

@section('title', $campaign['name'])

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Pemasaran', ['label' => 'Kampanye', 'href' => route('admin.campaigns.index')], ['label' => \Illuminate\Support\Str::limit($campaign['name'], 40)]]" />
@endsection

@section('content')
    <x-admin.page-header :title="$campaign['name']" :subtitle="$campaign['type_label'].' · '.$campaign['status_label']">
        <x-slot:actions>
            <x-admin.badge :text="$campaign['live'] ? 'Sedang Berjalan' : 'Belum Aktif'" :color="$campaign['live'] ? 'success' : 'secondary'" pill />
            <a href="{{ route('admin.campaigns.edit', $campaign['id']) }}" class="btn btn-outline-secondary btn-sm">
                <x-admin.icon name="pencil" :size="14" /> Ubah
            </a>
            <form method="POST" action="{{ route('admin.campaigns.toggle', $campaign['id']) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-{{ $campaign['status'] === 'active' ? 'warning' : 'primary' }}">
                    <x-admin.icon name="{{ $campaign['status'] === 'active' ? 'pause' : 'play' }}" :size="14" />
                    {{ $campaign['status'] === 'active' ? 'Jeda' : 'Aktifkan' }}
                </button>
            </form>
            <x-admin.confirmation-form
                :action="route('admin.campaigns.destroy', $campaign['id'])"
                message="Kampanye beserta produk dan kategori terkait akan dihapus. Lanjutkan?"
                label="Hapus"
                variant="outline-danger"
                icon="trash"
                size="btn-sm"
            />
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Anggaran" :value="$performance['budget']" money icon="cash" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Omzet Kampanye" :value="$performance['revenue']" money icon="trending-up" color="success" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="ROAS" :value="number_format($performance['roas'], 1, ',', '.').'%'" icon="percent" color="info" hint="Omzet dibagi anggaran" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat
                label="Klik ke Konversi"
                :value="number_format($performance['ctr'], 2, ',', '.').'%'"
                icon="target"
                color="warning"
                :hint="number_format($performance['conversions'], 0, ',', '.').' dari '.number_format($performance['clicks'], 0, ',', '.').' klik'"
            />
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <x-admin.card title="Biaya per Konversi" icon="calculator" class="mb-3">
                <dl class="row small mb-0">
                    <dt class="col-7 text-secondary">Anggaran terpakai / konversi</dt>
                    <dd class="col-5 text-end fw-semibold">{{ $performance['cost_per_conversion_formatted'] }}</dd>
                    <dt class="col-7 text-secondary">Omzet per konversi</dt>
                    <dd class="col-5 text-end">{{ $performance['revenue_per_conversion_formatted'] }}</dd>
                    <dt class="col-7 text-secondary">Klik</dt>
                    <dd class="col-5 text-end">{{ number_format($performance['clicks'], 0, ',', '.') }}</dd>
                    <dt class="col-7 text-secondary">Konversi</dt>
                    <dd class="col-5 text-end">{{ number_format($performance['conversions'], 0, ',', '.') }}</dd>
                </dl>
            </x-admin.card>

            <x-admin.card title="Kuota" icon="layers" class="mb-3">
                <dl class="row small mb-0">
                    <dt class="col-7 text-secondary">Batas keseluruhan</dt>
                    <dd class="col-5 text-end">{{ $quota['limit'] === null ? 'Tak terbatas' : number_format($quota['limit'], 0, ',', '.') }}</dd>
                    <dt class="col-7 text-secondary">Terpakai</dt>
                    <dd class="col-5 text-end">{{ number_format($quota['used'], 0, ',', '.') }}</dd>
                    <dt class="col-7 text-secondary">Sisa</dt>
                    <dd class="col-5 text-end">{{ $quota['remaining'] === null ? '-' : number_format($quota['remaining'], 0, ',', '.') }}</dd>
                    <dt class="col-7 text-secondary">Per pelanggan</dt>
                    <dd class="col-5 text-end">{{ $quota['per_user_limit'] === null ? 'Tak terbatas' : number_format($quota['per_user_limit'], 0, ',', '.') }}</dd>
                </dl>
                @if ($quota['usage_percent'] !== null)
                    <div class="progress mt-3" role="progressbar" aria-valuenow="{{ $quota['usage_percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Porsi kuota terpakai">
                        <div class="progress-bar" style="width: {{ min(100, $quota['usage_percent']) }}%">{{ number_format($quota['usage_percent'], 1, ',', '.') }}%</div>
                    </div>
                @endif
            </x-admin.card>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Riwayat" icon="clock" class="mb-3">
                <x-admin.timeline :items="$timeline" />
            </x-admin.card>
            <x-admin.card title="Aturan Targeting" icon="filter">
                <dl class="row small mb-0">
                    @forelse ($rules as $rule)
                        <dt class="col-5 text-secondary">{{ $rule['label'] }}</dt>
                        <dd class="col-7 text-end">{{ $rule['value'] }}</dd>
                    @empty
                        <dd class="col-12 text-secondary mb-0">Tidak ada aturan tambahan. Hanya cakup produk/kategori yang berlaku.</dd>
                    @endforelse
                </dl>
            </x-admin.card>
        </div>

        <div class="col-lg-4">
            @isset($audience)
                <x-admin.card title="Segmen Sasaran" icon="layers" class="mb-3">
                    @if ($audience['terbuka'] ?? true)
                        <span class="text-secondary small">Terbuka untuk semua pelanggan.</span>
                    @else
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($audience['segments'] as $segmen)
                                <x-admin.badge :text="$segmen['name']" color="info" pill />
                            @endforeach
                        </div>
                    @endif
                </x-admin.card>
            @endisset

            <x-admin.card title="Kategori" icon="category" class="mb-3">
                <div class="d-flex flex-wrap gap-2">
                    @forelse ($categories as $category)
                        <x-admin.badge :text="$category['name']" color="primary" pill />
                    @empty
                        <span class="text-secondary small">Belum ada kategori.</span>
                    @endforelse
                </div>
            </x-admin.card>

            <x-admin.card title="Produk" icon="package" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Produk</th>
                                <th scope="col" class="text-end">Harga</th>
                                <th scope="col" class="text-center">Stok</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $product)
                                <tr>
                                    <td>
                                        {{ $product['name'] }}
                                        <small class="d-block text-secondary">{{ $product['sku'] }}</small>
                                    </td>
                                    <td class="text-end">{{ $product['price_formatted'] }}</td>
                                    <td class="text-center">
                                        <x-admin.badge
                                            :text="number_format($product['stock'], 0, ',', '.')"
                                            :color="$product['stock'] <= 0 ? 'danger' : 'secondary'"
                                            pill
                                        />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <x-admin.empty-state compact icon="package" title="Belum ada produk" text="Tambahkan produk dari halaman ubah kampanye." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
    </div>

    @if (($campaign['type'] ?? '') === 'cashback')
        <x-admin.card title="Cashback" icon="cash" class="mb-3">
            <dl class="row small mb-0">
                <dt class="col-5 text-secondary">Periode</dt>
                <dd class="col-7 text-end">{{ $campaign['starts_at'] !== '' ? $campaign['starts_at'] : 'Tanpa mulai' }} &ndash; {{ $campaign['ends_at'] !== '' ? $campaign['ends_at'] : 'Tanpa akhir' }}</dd>
                <dt class="col-5 text-secondary">Status live</dt>
                <dd class="col-7 text-end">{{ $campaign['live'] ? 'Berjalan' : 'Belum aktif' }}</dd>
                <dt class="col-5 text-secondary">Terpakai / kuota</dt>
                <dd class="col-7 text-end">{{ number_format($quota['used'] ?? 0, 0, ',', '.') }} / {{ ($quota['limit'] ?? null) === null ? 'Tak terbatas' : number_format($quota['limit'], 0, ',', '.') }}</dd>
                <dt class="col-5 text-secondary">Batas per pelanggan</dt>
                <dd class="col-7 text-end">{{ ($quota['per_user_limit'] ?? null) === null ? 'Tak terbatas' : number_format($quota['per_user_limit'], 0, ',', '.') }}</dd>
            </dl>
            <p class="small text-secondary mb-0 mt-2">
                Cashback dikredit idempoten ke dompet (kunci <code>cashback-{id}-order-{order}</code>) atau poin loyalitas,
                dan hanya berlaku di dalam periode kampanye.
            </p>
        </x-admin.card>
    @endif
@endsection
