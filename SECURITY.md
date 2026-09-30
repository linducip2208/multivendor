# Security — Keamanan / Security

> ID ringkas + EN summary.

## 1. Kredensial (ID)

- Kunci API terenkripsi (Crypt) saat rest; UI hanya mask (`****`), tidak pernah nilai penuh.
- Health/plugin tidak membaca nilai rahasia — hanya keberadaan (`isEnabled()`).
- EN: encrypted at rest, masked in UI; health checks presence only.

## 2. Webhook & API (ID/EN)

- Verifikasi signature per gateway; replay aman via idempotency.
- Scope API: `read`/`write`/`vendor`/`admin`; rate limit + `Retry-After`.
