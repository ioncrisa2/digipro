# Backend API Contract — DigiPro Customer Mobile

## Tujuan

API mobile adalah layer baru di Laravel, bukan pengganti Inertia web. Customer web tetap berjalan lewat route web. Mobile Flutter memakai JSON API `/api/v1` dengan Bearer token.

## Status kontrak

Dokumen ini adalah **target contract**, bukan daftar route yang seluruhnya sudah tersedia.

Per 2026-07-04:

- **Implemented:** API foundation dan seluruh endpoint Auth pada bagian Auth.
- **Implemented:** Dashboard dan Appraisal read.
- **Implemented:** Appraisal draft, multi-aset, file upload/delete, consent accept, dan submit.
- **Implemented:** Profile read/update, location options, password, dan avatar.
- **Implemented:** Notification list/mark-read dan device-token registry.
- **Planned:** Push delivery, Documents, Payments, Reports, serta transactional flow offer/revision/contract/market-preview.

Status aktual, response nyata, dan aturan base URL ada di `API-IMPLEMENTATION-STATUS.md`. Flutter dilarang memanggil endpoint planned sebelum statusnya diubah menjadi Implemented berdasarkan route dan Pest test Laravel.

## Fakta repo saat audit

Stack utama:

- Laravel 12.
- PHP runtime proyek ditetapkan 8.4; constraint Composer saat audit mengizinkan `^8.2`.
- Inertia Laravel v2.
- Vue 3.
- Tailwind v4.
- Fortify v1 headless/custom route web.
- Pest v4.
- Midtrans PHP SDK.
- Integrasi Peruri SIGN-IT di service backend.

File referensi audit:

- `routes/web.php`
- `routes/customer.php`
- `composer.json`
- `config/fortify.php`
- `app/Http/Controllers/Auth/AuthController.php`
- `app/Http/Controllers/Auth/EmailVerificationController.php`
- `app/Http/Controllers/Customer/AppraisalController.php`
- `app/Http/Controllers/Customer/DashboardController.php`
- `app/Http/Controllers/Customer/PaymentController.php`
- `app/Http/Controllers/Customer/ReportController.php`
- `app/Http/Controllers/Account/ProfileController.php`
- `app/Http/Controllers/Account/UserNotificationController.php`
- `app/Enums/AppraisalStatusEnum.php`
- `app/Enums/AssetTypeEnum.php`
- `app/Enums/PurposeEnum.php`
- `app/Enums/ContractStatusEnum.php`

## Struktur backend target

```text
routes/
  api.php

app/
  Http/
    Controllers/
      Api/
        V1/
          Auth/
          Customer/
          Account/
    Requests/
      Api/
        V1/
    Resources/
      Api/
        V1/
  Services/
    Customer/
    Payments/
    Peruri/
```

## Aturan arsitektur API

- API controller harus tipis.
- Validasi di Form Request.
- Business logic di service/action.
- Response di JsonResource/ResourceCollection.
- Route mobile prefix `/api/v1`.
- Protected endpoint memakai token auth, bukan session web.
- Semua response error harus JSON.
- Semua aksi customer wajib authorize ownership.
- Jangan hanya `find($id)`. Gunakan query scoped ke customer login atau policy.
- Upload file harus multipart dan divalidasi MIME, extension, ukuran, dan ownership.
- Jangan expose URL Inertia sebagai navigasi mobile.
- Mobile butuh `id`, `status`, `action_key`, dan payload data.

## Middleware API

Protected route minimum:

```php
Route::middleware([
    'auth:sanctum',
    'abilities:mobile:customer',
    'verified',
    'customer.role',
])->group(function (): void {
    // customer API
});
```

`customer.role` harus API-safe:

- unauthenticated API request return 401 JSON;
- authenticated non-customer return 403 JSON;
- customer valid lanjut.

Per 2026-07-04 terdapat 38 route `/api/v1`. Hanya smoke endpoint yang memakai segment `customer/status`; dashboard, appraisal, profile, dan notifications berada langsung di bawah `/api/v1`.

## Response envelope

Object response:

```json
{
  "data": {},
  "message": "OK"
}
```

`message` dan `meta` bersifat opsional. `JsonResource` tunggal umumnya mengirim `data`; collection paginated mengirim `data`, `links`, dan `meta`; controller action dapat menambahkan `message`, `stats`, `filters`, atau `unread_count` di top level.

List pagination mengikuti Laravel:

```json
{
  "data": [],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  },
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 10,
    "total": 40
  }
}
```

