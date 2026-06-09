@extends('layouts.admin')

@section('title', 'Laporan & AI Analisis')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-chart-bar me-2 text-primary"></i> Laporan & AI Analisis</h4>
        <p class="text-muted small mb-0">Analisis performa marketplace dengan AI</p>
    </div>
</div>

{{-- Stats Overview --}}
<div class="row g-3 mb-4">
    @php
    $stats = [
        ['label' => 'Total Pendapatan', 'value' => 'Rp ' . number_format(\App\Models\Transaction::where('status','success')->sum('amount'),0,',','.'), 'icon' => 'fa-money-bill-wave', 'color' => 'primary'],
        ['label' => 'Total Pesanan', 'value' => number_format(\App\Models\Order::count()), 'icon' => 'fa-shopping-cart', 'color' => 'success'],
        ['label' => 'Total Produk', 'value' => number_format(\App\Models\Product::where('status','approved')->count()), 'icon' => 'fa-box', 'color' => 'warning'],
        ['label' => 'Total Vendor', 'value' => number_format(\App\Models\Shop::where('status','active')->count()), 'icon' => 'fa-store', 'color' => 'info'],
    ];
    @endphp
    @foreach($stats as $s)
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-4 shadow-sm p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div><div class="stat-label small text-muted">{{ $s['label'] }}</div><div class="fw-bold fs-5">{{ $s['value'] }}</div></div>
                <span class="badge bg-{{ $s['color'] }}-subtle text-{{ $s['color'] }} rounded-3 p-2"><i class="fas {{ $s['icon'] }}"></i></span>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- AI Analysis Section --}}
<div class="card border-0 rounded-4 shadow-sm mb-4">
    <div class="card-header bg-transparent border-0 pt-3 px-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h6 class="fw-bold mb-0"><i class="fas fa-robot me-2 text-purple"></i> AI Analisis — Produk Paling Laris</h6>
            <div class="d-flex gap-2 align-items-center">
                @if($aiProviders->count() > 0)
                <select id="aiProvider" class="form-select form-select-sm" style="width:200px;">
                    @foreach($aiProviders as $ap)
                    <option value="{{ $ap->id }}" data-model="{{ $ap->config['default_model'] ?? '' }}">{{ $ap->name }}</option>
                    @endforeach
                </select>
                <select id="aiModel" class="form-select form-select-sm" style="width:200px;">
                    <option value="">Default model</option>
                </select>
                <button type="button" id="fetchModelsBtn" class="btn btn-sm btn-outline-secondary" title="Ambil daftar model dari API"><i class="fas fa-sync-alt"></i></button>
                <button type="button" id="runAiBtn" class="btn btn-sm btn-primary px-3">
                    <i class="fas fa-brain me-1"></i> Analisis
                </button>
                @else
                <span class="text-muted small">Belum ada AI provider. <a href="{{ route('admin.providers.create') }}">Tambah di Integrasi <i class="fas fa-plug"></i></a></span>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div id="aiLoading" class="text-center py-5 d-none">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <p class="text-muted">AI sedang menganalisis data penjualan...</p>
            <small class="text-muted" id="aiModelName"></small>
        </div>
        <div id="aiResult" class="p-4 d-none">
            <div class="bg-light rounded-4 p-4" id="aiContent" style="font-size:.9rem; line-height:1.8;"></div>
            <div class="d-flex justify-content-between mt-2">
                <small class="text-muted" id="aiMeta"></small>
                <small class="text-muted">Powered by AI · Data 30 hari terakhir</small>
            </div>
        </div>
        <div id="aiEmpty" class="text-center py-5 text-muted">
            @if($aiProviders->count() > 0)
            <i class="fas fa-robot fa-3x mb-3 opacity-25"></i>
            <p>Klik tombol <strong>Analisis</strong> untuk meminta AI menganalisis produk paling laris, tren penjualan, dan memberikan rekomendasi.</p>
            @else
            <i class="fas fa-plug fa-3x mb-3 opacity-25"></i>
            <p>Tambahkan AI provider dulu di menu <strong>Integrasi</strong> (DeepSeek, OpenAI, Groq, Ollama, dll).</p>
            <a href="{{ route('admin.providers.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> Tambah AI Provider</a>
            @endif
        </div>
    </div>
