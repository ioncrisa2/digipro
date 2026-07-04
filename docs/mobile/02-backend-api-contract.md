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

Route yang sudah ada saat audit:

```text
GET /api/v1/customer/status
```

Endpoint tersebut berada di group `prefix('customer')`. Endpoint target di bawah, seperti `/api/v1/dashboard` dan `/api/v1/appraisals`, belum memiliki route. Jangan otomatis menambahkan atau menghapus segment `/customer`; ikuti path yang benar-benar didaftarkan saat feature API diimplementasikan.

## Response envelope

Object response:

```json
{
  "data": {},
  "message": "OK"
}
```

`meta` bersifat opsional dan hanya dikirim jika response membutuhkannya. Endpoint implemented `/customer/status` tidak mengirim `meta`.

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
    "per_page": 15,
    "total": 40
  },
  "message": "OK"
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

Status aktual per endpoint wajib dibaca dari `API-IMPLEMENTATION-STATUS.md`. Dashboard, Appraisal list/options/detail/tracking, Profile read, dan Notifications list sudah memiliki route, Form Request bila menerima query, Resource, ownership rule, dan Pest test. Endpoint write di bagian yang sama tetap draft sampai implementasinya tersedia.

## Dashboard endpoint

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/dashboard` | Stats, featured request, recent requests, action center, support contact. |

Resource dashboard tidak boleh mengembalikan route Inertia seperti `detail_url`. Gunakan `id`, `type`, `status`, `action_key`.

## Appraisal endpoints

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/appraisals` | List dengan filter `q`, `status`, pagination. |
| GET | `/api/v1/appraisals/options` | Enum asset type, purpose, status, initial location options. |
| POST | `/api/v1/appraisals` | Buat permohonan multi-aset. |
| GET | `/api/v1/appraisals/{appraisal}` | Detail. |
| GET | `/api/v1/appraisals/{appraisal}/tracking` | Timeline. |
| POST | `/api/v1/appraisals/{appraisal}/cancellation-request` | Ajukan pembatalan. |
| POST | `/api/v1/appraisals/consent/accept` | Accept consent latest. |
| POST | `/api/v1/appraisals/consent/decline` | Decline consent latest. |

## Draft dan upload appraisal

Untuk mobile yang lebih tahan koneksi buruk, gunakan draft server-side:

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| POST | `/api/v1/appraisals/drafts` | Buat draft server-side. |
| PUT | `/api/v1/appraisals/drafts/{draft}` | Update draft. |
| POST | `/api/v1/appraisals/drafts/{draft}/assets` | Tambah asset. |
| PUT | `/api/v1/appraisals/drafts/{draft}/assets/{asset}` | Update asset. |
| DELETE | `/api/v1/appraisals/drafts/{draft}/assets/{asset}` | Hapus asset. |
| POST | `/api/v1/appraisals/drafts/{draft}/assets/{asset}/files` | Upload file asset. |
| DELETE | `/api/v1/appraisals/drafts/{draft}/files/{file}` | Hapus file. |
| POST | `/api/v1/appraisals/drafts/{draft}/submit` | Submit final. |

Jika waktu pendek, boleh langsung `POST /api/v1/appraisals` multipart, tetapi UX lebih rapuh. Catat konsekuensi teknisnya.

## Create appraisal form contract

Step 1 — Tujuan dan data dasar:

- `purpose`: required.
- `asset_type_summary`: optional untuk ringkasan awal.
- `notes`: optional.

Step 2 — Daftar aset:

- `assets[]`: minimal 1.
- `asset_type`: required.
- `address`: required.
- `province_id`: required.
- `regency_id`: required.
- `district_id`: required.
- `village_id`: optional/required sesuai backend.
- `latitude`: optional tetapi disarankan.
- `longitude`: optional tetapi disarankan.

Step 3 — Detail aset:

- `land_area`: conditional.
- `building_area`: conditional.
- `building_year`: optional/conditional.
- `certificate_type`: optional/conditional.
- `road_width`: optional.
- `front_width`: optional.
- `description`: optional.

Step 4 — Dokumen dan foto:

- `asset_photos[]`: optional/conditional.
- `certificate_file`: optional/conditional.
- `identity_file`: optional/conditional.
- `supporting_files[]`: optional.

Step 5 — Review:

- `consent_accepted`: required true.
- submit final.

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

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/profile` | Data profil dan billing. |
| PUT | `/api/v1/profile` | Update profil. |
| GET | `/api/v1/profile/location-options` | Cascading province/regency/district/village. |
| PUT | `/api/v1/profile/password` | Ubah password. |
| POST | `/api/v1/profile/password/verify` | Verify current password jika UI butuh. |
| POST | `/api/v1/profile/avatar` | Upload avatar. |
| DELETE | `/api/v1/profile/avatar` | Remove avatar. |

## Notifications dan device token

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/notifications` | List notifications dengan unread count. |
| POST | `/api/v1/notifications/{notification}/read` | Mark one as read. |
| POST | `/api/v1/notifications/read-all` | Mark all as read. |
| POST | `/api/v1/notifications/device-token` | Register/update FCM/APNs token. |
| DELETE | `/api/v1/notifications/device-token` | Remove token saat logout/device revoke. |

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
