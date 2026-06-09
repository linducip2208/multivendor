@extends('layouts.storefront')
@section('title', 'Tiket Support')
@section('content')
<div class="container">
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-headset me-2 text-warning"></i> Tiket Support</h4>
    <a href="{{ route('tickets.create') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i> Buat Tiket</a>
</div>
@if($tickets->count()>0)
<div class="row g-3">@foreach($tickets as $t)
<div class="col-12"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-start"><div><a href="{{ route('tickets.show', $t) }}" class="fw-bold text-decoration-none">{{ $t->subject }}</a><div class="small text-muted">{{ ucfirst($t->type) }} · {{ ucfirst($t->priority) }}</div></div><span class="badge bg-{{ ['open'=>'warning','in_progress'=>'info','resolved'=>'success','closed'=>'secondary'][$t->status] }}-subtle">{{ $t->status }}</span></div></div></div></div>
@endforeach</div>{{ $tickets->links() }}
@else<div class="text-center py-5"><i class="fas fa-headset fa-4x text-muted mb-3 opacity-25"></i><h5>Belum ada tiket</h5></div>@endif
</div>
@endsection
