# Plugin System — Sistem Plugin / Plugin System

> ID ringkas + EN summary. Tanpa hardcode gateway baru di PHP.

## 1. Manifest (ID)

Setiap plugin: `app/Plugins/<kode>/plugin.json`:

```json
{
  "name": "Midtrans", "version": "1.0.0",
  "code": "midtrans", "provider": "Midtrans",
  "gateway": "App\\Payments\\Providers\\MidtransGateway",
  "countries": ["ID"], "currencies": ["IDR"],
  "methods": ["virtual_account", "qris"],
  "priority": 10, "test_mode_supported": true,
  "settings": ["MIDTRANS_SERVER_KEY"],
  "routes": ["payments.checkout"],
  "webhooks": ["payment.paid"]
}
```

EN: required keys — name/version/code/gateway/countries/currencies/methods; optional — provider/priority/test_mode_supported/settings/routes/webhooks.

## 2. Loader + aktif/nonaktif (ID)

- `App\Plugins\PluginManager::all()` — scan `plugin.json`, urut priority.
- Aktif/nonaktif via setting `plugins.enabled` (daftar koma). Kosong = semua aktif.
- `enable($code)` / `disable($code)` / `isEnabled($code)` / `active()`.
- EN: enable list stored in `plugins.enabled`; empty means all active.

## 3. Registrasi gateway (ID)

```php
(new PluginManager)->registerGateways($registry);
```

Sumber tunggal = manifest; tidak ada daftar hardcode baru.
EN: single source is the manifest; no new hardcoded list.

## 4. Tambah plugin baru (ID/EN)

1. Buat folder `app/Plugins/<kode>/plugin.json`. / Create the folder + manifest.
2. Pastikan `gateway` class implements `PaymentGatewayInterface`. / Ensure the class implements the contract.
3. Aktifkan via setting; cek di admin Developers → Plugins. / Enable + verify in admin.
