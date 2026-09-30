# API — API / API

> ID ringkas + EN summary. Kontrak existing tidak berubah (aditif).

## 1. Header lokalisasi (ID)

| Header | Contoh | Keterangan |
|---|---|---|
| `Accept-Language` | `id-ID`, `en-US` | Bahasa / locale |
| `X-Currency` | `IDR`, `USD` | ISO 4217 |
| `X-Country` | `ID`, `US` | ISO 3166-1 alpha-2 |

EN: send locale/currency/country headers; responses echo `meta.locale`, `meta.currency`, `X-Locale`, `X-Currency`.

## 2. Contoh (ID/EN)

```http
GET /api/v4/products HTTP/1.1
Accept-Language: id-ID
X-Currency: IDR
X-Country: ID
```

```http
GET /api/v4/products HTTP/1.1
Accept-Language: en-US
X-Currency: USD
X-Country: US
```

## 3. OpenAPI (ID)

- `App\Services\Api\OpenApiSpec` mendokumentasikan ketiga header global + contoh id-ID/en-US.
- Spec existing tidak diubah; hanya aditif. / Existing spec untouched, additive only.
