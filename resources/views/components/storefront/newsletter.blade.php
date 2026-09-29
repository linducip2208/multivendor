@props([
    'enabled' => null,
    'title' => null,
    'text' => null,
])

@php
    $isEnabled = $enabled === null
        ? (bool) \App\Models\SystemSetting::get('newsletter_enabled', '1')
        : (bool) $enabled;

    $heading = $title ?: \App\Models\SystemSetting::get('newsletter_title', 'Dapatkan promo sebelum orang lain');
    $lede = $text ?: \App\Models\SystemSetting::get('newsletter_text', 'Kirim satu email singkat berisi flash sale, kupon, dan produk baru ke inbox Anda.');
    $routeExists = \Illuminate\Support\Facades\Route::has('newsletter.subscribe');
    $errorBag = $errors ?? new \Illuminate\Support\ViewErrorBag;
@endphp

@if ($isEnabled)
    <section {{ $attributes->merge(['class' => 'sf-panel sf-section--subtle']) }} aria-labelledby="sf-newsletter-title">
        <div class="sf-row sf-row--between sf-row--wrap" style="gap:20px">
            <div style="min-width:0;flex:1 1 280px">
                <h2 class="sf-section-head__eyebrow" id="sf-newsletter-title" style="margin-bottom:4px">Newsletter</h2>
                <h3 class="sf-mb-0" style="font-size:1.1rem">{{ $heading }}</h3>
                <p class="sf-small sf-muted sf-mb-0" style="max-width:52ch">{{ $lede }}</p>
            </div>

            @if ($routeExists)
                <form method="POST" action="{{ route('newsletter.subscribe') }}" class="sf-newsletter" style="margin-top:0;flex:1 1 320px">
                    @csrf
                    <div class="sf-field" style="flex:1 1 220px">
                        <label class="sr-only" for="sf-newsletter-email">Alamat email</label>
                        <input class="sf-input" id="sf-newsletter-email" type="email" name="email" required
                               autocomplete="email" placeholder="nama@email.com"
                               @if ($errorBag->has('email')) aria-invalid="true" aria-describedby="sf-newsletter-email-error" @endif>
                        @if ($errorBag->has('email'))
                            <span class="sf-error" id="sf-newsletter-email-error">{{ $errorBag->first('email') }}</span>
                        @endif
                    </div>
                    <input type="hidden" name="source" value="newsletter">
                    <button type="submit" class="sf-btn sf-btn--primary">
                        <x-storefront.icon name="mail" :size="16" /> Berlangganan
                    </button>
                </form>
            @endif
        </div>
    </section>
@endif