Validation error 422 mengikuti Laravel:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["Email wajib diisi."]
  }
}
```

Flutter wajib punya parser khusus untuk 422.

## Implemented foundation endpoint

| Method | Endpoint | Auth | Status | Keterangan |
| --- | --- | --- | --- | --- |
| GET | `/api/v1/customer/status` | Bearer token + ability `mobile:customer` + verified + customer role | **Implemented** | Protected smoke check API v1 dan current user ringkas. |

Request tidak memiliki body. Success response:

```json
{
  "data": {
    "status": "ok",
    "api_version": "v1",
    "user": {
      "id": 1,
      "name": "Customer",
      "email": "customer@example.com",
      "email_verified": true,
      "roles": ["customer"]
    }
  },
  "message": "Mobile API is ready."
}
```

Token untuk smoke check diperoleh dari register, login, atau verifikasi 2FA Auth API.

## Error contract

| Status | Makna | Flutter action |
| --- | --- | --- |
| 400 | Bad request | Tampilkan error umum kontekstual. |
| 401 | Unauthenticated/token invalid | Clear token, redirect login. |
| 403 | Forbidden/non-customer/no access | Tampilkan akses ditolak. Jangan retry otomatis. |
| 404 | Resource tidak ditemukan atau bukan milik user | Tampilkan not found. |
| 422 | Validation error | Map errors ke field. |
| 429 | Rate limited | Tampilkan pesan tunggu. |
| 500 | Server error | Tampilkan retry, logging internal. |
| Timeout/offline | Network failure | Pertahankan data lama/draft, sediakan retry. |

Response error aktual yang harus dapat diparse Flutter:

```json
{"message":"Unauthenticated."}
```

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "billing_regency_id": ["Kabupaten/kota billing tidak cocok dengan provinsi yang dipilih."]
  }
}
```

Response `403` dari endpoint tertentu dapat menambahkan `code`, misalnya `customer_access_required`. Ownership failure sengaja memakai `404` dan tidak mengungkap apakah resource milik user lain ada.

## Auth endpoints

Status: **Implemented** dan diuji di `tests/Feature/MobileAuthApiTest.php`.

| Method | Endpoint | Auth | Keterangan |
| --- | --- | --- | --- |
| POST | `/api/v1/auth/register` | Public | Buat customer, assign role customer, kirim email verification, issue token terbatas. |
| POST | `/api/v1/auth/login` | Public | Login password. Bila 2FA aktif, return challenge token. |
| POST | `/api/v1/auth/two-factor/verify` | Public sementara | Verifikasi OTP/recovery code, issue access token. |
| POST | `/api/v1/auth/forgot-password` | Public | Kirim reset link. |
| POST | `/api/v1/auth/reset-password` | Public | Reset password dari token email. |
| POST | `/api/v1/auth/email/verification-notification` | Sanctum | Resend verification. |
| GET | `/api/v1/auth/email/verify/{id}/{hash}` | Signed | Verify email dari deep link. |
| GET | `/api/v1/auth/me` | Sanctum | Current user. |
| POST | `/api/v1/auth/logout` | Sanctum | Revoke current token. |
| POST | `/api/v1/auth/logout-all` | Sanctum | Revoke semua token dengan ability `mobile:customer`. |

Register/login menerima `device_name` opsional. Token memiliki ability `mobile:customer` dan expiry default 30 hari. Tidak ada refresh-token endpoint.

Outcome aktual:

| Endpoint | Success | Response |
| --- | --- | --- |
| `auth/register` | `201` | Token envelope, user, dan pesan verifikasi email. |
| `auth/login` | `200` | Token envelope atau 2FA challenge. |
| `auth/two-factor/verify` | `200` | Token envelope setelah challenge valid. |
| `auth/forgot-password` | `200` | Pesan generik anti-enumeration. |
| `auth/reset-password` | `200` | Pesan reset berhasil; semua token user dicabut. |
| `auth/email/verification-notification` | `200` | `data.email_verified` dan message. |
| `auth/email/verify/{id}/{hash}` | `200` | `data.email_verified: true` dan message. |
| `auth/me` | `200` | `data` berupa `UserResource`, plus `message: "OK"`. |
| `auth/logout` | `200` | `{"message":"Logout berhasil."}`. |
| `auth/logout-all` | `200` | `{"message":"Semua sesi mobile berhasil diakhiri."}`. |

Register request:

```json
{
  "name": "Customer",
  "email": "customer@example.com",
  "password": "Password1!",
  "password_confirmation": "Password1!",
  "terms": true,
  "device_name": "Pixel 9"
}
```

Login request:

```json
{
  "email": "customer@example.com",
  "password": "Password1!",
  "device_name": "Pixel 9"
}
```

2FA verify menerima tepat salah satu dari `code` atau `recovery_code`:

```json
{
  "challenge_token": "64-character-token",
  "code": "123456"
}
```

