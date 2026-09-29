@props(['count' => 8])

<div {{ $attributes->merge(['class' => 'sf-products']) }} aria-hidden="true">
    @for ($i = 0; $i < max(1, (int) $count); $i++)
        <div class="sf-pcard" style="pointer-events:none">
            <div class="sf-skeleton sf-skeleton--media"></div>
            <div class="sf-pcard__body">
                <div class="sf-skeleton sf-skeleton--text" style="width:45%"></div>
                <div class="sf-skeleton sf-skeleton--title" style="width:100%"></div>
                <div class="sf-skeleton sf-skeleton--text" style="width:70%;height:1rem"></div>
                <div class="sf-skeleton sf-skeleton--text" style="width:35%;height:1rem"></div>
            </div>
        </div>
    @endfor
</div>
<span class="sf-sr-only" role="status" aria-live="polite">Memuat produk</span>
