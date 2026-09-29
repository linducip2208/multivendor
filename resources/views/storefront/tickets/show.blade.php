@extends('layouts.storefront')

@section('content')
    @php
        $statusBadge = match ((string) $ticket->status) {
            'open' => 'sf-badge--warning',
            'in_progress' => 'sf-badge--info',
            'resolved' => 'sf-badge--success',
            default => 'sf-badge--neutral',
        };
        $replies = $ticket->replies;
    @endphp

    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <a href="{{ route('tickets.index') }}">Tiket Dukungan</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">{{ $ticket->subject }}</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-ticket-title">
        <div class="sf-container" style="max-width:820px">
            <div class="sf-row sf-row--between sf-row--wrap" style="gap:10px">
                <h1 class="sf-section-head__title" id="sf-ticket-title" style="font-size:clamp(1.3rem,1.1rem+1vw,1.8rem);min-width:0">
                    {{ $ticket->subject }}
                </h1>
                <span class="sf-badge {{ $statusBadge }}">{{ \Illuminate\Support\Str::headline((string) $ticket->status) }}</span>
            </div>

            <div class="sf-row sf-row--wrap sf-small sf-muted" style="gap:14px;margin-top:6px">
                <span>{{ \Illuminate\Support\Str::headline((string) $ticket->type) }}</span>
                <span>Prioritas {{ \Illuminate\Support\Str::headline((string) $ticket->priority) }}</span>
                <span>
                    <time datetime="{{ $ticket->created_at?->toAtomString() }}">{{ $ticket->created_at?->translatedFormat('d M Y H:i') }}</time>
                </span>
            </div>

            <div class="sf-panel" style="margin-top:20px">
                <p class="sf-small sf-bold sf-muted sf-mb-0">Deskripsi awal</p>
                <p class="sf-mb-0" style="white-space:pre-line;overflow-wrap:anywhere">{{ $ticket->description }}</p>
            </div>

            <h2 class="sf-section-head__title" style="font-size:1.15rem;margin:28px 0 14px" aria-label="Balasan">
                Balasan ({{ $replies->count() }})
            </h2>

            @if ($replies->isNotEmpty())
                <div class="sf-stack" style="gap:12px">
                    @foreach ($replies as $reply)
                        <article class="sf-panel">
                            <div class="sf-row sf-row--between sf-row--wrap" style="gap:8px;margin-bottom:8px">
                                <span class="sf-row" style="gap:8px">
                                    <span class="sf-avatar" style="width:30px;height:30px;font-size:.75rem" aria-hidden="true">
                                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($reply->user?->name ?? 'P', 0, 1)) }}
                                    </span>
                                    <span class="sf-bold" style="font-size:.9rem">{{ $reply->user?->name ?? 'Tim dukungan' }}</span>
                                </span>
                                <time class="sf-tiny sf-muted" datetime="{{ $reply->created_at?->toAtomString() }}">
                                    {{ $reply->created_at?->translatedFormat('d M Y H:i') }}
                                </time>
                            </div>
                            <p class="sf-small sf-mb-0" style="white-space:pre-line;overflow-wrap:anywhere">{{ $reply->message }}</p>
                        </article>
                    @endforeach
                </div>
            @else
                <p class="sf-small sf-muted">Belum ada balasan pada tiket ini.</p>
            @endif

            @if ($ticket->status !== 'closed')
                <form method="POST" action="{{ route('tickets.reply', $ticket) }}" class="sf-panel sf-stack" style="gap:12px;margin-top:24px" novalidate>
                    @csrf
                    <div class="sf-field">
                        <label class="sf-label" for="sf-ticket-reply">Tulis balasan</label>
                        <textarea class="sf-textarea" id="sf-ticket-reply" name="message" rows="4" required
                                  @error('message') aria-invalid="true" aria-describedby="sf-ticket-reply-error" @enderror>{{ old('message') }}</textarea>
                        @error('message')
                            <span class="sf-error" id="sf-ticket-reply-error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div>
                        <button type="submit" class="sf-btn sf-btn--primary">
                            <x-storefront.icon name="mail" :size="16" /> Kirim balasan
                        </button>
                    </div>
                </form>
            @else
                <x-storefront.alert type="info" title="Tiket ditutup" style="margin-top:24px">
                    Tiket ini sudah ditutup. Buat tiket baru bila Anda masih membutuhkan bantuan.
                </x-storefront.alert>
            @endif
        </div>
    </section>
@endsection
