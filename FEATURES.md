# FEATURES.md — MultiVendor E-Commerce (6valley-based)

## Ringkasan
Project ini based on **6valley v16.1** — platform multivendor e-commerce lengkap. Ported ke Laravel 13 custom stack dengan dynamic provider system (BYOK).

**Last updated:** 2026-06-09

---

## Project Statistics (Current State)

| Metric | Multivendor | 6valley v16.1 | Coverage |
|--------|-------------|---------------|----------|
| Admin view files | 59 | 420 | 14% |
| Admin view directories | 24 | 38 | 63% |
| Vendor view files | 28 | 161 | 17% |
| Vendor view directories | 15 | 18 | 83% |
| Storefront view files | 21 | ~50+ (web-views) | ~40% |
| Admin controllers | 23 | 98 | 23% |
| Vendor controllers | 16 | 33 | 48% |
| Storefront controllers | 10 | 18 | 56% |
| Total controllers | 51 | 149+ | 34% |
| Route declarations | 160 | 500+ (est.) | ~32% |
| Models | 35 | 111 | 32% |
| Migrations | 24 | 285 | 8% |
| Services | 10 | 15+ | ~65% |
| Tests | 7 files | 0-2 (est.) | 350% |
| Modules (addons) | 0 | 3 (AI, Blog, Tax) | 0% |

---

## 1. MULTI-AUTH SYSTEM

| Guard | Role | Multivendor | 6valley |
|-------|------|-------------|---------|
| `admin` | Admin | ? Full CRUD dashboard | ? Full CRUD dashboard |
| `vendor` | Vendor | ? Panel vendor | ? Panel vendor |
| `web` | Customer | ? Storefront | ? Storefront |
| `delivery` | Delivery Man | ? Not yet | ? Full delivery app |

---

## 2. ADMIN PANEL FEATURE COMPARISON

### Dashboard
| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| Stats overview (vendor, customer, product, order, revenue) | ? | ? |
| Pending alerts (new shops, products, orders) | ? | ? |
| Recent orders + shops table | ? | ? |
| Top selling stores/products | ? | ? |
| Top customer / delivery man | ? | ? |
| Most rated products | ? | ? |
| Wallet stats overview | ? | ? |
| Order status doughnut chart | ? | ? |
| Dashboard per-role (employee) | ? | ? |

### Manajemen
| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| **Toko / Vendor** CRUD + approve/reject | ? | ? |
| Vendor komisi per toko | ? | ? |
| Vendor filter status | ? | ? |
| Vendor order list | ? | ? |
| Vendor transaction list | ? | ? |
| Vendor review list | ? | ? |
| Vendor clearance sale list | ? | ? |
| Vendor product list per toko | ? | ? |
| Vendor setting view | ? | ? |
| Vendor withdraw methods CRUD | ? | ? |
| **Moderasi Produk** list + approve/suspend | ? | ? |
| Product detail (varian, ulasan) | ? | ? |
| Product SEO settings per product | ? | ? (partial) |
| Product filter by shop/brand | ? | ? |
| Product advanced search | ? | ? |
| **Kategori** CRUD 3-level | ? | ? |
| Category priority | ? | ? |
| Category SEO columns | ? | ? |
| **Brand** CRUD + status | ? | ? |
| **Attribute** CRUD | ? | ? |
| **Tags** CRUD | ? | ? |
| **VAT / Tax** management | ? | ? |
| **Subscriptions** | ? | ? |

### Transaksi
| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| **Pesanan** list + filter status/payment | ? | ? |
| Order detail + update status | ? (konfirmasi-proses-kirim-sampai-batal) | ? |
| Order tracking ID | ? | ? |
| Order edit (tambah/hapus produk) | ? | ? |
| Order verification code | ? | ? |
| Delivery info assignment | ? | ? |
| **Transaksi** list + filter status/amount | ? | ? |
| Komisi calculation | ? | ? |
| **Kupon admin** CRUD (%, Rp, free ongkir) | ? | ? |
| Coupon discount bearer (admin/vendor) | ? | ? |
| **Flash Deal** CRUD + multi produk + timer | ? | ? |
| **Featured Deal** CRUD | ? | ? |
| **Deal of the Day** CRUD | ? | ? |
| **Most Demanded** products | ? | ? |
| **Clearance Sale** (admin manage + priority) | ? | ? |
| **Refund Management** list + approve/reject | ? | ? |
| Refund transactions list | ? | ? |
| Refund statuses | ? | ? |

