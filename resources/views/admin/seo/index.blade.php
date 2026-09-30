@extends('layouts.admin')

@section('title', 'SEO')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Content', ['label' => 'SEO']]" />
@endsection

@section('content')
    <x-admin.page-header title="Pengaturan SEO" subtitle="Metadata global, data terstruktur, dan status sitemap." />

    {{-- Tab locale ID/EN (tampilan saja; fallback ID bila EN kosong) --}}
    <ul class="nav nav-tabs mb-3" data-locale-tabs role="tablist">
        <li class="nav-item" role="presentation"><button type="button" class="nav-link active" data-locale-tab="id" role="tab">ID</button></li>
        <li class="nav-item" role="presentation"><button type="button" class="nav-link" data-locale-tab="en" role="tab">EN</button></li>
        <li class="nav-item ms-auto d-flex align-items-center"><span class="text-muted small">Fallback: ID</span></li>
    </ul>
    <div data-locale-panel="en" class="alert alert-info d-none">Meta EN mengikuti meta ID bila terjemahan kosong (fallback ID).</div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Produk Terindeks" :value="number_format($counts['products'], 0, ',', '.')" icon="package" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Kategori & Brand" :value="number_format($counts['categories'] + $counts['brands'], 0, ',', '.')" icon="category" color="info" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Halaman PSEO Terbit" :value="number_format($counts['pseo_published'], 0, ',', '.')" icon="layers" color="success" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Redirect Aktif" :value="number_format($counts['redirects'], 0, ',', '.')" icon="corner-up-right" color="warning" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <x-admin.card title="Metadata Global" icon="search" class="mb-3">
                <form method="POST" action="{{ route('admin.seo.update') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <x-admin.form-field name="seo_title" label="Judul Situs" :value="$settings['seo_title']" :maxlength="120" />
                        </div>
                        <div class="col-12">
                            <x-admin.form-field name="seo_description" label="Deskripsi Situs" type="textarea" :rows="2" :value="$settings['seo_description']" :maxlength="320" />
                        </div>
                        <div class="col-12">
                            <x-admin.form-field name="seo_keywords" label="Kata Kunci" :value="$settings['seo_keywords']" :maxlength="255" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form-field name="seo_og_image" label="Gambar Open Graph" type="url" :value="$settings['seo_og_image']" :maxlength="500" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="seo-robots">Petunjuk Robots</label>
                            <select class="form-select" id="seo-robots" name="seo_robots">
                                @foreach (['index,follow' => 'Index, Follow', 'noindex,follow' => 'Noindex, Follow', 'index,nofollow' => 'Index, Nofollow', 'noindex,nofollow' => 'Noindex, Nofollow'] as $value => $label)
                                    <option value="{{ $value }}" @selected($settings['seo_robots'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <x-admin.form-field name="seo_canonical_host" label="Host Kanonik" type="url" :value="$settings['seo_canonical_host']" :maxlength="160" />
                        </div>
                    </div>

                    <hr class="my-4">

                    <h3 class="fw-semibold mb-3">Data Terstruktur</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-admin.form-field name="seo_organization_name" label="Nama Organisasi" :value="$settings['seo_organization_name']" :maxlength="120" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form-field name="seo_organization_logo" label="Logo Organisasi" type="url" :value="$settings['seo_organization_logo']" :maxlength="500" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form-field name="seo_verification_google" label="Verifikasi Google" :value="$settings['seo_verification_google']" :maxlength="255" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form-field name="seo_verification_bing" label="Verifikasi Bing" :value="$settings['seo_verification_bing']" :maxlength="255" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form-field name="seo_twitter_site" label="Akun Twitter" :value="$settings['seo_twitter_site']" :maxlength="60" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form-field name="seo_indexnow_key" label="Kunci IndexNow" :value="$settings['seo_indexnow_key']" :maxlength="255" help="Digunakan untuk pemberitahuan perubahan URL." />
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary">
                            <x-admin.icon name="save" :size="14" /> Simpan Metadata
                        </button>
                    </div>
                </form>
            </x-admin.card>
        </div>

        <div class="col-lg-5">
            <x-admin.card title="Status Sitemap" icon="file-text" class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <x-admin.badge
                        :text="$sitemap['index_exists'] ? ($sitemap['stale'] ? 'Perlu Dijalankan Ulang' : 'Aktual') : 'Belum Dibuat'"
                        :color="$sitemap['index_exists'] ? ($sitemap['stale'] ? 'warning' : 'success') : 'secondary'"
                        pill
                    />
                    <a href="{{ route('admin.seo.sitemaps') }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                </div>
                <dl class="row small mb-0">
                    @foreach ($sitemap['counts'] as $key => $bucket)
                        <dt class="col-7 text-secondary">{{ \Illuminate\Support\Str::headline($key) }}</dt>
                        <dd class="col-5 text-end">{{ number_format($bucket['count'], 0, ',', '.') }}</dd>
                    @endforeach
                    <dt class="col-7 text-secondary fw-semibold">Total URL</dt>
                    <dd class="col-5 text-end fw-semibold">{{ number_format($sitemap['total_urls'], 0, ',', '.') }}</dd>
                </dl>
            </x-admin.card>

            <x-admin.card title="Halaman PSEO Skor Tertinggi" icon="layers" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Halaman</th>
                                <th scope="col" class="text-center">Skor</th>
                                <th scope="col" class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($topPages as $page)
                                <tr>
                                    <td>
                                        {{ $page['title'] }}
                                        <small class="d-block text-secondary">{{ $page['product_count'] }} produk</small>
                                    </td>
                                    <td class="text-center">
                                        <x-admin.badge :text="(string) $page['quality_score']" :color="$page['quality_score'] >= 70 ? 'success' : 'warning'" pill />
                                    </td>
                                    <td class="text-center">
                                        <x-admin.badge :text="strtoupper($page['indexability'])" :color="$page['indexability'] === 'index' ? 'success' : 'secondary'" pill />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <x-admin.empty-state compact icon="layers" title="Belum ada halaman PSEO" text="Belum ada halaman yang dihasilkan." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-end">
                    <a href="{{ route('admin.pseo.index') }}" class="btn btn-sm btn-primary">Kelola PSEO</a>
                </div>
            </x-admin.card>
        </div>
    </div>
@endsection
