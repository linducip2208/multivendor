@extends('layouts.storefront')

@section('content')
    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <a href="{{ route('orders.show', $order) }}">{{ $order->order_number }}</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Nilai kurir</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-rate-title">
        <div class="sf-container" style="max-width:640px">
            <h1 class="sf-section-head__title" id="sf-rate-title">Nilai Pengalaman Pengiriman</h1>
            <p class="sf-muted sf-small" style="max-width:56ch">
                Penilaian Anda membantu meningkatkan kualitas layanan pengiriman untuk pembeli berikutnya.
            </p>

            <div class="sf-panel" style="margin-top:20px">
                <dl class="sf-summary">
                    <div class="sf-summary__row">
                        <dt class="sf-summary__label">Nomor pesanan</dt>
                        <dd class="sf-mb-0 sf-bold" style="color:var(--sf-text)">{{ $order->order_number }}</dd>
                    </div>
                    <div class="sf-summary__row">
                        <dt class="sf-summary__label">Kurir</dt>
                        <dd class="sf-mb-0">{{ $order->deliveryMan?->name ?? 'Tim pengiriman' }}</dd>
                    </div>
                    @if ($order->shipping_tracking_id)
                        <div class="sf-summary__row">
                            <dt class="sf-summary__label">Nomor resi</dt>
                            <dd class="sf-mb-0">{{ $order->shipping_tracking_id }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            @if ($existing)
                <x-storefront.alert type="info" title="Penilaian sudah tersimpan" style="margin-top:18px">
                    Anda sudah menilai kurir {{ $existing->rating }} dari 5 untuk pesanan ini. Penilaian dapat
                    diperbarui kapan saja dengan mengirim nilai baru.
                </x-storefront.alert>
            @endif

            <form method="POST" action="{{ route('delivery.rate.store', $order) }}" class="sf-panel sf-stack" style="gap:16px;margin-top:18px" novalidate>
                @csrf

                <div class="sf-field">
                    <label class="sf-label" for="sf-rate-rating">Rating <span class="sf-required">*</span></label>
                    <select class="sf-select" id="sf-rate-rating" name="rating" required
                            @error('rating') aria-invalid="true" aria-describedby="sf-rate-rating-error" @enderror>
                        <option value="">Pilih rating</option>
                        @for ($score = 5; $score >= 1; $score--)
                            <option value="{{ $score }}" @selected((string) old('rating', (string) ($existing->rating ?? '')) === (string) $score)>
                                {{ $score }} — {{ [5 => 'Sangat baik', 4 => 'Baik', 3 => 'Cukup', 2 => 'Kurang', 1 => 'Sangat buruk'][$score] }}
                            </option>
                        @endfor
                    </select>
                    @error('rating')
                        <span class="sf-error" id="sf-rate-rating-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sf-field">
                    <label class="sf-label" for="sf-rate-review">Ceritakan pengalaman Anda</label>
                    <textarea class="sf-textarea" id="sf-rate-review" name="review" rows="4" maxlength="500"
                              placeholder="Contoh: paket sampai cepat dan paket dalam kondisi baik">{{ old('review', $existing->review ?? '') }}</textarea>
                    @error('review')<span class="sf-error">{{ $message }}</span>@enderror
                </div>

                <div class="sf-row sf-row--wrap" style="gap:8px">
                    <button type="submit" class="sf-btn sf-btn--primary">
                        <x-storefront.icon name="star" :size="16" :stroke="0" /> Kirim penilaian
                    </button>
                    <a href="{{ route('orders.show', $order) }}" class="sf-btn sf-btn--ghost">Kembali ke pesanan</a>
                </div>
            </form>
        </div>
    </section>
@endsection
