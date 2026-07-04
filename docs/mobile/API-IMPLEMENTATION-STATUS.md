# API Implementation Status — DigiPro Customer Mobile

Terakhir diverifikasi: 2026-07-04
Sumber verifikasi: `php artisan route:list --path=api/v1 --except-vendor`, `routes/api.php`, controller, request, resource, service, migration, serta enam file test API utama. Snapshot verifikasi: 38 route API, 51 test API dengan 282 assertion, dan full suite 337 test dengan 2.888 assertion lulus.

## Fungsi dokumen ini

Dokumen ini mencatat API mobile yang benar-benar tersedia di repo Laravel saat ini. Ini berbeda dari `02-backend-api-contract.md`, yang berisi target kontrak untuk endpoint yang masih harus dibangun.

Aturan keras:

- Flutter hanya boleh memanggil endpoint yang berstatus **Implemented** di dokumen ini.
- Untuk area berstatus **Partial**, Flutter hanya boleh memanggil endpoint yang disebut eksplisit sebagai implemented; endpoint lain pada area tersebut tetap dianggap planned.
- Endpoint **Planned** boleh dipakai untuk desain DTO, mock repository, dan perencanaan, tetapi tidak boleh dianggap dapat dipanggil.
- Jangan memakai route web/Inertia sebagai fallback mobile.
- Setelah backend berubah, perbarui dokumen ini dari output `route:list` dan test, bukan dari ingatan.

## Base URL

Gunakan satu base URL yang sudah mencakup prefix API dan versi:

```text
https://<host>/api/v1/
```

Contoh development pada mesin Laravel:

```text
https://digipro.test/api/v1/
```

Pada Flutter:

```bash
flutter run --dart-define=API_BASE_URL=https://digipro.test/api/v1/
```

Path di API service harus relatif terhadap base URL. Contoh endpoint implemented dipanggil sebagai `customer/status`, bukan `/api/v1/customer/status`. Jangan menggandakan `/api/v1`.

`digipro.test` adalah host Laravel Herd pada mesin developer. Physical device atau emulator belum tentu dapat resolve domain tersebut atau mempercayai sertifikat development. Gunakan dev host HTTPS yang dapat diakses perangkat, lalu tetap akhiri base URL dengan `/api/v1/`.

## Endpoint yang sudah implemented

### Auth API

| Method | Relative path Flutter | Auth | Keterangan |
| --- | --- | --- | --- |
| POST | `auth/register` | Public | Register customer, kirim verifikasi email, dan terbitkan token terbatas. |
| POST | `auth/login` | Public | Login customer atau terbitkan 2FA challenge. |
| POST | `auth/two-factor/verify` | Public + challenge | Verifikasi OTP/recovery code dan terbitkan token final. |
| POST | `auth/forgot-password` | Public | Kirim reset link dengan response anti-enumeration. |
| POST | `auth/reset-password` | Public + reset token | Reset password dan revoke seluruh token user. |
| GET | `auth/email/verify/{id}/{hash}` | Signed URL | Verifikasi email customer. |
| POST | `auth/email/verification-notification` | Bearer token | Kirim ulang verifikasi email. |
| GET | `auth/me` | Bearer token | Current customer termasuk status verifikasi email. |
| POST | `auth/logout` | Bearer token | Revoke token yang sedang dipakai. |
| POST | `auth/logout-all` | Bearer token | Revoke semua token dengan ability `mobile:customer`. |

Token Auth API:

- ability: `mobile:customer`;
- expiry per token: 43.200 menit atau 30 hari secara default;
- expiry dapat dikonfigurasi lewat `MOBILE_API_TOKEN_EXPIRATION_MINUTES`;
- belum ada refresh-token endpoint;
- setelah `401`, Flutter harus menghapus session dan meminta login ulang.

Register dan login menerima `device_name` opsional dengan maksimum 100 karakter. Nilai default adalah `mobile`. Register membutuhkan `name`, `email`, `password`, `password_confirmation`, dan `terms`.

Customer belum verified tetap menerima token agar dapat memanggil `auth/me`, resend verification, dan logout. Endpoint feature customer seperti `customer/status` tetap menolak user tersebut melalui middleware `verified`.

Login akun dengan 2FA tidak mengembalikan access token. Response berisi `challenge_token` single-use dengan TTL 5 menit. Token final hanya diterbitkan setelah `auth/two-factor/verify` menerima `code` 6 digit atau `recovery_code` yang valid.

### GET `/customer/status`

Full path:

```text
GET /api/v1/customer/status
```

Route name:

```text
api.v1.customer.status
```

Tujuan: protected smoke check untuk membuktikan Bearer token, ability, verified email, dan role customer bekerja.

Middleware yang aktif:

```text
auth:sanctum
abilities:mobile:customer
verified
customer.role
```