Forgot-password request hanya berisi `email`. Reset-password request berisi `token`, `email`, `password`, dan `password_confirmation`.

```json
{
  "token": "password-broker-token",
  "email": "customer@example.com",
  "password": "PasswordBaru1!",
  "password_confirmation": "PasswordBaru1!"
}
```

Login sukses tanpa 2FA:

```json
{
  "data": {
    "access_token": "...",
    "token_type": "Bearer",
    "expires_at": "2026-07-29T12:00:00+00:00",
    "user": {
      "id": 1,
      "name": "Customer",
      "email": "customer@example.com",
      "email_verified": true,
      "roles": ["customer"]
    }
  },
  "message": "Login berhasil."
}
```

Login dengan 2FA:

```json
{
  "data": {
    "requires_two_factor": true,
    "challenge_token": "short-lived-token",
    "expires_in": 300,
    "email": "customer@example.com"
  },
  "message": "Masukkan kode autentikasi."
}
```

`GET /api/v1/auth/me` mengembalikan `UserResource` pada `data`. Endpoint ini sengaja tidak memakai middleware `verified` agar Flutter dapat menentukan apakah user harus masuk ke email-verification state.

Invalid credentials mengembalikan `422` pada field `email`. Login akun non-customer mengembalikan `403` dengan `code: customer_access_required`. Invalid/expired 2FA challenge mengembalikan `422` dengan `code: invalid_two_factor_challenge`; OTP/recovery code salah memakai `invalid_two_factor_code`.

2FA challenge token:

- expired singkat, misalnya 5 menit;
- single-use;
- disimpan hashed di cache/tabel khusus;
- bukan Sanctum access token final.

Customer belum verified tetap menerima token agar dapat mengakses `me`, resend verification, dan logout. Middleware `verified` tetap memblokir endpoint feature customer.

Forgot-password selalu mengembalikan pesan generik agar tidak membocorkan apakah email terdaftar. Reset password yang sukses revoke seluruh personal access token milik user.

## Feature endpoints

Status aktual per endpoint wajib dibaca dari `API-IMPLEMENTATION-STATUS.md`. Bagian berikut mendokumentasikan kontrak aktual endpoint yang sudah implemented, bukan desain hipotesis.

## Dashboard endpoint

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/dashboard` | Stats, featured request, recent requests, action center, support contact. |

Resource dashboard tidak boleh mengembalikan route Inertia seperti `detail_url`. Gunakan `id`, `type`, `status`, `action_key`.

Success `200`:

```json
{
  "data": {
    "stats": {
      "total_requests": 3,
      "in_progress": 2,
      "completed": 1,
      "need_revision": 0
    },
    "featured_request": {
      "id": 18,
      "request_number": "REQ-2026-0018",
      "status": {
        "value": "offer_sent",
        "label": "Penawaran Dikirim",
        "tone": "warning",
        "requires_action": true,
        "action_key": "review_offer"
      },
      "purpose": "jual_beli",
      "purpose_label": "Jual Beli",
      "report_type": "terinci",
      "report_type_label": "Terinci",
      "location": "Jl. Contoh No. 1",
      "assets_count": 1,
      "requested_at": "2026-07-01T08:00:00+00:00",
      "updated_at": "2026-07-04T08:00:00+00:00"
    },
    "recent_requests": [],
    "actions": [
      {
        "action_key": "review_offer",
        "label": "Penawaran Menunggu Respons",
        "count": 1,
        "tone": "warning",
        "status_filter": "offer_sent"
      }
    ],
    "profile_completion_alert": null,
    "support_contact": {
      "name": "Tim Support DigiPro by KJPP HJAR",
      "phone": "",
      "whatsapp": "",
      "email": "",
      "availability_label": "Senin-Jumat 08:00-17:00 WIB"
    }
  }
}
```

## Appraisal endpoints

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/appraisals` | List dengan filter `q`, `status`, pagination. |
| GET | `/api/v1/appraisals/options` | Enum asset type, purpose, status, initial location options. |
| GET | `/api/v1/appraisals/{appraisal}` | Detail. |
| GET | `/api/v1/appraisals/{appraisal}/tracking` | Timeline. |
| POST | `/api/v1/appraisals/consent/accept` | Accept consent latest. |

Query list aktual:

```text
GET /api/v1/appraisals?q=REQ-2026&status=submitted&per_page=10&page=1
```

`status` menerima `all` atau nilai `AppraisalStatusEnum`; `per_page` hanya `10`, `25`, atau `50`.

Success list `200`:

