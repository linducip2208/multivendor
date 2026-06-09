<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokumentasi Lengkap — {{ config('app.name') }}</title>
    <meta name="description" content="Panduan lengkap platform multivendor e-commerce. Tutorial admin, vendor, pelanggan. Demo accounts.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>:root{--brand:#4F46E5}body{font-family:'Inter',sans-serif;background:#f8f9fc}nav.navbar{background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.06)}.sidenav{position:sticky;top:80px}.sidenav a{display:block;padding:6px 12px;color:#475569;font-size:.85rem;border-left:3px solid transparent;text-decoration:none}.sidenav a:hover,.sidenav a.active{color:var(--brand);border-left-color:var(--brand);background:#eef2ff}.section{background:#fff;border-radius:16px;padding:32px;box-shadow:0 2px 12px rgba(0,0,0,.04);margin-bottom:24px}.section h2{font-weight:800;font-size:1.5rem}.section h3{font-weight:700;font-size:1.15rem;color:var(--brand);margin-top:24px}.ss{border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);max-width:100%;margin:12px 0}.demo-table td{padding:10px 14px;font-size:.875rem}.step{display:inline-flex;width:28px;height:28px;background:var(--brand);color:#fff;border-radius:50%;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;margin-right:8px;flex-shrink:0}</style>
</head>
<body>
<nav class="navbar navbar-expand-lg sticky-top"><div class="container"><a class="navbar-brand fw-bold" href="/"><i class="fas fa-store-alt text-primary me-2"></i>{{ config('app.name') }}</a><a href="/admin/login" class="btn btn-primary btn-sm">Admin Panel</a></div></nav>