Request headers:

```http
Accept: application/json
Authorization: Bearer <SANCTUM_TOKEN>
```

Request body: tidak ada.

Success response `200`:

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

Known failures:

| HTTP | Kondisi | Perilaku Flutter |
| --- | --- | --- |
| `401` | Tidak ada token atau token invalid | Hapus session lokal dan arahkan ke login. |
| `403` | Ability bukan `mobile:customer` | Jangan retry otomatis. |
| `403` | Email belum verified | Gunakan `auth/me` untuk mengarahkan user ke verification state. |
| `403` | User bukan role `customer` | Tampilkan akses ditolak dan akhiri session mobile. |

Response `403` foundation belum memiliki machine-readable `error_code` untuk membedakan missing ability, unverified email, dan wrong role. Flutter tidak boleh menebak penyebab hanya dari HTTP status; periksa `auth/me` setelah login untuk status verifikasi.

Contoh Dio:

```dart
final response = await dio.get<Map<String, dynamic>>('customer/status');
```

Contoh cURL untuk token yang sudah dibuat oleh backend/test tooling:

```bash
curl --request GET \
  --url https://digipro.test/api/v1/customer/status \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <SANCTUM_TOKEN>'
```

## Batasan auth saat ini

- Belum ada refresh-token contract. Jangan membuat silent refresh.
- Link reset password masih memakai route web `/reset-password/{token}` sebagai universal/app-link entry; Flutter mengambil token dan email lalu memanggil `auth/reset-password`.
- Link verifikasi mobile langsung memakai signed API URL.
- Foundation `customer/status` tetap membutuhkan email verified; gunakan `auth/me` untuk menentukan state sebelum memanggil feature endpoint.

## Customer Read API

Endpoint berikut sudah **Implemented** dan memakai middleware yang sama: `auth:sanctum`, `abilities:mobile:customer`, `verified`, dan `customer.role`.

| Method | Relative path Flutter | Keterangan |
| --- | --- | --- |
| GET | `dashboard` | Stats, featured request, lima request terbaru, action center, profile alert, dan support contact. |
| GET | `appraisals` | List milik customer login dengan search, filter status, statistik, dan pagination. |
| GET | `appraisals/options` | Enum/label backend, province awal, opsi field aset, dan upload limit. |
| GET | `appraisals/{id}` | Detail inti permohonan dan aset milik customer login. |
| GET | `appraisals/{id}/tracking` | Ringkasan request dan timeline status customer-safe. |
| GET | `profile` | Profil personal, billing, label lokasi, readiness, dan status 2FA. |
| GET | `notifications` | Notifikasi database customer login, unread count, dan pagination. |

Query `appraisals`:

- `q`: opsional, maksimum 100 karakter; mencari request number, client name, atau ID numerik;
- `status`: `all` atau salah satu nilai `AppraisalStatusEnum`;
- `per_page`: hanya `10`, `25`, atau `50`;
- `page`: integer minimal 1.

Query `notifications` menerima `per_page` bernilai `10`, `20`, atau `50`, serta `page` integer minimal 1.

Object status appraisal selalu memakai bentuk berikut; Flutter tidak perlu memetakan label atau tone sendiri:

```json
{
  "value": "offer_sent",
  "label": "Penawaran Dikirim",
  "tone": "warning",
  "requires_action": true,
  "action_key": "review_offer"
}
```

Potongan response list appraisal memakai envelope pagination Laravel berikut. Object `links` dan `meta` aktual juga memuat field pagination standar Laravel lain.

```json
{
  "data": [],
  "links": {},
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 10,
    "total": 0
  },
  "stats": {
    "total": 0,
    "by_status": {
      "draft": 0,
      "submitted": 0,
      "docs_incomplete": 0,
      "verified": 0,
      "waiting_offer": 0,
      "offer_sent": 0,
      "waiting_signature": 0,
      "contract_signed": 0,
      "valuation_in_progress": 0,
      "valuation_completed": 0,
      "preview_ready": 0,
      "report_preparation": 0,
      "report_ready": 0,
      "cancellation_review_pending": 0,
      "completed": 0,
      "cancelled": 0
    }
  },
  "filters": {
    "q": "",
    "status": "all",
    "per_page": 10
  }
}
```

Response notification memakai envelope pagination yang sama dan menambahkan `unread_count` di top level. Item notification hanya mengirim `action.key`, `resource_type`, dan `resource_id`; field `url` web sengaja tidak dikirim.

Detail appraisal berisi `status`, `purpose`, `valuation_objective`, `report`, `client`, `contract`, `assets`, ringkasan `payment`, serta `cancellation_request`. Tracking berisi ringkasan `request`, `timeline`, `payment`, dan `cancellation_request`. Dokumen/download URL belum menjadi kontrak kedua endpoint ini karena endpoint Documents/Reports belum implemented.

