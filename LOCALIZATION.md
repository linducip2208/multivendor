# Localization — Lokalisasi / Localization

> ID ringkas + EN summary.

## 1. Locale (ID)

- Urutan: user `locale` > session > `Accept-Language` browser (`id-ID`/`en-US` → `id`/`en`).
- Header respons `X-Locale`; envelope `meta.locale` + `available_locales: [id, en]`.
- EN: user over session over browser; envelope keeps contract + additive locale keys.

## 2. Copy BI/EN (ID/EN)

- Admin UI Tabler memakai copy ganda: ID primer + EN sekunder.
- / Admin UI uses bilingual copy: ID primary, EN secondary.
