@extends('layouts.admin')
@section('title','Ticket #'.$ticket->id)
@section('content')
<div class="mb-4"><a href="{{ route('admin.support-tickets.index') }}" class="small"><x-admin.icon name="arrow-left" :size="16" class="me-1" />Kembali</a><h4 class="fw-bold mt-2">{{ $ticket->subject }}</h4></div>
<div class="row g-4"><div class="col-lg-8"><x-admin.card :padding="false" class="mb-3"><div class="card-body p-4"><p>{{ $ticket->description }}</p></div></x-admin.card>@if($ticket->replies->count())<x-admin.card :padding="false"><div class="card-body p-3">@foreach($ticket->replies as $r)<div class="border-bottom pb-2 mb-2 small"><span class="fw-semibold">{{ $r->user->name ?? '-' }}</span> · {{ $r->created_at->format('d/m/Y H:i') }}<p class="mb-0 mt-1">{{ $r->message }}</p></div>@endforeach</div></x-admin.card>@endif</div>
<div class="col-lg-4"><x-admin.card :padding="false"><div class="card-body"><h6 class="fw-bold mb-3">Perbarui</h6>
<form action="{{ route('admin.support-tickets.update',$ticket) }}" method="POST">@csrf @method('PUT')
<div class="mb-2"><select name="status" class="form-select form-select-sm"><option value="open" {{ $ticket->status==='open'?'selected' : '' }}>Open</option><option value="in_progress" {{ $ticket->status==='in_progress'?'selected' : '' }}>In Progress</option><option value="resolved" {{ $ticket->status==='resolved'?'selected' : '' }}>Resolved</option><option value="closed" {{ $ticket->status==='closed'?'selected' : '' }}>Closed</option></select></div>
<div class="mb-2"><textarea name="message" class="form-control form-control-sm" rows="3" placeholder="Balasan..."></textarea></div>
<button class="btn btn-primary w-100"><x-admin.icon name="undo" :size="16" class="me-1" />Perbarui</button>
</form></div></x-admin.card></div></div>
@endsection
