# Themes — Tema / Themes

> ID ringkas + EN summary. Setting tema aktif + override view.

## 1. Tema aktif (ID)

- Kunci: `theme.active`, default `default` (`App\Services\Theme\ThemeManager`).
- `active()` / `activate($theme)` / `available()` / `settings($locale)`.
- EN: active theme stored in `theme.active`.

## 2. Cara buat tema (ID)

1. Buat `resources/views/themes/<nama>/theme.json` (`{"name": "...", "version": "1.0.0"}`).
2. Set `theme.active = <nama>` (via SystemSetting).
3. Override view: file `themes/<nama>/<view>.blade.php` dipakai sebagai `theme::<view>`; fallback ke view asal bila tidak ada.
4. Panggil `$themes->register()` agar namespace `theme` tersedia.

EN: create the folder + theme.json, set active, override via `theme::` namespace with fallback.

## 3. Settings multilingual (ID/EN)

- `theme_title_id` / `theme_title_en`, `theme_tagline_id` / `theme_tagline_en`.
- `$themes->settings('id'|'en')` → `['locale','title','tagline']`.