<div class="container py-4"><div class="row g-4">
    <div class="col-lg-3"><div class="sidenav">
        <h6 class="fw-bold mb-3">Dokumentasi</h6>
        <a href="#demo">🔑 Akun Demo</a>
        <a href="#admin">🛡️ Tutorial Admin</a>
        <a href="#vendor">🏪 Tutorial Vendor</a>
        <a href="#customer">🛒 Tutorial Pelanggan</a>
        <a href="#payment">💳 Payment Gateway</a>
        <a href="#shipping">🚚 Shipping</a>
        <a href="#ai">🤖 AI Analytics</a>
        <a href="#features">⭐ Semua Fitur</a>
    </div></div>

    <div class="col-lg-9">
        <div class="section text-center"><h1 class="fw-bold">📚 Dokumentasi {{ config('app.name') }}</h1><p class="lead">Panduan lengkap multivendor e-commerce. Tutorial step-by-step dengan screenshot asli.</p></div>

        {{-- Demo --}}
        <div class="section" id="demo"><h2>🔑 Akun Demo</h2>
            <div class="table-responsive"><table class="table table-bordered demo-table"><thead class="table-light"><tr><th>Role</th><th>Email</th><th>Password</th><th>Panel</th></tr></thead><tbody>
                <tr><td><span class="badge bg-danger">Admin</span></td><td><code>admin@multivendor.test</code></td><td><code>password</code></td><td><a href="/admin/login">/admin/login</a> — 48 menu</td></tr>
                <tr><td><span class="badge bg-success">Vendor</span></td><td><code>vendor@multivendor.test</code></td><td><code>password</code></td><td><a href="/vendor/login">/vendor/login</a> — 25 menu</td></tr>
                <tr><td><span class="badge bg-primary">Customer</span></td><td><code>customer@multivendor.test</code></td><td><code>password</code></td><td><a href="/login">/login</a> — Belanja online</td></tr>
            </tbody></table></div>
        </div>

        {{-- Admin --}}
        <div class="section" id="admin"><h2>🛡️ Tutorial Admin — 10 Langkah</h2>

            <h3><span class="step">1</span> Login</h3>
            <p>Buka <a href="/admin/login">/admin/login</a>. Masuk dengan <code>admin@multivendor.test</code> / <code>password</code>. Halaman login two-column branded.</p>
            <img src="/screenshots/admin-login.png" class="ss" alt="Admin Login" loading="lazy">

            <h3><span class="step">2</span> Dashboard</h3>
            <p>Dashboard menampilkan statistik: vendor, pelanggan, produk, pesanan, pendapatan. Ada alert pending + tabel pesanan terbaru.</p>
            <img src="/screenshots/admin-dashboard.png" class="ss" alt="Admin Dashboard" loading="lazy">

            <h3><span class="step">3</span> Vendor Management</h3>
            <p>Menu <strong>Toko / Vendor</strong> — lihat semua vendor. Filter by status. Tambah vendor baru dengan form komisi.</p>
            <img src="/screenshots/admin-vendors.png" class="ss" alt="Admin Vendors" loading="lazy">

            <h3><span class="step">4</span> Moderasi Produk</h3>
            <p>Menu <strong>Moderasi Produk</strong> — lihat semua produk dari vendor. Approve atau suspend produk.</p>
            <img src="/screenshots/admin-products.png" class="ss" alt="Admin Products" loading="lazy">

            <h3><span class="step">5</span> Pesanan</h3>
            <p>Menu <strong>Pesanan</strong> — lihat semua pesanan dengan status overview cards. Update status: konfirmasi → proses → kirim → sampai.</p>
            <img src="/screenshots/admin-orders.png" class="ss" alt="Admin Orders" loading="lazy">

            <h3><span class="step">6</span> Kupon & Promosi</h3>
            <p>Menu <strong>Kupon</strong> — buat kupon diskon (%, Rp, free ongkir). <strong>Flash Deal</strong> — flash sale multi produk dengan timer.</p>
            <img src="/screenshots/admin-flashdeals.png" class="ss" alt="Flash Deals" loading="lazy">

            <h3><span class="step">7</span> Integrasi Provider</h3>
            <p>Menu <strong>Integrasi</strong> — tambah payment gateway, shipping, AI provider. Pilih preset, autofill, masukkan API key sendiri.</p>
            <img src="/screenshots/admin-providers.png" class="ss" alt="Providers" loading="lazy">

            <h3><span class="step">8</span> Laporan & AI</h3>
            <p>Menu <strong>Laporan</strong> — top 15 produk terlaris. Klik <strong>Analisis</strong> untuk AI insight + rekomendasi.</p>
            <img src="/screenshots/admin-reports.png" class="ss" alt="Reports + AI" loading="lazy">

            <h3><span class="step">9</span> Pengaturan</h3>
            <p>Menu <strong>Pengaturan</strong> — konfigurasi SMTP, komisi, mata uang. <strong>Bahasa</strong> — edit translasi ID/EN.</p>

            <h3><span class="step">10</span> Export & Maintenance</h3>
            <p>Menu <strong>Export CSV</strong> — download laporan. <strong>Maintenance</strong> — toggle maintenance mode + clear cache.</p>
        </div>

        {{-- Vendor --}}
        <div class="section" id="vendor"><h2>🏪 Tutorial Vendor — 12 Langkah</h2>

            <h3><span class="step">1</span> Login</h3>
            <p>Buka <a href="/vendor/login">/vendor/login</a>. Masuk dengan <code>vendor@multivendor.test</code> / <code>password</code>.</p>
            <img src="/screenshots/vendor-login.png" class="ss" alt="Vendor Login" loading="lazy">

            <h3><span class="step">2</span> Dashboard</h3>
            <p>Stats: produk aktif, pesanan baru, pendapatan, saldo wallet. Tombol <strong>Mode Liburan</strong> untuk tutup toko sementara.</p>
            <img src="/screenshots/vendor-dashboard.png" class="ss" alt="Vendor Dashboard" loading="lazy">

            <h3><span class="step">3</span> Produk Saya</h3>
            <p>List semua produk. <strong>Tambah Produk</strong> — form multi-tab (5 step) dengan WYSIWYG Quill editor, upload foto & video.</p>
            <img src="/screenshots/vendor-products.png" class="ss" alt="Vendor Products" loading="lazy">

            <h3><span class="step">4</span> POS (Point of Sale)</h3>
            <p>Layar kasir untuk transaksi offline. Klik produk → masuk keranjang → diskon → pilih pembayaran → bayar.</p>
            <img src="/screenshots/vendor-pos.png" class="ss" alt="POS" loading="lazy">

            <h3><span class="step">5</span> Pesanan</h3>
            <p>Lihat pesanan masuk. Update status: konfirmasi → proses → kirim. Bisa input nomor resi.</p>
            <img src="/screenshots/vendor-orders.png" class="ss" alt="Vendor Orders" loading="lazy">

            <h3><span class="step">6</span> Kupon Toko</h3>
            <p>Buat kupon diskon khusus toko Anda. Persentase, nominal, atau gratis ongkir.</p>
            <img src="/screenshots/vendor-coupons.png" class="ss" alt="Vendor Coupons" loading="lazy">

            <h3><span class="step">7</span> Wallet & Payout</h3>
            <p>Lihat saldo, riwayat transaksi. Ajukan pencairan dana ke rekening bank.</p>
            <img src="/screenshots/vendor-wallet.png" class="ss" alt="Vendor Wallet" loading="lazy">

            <h3><span class="step">8</span> Laporan</h3>
            <p>Lihat performa produk (terjual, revenue), laporan pesanan (date filter), laporan transaksi (komisi admin).</p>
            <img src="/screenshots/vendor-reports.png" class="ss" alt="Vendor Reports" loading="lazy">

            <h3><span class="step">9</span> Barcode</h3>
            <p>Pilih produk, generate barcode untuk dicetak.</p>
            <img src="/screenshots/vendor-barcode.png" class="ss" alt="Vendor Barcode" loading="lazy">

            <h3><span class="step">10</span> Galeri</h3>
            <p>Tampilan grid semua produk dengan foto.</p>
            <img src="/screenshots/vendor-gallery.png" class="ss" alt="Vendor Gallery" loading="lazy">

            <h3><span class="step">11</span> Stok Menipis & Restock</h3>
            <p>Alert otomatis untuk produk dengan stok ≤ 10. Lihat restock request dari customer.</p>

            <h3><span class="step">12</span> Pengaturan Toko</h3>
            <p>Edit nama toko, deskripsi, alamat, logo, banner, info bank, dan metode pengiriman.</p>
            <img src="/screenshots/vendor-settings.png" class="ss" alt="Vendor Settings" loading="lazy">
        </div>

        {{-- Customer --}}
        <div class="section" id="customer"><h2>🛒 Tutorial Pelanggan — 15 Langkah</h2>

            <h3><span class="step">1</span> Homepage</h3>
            <p>Buka <a href="/">/</a>. Homepage menampilkan Deal of the Day, Featured Products, Flash Deals, dan 16 fitur.</p>
            <img src="/screenshots/store-home.png" class="ss" alt="Homepage" loading="lazy">

            <h3><span class="step">2</span> Produk</h3>
            <p>Buka <a href="/products">/products</a>. Browsing dengan filter kategori, harga, search, sort. Discount badge + rating stars.</p>
            <img src="/screenshots/store-products.png" class="ss" alt="Products" loading="lazy">

            <h3><span class="step">3</span> Detail Produk</h3>
            <p>Foto + thumbnail gallery + video. Info stok/terjual/SKU/brand. Varian selector. Deskripsi WYSIWYG. Review + submit.</p>
            <img src="/screenshots/store-product-detail.png" class="ss" alt="Product Detail" loading="lazy">

            <h3><span class="step">4</span> Cart</h3>
            <p>Keranjang multi-vendor — auto split per toko. Update qty, hapus item. Ringkasan belanja + total.</p>
            <img src="/screenshots/store-cart.png" class="ss" alt="Cart" loading="lazy">

            <h3><span class="step">5</span> Checkout</h3>
            <p>Pilih alamat, payment gateway, shipping per toko, kupon. Klik Bayar.</p>
            <img src="/screenshots/store-cart.png" class="ss" alt="Checkout" loading="lazy">

            <h3><span class="step">6</span> Pesanan</h3>
            <p>Lihat semua pesanan dengan status. Klik detail untuk lihat item + riwayat status.</p>
            <img src="/screenshots/store-orders.png" class="ss" alt="Orders" loading="lazy">

            <h3><span class="step">7</span> Lacak Pesanan</h3>
            <p>Input nomor pesanan di <a href="/track-order">/track-order</a> — lihat status + item + riwayat.</p>
            <img src="/screenshots/store-track-order.png" class="ss" alt="Track Order" loading="lazy">

            <h3><span class="step">8</span> Wishlist</h3>
            <p>Klik hati di produk untuk simpan. Lihat semua di halaman Wishlist.</p>
            <img src="/screenshots/store-wishlist.png" class="ss" alt="Wishlist" loading="lazy">

            <h3><span class="step">9</span> Bandingkan</h3>
            <p>Klik timbangan untuk bandingkan max 4 produk. Tabel perbandingan: harga, toko, kategori, stok, brand.</p>
            <img src="/screenshots/store-compare.png" class="ss" alt="Compare" loading="lazy">

            <h3><span class="step">10</span> Loyalty Points</h3>
            <p>Dapat poin dari referral + belanja. Tukar poin ke wallet (100 poin = Rp 100).</p>
            <img src="/screenshots/store-loyalty.png" class="ss" alt="Loyalty" loading="lazy">

            <h3><span class="step">11</span> Profil</h3>
            <p>Edit nama, password. Tambah alamat pengiriman. Lihat wallet + referral code.</p>
            <img src="/screenshots/store-profile.png" class="ss" alt="Profile" loading="lazy">

            <h3><span class="step">12</span> Blog</h3>
            <p>Baca artikel di <a href="/blog">/blog</a>. RSS Feed tersedia.</p>
            <img src="/screenshots/store-blog.png" class="ss" alt="Blog" loading="lazy">

            <h3><span class="step">13</span> Social Feed</h3>
            <p>Scroll produk ala TikTok di <a href="/feed">/feed</a>. Video + foto + caption. Klik beli langsung.</p>
            <img src="/screenshots/store-feed.png" class="ss" alt="Social Feed" loading="lazy">

            <h3><span class="step">14</span> Group Buy</h3>
            <p>Beli bareng untuk diskon lebih besar. Progress bar peserta. Join dan share.</p>
            <img src="/screenshots/store-group-buys.png" class="ss" alt="Group Buy" loading="lazy">

            <h3><span class="step">15</span> Leaderboard</h3>
            <p>Top 20 pembeli dengan total belanja. Badge tier (bronze/silver/gold).</p>
            <img src="/screenshots/store-leaderboard.png" class="ss" alt="Leaderboard" loading="lazy">
        </div>

        {{-- Payment --}}
        <div class="section" id="payment"><h2>💳 Payment Gateway</h2>
            <div class="row g-3">
                @foreach(['Midtrans Snap','Midtrans Core','Xendit','Tripay','Duitku','OY! Indonesia','iPaymu','Faspay','DOKU','ESIA Pay'] as $gw)
                <div class="col-md-6"><div class="border rounded-3 p-3"><h6 class="fw-bold">{{ $gw }}</h6><p class="small text-muted mb-0">Admin → Integrasi → Tambah Provider → Pilih preset → Autofill → Masukkan API Key → Aktifkan.</p></div></div>
                @endforeach
            </div>
        </div>

        {{-- Shipping --}}
        <div class="section" id="shipping"><h2>🚚 Shipping / Ongkos Kirim</h2>
            <div class="row g-3">
                @foreach(['RajaOngkir','JNE','J&T Express','SiCepat','TIKI','POS Indonesia','SAP Express','Lion Parcel','AnterAja','iDexpress','GoSend','GrabExpress','Borzo','Deliveree'] as $sp)
                <div class="col-md-6"><div class="border rounded-3 p-3"><h6 class="fw-bold">{{ $sp }}</h6><p class="small text-muted mb-0">Admin → Integrasi → Tambah Provider → Pilih preset → Autofill → Masukkan API Key. Cek ongkir real-time di checkout.</p></div></div>
                @endforeach
            </div>
        </div>

        {{-- AI --}}
        <div class="section" id="ai"><h2>🤖 AI Analytics (BYOK)</h2>
            <div class="row g-3">
                @foreach(['DeepSeek','OpenAI GPT-4o','Groq','Mistral','Together AI','OpenRouter','Fireworks AI','xAI Grok','Ollama (FREE)','LM Studio (FREE)'] as $ai)
                <div class="col-md-6"><div class="border rounded-3 p-3"><h6 class="fw-bold">{{ $ai }}</h6><p class="small text-muted mb-0">Admin → Integrasi → Tambah Provider → AI/LLM → Pilih preset → Autofill → Masukkan API Key → Buka Laporan → Analisis.</p></div></div>
                @endforeach
            </div>
        </div>

        {{-- Features --}}
        <div class="section" id="features"><h2>⭐ Semua Fitur</h2>
            <div class="row g-3">
                @php $f = [
                    ['i'=>'fa-store-alt','c'=>'primary','t'=>'Multi Vendor','d'=>'Vendor daftar, buka toko, kelola produk. Admin kontrol + komisi.'],
                    ['i'=>'fa-box','c'=>'success','t'=>'Produk','d'=>'5-tab form, WYSIWYG, foto, video, varian, tags, SEO.'],
                    ['i'=>'fa-cash-register','c'=>'danger','t'=>'POS','d'=>'Point of Sale untuk transaksi offline.'],
                    ['i'=>'fa-credit-card','c'=>'info','t'=>'Payment','d'=>'10 gateway BYOK. User input API key sendiri.'],
                    ['i'=>'fa-truck','c'=>'warning','t'=>'Shipping','d'=>'16 kurir BYOK. Ongkir real-time.'],
                    ['i'=>'fa-robot','c'=>'purple','t'=>'AI','d'=>'10 provider. Self-hosted Ollama gratis.'],
                    ['i'=>'fa-wallet','c'=>'teal','t'=>'Wallet','d'=>'Dompet + komisi otomatis + pencairan.'],
                    ['i'=>'fa-ticket-alt','c'=>'orange','t'=>'Promo','d'=>'Kupon, Flash Deal, Deal of Day, Featured, Clearance.'],
                    ['i'=>'fa-chart-bar','c'=>'indigo','t'=>'Laporan','d'=>'Revenue, top produk, AI insight, export CSV.'],
                    ['i'=>'fa-blog','c'=>'pink','t'=>'Blog + SEO','d'=>'Blog CMS, sitemap, IndexNow, robots.txt.'],
                    ['i'=>'fa-users','c'=>'dark','t'=>'Customer','d'=>'Wishlist, Compare, Loyalty, Referral, Ticket, Feed.'],
                    ['i'=>'fa-plug','c'=>'secondary','t'=>'API','d'=>'REST v1/v2/v3 untuk Flutter app.'],
                ]; @endphp
                @foreach($f as $x)
                <div class="col-md-6"><div class="border rounded-3 p-3"><div class="d-flex gap-3"><div class="rounded-3 bg-{{ $x['c'] }}-subtle text-{{ $x['c'] }} d-flex align-items-center justify-content-center" style="width:44px;height:44px"><i class="fas {{ $x['i'] }}"></i></div><div><h6 class="fw-bold mb-1">{{ $x['t'] }}</h6><p class="small text-muted mb-0">{{ $x['d'] }}</p></div></div></div></div>
                @endforeach
            </div>
        </div>

    </div>
</div></div>

<footer class="bg-dark text-white py-4"><div class="container text-center small opacity-50">&copy; {{ date('Y') }} {{ config('app.name') }}. Platform Multivendor E-Commerce Indonesia.</div></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
