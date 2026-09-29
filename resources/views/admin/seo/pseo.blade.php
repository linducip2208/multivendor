@extends('layouts.admin')

@section('title', 'PSEO')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Content', ['label' => 'SEO', 'href' => route('admin.seo.index')], ['label' => 'PSEO']]" />
@endsection

@section('content')
    <x-admin.page-header title="Programmatic SEO" subtitle="Halaman yang dihasilkan dari entitas katalog nyata, dengan gerbang kualitas.">
        <x-slot:actions>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#generate-modal" aria-haspopup="dialog">
                <x-admin.icon name="sparkles" :size="14" /> Generate
            </button>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#pseo-settings" aria-haspopup="dialog">
                <x-admin.icon name="sliders" :size="14" /> Pengaturan
            </button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.alert type="warning" :dismissible="false" title="Dua aturan yang tidak dapat dilewati" icon="alert-triangle">
        <ol class="mb-0 ps-3">
            <li>Tidak ada halaman yang dibuat untuk kombinasi tanpa produk nyata. Ambang minimum saat ini {{ $overview['settings']['min_products'] }} produk.</li>
            <li>Setiap halaman baru berstatus <code>noindex</code>. Halaman hanya menjadi <code>index</code> bila skornya mencapai {{ $overview['settings']['threshold'] }} <em>dan</em> administrator meninjaunya lalu menerbitkannya.</li>
        </ol>
    </x-admin.alert>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total Halaman" :value="$overview['pages']" icon="layers" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Diterbitkan" :value="$overview['published']" icon="check" color="success" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Dapat Diindeks" :value="$overview['indexable']" icon="search" color="info" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Sisa Kuota" :value="$overview['headroom']" icon="database" color="secondary" :hint="'Batas keras '.number_format($overview['settings']['max_pages'], 0, ',', '.')" />
        </div>
    </div>

    <x-admin.card title="Template" subtitle="Kombinasi entitas yang didukung engine PSEO." icon="layers" flush class="mb-3">
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Template</th>
                        <th scope="col">Pola Rute</th>
                        <th scope="col" class="text-center">Ambang Skor</th>
                        <th scope="col" class="text-end">Kandidat</th>
                        <th scope="col" class="text-end">Halaman</th>
                        <th scope="col" class="text-end">Diterbitkan</th>
                        <th scope="col" class="text-center">Aktif</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($overview['templates'] as $template)
                        <tr>
                            <td>
                                {{ $template['name'] }}
                                <small class="d-block text-secondary"><code>{{ $template['code'] }}</code></small>
                            </td>
                            <td><code class="small">{{ $template['route_pattern'] }}</code></td>
                            <td class="text-end">{{ $template['quality_threshold'] }}</td>
                            <td class="text-end">{{ number_format($template['candidates'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($template['pages'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($template['published'], 0, ',', '.') }}</td>
                            <td class="text-center">
                                <x-admin.badge :text="$template['is_enabled'] ? 'Aktif' : 'Nonaktif'" :color="$template['is_enabled'] ? 'success' : 'secondary'" pill />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <x-admin.card title="Rubrik Penilaian" subtitle="Setiap poin dihitung otomatis dari isi halaman." icon="award" class="mb-3">
        <div class="row g-2">
            @foreach ($overview['rubric'] as $key => $criterion)
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="border rounded-3 p-2 h-100">
                        <div class="d-flex justify-content-between">
                            <span class="small fw-semibold">{{ $criterion['label'] }}</span>
                            <x-admin.badge :text="$criterion['max'].' poin'" color="secondary" pill />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-admin.card>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.pseo.index')"
            :filters="[
                ['name' => 'state', 'label' => 'Status', 'type' => 'select', 'options' => [
                    '' => 'Semua status',
                    'generated' => 'Baru dihasilkan',
                    'reviewed' => 'Sudah ditinjau',
                    'published' => 'Diterbitkan',
                    'stale' => 'Kedaluwarsa',
                    'disabled' => 'Dinonaktifkan',
                ]],
                ['name' => 'template', 'label' => 'Template', 'type' => 'select', 'options' => array_merge(['' => 'Semua template'], array_column($overview['templates'], 'name', 'code'))],
            ]"
        />
    </x-admin.card>

    <x-admin.card title="Halaman" icon="file-text" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Judul &amp; URL</th>
                        <th scope="col">Template</th>
                        <th scope="col" class="text-center">Produk</th>
                        <th scope="col" class="text-center">Skor</th>
                        <th scope="col" class="text-center">Index</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pages['rows'] as $page)
                        <tr>
                            <td>
                                {{ $page['title'] }}
                                <small class="d-block text-secondary">{{ $page['url'] }}</small>
                            </td>
                            <td>{{ \Illuminate\Support\Str::headline($page['template_code']) }}</td>
                            <td class="text-end">{{ number_format($page['product_count'], 0, ',', '.') }}</td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="(string) $page['quality_score']"
                                    :color="$page['meets_threshold'] ? 'success' : 'warning'"
                                    pill
                                />
                                <small class="d-block text-secondary">ambang {{ $page['threshold'] }}</small>
                            </td>
                            <td class="text-center">
                                <x-admin.badge :text="strtoupper($page['indexability'])" :color="$page['indexability'] === 'index' ? 'success' : 'secondary'" pill />
                            </td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="$page['state']"
                                    :color="match($page['state']) { 'published' => 'success', 'reviewed' => 'info', 'disabled' => 'secondary', default => 'warning' }"
                                    pill
                                />
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Aksi halaman {{ $page['url'] }}">
                                    @if ($page['state'] === 'generated')
                                        <form method="POST" action="{{ route('admin.pseo.review', $page['id']) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-info">Tinjau</button>
                                        </form>
                                    @endif
                                    @if ($page['state'] === 'reviewed' || $page['state'] === 'generated')
                                        <form method="POST" action="{{ route('admin.pseo.publish', $page['id']) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success" @disabled(! $page['meets_threshold'])>Terbitkan</button>
                                        </form>
                                    @endif
                                    @if ($page['state'] === 'published')
                                        <form method="POST" action="{{ route('admin.pseo.disable', $page['id']) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-warning">Nonaktifkan</button>
                                        </form>
                                    @endif
                                    <x-admin.confirmation-form
                                        :action="route('admin.pseo.destroy', $page['id'])"
                                        message="Halaman ini akan dihapus permanen. Lanjutkan?"
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
                                <x-admin.empty-state
                                    icon="layers"
                                    title="Belum ada halaman PSEO"
                                    text="Jalankan generate untuk membuat halaman dari kombinasi entitas katalog yang memenuhi ambang minimum produk."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div class="mt-3">
        <x-admin.pagination :paginator="\App\Support\AdminPaginator::fromArray($pages['pagination'], $pages['pagination']['total'], $pages['pagination']['per_page'], $pages['pagination']['current_page'])" size="sm" />
    </div>

    <x-admin.modal id="generate-modal" title="Generate Halaman PSEO" icon="sparkles" size="sm">
        <form method="POST" action="{{ route('admin.pseo.generate') }}">
            @csrf
            <x-admin.alert type="info" :dismissible="false" title="Kombinasi tanpa produk dilewati">
                Hanya kombinasi yang memiliki minimal {{ $overview['settings']['min_products'] }} produk nyata yang akan dibuat. Semua halaman baru berstatus noindex.
            </x-admin.alert>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="pseo-template">Template</label>
                    <select class="form-select" id="pseo-template" name="template_code" required>
                        @foreach ($overview['templates'] as $template)
                            <option value="{{ $template['code'] }}">{{ $template['name'] }} ({{ number_format($template['candidates'], 0, ',', '.') }} kandidat)</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <x-admin.form-field name="limit" label="Maksimal Halaman" type="number" value="50" :min="1" :max="500" />
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Generate</button>
            </div>
        </form>
    </x-admin.modal>

    <x-admin.modal id="pseo-settings" title="Pengaturan PSEO" icon="sliders" size="sm">
        <form method="POST" action="{{ route('admin.pseo.update') }}">
            @csrf
            <div class="row g-3">
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="enabled" value="0">
                        <input class="form-check-input" type="checkbox" name="enabled" value="1" id="pseo-enabled" @checked($overview['settings']['enabled'])>
                        <label class="form-check-label" for="pseo-enabled">PSEO diaktifkan</label>
                    </div>
                    <div class="form-check form-switch mt-2">
                        <input type="hidden" name="auto_publish" value="0">
                        <input class="form-check-input" type="checkbox" name="auto_publish" value="1" id="pseo-auto" @checked($overview['settings']['auto_publish'])>
                        <label class="form-check-label" for="pseo-auto">Terbitkan otomatis bila skor mencapai ambang</label>
                    </div>
                </div>
                <div class="col-12">
                    <x-admin.form-field
                        name="quality_threshold"
                        label="Ambang Skor"
                        type="number"
                        :value="$overview['settings']['threshold']"
                        :min="0"
                        :max="100"
                        required
                        help="Halaman di bawah nilai ini tidak dapat diterbitkan, berapa pun jumlah tinjauan administrator."
                    />
                </div>
                <div class="col-6">
                    <x-admin.form-field name="min_products" label="Produk Minimum" type="number" :value="$overview['settings']['min_products']" :min="1" :max="200" required />
                </div>
                <div class="col-6">
                    <x-admin.form-field name="products_per_page" label="Produk per Halaman" type="number" :value="$overview['settings']['products_per_page']" :min="6" :max="48" required />
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </x-admin.modal>
@endsection