```json
{
  "data": [
    {
      "id": 18,
      "request_number": "REQ-2026-0018",
      "status": {
        "value": "submitted",
        "label": "Submitted",
        "tone": "info",
        "requires_action": false,
        "action_key": null
      },
      "purpose": "jual_beli",
      "purpose_label": "Jual Beli",
      "report_type": "terinci",
      "report_type_label": "Terinci",
      "location": "Jl. Contoh No. 1",
      "assets_count": 1,
      "requested_at": "2026-07-01T08:00:00+00:00",
      "updated_at": "2026-07-01T08:00:00+00:00"
    }
  ],
  "links": {},
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 10,
    "total": 1
  },
  "stats": {
    "total": 1,
    "by_status": {
      "submitted": 1
    }
  },
  "filters": {
    "q": "REQ-2026",
    "status": "submitted",
    "per_page": 10
  }
}
```

`GET appraisals/options` mengembalikan `data.asset_types`, `purposes`, `report_types`, `valuation_objectives`, `statuses`, `provinces`, `asset_fields`, `upload_limits`, dan `consent`, plus `message: "OK"`. Contoh berikut dipendekkan ke satu item per collection; nilai upload limit mengikuti konfigurasi PHP server:

```json
{
  "data": {
    "asset_types": [{"value": "tanah_bangunan", "label": "Tanah dan Bangunan"}],
    "purposes": [{"value": "jual_beli", "label": "Jual Beli"}],
    "report_types": [{"value": "terinci", "label": "Terinci"}],
    "valuation_objectives": [{"value": "kajian_nilai_pasar_dalam_bentuk_range", "label": "Kajian Nilai Pasar dalam Bentuk Range"}],
    "statuses": [{"value": "draft", "label": "Draft", "tone": "neutral", "requires_action": false, "action_key": null}],
    "provinces": [{"id": "31", "name": "DKI Jakarta"}],
    "asset_fields": {
      "usage": [{"value": "rumah_tinggal", "label": "Rumah Tinggal"}],
      "title_document": [{"value": "shm", "label": "SHM"}],
      "land_shape": [],
      "land_position": [],
      "land_condition": [],
      "topography": []
    },
    "upload_limits": {
      "max_files": 20,
      "max_file_size": "10M",
      "max_request_size": "20M"
    },
    "consent": null
  },
  "message": "OK"
}
```

Detail `GET appraisals/{id}` mengembalikan `data` dengan field: `id`, `request_number`, status presentation, `purpose`, `valuation_objective`, `report`, `client`, `contract`, `fee_total`, notes, timestamps, `assets_count`, `assets`, `payment`, dan `cancellation_request`:

```json
{
  "data": {
    "id": 18,
    "request_number": "REQ-2026-0018",
    "status": {"value": "submitted", "label": "Submitted", "tone": "info", "requires_action": false, "action_key": null},
    "purpose": {"value": "jual_beli", "label": "Jual Beli"},
    "valuation_objective": {"value": "kajian_nilai_pasar_dalam_bentuk_range", "label": "Kajian Nilai Pasar dalam Bentuk Range"},
    "report": {"type": "terinci", "type_label": "Terinci", "format": "both", "physical_copies_count": 1, "generated_at": null},
    "client": {"name": "PT Contoh", "address": "Jakarta", "spk_number": "SPK-001"},
    "contract": {"number": null, "date": null, "status": null, "status_label": null},
    "fee_total": null,
    "assets_count": 1,
    "assets": [
      {
        "id": 41,
        "asset_code": null,
        "type": "tanah_bangunan",
        "type_label": "Tanah dan Bangunan",
        "address": "Jl. Properti No. 1",
        "location": {
          "province": {"id": "31", "name": "DKI Jakarta"},
          "regency": {"id": "3171", "name": "Jakarta Selatan"},
          "district": {"id": "3171010", "name": "Tebet"},
          "village": {"id": "3171010001", "name": "Tebet Barat"}
        },
        "coordinates": {"latitude": -6.2297, "longitude": 106.8295},
        "land_area": 120,
        "building_area": 80
      }
    ],
    "payment": null,
    "cancellation_request": null
  }
}
```

Tracking mengembalikan `data.request`, `data.timeline`, `data.payment`, dan `data.cancellation_request`:

```json
{
  "data": {
    "request": {
      "id": 18,
      "request_number": "REQ-2026-0018",
      "status": {"value": "submitted", "label": "Submitted", "tone": "info", "requires_action": false, "action_key": null},
      "assets_count": 1,
      "requested_at": "2026-07-01T08:00:00+00:00",
      "verified_at": null,
      "cancelled_at": null
    },
    "timeline": [
      {
        "key": "request_submitted",
        "title": "Permohonan Dikirim",
        "description": "Permohonan REQ-2026-ABC123 berhasil dikirim.",
        "at": "2026-07-01 15:00",
        "type": "submitted"
      }
    ],
    "payment": null,
    "cancellation_request": null
  }
}
```

