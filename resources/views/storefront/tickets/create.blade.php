@extends('layouts.storefront')

@section('content')
    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <a href="{{ route('tickets.index') }}">Tiket Dukungan</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Buat tiket</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-ticket-create-title">
        <div class="sf-container" style="max-width:760px">
            <h1 class="sf-section-head__title" id="sf-ticket-create-title">Buat Tiket Baru</h1>
            <p class="sf-muted sf-small" style="max-width:60ch">
                Sertakan nomor pesanan dan detail yang relevan agar tim dukungan dapat memeriksa lebih cepat.
            </p>

            <form method="POST" action="{{ route('tickets.store') }}" class="sf-panel sf-stack" style="gap:16px;margin-top:20px" novalidate>
                @csrf

                <div class="sf-field">
                    <label class="sf-label" for="sf-ticket-subject">Subjek <span class="sf-required">*</span></label>
                    <input class="sf-input" id="sf-ticket-subject" type="text" name="subject" required maxlength="255"
                           value="{{ old('subject') }}" placeholder="Contoh: Paket belum sampai setelah 5 hari"
                           @error('subject') aria-invalid="true" aria-describedby="sf-ticket-subject-error" @enderror>
                    @error('subject')
                        <span class="sf-error" id="sf-ticket-subject-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
                    <div class="sf-field">
                        <label class="sf-label" for="sf-ticket-type">Jenis tiket <span class="sf-required">*</span></label>
                        <select class="sf-select" id="sf-ticket-type" name="type" required @error('type') aria-invalid="true" @enderror>
                            @foreach (['order' => 'Pesanan', 'product' => 'Produk', 'payment' => 'Pembayaran', 'account' => 'Akun', 'other' => 'Lainnya'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')<span class="sf-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="sf-field">
                        <label class="sf-label" for="sf-ticket-priority">Prioritas <span class="sf-required">*</span></label>
                        <select class="sf-select" id="sf-ticket-priority" name="priority" required @error('priority') aria-invalid="true" @enderror>
                            @foreach (['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi', 'urgent' => 'Mendesak'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority', 'medium') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('priority')<span class="sf-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="sf-field">
                    <label class="sf-label" for="sf-ticket-description">Deskripsi <span class="sf-required">*</span></label>
                    <textarea class="sf-textarea" id="sf-ticket-description" name="description" rows="7" required
                              placeholder="Jelaskan kronologi dan informasi yang relevan">{{ old('description') }}</textarea>
                    @error('description')
                        <span class="sf-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="sf-row sf-row--wrap" style="gap:8px">
                    <button type="submit" class="sf-btn sf-btn--primary">
                        <x-storefront.icon name="ticket" :size="16" /> Kirim tiket
                    </button>
                    <a href="{{ route('tickets.index') }}" class="sf-btn sf-btn--ghost">Batal</a>
                </div>
            </form>
        </div>
    </section>
@endsection
