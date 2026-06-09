@extends('layouts.storefront')
@section('title', 'Tiket #'.$ticket->id)
@section('content')
<div class="container" style="max-width:700px;">
<a href="{{ route('tickets.index') }}" class="small"><i class="fas fa-arrow-left me-1"></i>Kembali</a>
<h4 class="fw-bold mt-2 mb-1">{{ $ticket->subject }}</h4>
<p class="text-muted small">Status: <span class="badge bg-warning-subtle text-warning">{{ $ticket->status }}</span> · {{ $ticket->created_at->format('d/m/Y H:i') }}</p>
<div class="card border-0 shadow-sm rounded-4 mb-3"><div class="card-body p-4"><div class="bg-light rounded-3 p-3">{{ $ticket->description }}</div></div></div>
@if($ticket->replies->count()>0)
<div class="card border-0 shadow-sm rounded-4 mb-3"><div class="card-body p-3"><h6 class="fw-bold mb-3">Balasan ({{ $ticket->replies->count() }})</h6>@foreach($ticket->replies as $r)<div class="border-bottom pb-3 mb-3"><div class="d-flex justify-content-between small"><span class="fw-semibold">{{ $r->user->name ?? '-' }}</span><span class="text-muted">{{ $r->created_at->format('d/m/Y H:i') }}</span></div><p class="mb-0 mt-1">{{ $r->message }}</p></div>@endforeach</div></div>
@endif
<div class="card border-0 shadow-sm rounded-4"><div class="card-body p-3"><form action="{{ route('tickets.reply', $ticket) }}" method="POST">@csrf<div class="mb-2"><textarea name="message" class="form-control" rows="3" placeholder="Tulis balasan..."></textarea></div><button class="btn btn-primary"><i class="fas fa-reply me-1"></i>Kirim Balasan</button></form></div></div>
</div>
@endsection
