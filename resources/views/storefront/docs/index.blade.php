<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokumentasi Lengkap — {{ config('app.name') }}</title>
    <meta name="description" content="Panduan lengkap menggunakan platform multivendor e-commerce. Tutorial admin, vendor, dan pelanggan step-by-step dengan screenshot. Demo accounts tersedia.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--brand:#4F46E5;--brand-dark:#3730A3}body{font-family:'Inter',sans-serif;background:#f8f9fc;scroll-behavior:smooth}
        .sidebar-docs{position:sticky;top:80px;max-height:calc(100vh - 100px);overflow-y:auto}
        .sidebar-docs .nav-link{color:#475569;padding:6px 12px;border-left:3px solid transparent;font-size:.875rem}
        .sidebar-docs .nav-link.active,.sidebar-docs .nav-link:hover{color:var(--brand);border-left-color:var(--brand);background:#eef2ff}
        .content-section{background:#fff;border-radius:16px;padding:32px;box-shadow:0 2px 12px rgba(0,0,0,.04);margin-bottom:24px}
        .content-section h2{font-weight:800;font-size:1.5rem;margin-top:0}
        .content-section h3{font-weight:700;font-size:1.2rem;margin-top:24px;color:var(--brand)}
        .screenshot-box{background:#1e293b;border-radius:12px;margin:16px 0;text-align:center}
        .screenshot-box img{max-width:100%}
        .demo-table td{padding:10px 14px;font-size:.9rem}
        .step-num{display:inline-flex;width:32px;height:32px;background:var(--brand);color:#fff;border-radius:50%;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;margin-right:8px}
        pre{background:#f1f5f9;padding:16px;border-radius:8px;font-size:.85rem}
        .feature-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
        .feature-card{border:1px solid #e2e8f0;border-radius:12px;padding:20px;transition:all .2s}
        .feature-card:hover{border-color:var(--brand);box-shadow:0 4px 16px rgba(79,70,229,.1)}
        .feature-card .icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;margin-bottom:12px}
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg sticky-top bg-white shadow-sm"><div class="container">
    <a class="navbar-brand fw-bold" href="/"><i class="fas fa-store-alt text-primary me-2"></i>{{ config('app.name') }}</a>
    <a href="/admin/login" class="btn btn-primary btn-sm">Admin Panel</a>
</div></nav>

<div class="container py-4"><div class="row g-4">
    {{-- Sidebar --}}
    <div class="col-lg-3"><div class="sidebar-docs">
        <h6 class="fw-bold mb-3"><i class="fas fa-book me-2"></i>Dokumentasi</h6>
        <nav class="nav flex-column">
            <a class="nav-link active" href="#demo"><span class="step-num" style="width:24px;height:24px;font-size:.7rem">1</span> Akun Demo</a>
            <a class="nav-link" href="#admin"><span class="step-num" style="width:24px;height:24px;font-size:.7rem">2</span> Panel Admin</a>
            <a class="nav-link" href="#vendor"><span class="step-num" style="width:24px;height:24px;font-size:.7rem">3</span> Panel Vendor</a>
            <a class="nav-link" href="#customer"><span class="step-num" style="width:24px;height:24px;font-size:.7rem">4</span> Pelanggan</a>
            <a class="nav-link" href="#payment"><span class="step-num" style="width:24px;height:24px;font-size:.7rem">5</span> Payment Gateway</a>
            <a class="nav-link" href="#shipping"><span class="step-num" style="width:24px;height:24px;font-size:.7rem">6</span> Shipping</a>
            <a class="nav-link" href="#ai"><span class="step-num" style="width:24px;height:24px;font-size:.7rem">7</span> AI Analytics</a>
            <a class="nav-link" href="#features"><span class="step-num" style="width:24px;height:24px;font-size:.7rem">8</span> Semua Fitur</a>
        </nav>
    </div></div>

    {{-- Content --}}
    <div class="col-lg-9">

        {{-- Hero --}}
        <div class="content-section text-center">
            <h1 class="fw-bold mb-2">📚 Dokumentasi {{ config('app.name') }}</h1>
            <p class="lead">Panduan lengkap menggunakan platform multivendor e-commerce. Tutorial step-by-step untuk Admin, Vendor, dan Pelanggan.</p>
            <p class="text-muted">Terakhir update: {{ date('d F Y') }}</p>
        </div>

        {{-- Demo Accounts --}}
        <div class="content-section" id="demo">
            <h2><i class="fas fa-flask me-2 text-warning"></i>Akun Demo</h2>
            <p>Gunakan akun berikut untuk mencoba semua fitur:</p>
            <div class="table-responsive"><table class="table table-bordered demo-table">
                <thead class="table-light"><tr><th>Role</th><th>Email</th><th>Password</th><th>Panel</th><th>Akses Fitur</th></tr></thead>
                <tbody>
                    <tr><td><span class="badge bg-danger">Admin</span></td><td><code>admin@multivendor.test</code></td><td><code>password</code></td><td><a href="/admin/login" class="btn btn-sm btn-outline-primary">/admin/login</a></td><td>Dashboard, Vendor, Produk, Kategori, Brand, Pesanan, Transaksi, Kupon, Flash Deal, Deal of the Day, Featured Deal, Most Demanded, Pelanggan, Kurir, Employee, Banner, Blog, Notifikasi, SEO Produk, Tiket Support, Pengaturan, Bahasa, Mata Uang, Pajak/VAT, Translation DB, Custom Role, Withdraw, Email Templates, Pembayaran Offline, Integrasi, File Manager, Laporan AI, Stok Produk, Penjualan Vendor, Export CSV, SMS Gateway, 3rd Party, Maintenance, Product Bundles, Pages, Help Topics, Contacts, Vendor Settings, Inhouse Shop</td></tr>
                    <tr><td><span class="badge bg-success">Vendor</span></td><td><code>vendor@multivendor.test</code></td><td><code>password</code></td><td><a href="/vendor/login" class="btn btn-sm btn-outline-success">/vendor/login</a></td><td>Dashboard, POS, Produk, Bulk Import, Barcode, Galeri, Produk Digital, Pesanan, Order Edit, Refund, Ulasan, Kupon Toko, Clearance Sale, Laporan, Wallet & Payout, Metode Pengiriman, Restock Request, Stok Menipis, Chat/Inbox, Pengaturan Toko</td></tr>
                    <tr><td><span class="badge bg-primary">Customer</span></td><td><code>customer@multivendor.test</code></td><td><code>password</code></td><td><a href="/login" class="btn btn-sm btn-outline-primary">/login</a></td><td>Belanja, Cart, Checkout, Pesanan, Lacak Pesanan, Wishlist, Bandingkan, Tiket Support, Loyalty Points, Profil, Alamat, Feed, Bundle, Group Buy, Leaderboard</td></tr>
                    <tr><td><span class="badge bg-info">Kurir</span></td><td><code>delivery1@demo.test</code></td><td><code>password</code></td><td><small>Mobile API</small></td><td>Orders, Update Status, Wallet, Earnings (via Flutter app)</td></tr>
                    <tr><td><span class="badge bg-secondary">Employee</span></td><td><code>employee1@demo.test</code></td><td><code>password</code></td><td><small>Custom role</small></td><td>Tergantung permission yang diberikan admin</td></tr>
                </tbody>
            </table></div>
        </div>

        {{-- Admin Tutorial --}}
        <div class="content-section" id="admin">
            <h2><i class="fas fa-user-shield me-2 text-danger"></i>Tutorial Admin — 25 Langkah</h2>
            <p>Panduan lengkap untuk administrator platform multivendor.</p>

            <h3>Fase 1: Login & Dashboard</h3>
            <p><span class="step-num">1</span> Buka <a href="/admin/login">/admin/login</a>. Masuk dengan <code>admin@multivendor.test</code> / <code>password</code>.</p>
            <img src="/screenshots/admin-login.png" class="img-fluid rounded-3 shadow-sm mb-2" alt="Admin Login" loading="lazy"><small class="text-muted">Halaman Login Admin — two-column branded login dengan hero panel kiri (gradient ungu) dan form login kanan. Terdapat demo login box di bawah form.</div>
            <p><span class="step-num">2</span> Anda akan masuk ke <strong>Dashboard Admin</strong>. Dashboard menampilkan: total vendor, pelanggan, produk, pesanan, pendapatan. Juga ada alert pending (toko baru, produk baru, pesanan baru), tabel pesanan terbaru, dan toko baru.</p>
            <img src="/screenshots/admin-dashboard.png" class="img-fluid rounded-3 shadow-sm mb-2" alt="Dashboard Admin" loading="lazy"><small class="text-muted">Dashboard Admin — 5 stat cards (Vendor, Pelanggan, Produk, Pesanan, Pendapatan), alert cards warna-warni, tabel pesanan terbaru, sidebar navigasi lengkap</div>

            <h3>Fase 2: Setup Integrasi</h3>
            <p><span class="step-num">3</span> Buka menu <strong>Integrasi</strong>. Klik <strong>Tambah Provider</strong>. Pilih tipe Payment/Shipping/AI, pilih format API, klik tombol magic untuk autofill preset, masukkan API key Anda sendiri.</p>
            <img src="/screenshots/admin-providers.png" class="img-fluid rounded-3 shadow-sm mb-2" alt="Integrasi Provider" loading="lazy"><small class="text-muted">Halaman Integrasi Provider — dropdown tipe (Payment/Shipping/AI), dropdown format API, tombol autofill preset, field API key dan secret, toggle aktif</div>
            <p><span class="step-num">4</span> Provider yang sudah diaktifkan akan muncul di halaman Integrasi. Payment provider akan muncul di checkout customer. Shipping provider untuk cek ongkir. AI provider untuk laporan analisis.</p>

            <h3>Fase 3: Master Data</h3>
            <p><span class="step-num">5</span> Buka menu <strong>Kategori</strong> — tambah kategori produk (Elektronik, Fashion, dll). Bisa tambah sub-kategori (parent-child). Set icon Font Awesome.</p>
            <p><span class="step-num">6</span> Buka menu <strong>Brand</strong> — tambah brand produk (Apple, Samsung, Nike, dll).</p>
            <p><span class="step-num">7</span> Buka menu <strong>Toko / Vendor</strong> — lihat semua vendor. Bisa filter status (pending/active/suspended). Klik tombol <strong>Tambah Vendor</strong> untuk daftarkan vendor baru.</p>
            <img src="/screenshots/admin-vendors.png" class="img-fluid rounded-3 shadow-sm mb-2" alt="Vendors" loading="lazy"><small class="text-muted">Halaman Vendor — tabel dengan kolom Toko, Vendor, Kontak, Komisi, Status. Ada filter search + status dropdown. Tombol Tambah Vendor di kanan atas.</div>
            <p><span class="step-num">8</span> Setiap vendor punya halaman detail (klik nama toko) — tampil info vendor, komisi, produk, dan pesanan vendor tersebut.</p>

            <h3>Fase 4: Moderasi Produk</h3>
            <p><span class="step-num">9</span> Buka menu <strong>Moderasi Produk</strong> — lihat semua produk dari seluruh vendor. Filter by status (pending/approved/suspended).</p>
            <p><span class="step-num">10</span> Klik dropdown <strong>Aksi</strong> pada produk → <strong>Approve</strong> atau <strong>Suspend</strong>. Produk yang di-approve akan muncul di storefront.</p>
            <img src="/screenshots/admin-products.png" class="img-fluid rounded-3 shadow-sm mb-2" alt="Moderasi Produk" loading="lazy"><small class="text-muted">Halaman Moderasi Produk — tabel produk dengan thumbnail, nama, toko, harga, stok, status badge (warning/success/danger). Dropdown aksi: Detail, Approve, Suspend, Hapus.</div>

            <h3>Fase 5: Promosi & Marketing</h3>
            <p><span class="step-num">11</span> Buka <strong>Kupon</strong> — buat kupon diskon (persentase, nominal, gratis ongkir). Atur minimal pembelian, maksimal diskon, tanggal berlaku, batas pemakaian.</p>
            <p><span class="step-num">12</span> Buka <strong>Flash Deal</strong> — buat flash sale dengan multi produk. Tentukan timer mulai dan berakhir. Produk akan muncul di homepage.</p>
            <p><span class="step-num">13</span> Buka <strong>Deal of the Day</strong> — pilih 1 produk untuk diskon spesial hari ini.</p>
            <p><span class="step-num">14</span> Buka <strong>Featured Deal</strong> — pilih produk unggulan yang akan tampil di homepage.</p>
            <img src="/screenshots/admin-flashdeals.png" class="img-fluid rounded-3 shadow-sm mb-2" alt="Flash Deal" loading="lazy"><small class="text-muted">Halaman Flash Deal — judul, tanggal mulai/berakhir, grid produk dengan checkbox dan diskon per produk</div>

            <h3>Fase 6: Pesanan & Transaksi</h3>
            <p><span class="step-num">15</span> Buka <strong>Pesanan</strong> — lihat semua pesanan. Filter by status (pending → confirmed → processing → shipped → delivered → canceled). Klik detail untuk update status.</p>
            <img src="/screenshots/admin-orders.png" class="img-fluid rounded-3 shadow-sm mb-2" alt="Pesanan" loading="lazy"><small class="text-muted">Halaman Pesanan — status overview cards (Pending, Confirmed, Processing, Shipped, Delivered, Canceled), tabel pesanan dengan filter search + payment status</div>
            <p><span class="step-num">16</span> Buka <strong>Transaksi</strong> — lihat semua transaksi pembayaran dengan jumlah, metode, dan status.</p>

            <h3>Fase 7: Pengguna</h3>
            <p><span class="step-num">17</span> Buka <strong>Pelanggan</strong> — lihat semua customer dengan jumlah pesanan, status, dan wallet.</p>
            <p><span class="step-num">18</span> Buka <strong>Kurir</strong> — kelola delivery man (CRUD), lihat wallet + transaksi per kurir.</p>
            <p><span class="step-num">19</span> Buka <strong>Employee</strong> — kelola staff admin (CRUD + role).</p>

            <h3>Fase 8: Laporan & AI</h3>
            <p><span class="step-num">20</span> Buka <strong>Laporan</strong> — lihat statistik revenue dan top 15 produk terlaris.</p>
            <p><span class="step-num">21</span> Klik tombol <strong>Analisis</strong> — AI akan menganalisis data penjualan 30 hari dan memberikan insight + rekomendasi aksi. (Perlu AI provider di menu Integrasi)</p>
            <img src="/screenshots/admin-reports.png" class="img-fluid rounded-3 shadow-sm mb-2" alt="Laporan AI" loading="lazy"><small class="text-muted">Halaman Laporan + AI Analytics — stats overview, AI Analysis section dengan dropdown provider + model + tombol fetch models + tombol Analisis. Di bawahnya tabel Top 15 Produk Terlaris.</div>

            <h3>Fase 9: Sistem</h3>
            <p><span class="step-num">22</span> Buka <strong>Pengaturan</strong> — konfigurasi nama aplikasi, URL, SMTP, mata uang, komisi default, minimal payout.</p>
            <p><span class="step-num">23</span> Buka <strong>Bahasa</strong> — edit translasi ID ↔ EN.</p>
            <p><span class="step-num">24</span> Buka <strong>Export CSV</strong> — download laporan produk, pesanan, pelanggan, transaksi.</p>
            <p><span class="step-num">25</span> Buka <strong>Maintenance</strong> — toggle maintenance mode + clear cache.</p>
        </div>

        {{-- Vendor Tutorial --}}
        <div class="content-section" id="vendor">
            <h2><i class="fas fa-store me-2 text-success"></i>Tutorial Vendor — 20 Langkah</h2>
            <p>Panduan lengkap untuk penjual / vendor.</p>

            <h3>Login & Dashboard</h3>
            <p><span class="step-num">1</span> Buka <a href="/vendor/login">/vendor/login</a>. Masuk dengan <code>vendor@multivendor.test</code> / <code>password</code>.</p>
            <img src="/screenshots/vendor-login.png" class="img-fluid rounded-3 shadow-sm mb-2" alt="Vendor Login" loading="lazy"><small class="text-muted">Halaman Login Vendor — two-column branded login dengan hero panel kiri (gradient hijau) dan 3 benefit card (Kelola Produk, Proses Pesanan, Pantau Penjualan). Form login kanan + demo box + tombol Vacation Mode.</div>
            <p><span class="step-num">2</span> Dashboard vendor menampilkan: produk aktif, pesanan baru, total pesanan, pendapatan, saldo wallet. Juga ada tombol <strong>Mode Liburan</strong> untuk menutup toko sementara.</p>
            <img src="/screenshots/vendor-dashboard.png" class="img-fluid rounded-3 shadow-sm mb-2" alt="Vendor Dashboard" loading="lazy"><small class="text-muted">Dashboard Vendor — 5 stat cards hijau, tabel pesanan terbaru, tombol Vacation Mode toggle di kanan atas</div>

            <h3>Produk</h3>
            <p><span class="step-num">3</span> Buka <strong>Produk Saya</strong> — lihat semua produk Anda.</p>
            <p><span class="step-num">4</span> Klik <strong>Tambah Produk</strong> — form multi-tab (5 step): Basic Info, Harga & Stok, Gambar & Video, Varian & SKU, SEO & Tag. Dilengkapi WYSIWYG Quill Editor.</p>
            <img src="/screenshots/vendor-products.png" class="img-fluid rounded-3 shadow-sm mb-2" alt="Produk Vendor" loading="lazy"><small class="text-muted">Form Tambah Produk � 5-tab multi-step + WYSIWYG — step wizard (1-5), Quill editor untuk deskripsi, field nama, kategori, brand, harga, stok, upload foto utama, upload foto tambahan (max 5), upload video (URL + file), dynamic variant rows, SEO meta title & description, tags input</div>
            <p><span class="step-num">5</span> Produk akan berstatus <strong>Pending</strong> sampai admin approve.</p>
            <p><span class="step-num">6</span> <strong>Bulk Import</strong> — upload CSV/Excel untuk import banyak produk sekaligus.</p>
            <p><span class="step-num">7</span> <strong>Barcode</strong> — pilih produk, generate barcode untuk dicetak.</p>
            <p><span class="step-num">8</span> <strong>Galeri</strong> — tampilan grid semua produk dengan foto.</p>
            <p><span class="step-num">9</span> <strong>Stok Menipis</strong> — alert otomatis untuk produk dengan stok ≤ 10.</p>

            <h3>POS (Point of Sale)</h3>
            <p><span class="step-num">10</span> Buka <strong>Point of Sale</strong> — layar kasir untuk transaksi offline. Klik produk → masuk keranjang → diskon → pilih pembayaran (Cash/QRIS/Transfer) → tekan F8 untuk bayar.</p>
            <img src="/screenshots/vendor-pos.png" class="img-fluid rounded-3 shadow-sm mb-2" alt="POS" loading="lazy"><small class="text-muted">POS Screen � point of sale — grid produk kiri, keranjang kanan, input customer name, diskon, payment method, tombol Bayar hijau besar</div>

            <h3>Pesanan</h3>
            <p><span class="step-num">11</span> Buka <strong>Pesanan</strong> — lihat pesanan masuk. Klik detail untuk update status: Konfirmasi → Proses → Kirim. Bisa input nomor resi.</p>
            <p><span class="step-num">12</span> <strong>Order Edit</strong> — tambah/hapus produk dari pesanan yang sudah ada.</p>
            <p><span class="step-num">13</span> <strong>Refund</strong> — approve atau tolak permintaan refund customer.</p>

            <h3>Promosi & Keuangan</h3>
            <p><span class="step-num">14</span> <strong>Kupon Toko</strong> — buat kupon diskon khusus untuk toko Anda.</p>
            <p><span class="step-num">15</span> <strong>Clearance Sale</strong> — set diskon clearance untuk produk tertentu.</p>
            <p><span class="step-num">16</span> <strong>Wallet & Payout</strong> — lihat saldo, riwayat transaksi, dan ajukan pencairan dana ke rekening bank.</p>
            <img src="/screenshots/vendor-wallet.png" class="img-fluid rounded-3 shadow-sm mb-2" alt="Wallet Vendor" loading="lazy"><small class="text-muted">Wallet Vendor � saldo + pencairan — saldo besar, form pencairan (jumlah, bank, rekening, atas nama), tab riwayat transaksi + riwayat pencairan</div>

            <h3>Laporan & Pengaturan</h3>
            <p><span class="step-num">17</span> <strong>Laporan</strong> — lihat performa produk (terjual, revenue), laporan pesanan (date filter), laporan transaksi (komisi admin, diterima vendor).</p>
            <p><span class="step-num">18</span> <strong>Metode Pengiriman</strong> — enable/disable kurir + set ongkir per kurir.</p>
            <p><span class="step-num">19</span> <strong>Chat / Inbox</strong> — chat dengan customer yang pernah order.</p>
            <p><span class="step-num">20</span> <strong>Pengaturan Toko</strong> — edit nama toko, deskripsi, alamat, logo, banner, dan info bank untuk pencairan.</p>
        </div>

        {{-- Customer Tutorial --}}
        <div class="content-section" id="customer">
            <h2><i class="fas fa-user me-2 text-primary"></i>Tutorial Pelanggan — 15 Langkah</h2>
            <p>Panduan berbelanja di {{ config('app.name') }}.</p>

            <h3>Belanja</h3>
            <p><span class="step-num">1</span> Buka halaman utama <a href="/">/</a>. Homepage menampilkan: Deal of the Day (diskon besar), Featured Products (grid), Flash Deals (timer), dan 16 fitur platform.</p>
            <img src="/screenshots/store-home.png" class="img-fluid rounded-3 shadow-sm mb-2" alt="Homepage" loading="lazy"><small class="text-muted">Homepage dengan Featured Deal + Flash Deal — hero section dengan gradient ungu, search CTA, Deal of the Day kuning, Featured Products grid, Flash Deals section, Features grid 4×4, Demo Accounts table, Footer</div>
            <p><span class="step-num">2</span> <strong>Daftar akun</strong> di <a href="/register">/register</a> atau login dengan <code>customer@multivendor.test</code> / <code>password</code>. Bisa juga login dengan Google (Social Login). Ada field kode referral saat daftar.</p>
            <p><span class="step-num">3</span> Buka <a href="/products">/products</a> — browsing produk. Filter by kategori (sidebar kiri), harga (min/max), search, sort (terbaru/harga rendah/harga tinggi).</p>
            <img src="/screenshots/store-products.png" class="img-fluid rounded-3 shadow-sm mb-2" alt="Product Listing" loading="lazy"><small class="text-muted">Product Listing � grid + filter + search — sidebar filter kategori + harga, grid produk 4 kolom dengan foto, nama toko, nama produk, harga, rating stars, discount badge merah, produk featured dengan bintang</div>
            <p><span class="step-num">4</span> Klik produk untuk <strong>detail</strong>. Tampil: foto utama (klik thumbnail untuk ganti), video (YouTube embed atau HTML5 player), info produk (stok, terjual, SKU, brand, tipe, satuan), varian badge, tag, harga + diskon, deskripsi WYSIWYG, ulasan + form submit review, produk terkait.</p>
            <img src="/screenshots/store-product-detail.png" class="img-fluid rounded-3 shadow-sm mb-2" alt="Product Detail" loading="lazy"><small class="text-muted">Product Detail � foto + video + varian + review — foto besar dengan discount badge, thumbnail gallery, video section, varian selector, info cards grid, reviews dengan bintang, form submit review, related products</div>

            <h3>Cart & Checkout</h3>
            <p><span class="step-num">5</span> Klik <strong>Masukkan Keranjang</strong> — produk masuk ke cart. Cart auto split per toko (multi-vendor).</p>
            <p><span class="step-num">6</span> Buka <strong>Keranjang</strong> — lihat item per toko, update qty, hapus item.</p>
            <img src="/screenshots/store-products.png" class="img-fluid rounded-3 shadow-sm mb-2" alt="Cart" loading="lazy"><small class="text-muted">Cart � multi-vendor split per toko — grouped per toko dengan nama toko, item dengan thumbnail + nama + harga + qty input + tombol hapus, subtotal per toko, ringkasan belanja + total + tombol Checkout</div>
            <p><span class="step-num">7</span> Klik <strong>Checkout</strong> — pilih alamat pengiriman, pilih payment gateway (Midtrans/Xendit/dll), pilih shipping per toko, input kupon, catatan. Klik Bayar.</p>
            <img src="/screenshots/store-docs.png" class="img-fluid rounded-3 shadow-sm mb-2" alt="Docs" loading="lazy"><small class="text-muted">Halaman Dokumentasi /docs — alamat radio buttons, pesanan per toko, shipping dropdown per toko, coupon input, payment gateway radio buttons, total, tombol Bayar Sekarang</div>

            <h3>Setelah Belanja</h3>
            <p><span class="step-num">8</span> Buka <strong>Pesanan Saya</strong> — lihat semua pesanan dengan status.</p>
            <p><span class="step-num">9</span> <strong>Lacak Pesanan</strong> di <a href="/track-order">/track-order</a> — input nomor pesanan, lihat status + item + riwayat.</p>

            <h3>Fitur Tambahan</h3>
            <p><span class="step-num">10</span> <strong>Wishlist</strong> — klik hati di produk untuk simpan ke wishlist.</p>
            <p><span class="step-num">11</span> <strong>Bandingkan</strong> — klik timbangan untuk bandingkan max 4 produk.</p>
            <p><span class="step-num">12</span> <strong>Loyalty Points</strong> — dapat poin dari referral + belanja. Tukar ke wallet.</p>
            <p><span class="step-num">13</span> <strong>Social Feed</strong> di <a href="/feed">/feed</a> — scroll produk ala TikTok.</p>
            <p><span class="step-num">14</span> <strong>Group Buy</strong> — beli bareng untuk dapat diskon lebih besar.</p>
            <p><span class="step-num">15</span> <strong>Profil</strong> — edit nama, password, tambah alamat, lihat wallet + referral code.</p>
        </div>

        {{-- Payment --}}
        <div class="content-section" id="payment">
            <h2><i class="fas fa-credit-card me-2 text-info"></i>Payment Gateway Setup</h2>
            <div class="feature-grid">
                @foreach(['Midtrans Snap','Midtrans Core API','Xendit Invoice','Tripay Closed','Duitku Redirect','OY! Indonesia','iPaymu','Faspay','DOKU','ESIA Pay'] as $gw)
                <div class="feature-card"><div class="icon bg-info-subtle text-info"><i class="fas fa-credit-card"></i></div><h6 class="fw-bold">{{ $gw }}</h6><p class="small text-muted">Admin → Integrasi → Tambah Provider → Pilih {{ $gw }} → Autofill preset → Masukkan API Key → Aktifkan. Customer akan lihat opsi ini di checkout.</p></div>
                @endforeach
            </div>
        </div>

        {{-- Shipping --}}
        <div class="content-section" id="shipping">
            <h2><i class="fas fa-truck me-2 text-warning"></i>Shipping / Ongkos Kirim</h2>
            <div class="feature-grid">
                @foreach(['RajaOngkir Starter','RajaOngkir Pro','JNE','J&T Express','SiCepat','TIKI','POS Indonesia','SAP Express','Lion Parcel','AnterAja','iDexpress','GoSend','GrabExpress','Borzo','Deliveree','RPX/FedEx'] as $sp)
                <div class="feature-card"><div class="icon bg-success-subtle text-success"><i class="fas fa-truck"></i></div><h6 class="fw-bold">{{ $sp }}</h6><p class="small text-muted">Admin → Integrasi → Tambah Provider → Pilih {{ $sp }} → Autofill → Masukkan API Key. Cek ongkir real-time di checkout.</p></div>
                @endforeach
            </div>
        </div>

        {{-- AI --}}
        <div class="content-section" id="ai">
            <h2><i class="fas fa-robot me-2 text-purple"></i>AI Analytics (BYOK)</h2>
            <div class="feature-grid">
                @foreach(['DeepSeek','OpenAI (GPT-4o)','Groq','Mistral','Together AI','OpenRouter','Fireworks AI','xAI / Grok','Ollama (FREE self-hosted)','LM Studio (FREE self-hosted)'] as $ai)
                <div class="feature-card"><div class="icon bg-purple-subtle text-purple"><i class="fas fa-robot"></i></div><h6 class="fw-bold">{{ $ai }}</h6><p class="small text-muted">Admin → Integrasi → Tambah Provider → AI/LLM → Pilih {{ $ai }} → Autofill → Masukkan API Key → Buka Laporan → Klik Analisis. AI akan menganalisis penjualan + rekomendasi.</p></div>
                @endforeach
            </div>
            <p class="mt-3"><strong>Self-hosted (GRATIS):</strong> Install Ollama di laptop, pilih preset Ollama, URL <code>http://localhost:11434/v1</code>, tanpa API key. Bisa langsung analisis tanpa biaya.</p>
        </div>

        {{-- All Features --}}
        <div class="content-section" id="features">
            <h2><i class="fas fa-star me-2 text-warning"></i>Semua Fitur</h2>
            @php $allFeatures = [
                ['icon'=>'fa-store-alt','c'=>'primary','t'=>'Multi Vendor','d'=>'Vendor daftar, buka toko, kelola produk sendiri. Admin kontrol + approve/reject. Komisi otomatis per transaksi.'],
                ['icon'=>'fa-box','c'=>'success','t'=>'Manajemen Produk','d'=>'5-tab form: Basic Info, Harga & Stok, Gambar & Video, Varian & SKU, SEO & Tag. WYSIWYG Quill editor. Foto utama + 5 foto tambahan. Upload video.'],
                ['icon'=>'fa-shopping-cart','c'=>'warning','t'=>'Smart Cart & Checkout','d'=>'Multi-vendor global cart. Split order per toko otomatis. Dynamic payment + shipping selection. Coupon + note.'],
                ['icon'=>'fa-cash-register','c'=>'danger','t'=>'POS System','d'=>'Point of Sale untuk transaksi offline. Cari produk, cart dengan diskon, hold orders, cash/QRIS/transfer.'],
                ['icon'=>'fa-credit-card','c'=>'info','t'=>'Payment Gateway BYOK','d'=>'10 gateway preset: Midtrans, Xendit, Tripay, Duitku, OY, iPaymu, Faspay, DOKU, ESIA Pay. Format-based adapters. User input API key sendiri.'],
                ['icon'=>'fa-truck','c'=>'danger','t'=>'Shipping System','d'=>'16 kurir preset: RajaOngkir, JNE, J&T, SiCepat, TIKI, POS, SAP, GoSend, GrabExpress, Borzo, Deliveree, dll. Ongkir real-time.'],
                ['icon'=>'fa-robot','c'=>'purple','t'=>'AI Analytics','d'=>'10 AI provider BYOK. OpenAI-compatible adapter (1 adapter = 15+ provider). Auto-fetch models. Analisis penjualan + rekomendasi. Self-hosted Ollama gratis.'],
                ['icon'=>'fa-wallet','c'=>'teal','t'=>'Wallet & Payout','d'=>'Dompet digital customer & vendor. Komisi otomatis. Vendor withdraw request → admin approve. Info bank tersimpan.'],
                ['icon'=>'fa-ticket-alt','c'=>'orange','t'=>'Kupon & Flash Deal','d'=>'Kupon diskon (%, Rp, free ongkir). Flash deal timer. Deal of the Day. Featured Deal. Most Demanded. Clearance sale vendor.'],
                ['icon'=>'fa-chart-bar','c'=>'indigo','t'=>'Laporan Lengkap','d'=>'Revenue summary. Top 15 produk. Order stats. AI-powered insight. Stock report. Vendor sale report. Export CSV. Invoice PDF.'],
                ['icon'=>'fa-blog','c'=>'pink','t'=>'Blog & SEO','d'=>'Blog CMS: artikel, kategori, WYSIWYG Quill, meta SEO. Sitemap auto-generate (4 file). IndexNow auto-submit (Bing, Yandex, Seznam, Naver). robots.txt. RSS Feed.'],
                ['icon'=>'fa-users','c'=>'primary','t'=>'Customer Features','d'=>'Wishlist. Compare (max 4). Loyalty points + redeem. Referral system. Support ticket. Social Feed. Group Buy. Leaderboard. Price Alert. Restock request.'],
            ]; @endphp
            <div class="feature-grid">@foreach($allFeatures as $f)<div class="feature-card"><div class="icon bg-{{ $f['c'] }}-subtle text-{{ $f['c'] }}"><i class="fas {{ $f['icon'] }}"></i></div><h6 class="fw-bold">{{ $f['t'] }}</h6><p class="small text-muted">{{ $f['d'] }}</p></div>@endforeach</div>
        </div>

    </div>
</div></div>

<footer class="bg-dark text-white py-4"><div class="container text-center small opacity-50">&copy; {{ date('Y') }} {{ config('app.name') }}. Platform Multivendor E-Commerce Indonesia. Source Code Multivendor, Aplikasi Toko Online, Pengganti Shopee.</div></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>document.querySelectorAll('.sidebar-docs .nav-link').forEach(l=>l.addEventListener('click',function(e){e.preventDefault();document.querySelector(this.getAttribute('href')).scrollIntoView({behavior:'smooth'});document.querySelectorAll('.sidebar-docs .nav-link').forEach(x=>x.classList.remove('active'));this.classList.add('active')}));window.addEventListener('scroll',function(){let s=document.querySelectorAll('.content-section');s.forEach(function(e,i){if(window.scrollY+100>=e.offsetTop){document.querySelectorAll('.sidebar-docs .nav-link').forEach(l=>l.classList.remove('active'));document.querySelectorAll('.sidebar-docs .nav-link')[Math.min(i,document.querySelectorAll('.sidebar-docs .nav-link').length-1)].classList.add('active')}})});</script>
</body>
</html>