### POS (Point of Sale)
| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| POS screen + cart + customer info | ? | ? |
| POS order management | ? | ? |
| Inhouse product sale | ? | ? |

### Pengguna
| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| **Pelanggan** list + detail | ? (wallet, alamat, order history) | ? |
| Customer wallet management | ? | ? |
| Customer loyalty management | ? | ? |
| **Kurir / Delivery Man** CRUD | ? | ? |
| Delivery man wallet | ? | ? |
| Delivery man withdraw | ? | ? |
| Delivery man cash collect | ? | ? |
| Emergency contact per kurir | ? | ? |
| Delivery man rating | ? | ? |
| **Employee** CRUD (admin staff) | ? | ? |
| **Custom Role** management | ? | ? |

### Marketing
| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| **Banner** CRUD + posisi + sort + link | ? | ? |
| **Blog** CRUD artikel + kategori + publish/draft | ? | ? |
| Blog SEO per artikel | ? | ? |
| Blog AI-powered content | ? | ? (AI Module) |
| **Push Notification** (Firebase) | ? (basic) | ? (advanced) |
| Push notification to customer/vendor/deliveryman | ? | ? |
| Push notification inline menu | ? | ? |
| **Notifikasi** dashboard | ? | ? |
| Notification count badges | ? | ? |

### Sistem
| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| **Pengaturan aplikasi** | ? | ? |
| **Currency management** | ? | ? |
| **Language / translation** | ? | ? |
| **Email templates** | ? | ? |
| **Offline payment methods** | ? | ? |
| **SMS Gateway** | ? | ? |
| **Third-party** (recaptcha, map, social, analytics) | ? | ? |
| **File Manager** | ? | ? |
| **Maintenance mode** | ? | ? |
| **Export CSV** (products, orders, customers, transactions) | ? | ? (Excel/CSV) |
| **Roles & permissions** | ? (basic) | ? (CustomRole) |
| **Integrasi provider** (BYOK) | ? | ? |
| **Laporan** revenue + top produk + AI analysis | ? | ? |
| Stock report | ? | ? |
| Vendor sale report | ? | ? |
| Inhouse product sale report | ? | ? |
| Product in wishlist report | ? | ? |
| Product stock report | ? | ? |
| Order report | ? | ? |
| Transaction report | ? | ? |
| Expense transaction report | ? | ? |
| Refund transaction report | ? | ? |
| Vendor-wise tax report | ? | ? |
| **Vendor Registration Settings** | ? | ? |
| **Vendor Registration Reasons** | ? | ? |
| **Software Update** | ? | ? |
| **Database settings** | ? | ? |
| **Environment settings** | ? | ? |
| **Error logs** | ? | ? |
| **Theme management** | ? | ? |
| **Addon management** | ? | ? |
| **Invoice settings** | ? | ? |
| **Order settings** | ? | ? |
| **Delivery restriction** | ? | ? |
| **Storage connection settings** | ? | ? |
| **Social media settings** | ? | ? |
| **Firebase OTP verification** | ? | ? |
| **Inhouse shop management** | ? | ? |
| **Chatting** admin-customer-vendor | ? | ? |
| **Support tickets** | ? | ? (Help & Support) |
| **Help topics** | ? | ? |
| **Contact messages** | ? | ? |
| **Profile management** | ? | ? |
| **Advanced search** | ? | ? |
| **SEO settings** (webmaster, sitemap upload) | ? | ? |
| **Robots.txt meta content** | ? | ? |
| **Pages & media** (about, terms, privacy) | ? | ? |
| **Features section** management | ? | ? |
| **Priority setup** | ? | ? |
| **Login URL / customer login setup** | ? | ? |
| **Social media chat** | ? | ? |
| **Social login settings** | ? | ? |

---

## 3. VENDOR PANEL FEATURE COMPARISON

### Dashboard
| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| Stats real: produk, pesanan, revenue, wallet | ? | ? |
| Vacation Mode toggle | ? | ? |
| Setup guide wizard | ? | ? |
| Recent orders table | ? | ? |
| Wallet balance display | ? | ? |

