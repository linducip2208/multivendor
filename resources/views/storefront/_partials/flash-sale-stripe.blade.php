@php
    $flashDeal = $deal ?? null;
@endphp

@if ($flashDeal && $flashDeal->end_date)
    <aside class="sf-flash" aria-label="Pengingat flash sale">
        <div class="sf-container">
            <p class="sf-flash__label sf-mb-0">
                <x-storefront.icon name="flame" :size="20" />
                <span class="sf-truncate">{{ $flashDeal->title ?: 'Flash Sale' }}</span>
            </p>
            <x-storefront.countdown :ends-at="$flashDeal->end_date" />
            <a href="{{ route('flash-sale') }}" class="sf-btn sf-btn--sm" style="background:#fff;color:var(--sf-danger);margin-left:auto">
                Lihat semua <x-storefront.icon name="arrow-right" :size="14" />
            </a>
        </div>
    </aside>
@endif