Resource milik customer lain selalu disamarkan sebagai `404`.

## Draft dan upload appraisal

Untuk mobile yang lebih tahan koneksi buruk, gunakan draft server-side:

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| POST | `/api/v1/appraisals/drafts` | Buat draft server-side. |
| GET | `/api/v1/appraisals/drafts/{draft}` | Baca draft. |
| PUT | `/api/v1/appraisals/drafts/{draft}` | Update draft. |
| POST | `/api/v1/appraisals/drafts/{draft}/assets` | Tambah asset. |
| PUT | `/api/v1/appraisals/drafts/{draft}/assets/{asset}` | Update asset. |
| DELETE | `/api/v1/appraisals/drafts/{draft}/assets/{asset}` | Hapus asset. |
| POST | `/api/v1/appraisals/drafts/{draft}/assets/{asset}/files` | Upload file asset. |
| DELETE | `/api/v1/appraisals/drafts/{draft}/files/{file}` | Hapus file. |
| POST | `/api/v1/appraisals/drafts/{draft}/submit` | Submit final. |

`POST /api/v1/appraisals` langsung tidak diimplementasikan. Flutter wajib memakai draft server-side.

## Kontrak aktual draft appraisal

Create/update draft request:

```json
{
  "purpose": "jual_beli",
  "report_type": "terinci",
  "client_name": "PT Contoh",
  "client_address": "Jl. Contoh No. 1",
  "client_spk_number": "SPK-001",
  "user_request_note": "Catatan customer",
  "sertifikat_on_hand_confirmed": true,
  "certificate_not_encumbered_confirmed": true
}
```

Semua field draft memakai `sometimes`; create tanpa field akan memakai default service. Create sukses `201`, show/update sukses `200`. Ketiganya mengembalikan `AppraisalDraftResource`.

Create asset request; `asset_type` wajib saat `POST`, field lain dapat dilengkapi bertahap:

```json
{
  "asset_type": "tanah_bangunan",
  "peruntukan": "rumah_tinggal",
  "title_document": "shm",
  "province_id": "31",
  "regency_id": "3171",
  "district_id": "3171010",
  "village_id": "3171010001",
  "address": "Jl. Properti No. 1",
  "coordinates_lat": -6.2297,
  "coordinates_lng": 106.8295,
  "land_area": 120,
  "building_area": 80,
  "building_floors": 2,
  "build_year": 2020
}
```

Create asset sukses `201`; update dan delete asset sukses `200`. Response memakai bentuk draft berikut:

```json
{
  "data": {
    "id": 18,
    "request_number": "REQ-2026-ABC123",
    "status": {
      "value": "draft",
      "label": "Draft",
      "tone": "neutral",
      "requires_action": false,
      "action_key": null
    },
    "purpose": "jual_beli",
    "report_type": "terinci",
    "client_name": "PT Contoh",
    "client_address": "Jl. Contoh No. 1",
    "client_spk_number": "SPK-001",
    "user_request_note": "Catatan customer",
    "sertifikat_on_hand_confirmed": true,
    "certificate_not_encumbered_confirmed": true,
    "report_format": "both",
    "physical_copies_count": 1,
    "assets_count": 1,
    "assets": [
      {
        "id": 41,
        "asset_type": "tanah_bangunan",
        "province_id": "31",
        "regency_id": "3171",
        "district_id": "3171010",
        "village_id": "3171010001",
        "address": "Jl. Properti No. 1",
        "coordinates_lat": -6.2297,
        "coordinates_lng": 106.8295,
        "land_area": 120,
        "building_area": 80,
        "building_floors": 2,
        "build_year": 2020,
        "files": []
      }
    ],
    "created_at": "2026-07-04T08:00:00+00:00",
    "updated_at": "2026-07-04T08:00:00+00:00"
  }
}
```

Field asset lain yang dapat dikirim: `land_shape`, `land_position`, `land_condition`, `topography`, `maps_link`, `renovation_year`, `frontage_width`, dan `access_road_width`.

Upload file memakai `multipart/form-data`:

```text
type=photo_front
files[]=front-1.jpg
files[]=front-2.webp
```

Tipe valid: `doc_pbb`, `doc_imb`, `doc_old_report`, `doc_certs`, `photo_access_road`, `photo_front`, dan `photo_interior`. Maksimal 20 file/request; dokumen maksimal 10 MB per file dan foto 15 MB per file. Upload sukses `201`:

```json
{
  "data": [
    {
      "id": 91,
      "type": "photo_front",
      "original_name": "front-1.jpg",
      "mime": "image/jpeg",
      "size": 204800,
      "created_at": "2026-07-04T08:10:00+00:00"
    }
  ]
}
```

