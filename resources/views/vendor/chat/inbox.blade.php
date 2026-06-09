@extends('layouts.vendor')
@section('title', 'Chat')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-comments me-2 text-primary"></i> Inbox</h4>
<div class="card border-0 rounded-4 shadow-sm"><div class="list-group list-group-flush">
@forelse($customers as $c)
<a href="{{ route('vendor.chat.messages', $c) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
    <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:44px;height:44px;"><i class="fas fa-user text-primary"></i></div>
    <div class="flex-grow-1"><div class="fw-semibold">{{ $c->name }}</div><small class="text-muted">{{ $c->email }} · {{ $c->orders->count() }} pesanan</small></div>
    <i class="fas fa-chevron-right text-muted"></i>
</a>
@empty
<div class="text-center py-5 text-muted"><i class="fas fa-comments fa-3x mb-3 opacity-25"></i><p>Belum ada customer</p></div>
@endforelse
</div></div>
@endsection
