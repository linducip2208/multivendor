# Currencies — Mata Uang / Currencies

> ID ringkas + EN summary.

## 1. Negosiasi (ID)

- Urutan: header `X-Currency` > `X-Country` (mapping negara→mata uang) > session > default `IDR`.
- Format uang: string desimal `"125000.00"`, bukan JSON number.
- EN: header over country over session over IDR default; money is decimal string.

## 2. Dukungan (ID/EN)

- ID gateway: `IDR` saja. Intl: lihat `PAYMENTS.md` / manifest `currencies`.
- / ID gateways support IDR only; intl per manifest.