Delete file sukses mengembalikan `204 No Content`.

Accept consent request dan response `200`:

```json
{
  "document_id": 3,
  "hash": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
  "accepted": true
}
```

```json
{
  "data": {
    "document_id": 3,
    "version": "2026-07-v1",
    "hash": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
    "accepted_at": "2026-07-04T08:15:00+00:00"
  },
  "message": "Persetujuan berhasil disimpan."
}
```

Submit tidak memiliki body selain header auth. Success `200`:

```json
{
  "data": {
    "id": 18,
    "request_number": "REQ-2026-0018",
    "status": "submitted"
  },
  "message": "Permohonan berhasil dikirim."
}
```

Submit `422` jika profil billing, aset, hierarki lokasi, dokumen wajib, consent, atau guideline belum lengkap. Submit ulang terhadap record yang bukan lagi draft mengembalikan `404`.

## Offer dan negosiasi

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/appraisals/{appraisal}/offer` | Offer detail dan riwayat negosiasi. |
| POST | `/api/v1/appraisals/{appraisal}/offer/accept` | Terima offer. |
| POST | `/api/v1/appraisals/{appraisal}/offer/negotiate` | Ajukan negosiasi. |
| POST | `/api/v1/appraisals/{appraisal}/offer/select` | Pilih opsi fee. |
| POST | `/api/v1/appraisals/{appraisal}/offer/cancel` | Jika tetap disediakan, behavior sama dengan web cancellation request. |

## Revisi dokumen

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/appraisals/{appraisal}/revisions` | Open revision batch dan item wajib diperbaiki. |
| POST | `/api/v1/appraisals/{appraisal}/revisions` | Submit file/data revisi. |

## Kontrak dan Peruri

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/appraisals/{appraisal}/contract` | Contract status, readiness, actions. |
| GET | `/api/v1/appraisals/{appraisal}/contract/onboarding` | Payload onboarding tanda tangan digital. |
| POST | `/api/v1/appraisals/{appraisal}/contract/onboarding/identity` | Simpan identitas. |
| POST | `/api/v1/appraisals/{appraisal}/contract/onboarding/register-user` | Retry register user. |
| POST | `/api/v1/appraisals/{appraisal}/contract/onboarding/kyc` | Upload video/foto KYC sesuai vendor. |
| POST | `/api/v1/appraisals/{appraisal}/contract/onboarding/specimen` | Upload/gambar tanda tangan. |
| POST | `/api/v1/appraisals/{appraisal}/contract/onboarding/register-keyla` | Buat QR aktivasi KEYLA. |
| POST | `/api/v1/appraisals/{appraisal}/contract/onboarding/refresh` | Refresh status. |
| GET | `/api/v1/appraisals/{appraisal}/contract/pdf` | Stream PDF. |
| POST | `/api/v1/appraisals/{appraisal}/contract/sign` | Sign contract dengan kode KEYLA. |

Aturan Peruri:

- Jangan expose response mentah Peruri ke Flutter.
- Gunakan presenter/resource customer-safe.
- Jangan bungkus external API call dalam DB transaction panjang.
- Jika vendor gagal, data lokal valid jangan dibuang.
- Konfirmasi SDK/liveness Peruri sebelum native camera flow.

## Market preview

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/appraisals/{appraisal}/market-preview` | Preview hasil kajian customer-safe. |
| POST | `/api/v1/appraisals/{appraisal}/market-preview/approve` | Setujui preview. |
| POST | `/api/v1/appraisals/{appraisal}/market-preview/appeal` | Ajukan appeal dengan alasan tervalidasi. |

Status: **Planned**. Contract ini belum final dan wajib authorize ownership appraisal.

## Payment dan invoice

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/appraisals/{appraisal}/payment` | Payment state untuk request. |
| POST | `/api/v1/appraisals/{appraisal}/payment/session` | Buat/reuse Midtrans session. |
| GET | `/api/v1/appraisals/{appraisal}/invoice` | Invoice payload. |
| GET | `/api/v1/appraisals/{appraisal}/invoice/pdf` | Stream invoice PDF. |
| GET | `/api/v1/payments` | Riwayat pembayaran. |
| GET | `/api/v1/payments/{payment}` | Detail pembayaran. |

Webhook Midtrans tetap server-to-server:

```text
POST /payments/midtrans/notification
```

Mobile tidak memanggil webhook. Mobile hanya membaca status payment dari backend setelah redirect/WebView kembali.

## Reports

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/reports` | List laporan customer. |
| GET | `/api/v1/reports/{appraisal}` | Detail laporan. |
| GET | `/api/v1/reports/{appraisal}/download` | Stream PDF laporan. |

