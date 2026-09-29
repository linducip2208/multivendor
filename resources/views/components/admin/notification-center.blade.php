@props([
    'nav' => 'admin',
    'unread' => 0,
    'align' => 'dropdown-menu-end',
    'limit' => 5,
])

@php
    $guard = $nav === 'vendor' ? 'vendor' : 'admin';
    $user = auth($guard)->user();
    $items = [];
    $readAllRoute = $nav.'.notifications.read-all';

    try {
        if ($user) {
            $items = \App\Models\Notification::query()
                ->where('notifiable_id', $user->getAuthIdentifier())
                ->latest('created_at')
                ->limit($limit)
                ->get();
        }
    } catch (\Throwable $e) {
        $items = [];
    }
@endphp

<div class="dropdown-menu {{ $align }} admin-notification-center p-0" aria-label="Notifikasi">
    <div class="dropdown-header d-flex align-items-center justify-content-between gap-2">
        <span class="fw-semibold">Notifikasi</span>
        @if ((int) $unread > 0)
            <span class="badge bg-danger-lt text-danger">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </div>

    <div class="admin-notification-center__list">
        @forelse ($items as $notification)
            @php
                $payload = is_array($notification->data) ? $notification->data : [];
                $headline = (string) ($payload['title'] ?? $payload['message'] ?? $notification->type ?? 'Notifikasi');
                $body = (string) ($payload['body'] ?? $payload['text'] ?? '');
                $isUnread = $notification->read_at === null;
            @endphp
            <div class="admin-notification-center__item d-flex gap-2 {{ $isUnread ? 'is-unread' : '' }}">
                <span class="flex-shrink-0 mt-1 text-{{ $isUnread ? 'primary' : 'secondary' }}">
                    <x-admin.icon name="bell" :size="16" />
                </span>
                <div class="flex-fill">
                    <div class="small fw-semibold text-truncate">{{ $headline }}</div>
                    @if ($body !== '')
                        <div class="small text-secondary text-truncate">{{ $body }}</div>
                    @endif
                    <div class="small text-secondary">{{ $notification->created_at?->diffForHumans() }}</div>
                </div>
            </div>
        @empty
            <div class="p-3">
                <x-admin.empty-state icon="bell" title="Tidak ada notifikasi" text="Aktivitas terbaru akan muncul di sini." compact />
            </div>
        @endforelse
    </div>

    <div class="dropdown-divider"></div>

    <div class="p-2 d-flex align-items-center justify-content-between gap-2">
        @if ((int) $unread > 0)
            @if (\Route::has($readAllRoute))
                <form method="POST" action="{{ route($readAllRoute) }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-ghost-light btn-sm">
                        <x-admin.icon name="check" :size="16" />
                        <span>Tandai sudah dibaca</span>
                    </button>
                </form>
            @else
                <button type="button" class="btn btn-ghost-light btn-sm" disabled title="Fitur belum tersedia">
                    <x-admin.icon name="check" :size="16" />
                    <span>Tandai sudah dibaca</span>
                </button>
            @endif
        @else
            <span class="small text-secondary px-1">Semua notification sudah dibaca.</span>
        @endif

        @php
            $allRoute = \Route::has($nav.'.notifications') ? $nav.'.notifications' : (\Route::has($nav.'.notifications.index') ? $nav.'.notifications.index' : null);
        @endphp
        @if ($allRoute)
            <a href="{{ route($allRoute) }}" class="btn btn-ghost-light btn-sm">
                <span>Lihat semua</span>
                <x-admin.icon name="chevron-right" :size="16" />
            </a>
        @endif
    </div>
</div>