Kontrak keamanan dan navigasi:

- query appraisal selalu dibatasi `user_id` dari token;
- detail/tracking milik customer lain mengembalikan `404`;
- notification list berasal dari relation notification user login;
- payload tidak mengirim route Inertia, `detail_url`, `tracking_page_url`, raw storage path, atau URL web notification;
- navigasi mobile memakai `status.action_key` dan `notification.action`, bukan parsing URL web;
- `links` pagination adalah metadata API; Flutter sebaiknya memakai `meta.current_page` dan `meta.last_page`.

## Appraisal Draft, Asset, Upload, dan Submit API

Endpoint berikut berstatus **Implemented**:

| Method | Relative path Flutter | Keterangan |
| --- | --- | --- |
| POST | `appraisals/drafts` | Buat draft appraisal server-side. |
| GET | `appraisals/drafts/{draft}` | Baca draft milik customer login. |
| PUT | `appraisals/drafts/{draft}` | Update data dasar draft. |
| POST | `appraisals/drafts/{draft}/assets` | Tambah aset ke draft. |
| PUT | `appraisals/drafts/{draft}/assets/{asset}` | Update aset milik draft. |
| DELETE | `appraisals/drafts/{draft}/assets/{asset}` | Hapus aset dan file terkait. |
| POST | `appraisals/drafts/{draft}/assets/{asset}/files` | Upload multipart file aset. |
| DELETE | `appraisals/drafts/{draft}/files/{file}` | Hapus file milik draft. |
| POST | `appraisals/consent/accept` | Simpan acceptance consent versi aktif. |
| POST | `appraisals/drafts/{draft}/submit` | Validasi lengkap dan submit draft tepat sekali. |

Kontrak penting:

- semua draft, aset, dan file dibatasi ke user dari Bearer token;
- akses ke draft/aset milik user lain mengembalikan `404`;
- upload memvalidasi tipe dokumen/foto, MIME, extension, ukuran, dan jumlah file;
- submit memerlukan profil billing minimum, aset valid, file wajib, consent aktif, dan guideline aktif;
- endpoint `POST appraisals` langsung tidak digunakan; implementasi memilih draft server-side agar lebih tahan koneksi mobile;
- cancellation request dan consent decline masih **Planned**.

## Profile dan Account API

Seluruh endpoint berikut berstatus **Implemented**:

| Method | Relative path Flutter | Keterangan |
| --- | --- | --- |
| GET | `profile` | Profil, billing, label lokasi, readiness, dan status 2FA. |
| PUT | `profile` | Update profil dan billing; perubahan email memicu reverifikasi mobile. |
| GET | `profile/location-options` | Cascading province/regency/district/village tervalidasi. |
| PUT | `profile/password` | Ubah password setelah verifikasi password lama. |
| POST | `profile/password/verify` | Verifikasi password lama tanpa mengubahnya. |
| POST | `profile/avatar` | Upload/replace avatar JPG, PNG, atau WebP maksimal 2 MB. |
| DELETE | `profile/avatar` | Hapus avatar dan file storage milik user. |

## Notifications dan Device Token API

Seluruh endpoint database-notification dan registry token berikut berstatus **Implemented**:

| Method | Relative path Flutter | Keterangan |
| --- | --- | --- |
| GET | `notifications` | List notification milik customer dan unread count. |
| POST | `notifications/{notification}/read` | Mark satu notification milik customer sebagai read. |
| POST | `notifications/read-all` | Mark seluruh unread notification milik customer sebagai read. |
| POST | `notifications/device-token` | Register/update token FCM/APNs dan metadata perangkat. |
| DELETE | `notifications/device-token` | Hapus token hanya jika dimiliki customer login. |

Registry memakai tabel `mobile_device_tokens`. Token disimpan dengan encrypted cast, lookup memakai SHA-256 hash unik, dan satu token dapat dipindahkan ke user terbaru ketika akun pada perangkat berganti. Metadata yang didukung: `platform`, `provider`, `device_name`, `app_version`, `os_version`, `locale`, dan `last_seen_at`.

Batas keras: pengiriman push ke FCM/APNs belum implemented. Status selesai di area ini hanya berarti token registry dan database notifications siap.

## Matriks implementasi