## Profile dan account

Status: **Implemented** dan diuji di `tests/Feature/CustomerAccountApiTest.php`.

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/profile` | Data profil dan billing. |
| PUT | `/api/v1/profile` | Update profil. |
| GET | `/api/v1/profile/location-options` | Cascading province/regency/district/village. |
| PUT | `/api/v1/profile/password` | Ubah password. |
| POST | `/api/v1/profile/password/verify` | Verify current password jika UI butuh. |
| POST | `/api/v1/profile/avatar` | Upload avatar. |
| DELETE | `/api/v1/profile/avatar` | Remove avatar. |

`GET profile`, update profile, upload avatar, dan remove avatar mengembalikan bentuk `ProfileResource` berikut; action mutation menambahkan `message` di top level:

```json
{
  "data": {
    "id": 7,
    "name": "Customer Mobile",
    "email": "customer@example.com",
    "email_verified": true,
    "phone_number": "081234567890",
    "whatsapp_number": "081234567890",
    "address": "Jakarta",
    "company_name": null,
    "avatar_url": "https://api.example.com/storage/avatars/avatar.webp",
    "billing": {
      "recipient_name": "Finance Mobile",
      "email": "billing@example.com",
      "address": null,
      "address_detail": "Jl. Billing No. 10",
      "postal_code": "12810",
      "npwp": null,
      "nik": null,
      "province": {"id": "31", "name": "DKI Jakarta"},
      "regency": {"id": "3171", "name": "Jakarta Selatan"},
      "district": {"id": "3171010", "name": "Tebet"},
      "village": {"id": "3171010001", "name": "Tebet Barat"}
    },
    "profile_complete": true,
    "two_factor_enabled": false
  }
}
```

Update profile request `PUT profile`:

```json
{
  "name": "Customer Mobile",
  "email": "customer@example.com",
  "phone_number": "081234567890",
  "whatsapp_number": "081234567890",
  "address": "Jakarta",
  "billing_recipient_name": "Finance Mobile",
  "billing_province_id": "31",
  "billing_regency_id": "3171",
  "billing_district_id": "3171010",
  "billing_village_id": "3171010001",
  "billing_postal_code": "12810",
  "billing_address_detail": "Jl. Billing No. 10",
  "billing_npwp": null,
  "billing_nik": null,
  "billing_email": "billing@example.com"
}
```

`name` dan `email` wajib. Hierarki billing divalidasi lintas province/regency/district/village. Jika email berubah, `email_verified` menjadi `false`, notifikasi verifikasi mobile dikirim, dan feature endpoint akan terblokir middleware `verified` sampai verifikasi selesai.

Location options:

```text
GET /api/v1/profile/location-options?type=regencies&province_id=31
```

`type` bernilai `provinces`, `regencies`, `districts`, atau `villages`. Parent ID wajib sesuai type. Success `200`:

```json
{
  "data": [
    {"value": "3171", "label": "Jakarta Selatan (3171)"}
  ]
}
```

Password verify request dan response:

```json
{"current_password": "password-lama"}
```

```json
{"data": {"valid": true}}
```

Password update request:

```json
{
  "current_password": "password-lama",
  "password": "password-baru",
  "password_confirmation": "password-baru"
}
```

Success `200`: `{"message":"Password berhasil diperbarui."}`. Password lama salah menghasilkan `422` pada `current_password`.

Avatar upload memakai `multipart/form-data` dengan field `avatar`; format valid JPG/JPEG/PNG/WebP maksimal 2 MB. Upload/replace dan delete mengembalikan `ProfileResource`; file avatar lama dihapus. Delete tidak memerlukan body.

## Notifications dan device token

Status database notifications dan token registry: **Implemented**. Push delivery provider: **Planned**.

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/notifications` | List notifications dengan unread count. |
| POST | `/api/v1/notifications/{notification}/read` | Mark one as read. |
| POST | `/api/v1/notifications/read-all` | Mark all as read. |
| POST | `/api/v1/notifications/device-token` | Register/update FCM/APNs token. |
| DELETE | `/api/v1/notifications/device-token` | Remove token saat logout/device revoke. |

Notification list menerima `per_page` `10`, `20`, atau `50`, serta `page` minimal 1. Success `200` memakai pagination Laravel dan menambahkan `unread_count`:

```json
{
  "data": [
    {
      "id": "4e82d0b6-1f5c-4fe0-a371-90f78ac9fd7a",
      "type": "AppraisalRequestCreated",
      "title": "Permohonan berhasil dibuat",
      "message": "Permohonan REQ-2026-0018 berhasil dibuat.",
      "read": false,
      "read_at": null,
      "created_at": "2026-07-04T08:00:00+00:00",
      "action": {
        "key": "view_appraisal",
        "resource_type": "appraisal",
        "resource_id": 18
      },
      "context": {"appraisal_id": 18}
    }
  ],
  "links": {},
  "meta": {},
  "unread_count": 1
}
```