### Produk
| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| Product CRUD | ? | ? |
| Upload foto (thumbnail + additional) | ? | ? |
| Varian produk (SKU combinations) | ? (model + partial) | ? (full) |
| Color-wise images | ? | ? |
| Product video | ? | ? |
| Digital products support | ? | ? |
| Bulk Import (Excel/CSV) | ? | ? |
| Barcode generate + print PDF | ? | ? |
| Product gallery grid view | ? | ? |
| Limited stock alert | ? | ? |
| Restock request list | ? | ? |
| SEO per produk (meta title, desc, OG) | ? | ? |
| AI-powered product creation | ? | ? |
| Product translations | ? | ? |
| Tax model per product | ? | ? |
| Minimum order quantity | ? | ? |

### Pesanan
| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| List pesanan + filter status | ? | ? |
| Detail pesanan + item + riwayat status | ? | ? |
| Update status | ? | ? |
| Order Edit (tambah/hapus produk) | ? | ? |
| Invoice PDF generation | ? | ? |
| Refund management | ? | ? |

### Promosi
| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| Kupon toko CRUD | ? | ? |
| Clearance Sale (vendor config) | ? | ? |

### Laporan
| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| Laporan Produk | ? | ? (all product + stock) |
| Laporan Pesanan | ? | ? |
| Laporan Transaksi | ? | ? (order-wise + expense) |

### Keuangan
| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| Wallet & riwayat transaksi | ? | ? |
| Withdraw request | ? | ? |
| Info bank di pengaturan toko | ? | ? |
| Payment information per vendor | ? | ? |

### Pengaturan
| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| Pengaturan toko (nama, deskripsi, logo, banner) | ? | ? |
| Shipping methods (vendor bikin sendiri) | ? | ? |
| Category shipping cost | ? | ? |
| Payment info (vendor tambah metode) | ? | ? |
| Shop other setup | ? | ? |

### Chat / Inbox
| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| Chat vendor dengan customer | ? | ? |
| Chat vendor dengan delivery man | ? | ? |

### POS
| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| Point of Sale screen | ? | ? |
| POS cart + customer info | ? | ? |
| Discount + hold orders | ? | ? |
| Print invoice | ? | ? |

### Delivery Man Management
| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| Delivery Man CRUD | ? | ? |
| Delivery Man wallet + earning | ? | ? |
| Delivery Man withdraw | ? | ? |
| Emergency contact | ? | ? |
| Delivery Man rating | ? | ? |
| Cash collect tracking | ? | ? |

### Reviews
| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| Reviews list | ? | ? |
| Review edit/reply | ? | ? |

### Notifications
| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| Vendor notifications | ? | ? |

---
## 4. STOREFRONT FEATURE COMPARISON

| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| Landing page `/` | ? Hero + 16 fitur + demo | ? Full storefront |
| Product listing `/products` | ? Grid + search + filter | ? |
| Product detail `/products/{slug}` | ? | ? |
| Product compare | ? | ? |
| Shop view `/shop/{slug}` | ? | ? |
| Shop follower | ? | ? |
| Cart `/cart` | ? Multi-vendor + split | ? |
| Checkout `/checkout` | ? | ? |
| Orders `/orders` list + detail + tracking | ? | ? |
| Track order (by number) | ? | ? |
| Blog `/blog` list + detail | ? | ? |
| Wishlist | ? | ? |
| Support tickets | ? | ? |
| Loyalty points | ? | ? |
| Customer wallet | ? | ? |
| Customer profile | ? (basic) | ? (full) |
| Digital product download | ? | ? |
| Coupon code at checkout | ? | ? |
| Currency switcher | ? | ? |
| Chat with vendor | ? | ? |
| Docs `/docs` | ? (19 steps + 16 fitur) | ? |
| Sitemap `/sitemap.xml` | ? (4 files) | ? |
| Robots.txt | ? (dynamic) | ? |
| Auth: login, register, logout | ? | ? |
| Social auth (Google, Facebook) | ? | ? |
| Referral code at register | ? | ? (custom feature) |

---

## 5. PAYMENT GATEWAY (Dynamic, Zero Hardcode)

| Format | Adapter | Multivendor | 6valley |
|--------|---------|-------------|---------|
| `midtrans-snap` | SnapRedirectAdapter | ? | ? (hardcoded) |
| `midtrans-core` | CoreApiAdapter | ? | ? |
| `xendit-invoice` | XenditInvoiceAdapter | ? | ? |
| `tripay-closed` | TripayClosedAdapter | ? | ? |
| `duitku-redirect` | GenericRedirectAdapter | ? | ? |
| `oyindonesia-api` | GenericApiAdapter | ? | ? |
| `ipaymu-api` | GenericApiAdapter | ? | ? |
| `faspay-api` | GenericApiAdapter | ? | ? |
| `doku-api` | GenericApiAdapter | ? | ? |
| `esiapay-api` | GenericApiAdapter | ? | ? |
| Admin add provider via UI | ? | ? |
| Preset autofill JSON | ? | ? |
| API key encrypted (AES-256) | ? | ? |
| Masked key display | ? | ? |
| User BYOK (pick own provider) | ? | ? |
| Format-based adapters | ? | ? (vendor-specific) |
| Offline payment methods | ? | ? |

