# MultiVendor Marketplace — Laravel 13 Indonesia

> **EN:** Complete multi-vendor marketplace: public catalog, vendor shops, multi-vendor cart & checkout, BYOK payment gateway, shipping, wallet, commission, delivery, blog, SEO + 1M PSEO pages, and REST API.
> **ID:** Marketplace multi-vendor lengkap: katalog publik, toko vendor, cart & checkout multi-vendor, payment gateway BYOK, pengiriman, wallet, komisi, delivery, blog, SEO + 1M halaman PSEO, dan REST API.
> **AR:** سوق متعدد البائعين متكامل: كتالوج عام، متاجر البائعين، سلة ودفع متعدد البائعين، بوابات دفع BYOK، شحن، محفظة، عمولات، توصيل، مدونة، سيو + مليون صفحة PSEO، وواجهة API.

[![Laravel 13](https://img.shields.io/badge/Laravel-13-red)](https://laravel.com)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-blue)](https://php.net)
[![MySQL 8.4](https://img.shields.io/badge/MySQL-8.4-orange)](https://mysql.com)
[![License MIT](https://img.shields.io/badge/License-MIT-green)](LICENSE)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen)](https://github.com)

---

## 📞 Contact / Kontak / اتصل بنا

| | |
|---|---|
| **Developer** | **Lindu Cipta** |
| **WhatsApp** | **+62 812-9605-2010** — https://wa.me/6281296052010 |
| **Chat** | Click / Klik / اضغط: [wa.me/6281296052010](https://wa.me/6281296052010?text=Halo%20Lindu%20Cipta%2C%20saya%20tertarik%20dengan%20MultiVendor%20Marketplace) |

> EN: Need installation, customization, new features, or a license? Chat directly on WhatsApp.
> ID: Butuh instalasi, kustomisasi, tambah fitur, atau lisensi? Chat langsung via WhatsApp.
> AR: هل تحتاج إلى التثبيت أو التخصيص أو ميزات جديدة أو ترخيص؟ تواصل مباشرة عبر واتساب.

**Language / Bahasa / اللغة:** [🇬🇧 English](#-english) · [🇮🇩 Indonesia](#-indonesia) · [🇸🇦 العربية](#-العربية)

---

<a id="english"></a>
## 🇬🇧 English

### 1. Overview
MultiVendor Marketplace (Laravel 13, PHP 8.3+, MySQL 8.4, Bootstrap 5.3) is a 6valley-based multi-vendor e-commerce platform with a **100% dynamic BYOK provider system** — add any payment, shipping, or AI provider from Admin UI without code changes. All API keys are AES-256 encrypted at rest and masked in UI.

Demo data: 1 Admin, 100 vendors, 1000 customers, 50 delivery men, 50 employees, 100 brands, 3000 products, 1000 coupons, 50 flash deals, 100 deal-of-the-day, ~500 featured deals, 100 banners, 100 blog posts, 1000 reviews, 30 bundles, 50 group buys, 100 social feeds — **10,000+ records**.

### 2. Multi-Auth System
| Guard | Role | Access |
|---|---|---|
| `admin` | Admin / Staff / Employee | `/admin/login` → full dashboard |
| `vendor` | Vendor / Shop owner | `/vendor/login` → vendor panel + 4-step onboarding wizard |
| `web` | Customer | `/login` → storefront, cart, checkout, wallet, loyalty |
| `delivery` | Delivery man | `/delivery/*` + API v3 → cash-collect, order status |

Social login (Google, Facebook via Socialite), referral code at registration (+500 loyalty points to referrer), remember-me, throttled auth.

### 3. Admin Panel
**Dashboard:** revenue/vendor/customer/product/order stats, pending alerts (shops, products, orders), recent orders & shops, top stores/products/customers/delivery men, most rated products, wallet stats, order-status doughnut chart (Chart.js), per-employee view.

**Catalog & moderation:** vendor CRUD + approve/reject + commission per shop + status filter + order/transaction/review/clearance/product list per shop + withdraw methods; product moderation (approve/suspend), detail with variants & reviews, per-product SEO, filter by shop/brand, advanced search; 3-level categories + priority + SEO; brands; attributes + values; tags; VAT/Tax (e.g. PPN 11%, 0%); subscriptions (plans + vendor subscriptions + status).

**Orders & money:** order list + status/payment filter, detail + status flow (confirm–process–ship–deliver–cancel), tracking ID, verification code, delivery assignment, **order edit (add/remove products)**; transaction list + commission calc; vendor withdraw approve/reject; customer wallet adjust; delivery cash-collect tracking; tax report + vendor-wise tax.

**Promo:** admin coupons (%, Rp, free shipping, bearer admin/vendor, per-customer limit, per-order allocation); flash deals (multi-product + timer); featured deals; deal of the day; most demanded; clearance sale + priority; banners (position + sort + link); product bundles (title + % + products).

**Users:** customers (wallet, addresses, order history, loyalty, badges); delivery men CRUD + wallet + withdraw + rating + emergency contact + earning report; employees CRUD + custom roles & permissions.

**Marketing & content:** blog (categories, articles, publish/draft, per-article SEO, AI content); push notifications (basic + send/history); dashboard notifications + badges; social feeds; newsletter/contacts.

**System & settings:** app settings, currency (code/symbol/position/decimal), language + DB translations (ID/EN), email templates, offline payment methods, SMS gateway, third-party (reCaptcha, Maps, WhatsApp, FB Pixel, GA), file manager (upload/delete), maintenance mode + cache clear, CSV export (products/orders/customers/transactions), provider integration (BYOK), AI report (revenue + top products + AI analysis), stock / vendor-sale / inhouse / wishlist / order / transaction / expense / refund reports, vendor registration settings, software update check, DB optimize, env settings, error logs viewer, theme settings, module/addon manager (nwidart), invoice settings, order settings, discount settings, shipping-category cost, delivery restriction, storage settings, social media & chat settings, social login settings, inhouse shop, Firebase OTP (ready), pages (about/terms/privacy/return/FAQ), help topics, contact messages, profile, SEO (webmaster tools, sitemap upload), robots meta, priority setup.

### 4. Vendor Panel
**Dashboard:** real stats (products, orders, revenue, wallet), vacation mode toggle, setup-guide wizard (4 steps, skippable), recent orders.
**Products:** CRUD, thumbnail + additional photos, SKU variant combinations, color-wise images, video, **digital products** (private-disk upload + OTP download), bulk import (CSV/Excel), barcode generate + print/PDF, gallery grid, limited-stock alert, restock requests from customers, per-product SEO, translations, tax model, MOQ.
**Orders:** list + status filter, detail + items + status history, update status, order edit, invoice view + PDF download, refund approve/reject.
**Promo:** shop coupons CRUD, clearance sale config.
**Reports:** products, orders, transactions (order-wise + expense).
**Finance:** wallet + transaction history, withdraw request, bank info in shop settings, payment info.
**Shop:** name/desc/logo/banner, self-made shipping methods + costs, category shipping cost, other setup.
**POS:** fullscreen POS screen, cart + walk-in customer, discount, hold/resume orders, print invoice + PDF.
**More:** vendor↔customer chat inbox, delivery-man rating view, reviews list + reply, notifications, shipping toggles (10 couriers).

### 5. Storefront (Customer)
Home (`/`) with hero + featured + flash deals; product grid (`/products`) with search/filter/sort; product detail (`/products/{slug}`) with variants, reviews, price history, recommendations, related; shop page (`/shop/{slug}`) + follow; multi-vendor cart + split per shop (`/cart`); checkout (`/checkout`) with coupon + shipping-cost AJAX + address; orders list/detail/tracking (`/orders`); track by number (`/track-order`); compare list; wishlist; blog + detail + RSS (`/blog/feed.xml`); social feed (`/feed`); bundles (`/bundles`); group buys + join (`/group-buys`); leaderboard (`/leaderboard`); loyalty points + redeem (`/loyalty`); wallet; badges; profile + multi-address (`/profile`); support tickets + replies (`/tickets`); price alerts; restock requests; delivery-man rating; digital download via OTP; static pages (about/terms/privacy/return/FAQ); docs (`/docs`); sitemap (`/sitemap.xml` + 4 parts); dynamic `robots.txt`; auth + social auth + referral.

### 6. Payment (Dynamic BYOK, Zero Hardcode)
Formats & adapters: `midtrans-snap` (SnapRedirect), `midtrans-core` (Core API), `xendit-invoice`, `tripay-closed`, `duitku-redirect`, `oyindonesia-api`, `ipaymu-api`, `faspay-api`, `doku-api`, `esiapay-api` (generic redirect/API adapters). Admin adds provider via UI with preset autofill JSON (URL, key, secret, headers). Single payment group `PAY-*` for multi-vendor orders, verified-signature + idempotent webhook (`/webhook/payment/{provider}`, throttled), server-side recalculation of price/tax/discount/weight/shipping/stock/commission, atomic stock lock + one-time restore on cancel, idempotent wallet ledger + reserved withdraw balance, offline methods (COD/transfer).

### 7. Shipping (Dynamic)
RajaOngkir Starter/Pro + generic REST courier adapter, admin-added providers, per-shop origin (`shop_shipping_origin_{id}`), per-address destination (`shipping_destination_id`), cost calc + tracking, vendor self-made methods, category shipping cost, shipping types, zip/country codes.

### 8. AI BYOK
One OpenAI-compatible adapter = 15+ providers (OpenAI, DeepSeek, etc.), auto-fetch `/v1/models`, 10+ presets, admin report AI analysis, AI product creation (title/desc/SEO), AI blog generation, prompt templates, user-supplied keys.

### 9. SEO, PSEO & Marketing
Auto sitemap (main/products/categories/blog), **programmatic SEO 1M pages**: `alternatif-{slug}`, `pengganti-{slug}`, `source-code-{slug}`, `beli-{slug}`, `jual-{slug}`, `marketplace-{slug}`, `compare/{a}-vs-{b}`, `best/{slug}`, catch-all `{slug}` + chunk sitemaps `sitemap-pseo-{num}.xml`; IndexNow auto-submit (`seo:indexnow` + daily 02:45 scheduler) for Bing/Yandex/Seznam/Naver; dynamic robots.txt; canonical/OG meta; webmaster settings; RSS; docs page.

### 10. REST API (Sanctum)
- `GET /api/v1/products, /products/{slug}, /categories, /shops, /shops/{slug}` (public) + auth: profile, orders, cancel, reviews, track, cart CRUD.
- `POST /api/v1/login, /register` (throttled).
- `Vendor v2`: login, dashboard, products, orders, update status (`vendor.api`).
- `Delivery v3`: login, orders, update status (`delivery.api`).
- Filtered resources — shop bank accounts & customer PII never exposed.

### 11. Security & Reliability
Server-authoritative pricing, atomic stock, idempotent `PAY-*` callbacks, AES-256 key encryption + masking, private-disk digital files + OTP gate, auth/customer/vendor/admin/api throttles, role middleware, CSRF, audit logs, no secrets in repo, `LICENSE_DEV_BYPASS` localhost-only.

### 12. Tech Stack
Laravel 13 · PHP 8.3+ · MySQL 8.4 · Sanctum · Socialite · nwidart/laravel-modules · Bootstrap 5.3 + FontAwesome 6 · Chart.js · Vite · PHPUnit 12 (7 test files).

### 13. Installation
```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8765
php artisan queue:work
php artisan schedule:work
```
Configure DB/mail/queue/providers in Admin → Integration. Never put gateway credentials in code.

### 14. Demo Accounts
| Role | Email | Password | URL |
|---|---|---|---|
| Admin | admin@multivendor.test | password | `/admin/login` |
| Vendor | vendor@multivendor.test | password | `/vendor/login` |
| Customer | customer@multivendor.test | password | `/login` |

> Change demo passwords in production. See `/docs`.

### 15. Tests & Deploy
```bash
php artisan test
php artisan route:list
php artisan config:cache && php artisan route:cache && php artisan view:cache
```
See `DEPLOYMENT.md` (Nginx, Supervisor, queue, cron, storage, license). Set `APP_URL`, canonical/OG, submit `/sitemap.xml` to Search Console.

Need help? **Lindu Cipta — https://wa.me/6281296052010**

---

<a id="indonesia"></a>
## 🇮🇩 Indonesia

### 1. Ringkasan
MultiVendor Marketplace (Laravel 13, PHP 8.3+, MySQL 8.4, Bootstrap 5.3) adalah platform e-commerce multi-vendor berbasis 6valley dengan **sistem provider dinamis BYOK 100%** — tambah provider pembayaran, pengiriman, atau AI apa pun dari panel Admin tanpa ubah kode. Semua API key dienkripsi AES-256 dan disamarkan di UI.

Data demo: 1 Admin, 100 vendor, 1000 pelanggan, 50 kurir, 50 karyawan, 100 brand, 3000 produk, 1000 kupon, 50 flash deal, 100 deal of the day, ~500 featured deal, 100 banner, 100 artikel blog, 1000 ulasan, 30 bundel, 50 group buy, 100 social feed — **total 10.000+ records**.

### 2. Sistem Multi-Auth
| Guard | Peran | Akses |
|---|---|---|
| `admin` | Admin / Staf / Karyawan | `/admin/login` → dashboard penuh |
| `vendor` | Vendor / Pemilik toko | `/vendor/login` → panel vendor + wizard onboarding 4 langkah |
| `web` | Pelanggan | `/login` → etalase, cart, checkout, wallet, loyalitas |
| `delivery` | Kurir | `/delivery/*` + API v3 → cash-collect, status order |

Login sosial (Google, Facebook via Socialite), kode referral saat registrasi (+500 poin untuk pereferral), remember-me, throttle auth.

### 3. Panel Admin
**Dashboard:** statistik omzet/vendor/pelanggan/produk/order, peringatan pending (toko, produk, order), order & toko terbaru, toko/produk terlaris, pelanggan/kurir teratas, produk paling dirating, statistik wallet, grafik doughnut status order (Chart.js), tampilan per-employee.

**Katalog & moderasi:** CRUD toko + approve/reject + komisi per toko + filter status + daftar order/transaksi/ulasan/clearance/produk per toko + metode withdraw; moderasi produk (approve/suspend), detail varian & ulasan, SEO per produk, filter toko/brand, pencarian lanjutan; kategori 3 level + prioritas + SEO; brand; atribut + nilai; tag; VAT/pajak (mis. PPN 11%, 0%); langganan (paket + langganan vendor + status).

**Order & uang:** daftar order + filter status/pembayaran, detail + alur status (konfirmasi–proses–kirim–sampai–batal), tracking ID, kode verifikasi, penugasan kurir, **edit order (tambah/hapus produk)**; daftar transaksi + hitung komisi; approve/reject withdraw vendor; adjust wallet pelanggan; tracking cash-collect kurir; laporan pajak + pajak per vendor.

**Promo:** kupon admin (%, Rp, gratis ongkir, bearer admin/vendor, batas per pelanggan, alokasi per order); flash deal (multi-produk + timer); featured deal; deal of the day; most demanded; clearance sale + prioritas; banner (posisi + urutan + link); bundel produk (judul + % + produk).

**Pengguna:** pelanggan (wallet, alamat, riwayat order, loyalitas, badge); kurir CRUD + wallet + withdraw + rating + kontak darurat + laporan earning; karyawan CRUD + custom role & permission.

**Marketing & konten:** blog (kategori, artikel, publish/draft, SEO per artikel, konten AI); push notification (dasar + kirim/riwayat); notifikasi dashboard + badge; social feed; kontak/newsletter.

**Sistem & pengaturan:** pengaturan aplikasi, mata uang (kode/simbol/posisi/desimal), bahasa + terjemahan DB (ID/EN), template email, metode pembayaran offline, SMS gateway, third-party (reCaptcha, Maps, WhatsApp, FB Pixel, GA), file manager (upload/hapus), maintenance mode + clear cache, export CSV (produk/order/pelanggan/transaksi), integrasi provider (BYOK), laporan AI (omzet + produk teratas + analisis AI), laporan stok / penjualan vendor / inhouse / wishlist / order / transaksi / expense / refund, pengaturan registrasi vendor, cek update software, optimasi DB, pengaturan env, viewer error log, pengaturan tema, manajer modul/addon (nwidart), pengaturan invoice, pengaturan order, pengaturan diskon, biaya shipping per kategori, batasan delivery, pengaturan storage, media sosial & chat, login sosial, inhouse shop, OTP Firebase (ready), halaman (tentang/syarat/privasi/retur/FAQ), topik bantuan, pesan kontak, profil, SEO (webmaster, upload sitemap), meta robots, priority setup.

### 4. Panel Vendor
**Dashboard:** statistik real (produk, order, omzet, wallet), toggle vacation mode, wizard panduan setup (4 langkah, bisa skip), order terbaru.
**Produk:** CRUD, thumbnail + foto tambahan, kombinasi varian SKU, gambar per warna, video, **produk digital** (upload disk privat + download OTP), bulk import (CSV/Excel), barcode generate + cetak/PDF, galeri grid, peringatan stok menipis, permintaan restock pelanggan, SEO per produk, terjemahan, model pajak, MOQ.
**Pesanan:** daftar + filter status, detail + item + riwayat status, ubah status, edit order, invoice + download PDF, approve/reject refund.
**Promosi:** CRUD kupon toko, konfigurasi clearance sale.
**Laporan:** produk, pesanan, transaksi (per order + expense).
**Keuangan:** wallet + riwayat, request withdraw, info bank di pengaturan toko, info pembayaran.
**Toko:** nama/deskripsi/logo/banner, metode pengiriman sendiri + ongkos, ongkos per kategori, pengaturan lain.
**POS:** layar POS fullscreen, cart + pelanggan walk-in, diskon, hold/resume order, cetak invoice + PDF.
**Lainnya:** chat vendor↔pelanggan (inbox), rating delivery, daftar ulasan + balas, notifikasi, toggle pengiriman (10 kurir).

### 5. Etalase (Pelanggan)
Home (`/`) hero + unggulan + flash deal; grid produk (`/products`) + cari/filter/sort; detail (`/products/{slug}`) varian, ulasan, riwayat harga, rekomendasi; halaman toko (`/shop/{slug}`) + follow; cart multi-vendor terpisah per toko (`/cart`); checkout (`/checkout`) kupon + ongkir AJAX + alamat; daftar/detail/lacak order (`/orders`); lacak via nomor (`/track-order`); daftar banding; wishlist; blog + detail + RSS (`/blog/feed.xml`); social feed (`/feed`); bundel (`/bundles`); group buy + gabung (`/group-buys`); leaderboard (`/leaderboard`); poin loyalitas + redeem (`/loyalty`); wallet; badge; profil + multi-alamat (`/profile`); tiket support + balasan (`/tickets`); price alert; request restock; rating kurir; download digital via OTP; halaman statis; docs (`/docs`); sitemap (`/sitemap.xml` + 4 bagian); `robots.txt` dinamis; auth + sosial + referral.

### 6. Pembayaran (BYOK Dinamis, Tanpa Hardcode)
Format & adapter: `midtrans-snap` (SnapRedirect), `midtrans-core` (Core API), `xendit-invoice`, `tripay-closed`, `duitku-redirect`, `oyindonesia-api`, `ipaymu-api`, `faspay-api`, `doku-api`, `esiapay-api` (generic redirect/API). Admin tambah provider via UI dengan preset JSON (URL, key, secret, header). Satu grup bayar `PAY-*` untuk order multi-vendor, webhook terverifikasi + idempoten (`/webhook/payment/{provider}`, throttle), hitung ulang server (harga/pajak/diskon/bobot/ongkir/stok/komisi), kunci stok atomik + restore sekali saat batal, ledger wallet idempoten + saldo withdraw di-reserve, metode offline (COD/transfer).

### 7. Pengiriman (Dinamis)
RajaOngkir Starter/Pro + adapter REST generik, tambah provider via admin, origin per toko (`shop_shipping_origin_{id}`), destinasi per alamat (`shipping_destination_id`), hitung ongkir + tracking, metode vendor sendiri, ongkos per kategori, tipe pengiriman, kode pos/negara.

### 8. AI BYOK
Satu adapter kompatibel OpenAI = 15+ provider (OpenAI, DeepSeek, dll.), auto-fetch `/v1/models`, 10+ preset, analisis AI untuk laporan admin, pembuatan produk AI (judul/deskripsi/SEO), generate blog AI, template prompt, key milik sendiri.

### 9. SEO, PSEO & Marketing
Sitemap otomatis (utama/produk/kategori/blog), **PSEO 1 juta halaman**: `alternatif-{slug}`, `pengganti-{slug}`, `source-code-{slug}`, `beli-{slug}`, `jual-{slug}`, `marketplace-{slug}`, `compare/{a}-vs-{b}`, `best/{slug}`, catch-all `{slug}` + sitemap pecahan `sitemap-pseo-{num}.xml`; IndexNow auto-submit (`seo:indexnow` + scheduler harian 02:45) ke Bing/Yandex/Seznam/Naver; robots.txt dinamis; canonical/OG; webmaster; RSS; halaman docs.

### 10. REST API (Sanctum)
- `GET /api/v1/products, /products/{slug}, /categories, /shops, /shops/{slug}` (publik) + auth: profil, order, batal, ulasan, lacak, cart CRUD.
- `POST /api/v1/login, /register` (throttle).
- `Vendor v2`: login, dashboard, produk, order, ubah status (`vendor.api`).
- `Delivery v3`: login, order, ubah status (`delivery.api`).
- Resource terfilter — rekening toko & PII pelanggan tidak diekspos.

### 11. Keamanan & Keandalan
Harga otoritatif di server, stok atomik, callback `PAY-*` idempoten, enkripsi AES-256 + masking, file digital di disk privat + gerbang OTP, throttle auth/customer/vendor/admin/api, middleware role, CSRF, audit log, tanpa secret di repo, `LICENSE_DEV_BYPASS` hanya localhost.

### 12. Tech Stack
Laravel 13 · PHP 8.3+ · MySQL 8.4 · Sanctum · Socialite · nwidart/laravel-modules · Bootstrap 5.3 + FontAwesome 6 · Chart.js · Vite · PHPUnit 12 (7 file tes).

### 13. Instalasi
```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8765
php artisan queue:work
php artisan schedule:work
```
Konfigurasi DB/mail/queue/provider di Admin → Integrasi. Jangan taruh kredensial gateway di kode.

### 14. Akun Demo
| Peran | Email | Password | URL |
|---|---|---|---|
| Admin | admin@multivendor.test | password | `/admin/login` |
| Vendor | vendor@multivendor.test | password | `/vendor/login` |
| Pelanggan | customer@multivendor.test | password | `/login` |

> Ganti password demo di production. Lihat `/docs`.

### 15. Tes & Deploy
```bash
php artisan test
php artisan route:list
php artisan config:cache && php artisan route:cache && php artisan view:cache
```
Lihat `DEPLOYMENT.md` (Nginx, Supervisor, queue, cron, storage, license). Set `APP_URL`, canonical/OG, submit `/sitemap.xml` ke Search Console.

Butuh bantuan? **Lindu Cipta — https://wa.me/6281296052010**

---

<a id="العربية"></a>
## 🇸🇦 العربية

### 1. نظرة عامة
سوق MultiVendor (لارافيل 13، PHP 8.3+، MySQL 8.4، Bootstrap 5.3) هو منصة تجارة إلكترونية متعددة البائعين مع **نظام BYOK ديناميكي 100%** — أضف أي مزود دفع أو شحن أو ذكاء اصطناعي من لوحة الإدارة دون تعديل الكود. جميع مفاتيح API مشفرة بـ AES-256 ومخفية في الواجهة.

بيانات تجريبية: 1 مشرف، 100 بائع، 1000 عميل، 50 مندوب توصيل، 50 موظف، 100 علامة تجارية، 3000 منتج، 1000 قسيمة، 50 عرض خاطف، 100 عرض اليوم، ~500 عرض مميز، 100 بانر، 100 مقال، 1000 تقييم، 30 حزمة، 50 شراء جماعي، 100 منشور اجتماعي — **أكثر من 10,000 سجل**.

### 2. نظام الدخول المتعدد
| الحارس | الدور | الوصول |
|---|---|---|
| `admin` | مشرف / موظف | `/admin/login` → لوحة كاملة |
| `vendor` | بائع / صاحب متجر | `/vendor/login` → لوحة البائع + معالج 4 خطوات |
| `web` | عميل | `/login` → المتجر، السلة، الدفع، المحفظة، النقاط |
| `delivery` | مندوب توصيل | `/delivery/*` + API v3 → تحصيل نقدي، حالة الطلب |

دخول اجتماعي (Google وFacebook عبر Socialite)، كود إحالة عند التسجيل (+500 نقطة للمُحيل)، تذكرني، تحديد معدل الدخول.

### 3. لوحة المشرف
**لوحة القيادة:** إحصائيات الإيرادات/البائعين/العملاء/المنتجات/الطلبات، تنبيهات معلقة، أحدث الطلبات والمتاجر، الأعلى مبيعًا، الأعلى تقييمًا، إحصائيات المحفظة، رسم دائري للحالات (Chart.js)، عرض حسب الموظف.

**الكتالوج والإشراف:** إدارة المتاجر + قبول/رفض + عمولة لكل متجر + فلترة + قوائم الطلبات/المعاملات/التقييمات/التصفية/المنتجات + طرق السحب؛ إشراف المنتجات (قبول/تعليق)، تفاصيل المتغيرات والتقييمات، سيو لكل منتج، فلترة حسب المتجر/العلامة، بحث متقدم؛ فئات 3 مستويات + أولوية + سيو؛ علامات؛ خصائص + وسوم؛ ضرائب VAT (مثل 11% و0%)؛ اشتراكات (خطط + اشتراكات البائعين).

**الطلبات والأموال:** قائمة الطلبات + فلترة الحالة/الدفع، تفاصيل + مسار الحالة (تأكيد–تجهيز–شحن–تسليم–إلغاء)، رقم تتبع، رمز تحقق، تعيين مندوب، **تعديل الطلب (إضافة/حذف منتجات)**؛ قائمة المعاملات + حساب العمولة؛ قبول/رفض السحب؛ تعديل محفظة العميل؛ تحصيل الكاش؛ تقرير الضرائب.

**العروض:** قسائم المشرف (نسبة، مبلغ، شحن مجاني، bearer، حد لكل عميل)؛ عروض خاطفة (متعددة + مؤقت)؛ عروض مميزة؛ عرض اليوم؛ الأكثر طلبًا؛ تصفية Clearance + أولوية؛ بانرات (موضع + ترتيب + رابط)؛ حزم منتجات.

**المستخدمون:** العملاء (محفظة، عناوين، سجل، نقاط، شارات)؛ مناديب CRUD + محفظة + سحب + تقييم + طوارئ + أرباح؛ موظفون + أدوار مخصصة.

**التسويق والمحتوى:** مدونة (فئات، مقالات، مسودة/نشر، سيو، محتوى AI)؛ إشعارات فورية؛ إشعارات اللوحة + شارات؛ منشورات اجتماعية؛ جهات اتصال.

**النظام:** إعدادات التطبيق، العملة، اللغة + ترجمات DB (ID/EN)، قوالب البريد، دفع offline، بوابة SMS، طرف ثالث (reCaptcha، خرائط، واتساب، بكسل، GA)، مدير ملفات، صيانة + مسح الكاش، تصدير CSV، تكامل المزودين BYOK، تقارير AI، تقارير المخزون/المبيعات/الطلبات/المعاملات/المصاريف/الاسترداد، إعدادات تسجيل البائعين، تحديث النظام، تحسين DB، إعدادات البيئة، سجلات الأخطاء، الثيم، الوحدات، الفواتير، الطلبات، الخصومات، الشحن حسب الفئة، التوصيل، التخزين، التواصل، الدخول الاجتماعي، المتجر الداخلي، OTP، الصفحات، المساعدة، السيو والروبوت.

### 4. لوحة البائع
**القيادة:** إحصائيات حقيقية، وضع الإجازة، معالج الإعداد (4 خطوات)، أحدث الطلبات.
**المنتجات:** CRUD، صور، متغيرات SKU، صور الألوان، فيديو، **منتجات رقمية** (رفع خاص + تحميل OTP)، استيراد جماعي، باركود + طباعة/PDF، معرض، تنبيه المخزون، طلبات إعادة التعبئة، سيو، ترجمات، ضريبة، حد أدنى.
**الطلبات:** قائمة + فلترة، تفاصيل + سجل، تحديث الحالة، تعديل، فاتورة + PDF، استرداد.
**العروض:** قسائم المتجر، تصفية Clearance.
**التقارير:** المنتجات، الطلبات، المعاملات.
**المالية:** المحفظة + السجل، طلب سحب، معلومات البنك والدفع.
**المتجر:** الاسم/الوصف/الشعار/البانر، شحن خاص + تكاليف، شحن الفئات.
**POS:** شاشة كاملة، سلة + عميل، خصم، تعليق/استئناف، طباعة + PDF.
**أخرى:** دردشة مع العملاء، تقييمات + ردود، إشعارات، تبديل الشحن (10 شركات).

### 5. المتجر (العميل)
رئيسية (`/`) + مميز + خاطف؛ شبكة (`/products`) بحث/فلترة؛ تفاصيل (`/products/{slug}`) متغيرات وتقييمات وسجل أسعار؛ متجر (`/shop/{slug}`) + متابعة؛ سلة متعددة مقسمة (`/cart`)؛ دفع (`/checkout`) قسيمة + شحن AJAX + عناوين؛ طلبات (`/orders`)؛ تتبع (`/track-order`)؛ مقارنة؛ مفضلة؛ مدونة + RSS؛ feed؛ حزم؛ شراء جماعي؛ صدارة؛ نقاط + استبدال؛ محفظة؛ شارات؛ حساب + عناوين؛ تذاكر دعم؛ تنبيه سعر؛ طلب restock؛ تقييم مندوب؛ تحميل رقمي OTP؛ صفحات؛ docs؛ خريطة موقع؛ robots؛ دخول + اجتماعي + إحالة.

### 6. الدفع (BYOK ديناميكي)
الصيغ: `midtrans-snap` و`midtrans-core` و`xendit-invoice` و`tripay-closed` و`duitku` و`oy` و`ipaymu` و`faspay` و`doku` و`esiapay`. إضافة من الإدارة مع JSON جاهز. مجموعة دفع واحدة `PAY-*` لطلبات متعددة، webhook متحقق + idempotent، إعادة حساب من الخادم، قفل مخزون ذري + استعادة مرة واحدة، ledger idempotent، دفع offline.

### 7. الشحن (ديناميكي)
RajaOngkir Starter/Pro + REST عام، مزودون من الإدارة، أصل لكل متجر ووجهة لكل عنوان، حساب + تتبع، طرق البائع، تكلفة الفئات، رموز بريدية.

### 8. الذكاء الاصطناعي BYOK
محول OpenAI واحد = +15 مزودًا، جلب `/v1/models`، قوالب جاهزة، تحليل AI للتقارير، إنشاء منتجات ومقالات، مفاتيح خاصة.

### 9. السيو والتسويق
خريطة تلقائية، **مليون صفحة PSEO**: `alternatif-` و`pengganti-` و`source-code-` و`beli-` و`jual-` و`marketplace-` و`compare` و`best` وcatch-all + خرائط `sitemap-pseo-{num}.xml`؛ IndexNow (Bing/Yandex) + مجدول 02:45؛ robots ديناميكي؛ canonical/OG؛ RSS؛ docs.

### 10. REST API (Sanctum)
- `GET /api/v1/products, /products/{slug}, /categories, /shops` (عام) + auth: حساب، طلبات، إلغاء، تقييم، تتبع، سلة.
- `POST /api/v1/login, /register`.
- `Vendor v2` و`Delivery v3`: دخول، لوحة، منتجات/طلبات، تحديث الحالة.
- موارد مفلترة — لا تُكشف الحسابات البنكية أو بيانات العملاء.

### 11. الأمان
أسعار من الخادم، مخزون ذري، callbacks idempotent، تشفير AES-256، ملفات خاصة + OTP، تحديد معدل، صلاحيات، CSRF، سجلات، لا أسرار في المستودع.

### 12. التقنيات
Laravel 13 · PHP 8.3+ · MySQL 8.4 · Sanctum · Socialite · Modules · Bootstrap 5.3 + FA6 · Chart.js · Vite · PHPUnit 12.

### 13. التثبيت
```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8765
php artisan queue:work
php artisan schedule:work
```

### 14. حسابات تجريبية
| الدور | البريد | كلمة المرور | الرابط |
|---|---|---|---|
| مشرف | admin@multivendor.test | password | `/admin/login` |
| بائع | vendor@multivendor.test | password | `/vendor/login` |
| عميل | customer@multivendor.test | password | `/login` |

### 15. الاختبار والنشر
```bash
php artisan test
php artisan route:list
php artisan config:cache && php artisan route:cache && php artisan view:cache
```
راجع `DEPLOYMENT.md`. اضبط `APP_URL` وأرسل `/sitemap.xml` إلى Search Console.

تحتاج مساعدة؟ **Lindu Cipta — https://wa.me/6281296052010**

---

## 📄 License / Lisensi / الترخيص
MIT — free for commercial use. See `LICENSE`.

## 🙏 Credits
Based on 6valley concept, rebuilt on Laravel 13 custom stack with dynamic BYOK architecture. Developed by **Lindu Cipta** — WhatsApp: **https://wa.me/6281296052010**
