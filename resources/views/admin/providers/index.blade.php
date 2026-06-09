@extends('layouts.admin')

@section('title', 'Integrasi Provider')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-plug me-2 text-info"></i> Integrasi Provider</h4>
        <p class="text-muted small mb-0">Payment gateway, shipping, AI — masing-masing dengan API key terpisah & terenkripsi</p>
    </div>
    <a href="{{ route('admin.providers.create') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i> Tambah Provider</a>
</div>

<div class="card border-0 rounded-4 shadow-sm">
    <div class="p-3 border-bottom">
        <div class="d-flex gap-2 flex-wrap">
            <a href="?" class="btn btn-sm btn-{{ !request('type') ? 'primary' : 'outline-secondary' }}">Semua</a>
            <a href="?type=payment" class="btn btn-sm btn-{{ request('type') === 'payment' ? 'primary' : 'outline-secondary' }}"><i class="fas fa-credit-card me-1"></i> Payment</a>
            <a href="?type=shipping" class="btn btn-sm btn-{{ request('type') === 'shipping' ? 'primary' : 'outline-secondary' }}"><i class="fas fa-truck me-1"></i> Shipping</a>
            <a href="?type=ai" class="btn btn-sm btn-{{ request('type') === 'ai' ? 'primary' : 'outline-secondary' }}"><i class="fas fa-robot me-1"></i> AI / LLM</a>
        </div>
    </div>

    @php
    $grouped = $providers->groupBy('type');
    $labels = ['payment' => ['Payment Gateway', 'credit-card', 'primary'], 'shipping' => ['Shipping / Kurir', 'truck', 'success'], 'ai' => ['AI / LLM', 'robot', 'purple']];
    @endphp

    @if(!request('type'))
        @foreach(['payment','shipping','ai'] as $type)
            @if(isset($grouped[$type]) && $grouped[$type]->count() > 0)
            <div class="p-3 bg-light border-bottom fw-semibold small text-uppercase text-muted">
                <i class="fas fa-{{ $labels[$type][1] }} me-2 text-{{ $labels[$type][2] }}"></i> {{ $labels[$type][0] }} ({{ $grouped[$type]->count() }})
            </div>
            @foreach($grouped[$type] as $p)
            <div class="border-bottom px-3 py-2 d-flex align-items-center justify-content-between provider-row">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-{{ $labels[$type][2] }}-subtle rounded-3 p-2"><i class="fas fa-{{ $labels[$type][1] }} text-{{ $labels[$type][2] }}"></i></div>
                    <div>
                        <div class="fw-semibold">{{ $p->name }} <code class="small bg-light px-1 rounded ms-1">{{ $p->api_format }}</code></div>
                        <small class="text-muted">Key: {{ $p->getMaskedKey() }} · {{ $p->base_url ? parse_url($p->base_url, PHP_URL_HOST) : '-' }}</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-{{ $p->is_active ? 'success' : 'secondary' }}-subtle">{{ $p->is_active ? 'ON' : 'OFF' }}</span>
                    <a href="{{ route('admin.providers.edit', $p) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                    <form action="{{ route('admin.providers.destroy', $p) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                </div>
            </div>
            @endforeach
            @endif
        @endforeach
        @if($providers->isEmpty())
        <div class="text-center py-5 text-muted"><i class="fas fa-plug fa-3x mb-3 opacity-25"></i><p>Belum ada provider. <a href="{{ route('admin.providers.create') }}">Tambah provider pertama</a></p></div>
        @endif
    @else
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Provider</th><th>Format</th><th>API Key</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($providers as $p)
                <tr>
                    <td><div class="fw-semibold">{{ $p->name }}</div><small class="text-muted">{{ parse_url($p->base_url, PHP_URL_HOST) ?? $p->base_url }}</small></td>
                    <td><code class="small bg-light px-2 py-1 rounded">{{ $p->api_format }}</code></td>
                    <td><small class="font-monospace text-muted">{{ $p->getMaskedKey() }}</small></td>
                    <td><span class="badge bg-{{ $p->is_active ? 'success' : 'secondary' }}-subtle">{{ $p->is_active ? 'Aktif' : 'Off' }}</span></td>
                    <td>
                        <a href="{{ route('admin.providers.edit', $p) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                        <form action="{{ route('admin.providers.destroy', $p) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-5 text-muted">Belum ada provider tipe ini. <a href="{{ route('admin.providers.create') }}">Tambah</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif
    @if($providers->hasPages())<div class="p-3">{{ $providers->links() }}</div>@endif
</div>
@endsection
