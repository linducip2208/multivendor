@extends('layouts.storefront')

@section('content')
    @php
        $statusBadge = static fn (string $value): string => match ($value) {
            'open' => 'sf-badge--warning',
            'in_progress' => 'sf-badge--info',
            'resolved' => 'sf-badge--success',
            default => 'sf-badge--neutral',
        };
        $priorityBadge = static fn (string $value): string => match ($value) {
            'urgent' => 'sf-badge--danger',
            'high' => 'sf-badge--warning',
            'medium' => 'sf-badge--info',
            default => 'sf-badge--neutral',
        };
    @endphp

    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Tiket Dukungan</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-tickets-title">
        <div class="sf-container">
            <div class="sf-section-head">
                <div>
                    <h1 class="sf-section-head__title" id="sf-tickets-title">Tiket Dukungan</h1>
                    <p class="sf-muted sf-small sf-mt-0" style="max-width:60ch">
                        Buka tiket bila Anda membutuhkan bantuan soal pesanan, pembayaran, atau akun.
                    </p>
                </div>
                <a href="{{ route('tickets.create') }}" class="sf-btn sf-btn--primary">
                    <x-storefront.icon name="plus" :size="16" /> Buat tiket
                </a>
            </div>

            @if ($tickets->total() > 0)
                <div class="sf-stack" style="gap:12px">
                    @foreach ($tickets as $ticket)
                        <article class="sf-card">
                            <div class="sf-card__body">
                                <div class="sf-row sf-row--between sf-row--wrap" style="gap:10px">
                                    <div style="min-width:0;flex:1 1 260px">
                                        <a href="{{ route('tickets.show', $ticket) }}" class="sf-bold" style="color:var(--sf-text)">
                                            {{ $ticket->subject }}
                                        </a>
                                        <p class="sf-small sf-muted sf-mb-0">
                                            {{ \Illuminate\Support\Str::headline((string) $ticket->type) }}
                                            &middot;
                                            <time datetime="{{ $ticket->created_at?->toAtomString() }}">{{ $ticket->created_at?->translatedFormat('d M Y H:i') }}</time>
                                        </p>
                                    </div>
                                    <div class="sf-row sf-row--wrap" style="gap:6px">
                                        <span class="sf-badge {{ $statusBadge((string) $ticket->status) }}">
                                            {{ \Illuminate\Support\Str::headline((string) $ticket->status) }}
                                        </span>
                                        <span class="sf-badge {{ $priorityBadge((string) $ticket->priority) }}">
                                            {{ \Illuminate\Support\Str::headline((string) $ticket->priority) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="sf-card__foot">
                                <a href="{{ route('tickets.show', $ticket) }}" class="sf-small sf-row" style="gap:6px">
                                    Lihat percakapan <x-storefront.icon name="arrow-right" :size="14" />
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                <x-storefront.pagination :paginator="$tickets" />
            @else
                <x-storefront.empty
                    title="Belum ada tiket"
                    text="Bila Anda membutuhkan bantuan, buat tiket dan tim dukungan akan menindaklanjuti."
                    :href="route('tickets.create')"
                    label="Buat tiket"
                    icon="headset"
                />
            @endif
        </div>
    </section>
@endsection
