@extends('layouts.admin')

@section('title', 'Sitemap')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Content', ['label' => 'SEO', 'href' => route('admin.seo.index')], ['label' => 'Sitemap']]" />
@endsection

@section('content')
    <x-admin.page-header title="Sitemap" subtitle="Daftar URL yang seharusnya muncul di sitemap, dan berkas yang sudah terbit." />

    {{-- Tab locale ID/EN (tampilan saja; URL kanonik + fallback ID) --}}
    <ul class="nav nav-tabs mb-3" data-locale-tabs role="tablist">
        <li class="nav-item" role="presentation"><button type="button" class="nav-link active" data-locale-tab="id" role="tab">ID</button></li>
        <li class="nav-item" role="presentation"><button type="button" class="nav-link" data-locale-tab="en" role="tab">EN</button></li>
        <li class="nav-item ms-auto d-flex align-items-center"><span class="text-muted small">Fallback: ID</span></li>
    </ul>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat
                label="Status"
                :value="$status['index_exists'] ? ($status['stale'] ? 'Perlu Diperbarui' : 'Aktual') : 'Belum Dibuat'"
                icon="file-text"
                :color="$status['index_exists'] ? ($status['stale'] ? 'warning' : 'success') : 'secondary'"
            />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total URL" :value="number_format($status['total_urls'], 0, ',', '.')" icon="list" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Ukuran Index" :value="number_format($status['index_bytes'] / 1024, 1, ',', '.').' KB'" icon="save" color="info" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Terakhir Dipublikasikan" :value="$status['published_at'] !== '' ? $status['published_at'] : 'Belum pernah'" icon="clock" color="secondary" />
        </div>
    </div>

    <x-admin.alert type="info" :dismissible="false" title="Sitemap dibuat oleh perintah terjadwal" icon="info">
        Layar ini hanya melaporkan; halaman ini tidak menulis berkas saat dibuka. Jalankan <code>php artisan sitemap:generate</code> dari scheduler untuk memperbarui isi.
    </x-admin.alert>

    <div class="row g-3">
        <div class="col-lg-6">
            <x-admin.card title="Rincian URL" icon="list" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Jenis</th>
                                <th scope="col" class="text-end">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($status['counts'] as $key => $bucket)
                                <tr>
                                    <td>{{ \Illuminate\Support\Str::headline($key) }}</td>
                                    <td class="text-end">{{ number_format($bucket['count'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <th scope="row">Total</th>
                                <td class="text-end">{{ number_format($status['total_urls'], 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-6">
            <x-admin.card title="Berkas Sitemap" icon="folder" flush class="mb-3">
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Berkas</th>
                                <th scope="col" class="text-end">Ukuran</th>
                                <th scope="col">Diubah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sitemaps as $file)
                                <tr>
                                    <td><code>{{ $file['name'] }}</code></td>
                                    <td class="text-end">{{ number_format($file['bytes'] / 1024, 1, ',', '.').' KB' }}</td>
                                    <td class="text-nowrap">{{ $file['modified'] ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <x-admin.empty-state compact icon="folder" title="Belum ada berkas sitemap" text="Belum ada sitemap.xml di direktori public." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>

            <x-admin.card title="robots.txt" icon="file-code">
                <x-admin.alert
                    :type="$robots['exists'] ? 'success' : 'warning'"
                    :dismissible="false"
                    :title="$robots['exists'] ? 'robots.txt ditemukan di public' : 'robots.txt belum ada di public'"
                />
                <pre class="small mb-0" style="max-height: 280px; overflow-y: auto; white-space: pre-wrap;">{{ $robots['content'] }}</pre>
            </x-admin.card>
        </div>
    </div>
@endsection
