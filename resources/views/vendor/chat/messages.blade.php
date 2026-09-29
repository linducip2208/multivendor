@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Chat dengan '.($participants->first()?->user?->name ?? 'Pelanggan'))
@section('subtitle', $conversation->subject)

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Chat', 'href' => route('vendor.chat.inbox')],
    ['label' => 'Percakapan'],
])

@section('actions')
    <a href="{{ route('vendor.tickets.index') }}" class="btn btn-outline-secondary">
        <x-admin.icon name="life-buoy" :size="16" class="me-1" />
        <span>Buat tiket</span>
    </a>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-admin.card :padding="false" flush>
                <div class="chat-thread p-3" style="max-height: 60vh; overflow-y: auto;" data-chat-thread>
                    @forelse ($messages as $message)
                        @php $mine = (int) $message->user_id === (int) auth('vendor')->id(); @endphp
                        <div class="d-flex gap-2 mb-3 {{ $mine ? 'flex-row-reverse' : '' }}">
                            <x-admin.avatar :name="$message->author?->name ?? 'Pelanggan'" size="sm" />
                            <div class="{{ $mine ? 'text-end' : '' }}" style="max-width: 78%;">
                                <div class="d-flex align-items-baseline gap-2 {{ $mine ? 'flex-row-reverse' : '' }}">
                                    <span class="fw-medium">{{ $mine ? 'Anda' : ($message->author?->name ?? 'Pelanggan') }}</span>
                                    <span class="text-secondary small">{{ $message->created_at->format('d/m/Y H:i') }}</span>
                                </div>
                                <div class="rounded-3 px-3 py-2 mt-1 text-start" style="background: {{ $mine ? 'var(--tblr-primary)' : 'var(--tblr-secondary-bg)' }}; color: {{ $mine ? 'var(--tblr-primary-fg)' : 'inherit' }};">
                                    {{ $message->body }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <x-admin.empty-state icon="message-square" text="Belum ada pesan. Mulai percakapan di bawah." compact />
                    @endforelse
                </div>

                <div class="card-footer bg-transparent border-top">
                    <form method="POST" action="{{ route('vendor.chat.send') }}">
                        @csrf
                        <input type="hidden" name="conversation_id" value="{{ $conversation->id }}">

                        <div class="row g-2 align-items-end">
                            <div class="col-12">
                                <label class="form-label small mb-1" for="chat-body">Pesan</label>
                                <textarea class="form-control" id="chat-body" name="body" rows="3" required maxlength="5000" placeholder="Tulis balasan untuk pelanggan&hellip;">{{ old('body') }}</textarea>
                            </div>
                            <div class="col-12 d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-primary" data-chat-send>
                                    <x-admin.icon name="mail" :size="16" class="me-1" />
                                    <span>Kirim</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </x-admin.card>
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Detail percakapan" icon="info">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-secondary fw-normal">Status</dt>
                    <dd class="col-7 text-end">{{ $__status($conversation->status) }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Dibuat</dt>
                    <dd class="col-7 text-end">{{ $conversation->created_at?->format('d M Y') }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Pesan terakhir</dt>
                    <dd class="col-7 text-end">{{ $conversation->last_message_at?->format('d M Y H:i') ?? '—' }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Jumlah pesan</dt>
                    <dd class="col-7 text-end">{{ $messages->count() }}</dd>
                    @isset($sla)
                        <dt class="col-5 text-secondary fw-normal">SLA respons</dt>
                        <dd class="col-7 text-end">
                            <x-admin.badge :text="$sla['breached'] ? 'Terlewati' : $sla['elapsed_hours'].' / '.$sla['sla_hours'].' jam'" :color="$sla['breached'] ? 'danger' : 'success'" pill />
                        </dd>
                    @endisset
                </dl>
            </x-admin.card>

            @isset($quickReplies)
                <x-admin.card title="Balasan cepat" icon="zap" class="mt-3">
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($quickReplies as $reply)
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-quick-reply="{{ e($reply['body']) }}" title="{{ e($reply['label']) }}">{{ $reply['label'] }}</button>
                        @endforeach
                    </div>
                    <p class="text-secondary small mb-0 mt-2">Klik untuk mengisi kolom pesan. Target respons maksimal 24 jam.</p>
                </x-admin.card>
            @endisset

            <x-admin.card title="Peserta" icon="users" class="mt-3">
                @foreach ($participants as $participant)
                    <div class="d-flex align-items-center gap-2 {{ $loop->last ? '' : 'mb-3' }}">
                        <x-admin.avatar :name="$participant->user?->name ?? 'Pelanggan'" size="sm" />
                        <span class="min-w-0">
                            <span class="d-block text-truncate fw-medium">{{ $participant->user?->name ?? 'Pelanggan' }}</span>
                            <span class="d-block text-secondary small text-truncate">{{ $participant->user?->email }}</span>
                        </span>
                    </div>
                @endforeach

                @if ($participants->isNotEmpty() && $conversation->order_id)
                    <a href="{{ route('vendor.orders.show', $conversation->order_id) }}" class="btn btn-outline-secondary w-100 mt-3">
                        <x-admin.icon name="shopping-cart" :size="16" class="me-1" />
                        <span>Lihat pesanan terkait</span>
                    </a>
                @endif
            </x-admin.card>
        </div>
    </div>
@endsection

@push('scripts')
    <script data-chat-thread>
        (function () {
            const thread = document.querySelector('[data-chat-thread]');
            if (thread) {
                thread.scrollTop = thread.scrollHeight;
            }
            document.querySelectorAll('[data-quick-reply]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const box = document.getElementById('chat-body');
                    if (box) {
                        box.value = btn.getAttribute('data-quick-reply') || '';
                        box.focus();
                    }
                });
            });
        })();
    </script>
@endpush