</div>

{{-- Top Products Table --}}
<div class="card border-0 rounded-4 shadow-sm">
    <div class="card-header bg-transparent border-0 pt-3 px-4">
        <h6 class="fw-bold mb-0"><i class="fas fa-trophy me-2 text-warning"></i> Produk Paling Laris (30 Hari)</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>Produk</th><th>Toko</th><th>Kategori</th><th>Terjual</th><th>Pendapatan</th><th>Harga</th></tr>
            </thead>
            <tbody>
                @php
                $topProducts = \App\Models\Product::where('status','approved')
                    ->withCount(['orderItems as sold' => function($q){
                        $q->whereHas('order', fn($o)=>$o->whereIn('order_status',['delivered','shipped','processing','confirmed'])->where('created_at','>=',now()->subDays(30)));
                    }])
                    ->withSum(['orderItems as revenue' => function($q){
                        $q->whereHas('order', fn($o)=>$o->whereIn('order_status',['delivered','shipped','processing','confirmed'])->where('created_at','>=',now()->subDays(30)));
                    }],'sub_total')
                    ->orderByDesc('revenue')
                    ->take(15)
                    ->with(['shop','category'])
                    ->get();
                @endphp
                @forelse($topProducts as $i => $p)
                <tr>
                    <td class="fw-bold text-muted">{{ $i + 1 }}</td>
                    <td class="fw-medium">{{ Str::limit($p->name, 50) }}</td>
                    <td><small>{{ $p->shop->name ?? '-' }}</small></td>
                    <td><small>{{ $p->category->name ?? '-' }}</small></td>
                    <td>{{ $p->sold }}</td>
                    <td class="fw-semibold">Rp {{ number_format($p->revenue ?? 0, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($p->price, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center py-5 text-muted">Belum ada data penjualan</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
document.getElementById('runAiBtn')?.addEventListener('click', async function() {
    const providerId = document.getElementById('aiProvider').value;
    const model = document.getElementById('aiModel').value;
    const btn = this;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menganalisis...';
    document.getElementById('aiLoading').classList.remove('d-none');
    document.getElementById('aiResult').classList.add('d-none');
    document.getElementById('aiEmpty').classList.add('d-none');
    document.getElementById('aiModelName').textContent = model ? 'Model: ' + model : 'Model default';

    try {
        const res = await fetch('{{ route("admin.reports.ai") }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({provider_id: providerId, model: model || null})
        });

        const data = await res.json();

        if (data.success) {
            document.getElementById('aiContent').innerHTML = marked.parse(data.content);
            document.getElementById('aiMeta').textContent = 'Model: ' + data.model + ' · Tokens: ' + (data.tokens?.total_tokens || '-');
            document.getElementById('aiLoading').classList.add('d-none');
            document.getElementById('aiResult').classList.remove('d-none');
        } else {
            alert('Error: ' + (data.error || 'Gagal'));
            document.getElementById('aiLoading').classList.add('d-none');
            document.getElementById('aiEmpty').classList.remove('d-none');
        }
    } catch(e) {
        alert('Error: ' + e.message);
        document.getElementById('aiLoading').classList.add('d-none');
        document.getElementById('aiEmpty').classList.remove('d-none');
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-brain me-1"></i> Analisis';
});

document.getElementById('fetchModelsBtn')?.addEventListener('click', async function() {
    const providerId = document.getElementById('aiProvider').value;
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    try {
        const res = await fetch('{{ route("admin.reports.fetch-models") }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({provider_id: providerId})
        });
        const data = await res.json();
        const select = document.getElementById('aiModel');
        select.innerHTML = '<option value="">Default model</option>';
        if (data.success && data.models) {
            data.models.forEach(m => select.innerHTML += `<option value="${m}">${m}</option>`);
            alert(data.models.length + ' model berhasil diambil!');
        } else {
            alert('Gagal mengambil model: ' + (data.error || 'unknown'));
        }
    } catch(e) {
        alert('Error: ' + e.message);
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-sync-alt"></i>';
});
</script>
@endpush