**Advantage:** Our payment system is fully dynamic — user can add ANY provider without code changes. 6valley has per-vendor hardcoded implementations.

---

## 6. SHIPPING (Dynamic)

| Format | Multivendor | 6valley |
|--------|-------------|---------|
| RajaOngkir Starter | ? | ? |
| RajaOngkir Pro | ? | ? |
| Generic REST courier adapter | ? | ? |
| Admin add provider via UI | ? | ? |
| Shipping cost calculation | ? | ? |
| Tracking service | ? | ? |
| User pick own provider | ? | ? |
| Vendor shipping methods | ? | ? |
| Category shipping cost | ? | ? |
| Shipping types | ? | ? |
| Delivery zip codes | ? | ? |
| Delivery country codes | ? | ? |

---

## 7. AI BYOK

| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| OpenAI-compatible adapter | ? (1 adapter = 15+ providers) | ? (AI Module) |
| Auto-fetch models from `/v1/models` | ? | ? |
| Admin Report AI Analysis | ? | ? |
| AI product creation (auto title, desc, SEO) | ? | ? |
| AI blog content generation | ? | ? |
| User input API key sendiri | ? | ? |
| 10+ AI provider presets | ? | ? |
| Prompt templates | ? | ? |

---

## 8. COUPON & PROMO

| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| Admin Coupon CRUD (%, Rp, free ongkir) | ? | ? |
| Admin Flash Deal CRUD + multi produk | ? | ? |
| Admin Featured Deal | ? | ? (Most Demanded + Featured) |
| Admin Deal of the Day | ? | ? |
| Admin Clearance Sale | ? | ? |
| Admin Banner CRUD | ? | ? |
| Vendor Coupon CRUD per toko | ? | ? |
| Vendor Clearance Sale | ? | ? |
| Checkout coupon input + validation | ? | ? |
| Coupon discount bearer split | ? | ? |

---
## 9. SEO & MARKETING

| Feature | Multivendor | 6valley |
|---------|-------------|---------|
| Sitemap auto-generate (4 files) | ? | ? |
| IndexNow auto-submit | ? | ? |
| `seo:indexnow` command + scheduler | ? | ? |
| robots.txt | ? | ? |
| Blog system (admin CRUD + public) | ? | ? |
| Documentation page `/docs` | ? | ? |
| RSS Feed `/blog/feed.xml` | ? | ? |
| SEO settings (webmaster tools) | ? | ? |
| Robots meta content | ? | ? |
| Page SEO (about, terms, privacy) | ? | ? |
| Product SEO per item | ? (partial) | ? |
| Category SEO columns | ? | ? |

---

## 10. TECHNICAL COMPARISON

| Item | Multivendor | 6valley v16.1 |
|------|-------------|---------------|
| Laravel version | 13.x | 11.x |
| PHP | 8.4+ | 8.2+ |
| MySQL | 8.4 | 8.0+ |
| Frontend stack | Bootstrap 5.3 + FA 6 | Bootstrap 4-5 + custom |
| Chart library | Chart.js | ApexCharts |
| Module system | No (monolith) | Yes (nwidart/laravel-modules) |
| OAuth / Passport | Sanctum | Passport |
| Dynamic provider (BYOK) | ? | Partial |
| Encrypted API keys (AES-256) | ? | ? |
| Format-based adapters | ? | ? |
| Admin views count | 59 | 420 |
| Vendor views count | 28 | 161 |
| Storefront views count | 21 | ~50+ |
| Admin controllers | 23 | 98 |
| Vendor controllers | 16 | 33 |
| Storefront controllers | 10 | 18 |
| Total controllers | 51 | 149+ |
| Routes declared | 160 | 500+ |
| Models | 35 | 111 |
| Migrations | 24 | 285 |
| Services | 10 | 15+ |
| Modules / Addons | 0 | 3 (AI, Blog, Tax) |
| Tests | 7 files | minimal |
| .env.example | ? | ? |
| DEPLOYMENT.md | ? | ? |
| nginx.conf | ? | ? |
| supervisor.conf | ? | ? |
| Console commands | 1 (seo:indexnow) | 10+ |
| Scheduler jobs | 1 (daily 02:45) | 5+ |

