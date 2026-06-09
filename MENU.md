# MENU.md — Demo Data & Menu Status (FINAL)

## Database Summary

| Data | Count |
|------|-------|
| Admin | 1 |
| Vendors | 100 |
| Customers | 1000 |
| Delivery Men | 50 |
| Employees | 50 |
| Brands | 100 |
| Products | 3000 (30/vendor, 5 gambar) |
| Coupons | 1000 |
| Flash Deals | 50 |
| Deal of the Day | 100 |
| Featured Deals | ~500 (random) |
| Banners | 100 |
| Blog Posts | 100 |
| Push Notifications | 100 |
| Support Tickets | 100 |
| Product Reviews | 1000 |
| Wishlist Items | 1000 |
| Shop Followers | 500 |
| Product Bundles | 30 |
| Social Feeds | 100 |
| Group Buys | 50 |
| Loyalty Points | 200 customers |
| Providers | 4 (Midtrans, RajaOngkir, DeepSeek, SMTP) |
| VAT/Tax | 2 (PPN 11%, PPN 0%) |

**Total: ~10,000+ records**

---

## Admin Menu Status

| Menu | Ada Data? | Volume |
|------|-----------|--------|
| Dashboard | ✅ | Real stats |
| Toko / Vendor | ✅ | 100 toko aktif |
| Moderasi Produk | ✅ | 3000 produk |
| Kategori | ✅ | 5 parent + sub |
| Brand | ✅ | 100 brand |
| Pesanan | ⬜ | 0 (perlu order flow) |
| Transaksi | ⬜ | 0 (perlu payment) |
| Kupon | ✅ | 1000 kupon |
| Flash Deal | ✅ | 50 deals |
| Deal of the Day | ✅ | 100 deals |
| Featured Deal | ✅ | ~500 produk |
| Most Demanded | ✅ | By order count |
| Pelanggan | ✅ | 1000 customers |
| Kurir | ✅ | 50 delivery men |
| Employee | ✅ | 50 employees |
| Banner | ✅ | 100 banners |
| Blog | ✅ | 100 posts |
| Notifikasi | ✅ | 100 push notifications |
| SEO Produk | ✅ | Meta fields |
| Tiket Support | ✅ | 100 tickets |
| Pengaturan | ✅ | System settings |
| Bahasa | ✅ | ID/EN |
| Mata Uang | ✅ | IDR |
| Pajak / VAT | ✅ | PPN 11% + 0% |
| Translation DB | ⬜ | Empty (fill via UI) |
| Custom Role | ⬜ | Empty |
| Withdraw | ⬜ | No requests yet |
| Email Templates | ⬜ | Empty |
| Pembayaran Offline | ⬜ | Empty |
| Integrasi | ✅ | 4 providers |
| File Manager | ⬜ | Empty |
| Laporan (AI) | ✅ | Revenue + products |
| Stok Produk | ✅ | 3000 products |
| Penjualan Vendor | ✅ | 100 vendors |
| Export CSV | ✅ | Downloadable |
| SMS Gateway | ⬜ | Empty |
| 3rd Party | ⬜ | Empty |
| Maintenance | ✅ | Toggle |
| Product Bundles | ✅ | 30 bundles |
| Pages | ⬜ | Empty |
| Help Topics | ⬜ | Empty |
| Contacts | ⬜ | Empty |
| Vendor Settings | ✅ | Registration config |
| Inhouse Shop | ⬜ | Empty |

---

## Vendor Menu Status

| Menu | Ada Data? | Volume |
|------|-----------|--------|
| Dashboard | ✅ | Stats per vendor |
| Point of Sale | ✅ | Walk-in |
| Produk Saya | ✅ | 30/vendor |
| Bulk Import | ⬜ | Empty form |
| Barcode | ✅ | Select + print |
| Galeri | ✅ | Grid with photos |
| Produk Digital | ⬜ | All physical |
| Pesanan | ⬜ | No orders |
| Order Edit | ⬜ | No orders |
| Refund | ⬜ | Empty |
| Ulasan | ✅ | Reviews exist |
| Kupon Toko | ⬜ | Empty |
| Clearance Sale | ⬜ | Empty |
| Laporan | ✅ | Reports |
| Wallet & Payout | ✅ | Balance |
| Metode Pengiriman | ✅ | 10 toggle |
| Restock Request | ⬜ | Empty |
| Stok Menipis | ✅ | Some low stock |
| Chat / Inbox | ✅ | Customers |
| Pengaturan Toko | ✅ | Info |

---

## Storefront Status

| Halaman | Ada Data? |
|---------|-----------|
| Home `/` | ✅ Featured + Flash deals |
| Produk `/products` | ✅ 3000 grid |
| Detail `/products/{slug}` | ✅ Full data |
| Blog `/blog` | ✅ 100 posts |
| RSS `/blog/feed.xml` | ✅ |
| Feed `/feed` | ✅ 100 social posts |
| Bundle `/bundles` | ✅ 30 bundles |
| Group Buy `/group-buys` | ✅ 50 active |
| Leaderboard `/leaderboard` | ✅ Top customers |
| Wishlist `/wishlist` | ✅ 1000 items |
| Bandingkan `/compare` | ⬜ |
| Tiket Support `/tickets` | ✅ 100 tickets |
| Loyalty `/loyalty` | ✅ Points |
| Profil `/profile` | ✅ |
| Shop `/shop/{slug}` | ✅ |
| Lacak `/track-order` | ⬜ |
| Docs `/docs` | ✅ |
| Sitemap `/sitemap.xml` | ✅ |
| Robots `/robots.txt` | ✅ |
