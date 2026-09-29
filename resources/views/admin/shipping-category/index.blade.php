@extends('layouts.admin')
@section('title', 'Biaya Pengiriman Kategori')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><x-admin.icon name="truck" :size="16" class="me-2" />Biaya Kirim per Kategori</h4></div>
<x-admin.card :padding="false" style="max-width:600px">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.shipping-category.store') }}">
            @csrf
            <div class="mb-2"><label class="fw-medium small">Kategori</label>
                <select name="category_id" class="form-select" required>
                    <option value="">Pilih</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @foreach($cat->children as $child)
                    <option value="{{ $child->id }}">&nbsp;&nbsp;{{ $child->name }}</option>
                    @endforeach
                    @endforeach
                </select>
            </div>
            <div class="mb-2"><label class="fw-medium small">Metode Pengiriman</label>
                <select name="shipping_method_id" class="form-select" required>
                    @foreach($shippingMethods as $m)
                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-2"><label class="fw-medium small">Biaya Tambahan (Rp)</label><input type="number" name="cost" class="form-control" value="0" required></div>
            <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
        </form>
    </div>
</x-admin.card>
<x-admin.card :padding="false" class="mt-3">
    <div class="card-header bg-transparent"><h6 class="fw-bold mb-0">Daftar Harga Tersimpan</h6></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 small">
            <thead class="table-light"><tr><th>Kategori</th><th>Metode</th><th>Biaya</th><th></th></tr></thead>
            <tbody>
                @php $catSettings = \App\Models\SystemSetting::where('key','like','cat_shipping_%')->get(); @endphp
                @forelse($catSettings as $cs)
                    @php $parts = explode('_', str_replace('cat_shipping_','',$cs->key)); $catId = $parts[0]; $methodId = implode('_',array_slice($parts,1)); @endphp
                <tr><td>{{ \App\Models\Category::find($catId)?->name ?? '-' }}</td><td>{{ \App\Models\ShippingMethod::find((int)$methodId)?->name ?? $methodId }}</td><td>Rp {{ number_format($cs->value,0,',','.') }}</td><td><form method="POST" action="{{ route('admin.shipping-category.destroy') }}" class="d-inline">@csrf @method('DELETE')<input type="hidden" name="category_id" value="{{ $catId }}"><input type="hidden" name="shipping_method_id" value="{{ $methodId }}"><button class="btn btn-sm text-danger"><x-admin.icon name="trash" :size="16" /></button></form></td></tr>
                @empty
                <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.card>
@endsection
