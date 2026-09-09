# MultiVendor Marketplace

Marketplace multi-vendor Laravel 13 untuk Indonesia: katalog publik, toko vendor, cart, checkout multi-vendor, pembayaran gateway BYOK, pengiriman, wallet, komisi, delivery, blog, dan SEO.

## Fitur produksi utama

- Payment group tunggal untuk beberapa order vendor (`PAY-*`) dengan callback terverifikasi dan idempoten.
- Harga, pajak, diskon, bobot, ongkir, stok, dan komisi dihitung ulang dari server; browser tidak menjadi sumber angka otoritatif.
- Stok dikunci saat checkout, dikurangi atomik, dan dipulihkan tepat sekali bila order dibatalkan.
- Coupon global/vendor, pembatasan penggunaan per customer, alokasi diskon per order, dan free-shipping.
- Wallet ledger dengan reference key idempoten serta saldo withdraw yang di-reserve.
- API publik menggunakan resource terfilter; data rekening toko dan PII customer tidak diekspos.
- File digital disimpan pada disk privat dan hanya tersedia untuk pembeli yang telah membayar serta lulus OTP.

## Instalasi

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
```

Konfigurasikan database, mail, queue, dan provider melalui panel admin. API key provider tersimpan terenkripsi; jangan menaruh credential gateway di source code.

## Menjalankan aplikasi

```bash
php artisan serve --host=127.0.0.1 --port=8765
php artisan queue:work
php artisan schedule:work
```

## Pembayaran dan pengiriman

Provider ditambahkan di Admin → Integrasi. Pilih format API yang sesuai lalu masukkan URL, key, secret, dan header sendiri. Callback aman didukung untuk format yang memiliki kontrak signature (`midtrans-*`, `xendit-invoice`, dan `tripay-closed`). Generic adapter tidak akan meng-settle callback otomatis tanpa signature strategy yang diverifikasi.

Setiap toko fisik memerlukan origin provider di setting `shop_shipping_origin_{shop_id}` dan alamat customer memerlukan `shipping_destination_id` yang sesuai provider sebelum checkout.

## Akun demo

Setelah seeding, lihat data demo yang dibuat oleh seeder atau halaman `/docs`. Password demo tidak boleh dipakai di production.

## Pengujian dan deployment

```bash
php artisan test
php artisan route:list
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Ikuti [DEPLOYMENT.md](DEPLOYMENT.md) untuk Nginx, Supervisor, queue, cron, storage, dan pasangan license. Setelah memilih domain production, ubah `APP_URL`, canonical/OG meta, dan submit `/sitemap.xml` ke Google Search Console.

## Keamanan

Jangan aktifkan `LICENSE_DEV_BYPASS` di luar host local/testing. Jangan kirim secret, token license, atau backup database ke repository. Laporkan kerentanan secara privat ke maintainer.
