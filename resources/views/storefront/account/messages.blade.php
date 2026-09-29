@extends('layouts.storefront')

@section('content')
    @php
        $conversations = collect($conversations ?? []);
        $messages = collect($messages ?? []);
        $active = $conversation ?? null;
    @endphp

    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <a href="{{ route('account.dashboard') }}">Dasbor</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Pesan</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-messages-title">
        <div class="sf-container">
            <h1 class="sf-section-head__title" id="sf-messages-title">Pesan</h1>
            <p class="sf-muted sf-small" style="max-width:60ch">
                Percakapan dengan penjual dan tim dukungan akan tampil di sini.
            </p>

            <div class="sf-account">
                <aside>
                    <x-storefront.account-nav current="account.messages" />
                </aside>

                <div>
                    @if ($conversations->isNotEmpty())
                        <div class="sf-panel" style="padding:0;overflow:hidden">
                            <div class="sf-row sf-row--between sf-row--wrap" style="gap:8px;padding:14px 18px;border-bottom:1px solid var(--sf-border)">
                                <h2 class="sf-mb-0" style="font-size:1rem">
                                    {{ $active?->subject ?? 'Percakapan' }}
                                </h2>
                                <a href="{{ route('tickets.create') }}" class="sf-btn sf-btn--outline sf-btn--sm">
                                    <x-storefront.icon name="plus" :size="15" /> Tiket baru
                                </a>
                            </div>

                            <ol class="sf-stack" style="gap:12px;padding:18px;list-style:none;margin:0;max-height:520px;overflow-y:auto">
                                @foreach ($messages as $message)
                                    @php
                                        $mine = (int) ($message->user_id ?? 0) === (int) auth()->id();
                                    @endphp
                                    <li style="display:flex;justify-content:{{ $mine ? 'flex-end' : 'flex-start' }}">
                                        <div class="sf-panel" style="max-width:min(78%,520px);background:{{ $mine ? 'var(--sf-bg-muted)' : 'var(--sf-bg)' }}">
                                            <p class="sf-tiny sf-bold sf-muted sf-mb-0">
                                                {{ $mine ? 'Anda' : ($message->user?->name ?? 'Tim dukungan') }}
                                            </p>
                                            <p class="sf-small sf-mb-0" style="white-space:pre-line;overflow-wrap:anywhere">
                                                {{ $message->body ?? '' }}
                                            </p>
                                            <p class="sf-tiny sf-muted sf-mb-0" style="margin-top:6px;text-align:right">
                                                <time datetime="{{ $message->created_at?->toAtomString() }}">
                                                    {{ $message->created_at?->translatedFormat('d M Y H:i') }}
                                                </time>
                                            </p>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>

                            <div style="padding:16px 18px;border-top:1px solid var(--sf-border)">
                                <p class="sf-small sf-muted sf-mb-0">
                                    Kirim pesan baru melalui tiket dukungan agar seluruh riwayat percakapan tersimpan.
                                </p>
                                <a href="{{ route('tickets.index') }}" class="sf-btn sf-btn--primary sf-btn--sm" style="margin-top:12px">
                                    <x-storefront.icon name="ticket" :size="15" /> Buka tiket dukungan
                                </a>
                            </div>
                        </div>
                    @else
                        <x-storefront.empty
                            title="Belum ada percakapan"
                            text="Mulai percakapan dengan membuka tiket dukungan. Balasan penjual dan tim dukungan akan tampil di halaman ini."
                            :href="route('tickets.create')"
                            label="Buat tiket"
                            icon="mail"
                        />
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
