<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    @yield('meta')
    <title>@yield('title') — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @yield('jsonld')
    <style>:root{--brand:#4F46E5}body{font-family:'Inter',sans-serif;background:#f8f9fc;color:#1e293b}.cta-banner{background:linear-gradient(135deg,#4F46E5,#7C3AED);color:#fff;border-radius:20px;padding:40px}.cta-banner .btn-warning{background:#f59e0b;border:none;font-weight:700;padding:12px 32px;border-radius:12px}.breadcrumb{font-size:.85rem}.content-section{background:#fff;border-radius:16px;padding:32px;box-shadow:0 2px 12px rgba(0,0,0,.04);margin-bottom:20px}h1{font-weight:800;font-size:2rem}h2{font-weight:700;font-size:1.5rem;margin-top:24px}.table-comparison td,.table-comparison th{padding:12px}.whatsapp-float{position:fixed;bottom:24px;right:24px;width:56px;height:56px;background:#25D366;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(37,211,102,.4);z-index:9999}footer{background:#1e293b;color:#94a3b8;padding:30px 0;margin-top:40px}footer a{color:#94a3b8}</style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-white shadow-sm"><div class="container"><a class="navbar-brand fw-bold" href="/"><i class="fas fa-store-alt text-primary me-2"></i>{{ config('app.name') }}</a></div></nav>

<main class="container py-4">
    @yield('content')
</main>

<div class="container mb-4">
    <div class="cta-banner text-center">
        <h2 class="fw-bold mb-3">🚀 Butuh Aplikasi Multivendor Seperti Ini?</h2>
        <p class="mb-3" style="font-size:1.1rem">Source code siap pakai. Payment gateway Indonesia lengkap, ongkos kirim, AI analytics. Full-stack Laravel + Flutter.</p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="https://wa.me/6281296052010?text=Halo%20saya%20tertarik%20source%20code%20multivendor" class="btn btn-warning btn-lg"><i class="fab fa-whatsapp me-2"></i>Chat WhatsApp</a>
            <a href="/docs" class="btn btn-outline-light btn-lg">📖 Dokumentasi</a>
        </div>
    </div>
</div>

<footer><div class="container text-center small">
    <p>&copy; {{ date('Y') }} {{ config('app.name') }} — Platform Multivendor E-Commerce Indonesia. Source Code Multivendor, Aplikasi Toko Online, Pengganti Shopee, Payment Gateway Terbaik.</p>
</div></footer>

<a href="https://wa.me/6281296052010?text=Halo%20saya%20mau%20tanya%20source%20code%20multivendor" class="whatsapp-float text-white text-decoration-none" target="_blank"><i class="fab fa-whatsapp fa-2x"></i></a>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