| Area | Status | Endpoint yang dapat dipanggil sekarang |
| --- | --- | --- |
| API foundation | **Implemented** | `GET /api/v1/customer/status` |
| Auth | **Implemented** | Register, login, 2FA, forgot/reset password, email verification, `me`, logout, logout-all |
| Dashboard | **Implemented** | `GET /api/v1/dashboard` |
| Appraisal list/detail/tracking | **Implemented** | `GET /api/v1/appraisals`, `GET /api/v1/appraisals/options`, detail, tracking |
| Appraisal create/draft/upload | **Implemented** | Draft CRUD, asset CRUD, file upload/delete, consent accept, submit |
| Profile/account | **Implemented** | Read/update, location options, password verify/update, avatar upload/remove |
| Notifications/device token | **Implemented** | List, mark read/read-all, device token register/remove |
| Push delivery | **Planned** | Belum ada adapter/queue FCM atau APNs |
| Documents/reports | **Planned** | Tidak ada |
| Payments/invoice | **Planned** | Tidak ada |
| Offer/revision/market preview | **Planned** | Tidak ada |
| Contract/Peruri | **Planned** | Tidak ada |

## Checklist Eksekusi Backend Berikutnya

Urutan ini mengikat pekerjaan backend berikutnya. Jangan melompat ke area lain hanya karena route web-nya sudah ada.

- [x] Foundation dan Auth API.
- [x] Dashboard dan Appraisal Read API.
- [x] Appraisal Draft/Multi-asset/Upload/Submit API.
- [x] Profile dan Account API.
- [x] Database Notifications dan Device Token Registry API.
- [ ] P0 — Tambahkan contoh request/response aktual ke `02-backend-api-contract.md` untuk seluruh endpoint implemented.
- [ ] P1 — Offer: detail, accept, negotiate, select, ownership, status transition, dan idempotency test.
- [ ] P2 — Cancellation request sebagai resource appraisal tersendiri; jangan mempertahankan `offer/cancel` yang ambigu.
- [ ] P3 — Revision: open batch/detail dan submit file/data revision.
- [ ] P4 — Contract/Peruri: read, onboarding, PDF, sign; tunggu keputusan vendor untuk flow mobile.
- [ ] P5 — Market preview: read, approve, appeal.
- [ ] P6 — Payment/invoice: state, session, invoice payload/PDF, history/detail.
- [ ] P7 — Reports: list, detail, download.
- [ ] P8 — Push delivery adapter, queued delivery, invalid-token cleanup, retry/backoff, dan provider fakes.

Definition of done per item:

- [ ] Route terdaftar dan diberi nama di `/api/v1`.
- [ ] Middleware auth/ability/verified/customer-role benar.
- [ ] Form Request dan customer-safe Resource tersedia.
- [ ] Ownership dan status transition diuji.
- [ ] Success, `401`, `403`, `404`, dan `422` relevan diuji dengan Pest.
- [ ] Tidak ada route Inertia, raw storage path, atau raw vendor payload dalam response.
- [ ] Dokumen ini dan kontrak API diperbarui pada commit yang sama.

### Guardrail anti-scope-drift

1. Mulai setiap sesi backend dengan membaca checklist ini dan output `route:list` terbaru.
2. Kerjakan hanya prioritas pertama yang belum dicentang. Item setelahnya tidak boleh dimulai sebelum item aktif selesai atau blocker dicatat eksplisit.
3. Satu slice harus mencakup route, request, resource, service, ownership/status tests, dan dokumentasi. Jangan meninggalkan kontrak setengah jadi.
4. Jangan menandai item selesai berdasarkan keberadaan controller. Bukti selesai adalah route aktif dan Pest test yang relevan lulus.
5. Commit feature dan pembaruan status dokumentasinya bersama-sama agar status tidak kembali basi.

Semua path planned dijelaskan di `02-backend-api-contract.md`. Path tersebut adalah target desain dan masih boleh berubah sampai route, request validation, resource response, ownership rule, dan Pest test benar-benar tersedia.

## Gate sebelum integrasi Flutter real

Sebuah endpoint baru hanya boleh diubah menjadi **Implemented** setelah seluruh poin berikut terpenuhi:

1. Route tampil di `php artisan route:list --path=api --except-vendor`.
2. Middleware auth/ability/verified/role sesuai kebutuhan.
3. Request validation dan response Resource sudah stabil.
4. Ownership customer diuji.
5. Success dan error contract diuji dengan Pest.
6. Contoh request/response aktual ditambahkan ke dokumentasi.
7. Flutter DTO dan integration test memakai response aktual tersebut.

## Aturan untuk repo Flutter terpisah

Salin seluruh folder `docs/mobile/` ke repo Flutter. Jangan hanya menyalin design guide tunggal karena API status, architecture, feature specification, roadmap, checklist, dan prompt saling bergantung.

Selain folder dokumentasi, salin melalui proses asset yang disetujui:

- logo aktif `public/images/brand/digipro-by-kjpp-hjar-logo.png`;
- file font `Instrument Sans` yang akan dibundle;
- asset app icon/splash compact setelah disetujui owner.

Jangan mengandalkan relative link ke source Laravel setelah dokumen dipindahkan. Dokumen ini menjadi snapshot kontrak; backend repo tetap source of truth ketika kontrak berubah.
