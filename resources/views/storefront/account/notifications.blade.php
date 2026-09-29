@extends('layouts.storefront')

@section('content')
    @php
        $notifications = $notifications ?? null;
        $rows = $notifications instanceof \Illuminate\Contracts\Pagination\Paginator
            ? collect($notifications->items())
            : collect($notifications ?? []);
        $unreadCount = (int) ($unreadCount ?? collect($notifications ?? [])->whereNull('read_at')->count());
    @endphp

    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <a href="{{ route('account.dashboard') }}">Dashboard</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Notifikasi</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-notifications-title">
        <div class="sf-container">
            <div class="sf-row sf-row--between sf-row--wrap" style="gap:12px;margin-bottom:20px">
                <h1 class="sf-section-head__title" id="sf-notifications-title">
                    Notifikasi
                    @if ($unreadCount > 0)
                        <span class="sf-badge sf-badge--brand">{{ \App\Support\Currency::number($unreadCount) }} belum dibaca</span>
                    @endif
                </h1>
                @if ($unreadCount > 0)
                    <form method="POST" action="{{ route('account.notifications.read') }}">
                        @csrf
                        <button type="submit" class="sf-btn sf-btn--outline sf-btn--sm">
                            <x-storefront.icon name="check" :size="15" /> Tandai semua dibaca
                        </button>
                    </form>
                @endif
            </div>

            <div class="sf-account">
                <aside>
                    <x-storefront.account-nav current="account.notifications" />
                </aside>

                <div>
                    @if ($rows->isNotEmpty())
                        <div class="sf-stack" style="gap:10px">
                            @foreach ($rows as $notification)
                                <article @class(['sf-panel', 'is-unread' => $notification->read_at === null])
                                         style="{{ $notification->read_at === null ? 'border-color:var(--sf-brand)' : '' }}">
                                    <div class="sf-row sf-row--between sf-row--wrap" style="gap:10px">
                                        <div style="min-width:0;flex:1 1 240px">
                                            <p class="sf-bold sf-mb-0" style="font-size:.95rem">{{ $notification->title ?? '' }}</p>
                                            <p class="sf-small sf-muted sf-mb-0" style="overflow-wrap:anywhere">
                                                {{ $notification->body ?? '' }}
                                            </p>
                                            <p class="sf-tiny sf-muted sf-mb-0" style="margin-top:4px">
                                                <time datetime="{{ $notification->created_at?->toAtomString() }}">
                                                    {{ $notification->created_at?->translatedFormat('d M Y H:i') }}
                                                </time>
                                                @if ($notification->category)
                                                    &middot; {{ \Illuminate\Support\Str::headline((string) $notification->category) }}
                                                @endif
                                            </p>
                                        </div>

                                        <div class="sf-row sf-row--wrap" style="gap:8px">
                                            @if ($notification->action_url && ! $notification->action_url.startsWith('#'))
                                                <a href="{{ $notification->action_url }}" class="sf-btn sf-btn--outline sf-btn--sm">
                                                    {{ $notification->action_label ?: 'Buka' }}
                                                </a>
                                            @endif
                                            @if ($notification->read_at === null)
                                                <form method="POST" action="{{ route('account.notifications.read', $notification) }}">
                                                    @csrf
                                                    <button type="submit" class="sf-btn sf-btn--ghost sf-btn--sm" aria-label="Tandai notifikasi ini dibaca">
                                                        <x-storefront.icon name="check" :size="15" /> Tandai dibaca
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        @if ($notifications instanceof \Illuminate\Contracts\Pagination\Paginator)
                            <x-storefront.pagination :paginator="$notifications" />
                        @endif
                    @else
                        <x-storefront.empty
                            title="Belum ada notifikasi"
                            text="Pembaruan status pesanan, promo, dan informasi penting akan muncul di sini."
                            :href="route('orders.index')"
                            label="Lihat pesanan saya"
                            icon="bell"
                        />
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
