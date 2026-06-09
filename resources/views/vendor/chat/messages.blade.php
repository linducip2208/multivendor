@extends('layouts.vendor')
@section('title', 'Chat: '.$customer->name)
@section('content')
<div class="mb-4"><a href="{{ route('vendor.chat.inbox') }}" class="small"><i class="fas fa-arrow-left me-1"></i>Inbox</a><h4 class="fw-bold mt-2">Chat dengan {{ $customer->name }}</h4></div>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body text-center py-5"><i class="fas fa-comments fa-4x text-muted mb-3 opacity-25"></i><h5>Chat System</h5><p class="text-muted">Real-time chat akan aktif setelah integrasi Pusher / Laravel Reverb / WebSocket.</p><p class="text-muted small">Saat ini gunakan kontak langsung: <strong>{{ $customer->email }}</strong></p></div></div>
@endsection
