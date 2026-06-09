@extends('layouts.admin')
@section('title', 'SEO Produk')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-search me-2 text-success"></i> SEO Meta Produk</h4>
<div class="card border-0 rounded-4 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Produk</th><th>Meta Title</th><th>Meta Description</th><th>Toko</th><th></th></tr></thead><tbody>@foreach($products as $p)
<tr><form action="{{ route('admin.product-seo.update', $p) }}" method="POST">@csrf @method('PUT')
<td class="fw-medium" style="width:200px;">{{ Str::limit($p->name, 30) }}</td>
<td><input type="text" name="meta_title" class="form-control form-control-sm" value="{{ $p->meta_title }}" placeholder="Meta title..."></td>
<td><input type="text" name="meta_description" class="form-control form-control-sm" value="{{ $p->meta_description }}" placeholder="Meta description..."></td>
<td><small>{{ $p->shop->name ?? '' }}</small></td>
<td><button class="btn btn-sm btn-primary">Simpan</button></td>
</form></tr>@endforeach
</tbody></table></div>@if($products->hasPages())<div class="p-3">{{ $products->links() }}</div>@endif</div>
@endsection
