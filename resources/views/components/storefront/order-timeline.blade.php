@props(['order' => null])

@php
    use App\Enums\OrderStatus;
    use App\Domain\Order\OrderStateMachine;

    $badgeModifier = static fn (string $badge): string => match ($badge) {
        'warning' => 'sf-badge--warning',
        'info' => 'sf-badge--info',
        'primary' => 'sf-badge--brand',
        'success' => 'sf-badge--success',
        default => 'sf-badge--neutral',
    };

    $stages = [
        'pending' => 'Pesanan dibuat',
        'paid' => 'Pembayaran diterima',
        'processing' => 'Diproses penjual',
        'packed' => 'Dikemas',
        'shipped' => 'Dikirim',
        'delivered' => 'Diterima',
        'completed' => 'Selesai',
    ];

    $stageKeys = array_keys($stages);
    $status = (string) ($order?->order_status ?? 'pending');
    $statusEnum = OrderStatus::fromStored($status);
    $onMainPath = array_key_exists($status, $stages);
    $currentIndex = $onMainPath ? array_search($status, $stageKeys, true) : null;

    $history = collect($order?->statusHistory ?? [])->sortBy('created_at')->values();

    $steps = [];

    if ($onMainPath) {
        foreach ($stages as $key => $label) {
            $entry = $history->firstWhere('status', $key);
            $steps[] = [
                'title' => $label,
                'meta' => $entry?->note,
                'time' => $entry?->created_at,
                'state' => $key === $status ? 'is-current' : (array_search($key, $stageKeys, true) < $currentIndex ? 'is-done' : ''),
                'label' => OrderStatus::fromStored($key)->label(),
                'badge' => $badgeModifier(OrderStatus::fromStored($key)->badge()),
            ];
        }
    } else {
        foreach ($history as $entry) {
            $entryStatus = OrderStatus::fromStored($entry->status);
            $steps[] = [
                'title' => $entryStatus->label(),
                'meta' => $entry->note,
                'time' => $entry->created_at,
                'state' => $entry->status === $status ? 'is-current' : 'is-done',
                'label' => $entryStatus->label(),
                'badge' => $badgeModifier($entryStatus->badge()),
            ];
        }

        if ($steps === []) {
            $steps[] = [
                'title' => $statusEnum->label(),
                'meta' => null,
                'time' => $order?->created_at,
                'state' => 'is-current',
                'label' => $statusEnum->label(),
                'badge' => $badgeModifier($statusEnum->badge()),
            ];
        }
    }

    $allowedNext = $statusEnum->isTerminal() ? [] : OrderStateMachine::allowedFrom($status);
@endphp

@if ($order)
    <div {{ $attributes->merge(['class' => 'sf-timeline']) }}>
        @foreach ($steps as $step)
            <div class="sf-timeline__item {{ $step['state'] }}">
                <span class="sf-timeline__dot" aria-hidden="true"></span>
                <p class="sf-timeline__title sf-mb-0">
                    {{ $step['title'] }}
                    @unless ($onMainPath)
                        <span class="sf-badge {{ $step['badge'] }}">{{ $step['label'] }}</span>
                    @endunless
                </p>
                @if ($step['meta'] || $step['time'])
                    <p class="sf-timeline__meta sf-mb-0">
                        @if ($step['time'])
                            <time datetime="{{ $step['time']->toAtomString() }}">{{ $step['time']->translatedFormat('d M Y H:i') }}</time>
                        @endif
                        @if ($step['time'] && $step['meta'])<span aria-hidden="true"> &middot; </span>@endif
                        {{ $step['meta'] }}
                    </p>
                @endif
            </div>
        @endforeach
    </div>

    @if ($allowedNext !== [])
        <p class="sf-small sf-muted sf-mb-0" style="margin-top:14px">
            Tahap berikutnya: {{ collect($allowedNext)->map(fn (OrderStatus $next) => $next->label())->implode(', ') }}.
        </p>
    @endif
@endif
