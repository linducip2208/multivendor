# Webhooks — Webhook / Webhooks

> ID ringkas + EN summary.

## 1. Alur (ID)

Provider → signature verify → `WebhookPipeline::handle()` → dedup event → apply.
EN: verify → dedupe → apply; replay same payload returns original.

## 2. Event (ID/EN)

- ID gateway: `payment.paid`, `payment.failed`, `payment.expired`, `payment.refunded`.
- Intl: `payment.paid`, `payment.failed`, `payment.refunded` (per manifest `webhooks`).
- Admin: Developers → Webhooks (endpoint, secret server-generated, replay).

## 3. Aman (ID/EN)

- Secret tidak pernah ditampilkan penuh; verify gagal → `WebhookVerificationException`, tercatat `FAILED`.
- / Secrets never fully shown; failed verify is logged as FAILED.