---

## 11. KEY ADVANTAGES (Our Project vs 6valley)

| Advantage | Detail |
|-----------|--------|
| **Dynamic BYOK** | Payment, shipping, AI providers are 100% user-configurable. No code changes needed to add new providers. |
| **Encrypted at rest** | All API keys AES-256 encrypted, never logged, masked display in UI. |
| **IndexNow SEO** | Auto-submit new URLs to Bing, Yandex, Seznam, Naver — 6valley does not have this. |
| **Documentation page** | `/docs` with tutorial + demo accounts — 6valley does not have this. |
| **Laravel 13** | Latest version with full Sanctum API auth. |
| **Mobile responsive** | All views responsive with mobile-first approach. |
| **Tests** | 7 test files covering auth, order flow, coupon calculation, wallet. |
| **Clean architecture** | Format-based adapters (not per-vendor), single responsibility controllers. |
| **Referral system** | Customer referral codes at registration with loyalty point rewards. |

---

## 12. KEY ADVANTAGES (6valley vs Our Project)

| Advantage | Detail |
|-----------|--------|
| **Feature completeness** | 285 migrations vs 24. Vastly more features in every category. |
| **POS system** | Full Point of Sale with hold orders, customer info, print invoice. |
| **Delivery man ecosystem** | Full CRUD, wallet, withdraw, cash collect, emergency contact, rating. |
| **Vendor setup wizard** | Step-by-step onboarding guide for new vendors. |
| **Admin per-employee role** | CustomRole CRUD + employee management. |
| **Tax/VAT module** | Full tax calculation, system tax setup, tax reports. |
| **AI product creation** | Vendor can generate product title, description, SEO via AI. |
| **Digital products** | Upload digital files, OTP verification for download. |
| **Bulk advanced features** | Product gallery with filters, restock requests, product translations. |
| **ApexCharts** | More advanced charting library than Chart.js. |
| **Module system** | Extendable via nwidart/laravel-modules addons. |
| **Firebase integration** | Push notifications, OTP verification, real-time chat. |
| **Multi-language** | Full translation system with admin UI. |
| **Invoice PDF** | Professional invoice generation with settings. |
| **Refund workflow** | Full refund request - approve/reject - refund transaction - statuses. |

---
## 13. NEXT PRIORITY (Belum Dibangun)

| # | Fitur | Lokasi | 6valley has? |
|---|-------|--------|--------------|
| 1 | POS System (hold orders, customer info, print) | Vendor panel | ? |
| 2 | Delivery Man CRUD + Wallet + Withdraw | Vendor + Admin | ? |
| 3 | Bulk Import Produk (enhanced) | Vendor panel | ? |
| 4 | Product Gallery Grid + Filter | Vendor panel | ? |
| 5 | Clearance Sale (admin side + priority) | Admin panel | ? |
| 6 | Order Edit (tambah/hapus produk) | Vendor + Admin | ? |
| 7 | Chat / Inbox (vendor-customer-admin) | All panels | ? |
| 8 | Refund Management (full workflow) | Vendor + Admin | ? |
| 9 | Push Notification (Firebase) | Admin | ? |
| 10 | Digital Products support | Vendor | ? |
| 11 | Vendor Shipping Methods | Vendor | ? |
| 12 | RSS Feed | Public | ? |
| 13 | AI Product Creation | Vendor | ? |
| 14 | Setup Guide Wizard | Vendor | ? |
| 15 | Attributes + Tags | Admin | ? |
| 16 | Tax/VAT module | Admin + Vendor | ? |
| 17 | Employee + CustomRole | Admin | ? |
| 18 | Advanced Search | Admin | ? |
| 19 | Support Tickets + Help Topics | Admin | ? |
| 20 | Vendor Registration Settings | Admin | ? |
| 21 | Dashboard per-role (employee) | Admin | ? |
| 22 | Order/Invoice settings | Admin | ? |

---

## 14. ACCESS

| Role | Email | Password | URL |
|------|-------|----------|-----|
| Admin | admin@multivendor.test | password | `/admin/login` |
| Vendor | vendor@multivendor.test | password | `/vendor/login` |
| Customer | customer@multivendor.test | password | `/login` |

Dev server: `http://127.0.0.1:8765`