Mark one read tidak memiliki body. Success `200` mengembalikan notification resource yang sama dengan `read: true` dan `unread_count` terbaru. Notification milik user lain menghasilkan `404`.

Mark all read tidak memiliki body. Success `200`:

```json
{
  "data": {
    "updated_count": 3,
    "unread_count": 0
  }
}
```

Register/update device token request:

```json
{
  "token": "provider-device-token-minimum-20-characters",
  "platform": "android",
  "provider": "fcm",
  "device_name": "Pixel 9",
  "app_version": "1.0.0",
  "os_version": "16",
  "locale": "id-ID"
}
```

`platform` hanya `android`/`ios`; `provider` hanya `fcm`/`apns` dan default `fcm`. Create pertama mengembalikan `201`; update token yang sama mengembalikan `200`:

```json
{
  "data": {
    "id": 12,
    "platform": "android",
    "provider": "fcm",
    "device_name": "Pixel 9",
    "app_version": "1.0.0",
    "os_version": "16",
    "locale": "id-ID",
    "last_seen_at": "2026-07-04T08:00:00+00:00"
  },
  "message": "Device token berhasil disimpan."
}
```

Token mentah tidak pernah dikembalikan. Backend menyimpannya terenkripsi dan memakai SHA-256 hash unik untuk lookup. Device token delete menerima JSON `{"token":"..."}` dan selalu mengembalikan `204 No Content`; query dibatasi ke user login sehingga token user lain tidak terhapus.

Tabel `user_device_tokens`:

- `id`
- `user_id`
- `token`
- `platform`: `android`, `ios`
- `device_id` nullable
- `app_version` nullable
- `last_seen_at`
- timestamps

Tambahkan unique index agar token tidak duplikat lintas user.

## Enum dan data referensi

Backend harus expose enum sebagai payload agar Flutter tidak hardcode label.

### AppraisalStatusEnum

- `draft`
- `submitted`
- `docs_incomplete`
- `verified`
- `waiting_offer`
- `offer_sent`
- `waiting_signature`
- `contract_signed`
- `valuation_in_progress`
- `valuation_completed`
- `preview_ready`
- `report_preparation`
- `report_ready`
- `cancellation_review_pending`
- `completed`
- `cancelled`

Flutter mengikuti value API, bukan nama case PHP.

### AssetTypeEnum

- `tanah`
- `tanah_bangunan`
- `rumah_tinggal`
- `ruko`
- `apartement`
- `kios`
- `gudang`
- `kantor`
- `pabrik`
- `tanah_kebun`
- `tanah_dan_bangunan`
- `sawah`

Catatan: value historis `apartement` jangan diam-diam diganti di Flutter. Label boleh diperbaiki lewat presenter, value tetap sesuai backend.

### PurposeEnum

- `jual_beli`
- `penjaminan_utang`
- `lelang`

### ContractStatusEnum

- `none`
- `draft`
- `sent_to_client`
- `waiting_signature`
- `signed`
- `negotiation`
- `cancelled`

## Backend test plan

Minimum Pest test sebelum mobile integration besar:

Auth API:

- Register customer membuat user role customer.
- Register menolak disposable email sesuai rule existing.
- Login sukses mengembalikan token.
- Login user non-customer ditolak.
- Login user dengan 2FA mengembalikan challenge token, bukan access token final.
- 2FA verify sukses menerbitkan token.
- Logout revoke token aktif.
- Unverified user tidak bisa akses protected endpoint.
- Resend verification butuh auth token.
- Forgot/reset password berjalan JSON.

Customer API:

- Dashboard hanya data milik user login.
- List appraisals tidak leak data user lain.
- Detail appraisal user lain return 404/403.
- Create appraisal valid membuat request dan asset.
- Create appraisal invalid return 422 dengan field errors.
- Upload file menolak MIME/size invalid.
- Cancellation request hanya bisa untuk status valid.

Payment API:

- Session payment hanya bisa dibuat setelah `contract_signed`.
- Reuse active Midtrans session berjalan.
- Paid payment tidak bisa membuat session baru.
- Webhook signature invalid return 403.
- Webhook valid update status payment.

Notifications API:

- List notifications hanya milik user login.
- Mark read satu notification milik user.
- Mark read notification user lain ditolak.
- Device token upsert idempotent.
- Logout bisa menghapus/revoke device token.

Perintah:

```bash
php artisan test --compact --filter=Api
vendor/bin/pint --dirty
```
