@extends('layouts.admin')
@section('title','VAT / Pajak')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-percent me-2 text-info"></i> VAT / Pajak</h4>
<div class="row g-4"><div class="col-md-5"><div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4"><form action="{{ route('admin.vat.store') }}" method="POST">@csrf
<div class="row g-2"><div class="col-7"><input type="text" name="name" class="form-control" placeholder="Nama pajak (PPN 11%)" required></div><div class="col-3"><input type="number" name="rate" class="form-control" step="0.01" placeholder="11" required></div><div class="col-2"><button class="btn btn-primary w-100"><i class="fas fa-plus"></i></button></div></div>
</form></div></div></div>
<div class="col-md-7"><div class="card border-0 rounded-4 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Nama</th><th>Rate</th><th>Status</th><th></th></tr></thead><tbody>@forelse(\App\Models\VatTax::all() as $v)<tr><td>{{ $v->name }}</td><td>{{ $v->rate }}%</td><td><span class="badge bg-{{ $v->is_active?'success' : 'secondary' }}-subtle">{{ $v->is_active?'Aktif' : 'Off' }}</span></td><td><form action="{{ route('admin.vat.destroy',$v) }}" method="POST">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form></td></tr>@empty<tr><td colspan="4" class="text-center py-4 text-muted">Belum ada pajak</td></tr>@endforelse</tbody></table></div></div></div></div>
@endsection
