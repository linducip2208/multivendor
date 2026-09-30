# Payments — Pembayaran / Payments

> ID ringkas + EN summary per bagian. UI admin Tabler, copy BI/EN.

## 1. Provider (ID)

| Code | Country | Currency | Methods | Priority | Test |
|---|---|---|---|---|---|
| `midtrans` | ID | IDR | va, bank_transfer, e_wallet, qris, credit_card, retail_outlet | 10 | ya/yes |
| `xendit` | ID | IDR | va, qris, e_wallet, retail_outlet | 20 | ya/yes |
| `tripay` | ID | IDR | va, e_wallet, qris | 30 | ya/yes |
| `ipaymu` | ID | IDR | va, bank_transfer, e_wallet, qris, retail_outlet, credit_card | 40 | ya/yes |

## 2. Provider (Intl)

| Code | Countries | Currencies | Methods | Priority |
|---|---|---|---|---|
| `stripe` | US/GB/DE/…/ID | USD/EUR/…/IDR | credit_card, bank_transfer, direct_debit, e_wallet | 50 |
| `paypal` | US/GB/…/ID/AE | USD/EUR/…/IDR | e_wallet, credit_card, paylater, bank_transfer | 60 |
| `adyen` | NL/BE/…/ID | USD/EUR/…/IDR | credit_card, bank_transfer, e_wallet, direct_debit | 70 |
| `mollie` | NL/BE/…/CH | EUR/USD/… | credit_card, bank_transfer, direct_debit, e_wallet | 80 |
| `razorpay` | IN/US/GB/AE/SG | INR/USD/… | credit_card, bank_transfer, e_wallet, paylater | 90 |
| `verifone` | US/GB/DE/… | USD/EUR/GBP | credit_card, bank_transfer | 100 |

EN: Capabilities are honest — local VA/QRIS/retail are never claimed by intl gateways.

## 3. Admin UI

- ID: `admin/payments/providers` — daftar provider: status/country/currency/methods/priority/test-mode + aksi enable/disable/configure/test + health + log.
- EN: provider list with capabilities, health badge, and recent webhook log.
- Configure kredensial via `admin/providers` (nilai rahasia tidak pernah ditampilkan / secrets never shown).

## 4. Health jujur / Honest health

- Hanya cek lokal: kelas ada, `isEnabled()`, baris provider aktif. / Local checks only.
- `unknown` = belum dikonfigurasi (bukan error). / `unknown` = not configured.
- TANPA kredensial live, TANPA HTTP keluar. / No live credentials, no outbound HTTP.
