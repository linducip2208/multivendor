{{-- Language switcher ID/EN. Preserve path + query (cart di session/DB tak tersentuh bahasa). --}}
@props(['variant' => 'topbar'])
@php
    try {
        $codes = app(\App\Services\Localization\LanguageService::class)->activeCodes();
    } catch (\Throwable) {
        $codes = ['id', 'en'];
    }
    // Kontrak scope: copy BI/EN — tampilkan id + en bila tersedia, fallback ke kode aktif.
    $show = collect($codes)->filter(fn ($c) => in_array(strtolower(\App\Models\Language::baseCode((string) $c)), ['id', 'en'], true))->values()->all();
    if ($show === []) {
        $show = array_slice($codes, 0, 2);
    }
    if ($show === []) {
        $show = ['id', 'en'];
    }
    $current = strtolower(\App\Models\Language::baseCode((string) app()->getLocale()));
    $urlFor = fn (string $code): string => request()->fullUrlWithQuery(['lang' => $code]);
@endphp
@if ($variant === 'button')
    <span class="sf-row" role="group" aria-label="{{ __('Language') }}" style="gap:6px">
        @foreach ($show as $i => $code)
            @php($base = strtolower(\App\Models\Language::baseCode((string) $code)))
            @if ($i > 0)<span aria-hidden="true">|</span>@endif
            <a href="{{ $urlFor($base) }}" class="sf-btn sf-btn--ghost sf-btn--sm" hreflang="{{ $base }}" lang="{{ $base }}"
               @if ($current === $base) aria-current="true" style="font-weight:700" @endif>{{ strtoupper($base) }}</a>
        @endforeach
    </span>
@else
    <span class="sf-row" role="group" aria-label="{{ __('Language') }}" style="gap:6px">
        <x-storefront.icon name="globe" :size="13" />
        @foreach ($show as $i => $code)
            @php($base = strtolower(\App\Models\Language::baseCode((string) $code)))
            @if ($i > 0)<span aria-hidden="true">|</span>@endif
            <a href="{{ $urlFor($base) }}" hreflang="{{ $base }}" lang="{{ $base }}"
               @if ($current === $base) aria-current="true" style="font-weight:700" @endif>{{ strtoupper($base) }}</a>
        @endforeach
    </span>
@endif
