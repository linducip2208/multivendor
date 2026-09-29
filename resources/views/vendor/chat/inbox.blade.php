@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Inbox')
@section('subtitle', 'Percakapan dengan pelanggan toko Anda')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Chat'],
])

@section('actions')
    <a href="{{ route('vendor.tickets.index') }}" class="btn btn-outline-secondary">
        <x-admin.icon name="life-buoy" :size="16" class="me-1" />
        <span>Tiket dukungan</span>
    </a>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <x-admin.card title="Percakapan" icon="message-square" :padding="false">
                @forelse ($conversations as $conversation)
                    @php $counterpart = $conversation->participants->firstWhere('user_id', '!=', auth('vendor')->id()); @endphp
                    <a href="{{ route('vendor.chat.messages', $conversation->id) }}" class="d-flex align-items-center gap-3 p-3 text-reset {{ $loop->last ? '' : 'border-bottom' }}">
                        <x-admin.avatar :name="$counterpart?->user?->name ?? 'Pelanggan'" size="md" />
                        <span class="flex-grow-1 min-w-0">
                            <span class="d-flex align-items-center justify-content-between gap-2">
                                <span class="fw-medium text-truncate">{{ $counterpart?->user?->name ?? 'Pelanggan' }}</span>
                                <span class="text-secondary small text-nowrap">
                                    {{ $conversation->last_message_at?->diffForHumans(short: true) ?? $conversation->created_at->diffForHumans(short: true) }}
                                </span>
                            </span>
                            <span class="d-block text-secondary small text-truncate">
                                {{ \Illuminate\Support\Str::limit($conversation->lastMessage?->body ?? $conversation->subject, 70) }}
                            </span>
                        </span>
                        @php $unread = $conversation->participants->firstWhere('user_id', auth('vendor')->id())?->unread_count ?? 0; @endphp
                        @if ($unread > 0)
                            <x-admin.badge color="danger" class="rounded-pill" :text="$unread" />
                        @endif
                    </a>
                @empty
                    <x-admin.empty-state
                        icon="message-square"
                        title="Belum ada percakapan"
                        text="Mulai chat dengan pelanggan dari halaman detail pelanggan."
                    />
                @endforelse
            </x-admin.card>

            <x-admin.pagination :paginator="$conversations" class="mt-3" />
        </div>

        <div class="col-12 col-lg-7">
            <x-admin.card title="Mulai percakapan" icon="plus">
                @if ($customers->isEmpty())
                    <x-admin.empty-state
                        icon="users"
                        title="Belum ada pelanggan"
                        text="Pelanggan muncul di sini setelah pernah memesan di toko Anda."
                    />
                @else
                    <form method="GET" action="{{ route('vendor.chat.inbox') }}" class="mb-3">
                        <label class="form-label small mb-1" for="chat-customer">Cari pelanggan</label>
                        <input class="form-control" type="search" id="chat-customer" placeholder="Nama atau email">
                    </form>

                    <div class="list-group list-group-flush" style="max-height: 420px; overflow-y: auto;">
                        @foreach ($customers as $customer)
                            <a href="{{ route('vendor.chat.customer', $customer->id) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                                <x-admin.avatar :name="$customer->name" size="sm" />
                                <span class="flex-grow-1 text-truncate">
                                    <span class="d-block text-truncate fw-medium">{{ $customer->name }}</span>
                                    <span class="d-block text-secondary small text-truncate">{{ $customer->email }}</span>
                                </span>
                                <x-admin.icon name="chevron-right" :size="16" class="text-secondary" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-admin.card>
        </div>
    </div>
@endsection
