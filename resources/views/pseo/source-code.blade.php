@extends('pseo._layout')
@section('meta')<link rel="canonical" href="{{ $canonical }}"/><meta name="description" content="{{ $desc }}"><meta property="og:title" content="{{ $title }}"><meta property="og:description" content="{{ $desc }}"><meta property="og:type" content="website"><meta property="og:url" content="{{ $canonical }}">@endsection
@section('title'){{ $title }}@endsection
@section('content')
<nav class="breadcrumb"><a href="/">Home</a> &raquo; <strong>{{ $label }}</strong></nav>
<div class="content-section">
    <h1>{{ $title }}</h1>
    <p class="lead">Jual <strong>source code {{ $label }}</strong> full-stack. Laravel 13 + MySQL + Bootstrap 5 + Flutter. Semua fitur e-commerce sudah include: multivendor, payment gateway Indonesia, ongkos kirim, AI analytics.</p>

    <h2>Fitur Lengkap {{ $label }}</h2>
    <div class="row g-3 mb-4">
        <div class="col-md-6">✅ <strong>Multi-Vendor</strong> — Ratusan toko dalam 1 platform. Vendor daftar sendiri, admin approve.</div>
        <div class="col-md-6">✅ <strong>10 Payment Gateway</strong> — Midtrans, Xendit, Tripay, Duitku, OY, iPaymu, Faspay, DOKU, ESIA Pay.</div>
        <div class="col-md-6">✅ <strong>16 Kurir Pengiriman</strong> — JNE, J&T, SiCepat, TIKI, POS, GoSend, GrabExpress, dll.</div>
        <div class="col-md-6">✅ <strong>AI Analytics</strong> — 10 AI provider. Analisis produk terlaris + rekomendasi bisnis.</div>
        <div class="col-md-6">✅ <strong>POS System</strong> — Point of sale untuk transaksi offline.</div>
        <div class="col-md-6">✅ <strong>Blog & SEO</strong> — Sitemap auto, IndexNow, robots.txt.</div>
        <div class="col-md-6">✅ <strong>Flutter App Ready</strong> — REST API v1/v2/v3 untuk mobile app.</div>
        <div class="col-md-6">✅ <strong>Loyalty & Referral</strong> — Poin reward, kode referral.</div>
    </div>

    <h2>Kenapa Beli Source Code dari Kami?</h2>
    <ul><li>🇮🇩 Dibuat khusus untuk pasar Indonesia</li><li>💳 Payment gateway Indonesia lengkap</li><li>📦 Ongkos kirim real-time dari RajaOngkir + kurir direkt</li><li>🤖 AI BYOK — user bisa pakai DeepSeek, OpenAI, Groq, Ollama (gratis)</li><li>📱 Flutter app ready — 1 codebase untuk Android + iOS</li><li>📚 Dokumentasi lengkap + tutorial step-by-step</li><li>⚡ Free install + setup + 1 bulan support</li></ul>

    <div class="text-center mt-4"><a href="https://wa.me/{{ $wa }}?text=Halo%20saya%20tertarik%20{{ urlencode($label) }}" class="btn btn-warning btn-lg px-5"><i class="fab fa-whatsapp me-2"></i>Chat WhatsApp — Dapatkan Penawaran</a></div>
</div>
@endsection
