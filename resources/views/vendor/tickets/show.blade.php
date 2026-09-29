@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', $ticket->subject)
@section('subtitle', $ticket->reference)

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Tiket', 'href' => route('vendor.tickets.index')],
    ['label' => $ticket->reference],
])

@section('actions')
    @if ($ticket->status !== 'closed' && $ticket->status !== 'resolved')
        <form method="POST" action="{{ route('vendor.tickets.close', $ticket->id) }}" data-confirm="Tandai tiket ini selesai?">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">
                <x-admin.icon name="check" :size="16" class="me-1" />
                <span>Tandai selesai</span>
            </button>
        </form>
    @endif
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-admin.card title="Percakapan" icon="message-square" class="mb-3">
                <div class="mb-3 pb-3 border-bottom">
                    <p class="mb-1 text-secondary small">{{ \Carbon\Carbon::parse($ticket->created_at)->format('d M Y H:i') }}</p>
                    <p class="mb-0">{{ $ticket->description }}</p>
                </div>

                @forelse ($replies as $reply)
                    @php $isVendor = $reply->author_role === 'vendor'; @endphp
                    <div class="d-flex gap-2 mb-3 {{ $isVendor ? '' : '' }}">
                        <x-admin.avatar :name="$reply->author ?? 'Tim Dukungan'" size="sm" />
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-baseline gap-2">
                                <span class="fw-medium">{{ $reply->author ?? 'Tim Dukungan' }}</span>
                                <span class="badge bg-{{ $isVendor ? 'primary' : 'secondary' }}-lt text-{{ $isVendor ? 'primary' : 'secondary' }}">{{ $isVendor ? 'Anda' : 'Dukungan' }}</span>
                                <span class="text-secondary small">{{ \Carbon\Carbon::parse($reply->created_at)->format('d M Y H:i') }}</span>
                            </div>
                            <p class="mb-0 mt-1">{{ $reply->body }}</p>
                        </div>
                    </div>
                @empty
                    <x-admin.empty-state icon="message-square" text="Belum ada balasan." compact />
                @endforelse
            </x-admin.card>

            @if ($ticket->status !== 'closed')
                <x-admin.card title="Tambah balasan" icon="reply">
                    <form method="POST" action="{{ route('vendor.tickets.reply', $ticket->id) }}">
                        @csrf
                        <x-admin.form-field name="body" label="Balasan" type="textarea" :rows="5" required />
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <x-admin.icon name="mail" :size="16" class="me-1" />
                                <span>Kirim balasan</span>
                            </button>
                        </div>
                    </form>
                </x-admin.card>
            @endif
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Detail tiket" icon="info">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-secondary fw-normal">Status</dt>
                    <dd class="col-7 text-end">{{ $__status($ticket->status) }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Prioritas</dt>
                    <dd class="col-7 text-end">{{ $__status($ticket->priority) }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Dibuat</dt>
                    <dd class="col-7 text-end">{{ \Carbon\Carbon::parse($ticket->created_at)->format('d M Y') }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Diperbarui</dt>
                    <dd class="col-7 text-end">{{ \Carbon\Carbon::parse($ticket->updated_at ?? $ticket->created_at)->format('d M Y') }}</dd>
                    @if ($ticket->order_id)
                        <dt class="col-5 text-secondary fw-normal">Pesanan</dt>
                        <dd class="col-7 text-end">
                            <a href="{{ route('vendor.orders.show', $ticket->order_id) }}">#{{ $ticket->order_id }}</a>
                        </dd>
                    @endif
                    @if ($ticket->resolved_at)
                        <dt class="col-5 text-secondary fw-normal">Selesai</dt>
                        <dd class="col-7 text-end">{{ \Carbon\Carbon::parse($ticket->resolved_at)->format('d M Y') }}</dd>
                    @endif
                </dl>
            </x-admin.card>
        </div>
    </div>
@endsection
