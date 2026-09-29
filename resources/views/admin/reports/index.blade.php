@extends('layouts.admin')

@section('title', 'Laporan dan AI Analisis')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Analitik', ['label' => 'Laporan AI']]" />
@endsection

@section('content')
    <x-admin.page-header title="Laporan dan AI Analisis" subtitle="Ringkasan performa dengan bantuan AI.">
        <x-slot:actions>
            <a href="{{ route('admin.analytics.index') }}" class="btn btn-outline-secondary btn-sm">Dasbor Analitik</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.alert type="warning" title="Output AI bersifat consultatif" icon="alert-triangle">
        Teks yang dihasilkan AI adalah saran. Teks itu tidak pernah menjalankan aksi keuangan, mengubah status pesanan, atau menulis ke database.
    </x-admin.alert>

    <div class="row g-3 mb-3">
        @foreach ($stats as $stat)
            <div class="col-6 col-xl-3">
                <x-admin.stat :label="$stat['label']" :value="$stat['value']" :icon="$stat['icon']" :color="$stat['color']" />
            </div>
        @endforeach
    </div>

    <x-admin.card class="mb-3" title="Analisis AI" icon="sparkles" subtitle="Data {{ $windowDays }} hari terakhir.">
        @if ($aiProviders->isEmpty())
            <x-admin.empty-state
                icon="plug"
                title="Belum ada AI provider"
                text="Tambahkan provider dengan tipe AI di menu Integrasi untuk mengaktifkan analisis."
                action-label="Tambah AI Provider"
                action-url="{{ route('admin.providers.create') }}"
            />
        @else
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="ai-provider">Provider</label>
                    <select class="form-select" id="ai-provider">
                        @foreach ($aiProviders as $provider)
                            <option value="{{ $provider->id }}" data-model="{{ is_array($provider->config) ? ($provider->config['default_model'] ?? '') : '' }}">{{ $provider->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="ai-model">Model</label>
                    <input type="text" class="form-control" id="ai-model" placeholder="Kosongkan untuk model bawaan">
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary flex-fill" id="fetch-models">
                        <x-admin.icon name="refresh" :size="14" /> Ambil Model
                    </button>
                    <button type="button" class="btn btn-primary flex-fill" id="run-analysis">
                        <x-admin.icon name="sparkles" :size="14" /> Analisis
                    </button>
                </div>
            </div>
        @endif

        <div class="mt-3" aria-live="polite">
            <div id="ai-loading" class="d-none d-flex align-items-center gap-2 py-4">
                <span class="spinner-border spinner-border-sm" role="status"></span>
                <span class="text-secondary small">AI sedang menganalisis…</span>
            </div>
            <div id="ai-error" class="d-none"></div>
            <div id="ai-output" class="d-none">
                <p class="small text-secondary mb-2" id="ai-meta"></p>
                <div class="border rounded-3 p-3" id="ai-content" style="white-space: pre-wrap;"></div>
            </div>
            <div id="ai-empty" class="py-4 text-center text-secondary small">
                Tekan Analisis untuk meminta AI merangkum performa penjualan.
            </div>
        </div>
    </x-admin.card>

    <x-admin.card class="mb-3" title="Produk Terlaris" icon="trophy" :subtitle="$windowDays.' hari terakhir'" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Produk</th>
                        <th scope="col">Toko</th>
                        <th scope="col">Kategori</th>
                        <th scope="col" class="text-end">Terjual</th>
                        <th scope="col" class="text-end">Pendapatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($topProducts as $product)
                        <tr>
                            <td>{{ \Illuminate\Support\Str::limit($product['name'], 60) }}</td>
                            <td>{{ $product['shop'] !== '' ? $product['shop'] : '-' }}</td>
                            <td>{{ $product['category'] !== '' ? $product['category'] : '-' }}</td>
                            <td class="text-end">{{ number_format($product['sold'], 0, ',', '.') }}</td>
                            <td class="text-end fw-semibold">{{ \App\Support\Currency::format($product['revenue']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-admin.empty-state compact icon="package" title="Belum ada penjualan" text="Belum ada penjualan pada periode ini." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div class="row g-3">
        <div class="col-lg-6">
            <x-admin.card title="Kategori Teratas" icon="category" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Kategori</th>
                                <th scope="col" class="text-end">Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($topCategories as $category)
                                <tr>
                                    <td>{{ $category['name'] }}</td>
                                    <td class="text-end fw-semibold">{{ \App\Support\Currency::format($category['revenue']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2"><x-admin.empty-state compact icon="category" title="Belum ada data" text="Belum ada penjualan berkategori." /></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
        <div class="col-lg-6">
            <x-admin.card title="Vendor Teratas" icon="store" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Toko</th>
                                <th scope="col" class="text-end">Pesanan</th>
                                <th scope="col" class="text-end">Omzet</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($topShops as $shop)
                                <tr>
                                    <td>{{ $shop['name'] }}</td>
                                    <td class="text-end">{{ number_format($shop['orders'], 0, ',', '.') }}</td>
                                    <td class="text-end fw-semibold">{{ \App\Support\Currency::format($shop['revenue']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3"><x-admin.empty-state compact icon="store" title="Belum ada data" text="Belum ada toko dengan penjualan." /></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var runButton = document.getElementById('run-analysis');
    var fetchButton = document.getElementById('fetch-models');
    var providerSelect = document.getElementById('ai-provider');
    var modelInput = document.getElementById('ai-model');

    if (!runButton || !providerSelect) {
        return;
    }

    var loading = document.getElementById('ai-loading');
    var output = document.getElementById('ai-output');
    var content = document.getElementById('ai-content');
    var meta = document.getElementById('ai-meta');
    var empty = document.getElementById('ai-empty');
    var errorBox = document.getElementById('ai-error');

    function showError(message) {
        errorBox.classList.remove('d-none');
        errorBox.innerHTML = '<div class="alert alert-warning mb-0">' + message + '</div>';
        loading.classList.add('d-none');
        output.classList.add('d-none');
        empty.classList.add('d-none');
    }

    runButton.addEventListener('click', function () {
        errorBox.classList.add('d-none');
        output.classList.add('d-none');
        empty.classList.add('d-none');
        loading.classList.remove('d-none');
        loading.classList.add('d-flex');
        runButton.disabled = true;

        fetch('{{ route('admin.reports.ai') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                provider_id: providerSelect.value,
                model: modelInput.value || null
            })
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) {
                    showError(data.error || 'Analisis gagal.');
                    return;
                }
                content.textContent = data.content;
                meta.textContent = 'Model: ' + (data.model || '-') + ' · token: ' + ((data.tokens && data.tokens.total_tokens) || '-') + ' · output bersifat consultatif';
                output.classList.remove('d-none');
            })
            .catch(function (error) {
                showError('Permintaan gagal: ' + error.message);
            })
            .finally(function () {
                loading.classList.add('d-none');
                loading.classList.remove('d-flex');
                empty.classList.toggle('d-none', !output.classList.contains('d-none'));
                runButton.disabled = false;
            });
    });

    fetchButton?.addEventListener('click', function () {
        fetchButton.disabled = true;
        fetch('{{ route('admin.reports.fetch-models') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ provider_id: providerSelect.value })
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) {
                    showError(data.error || 'Daftar model tidak dapat diambil.');
                    return;
                }
                modelInput.value = '';
                if (!modelInput.dataset.hint) {
                    modelInput.placeholder = (data.models || []).slice(0, 3).join(' | ') || 'Tidak ada model';
                }
                meta.textContent = (data.models || []).length + ' model tersedia dari provider.';
                output.classList.remove('d-none');
                content.textContent = '';
            })
            .catch(function (error) {
                showError('Permintaan gagal: ' + error.message);
            })
            .finally(function () {
                fetchButton.disabled = false;
            });
    });
})();
</script>
@endpush
