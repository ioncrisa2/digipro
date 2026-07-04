# Implementation Plan Mobile App Customer DigiPro

Tanggal audit awal: 2026-06-26
Terakhir disinkronkan dengan implementasi: 2026-07-04

> Catatan handoff: source of truth canonical untuk pengerjaan di repo Flutter terpisah adalah seluruh folder [`docs/mobile/`](./mobile/). Periksa `docs/mobile/API-IMPLEMENTATION-STATUS.md` untuk endpoint yang benar-benar tersedia. Daftar endpoint dalam dokumen lama ini adalah rencana dan tidak boleh dianggap implemented tanpa verifikasi route/test Laravel.

Dokumen ini adalah context handoff untuk agent/developer berikutnya saat pekerjaan mobile app dipindah ke proyek lain. Tujuannya bukan menjual ide mobile app, tetapi memberi urutan implementasi yang bisa dieksekusi tanpa harus scan ulang seluruh repo dari nol.

## Ringkasan Eksekutif

Backend mobile MVP utama sudah tersedia sebagai JSON API Laravel 12 di `/api/v1`. Yang belum tersedia adalah transactional flow setelah submit: cancellation, offer/negosiasi, revisi, kontrak/Peruri, market preview, payment/invoice, dan report.

Keputusan teknis yang harus dipegang:

1. Bangun API layer customer dulu di Laravel.
2. Jangan copy-paste logic controller Inertia ke API controller. Extract logic ke service/action/resource yang bisa dipakai web dan API.
3. Flutter hanya boleh mulai integrasi real setelah kontrak response API stabil untuk Auth, Dashboard, Appraisal, Profile, dan Upload.
4. Peruri dan Midtrans adalah dependency eksternal paling berisiko. Jangan jadikan keduanya blocker MVP mobile.
5. Mobile MVP harus fokus pada flow customer utama: auth, dashboard, buat permohonan, tracking, profil, dan notifikasi dasar.

Kalau tim langsung mulai Flutter tanpa API contract, hasilnya akan jadi prototype palsu: UI kelihatan bergerak, tetapi tidak siap produksi.

## Fakta Repo Saat Ini

Stack utama:

- Laravel 12
- PHP 8.4 sesuai AGENTS.md, composer saat ini mengizinkan `^8.2`
- Inertia Laravel v2
- Vue 3
- Tailwind v4
- Fortify v1, tetapi dikonfigurasi headless untuk route web sendiri
- Pest v4
- Midtrans PHP SDK
- Integrasi Peruri SIGN-IT sudah ada di service backend

File yang sudah dicek:

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

Temuan terkini per 2026-07-04:

- `routes/api.php` aktif dan memiliki 38 route `/api/v1`.
- `laravel/sanctum` terpasang dan token mobile memakai ability `mobile:customer`.
- Auth, dashboard, appraisal read, draft/multi-aset/upload/submit, profile/account, notification read, dan device-token registry sudah implemented.
- Protected feature route memakai `auth:sanctum`, `abilities:mobile:customer`, `verified`, dan `customer.role`.
- Enam file test API utama mencakup 51 test dan 282 assertion; full suite terakhir 337 test dan 2.888 assertion lulus.
- Push provider delivery belum implemented. Yang tersedia baru registry token FCM/APNs.
- Transactional flow setelah appraisal submit belum memiliki route mobile.

## Tracker Implementasi API

Status canonical dan detail endpoint ada di [`docs/mobile/API-IMPLEMENTATION-STATUS.md`](./mobile/API-IMPLEMENTATION-STATUS.md). Checklist ini hanya ringkasan agar urutan kerja tidak melebar.

### Selesai dan terverifikasi

- [x] API foundation, JSON errors, Sanctum ability, verified email, dan customer role.
- [x] Auth: register, login, 2FA, forgot/reset password, email verification, `me`, dan logout.
- [x] Dashboard customer.
- [x] Appraisal list, options, detail, dan tracking dengan ownership.
- [x] Draft appraisal server-side, update draft, multi-aset, dan asset ownership.
- [x] Upload/delete file aset, consent accept, validasi final, dan submit draft.
- [x] Profile read/update, billing hierarchy, password verify/update, dan avatar upload/remove.
- [x] Notification list, mark one read, dan mark all read.
- [x] Device-token register/update/remove dengan token terenkripsi dan hash unik.

### Belum selesai — kerjakan dalam urutan ini

- [ ] P0 — Bekukan contoh request/response aktual untuk endpoint implemented di dokumentasi kontrak.
- [ ] P1 — Offer detail, accept, negotiate, dan select; cancellation memakai endpoint appraisal tersendiri, bukan `offer/cancel` ambigu.
- [ ] P2 — Revisi dokumen: open batch/detail dan submit revisi.
- [ ] P3 — Contract read, onboarding, PDF, dan sign; keputusan Peruri/mobile flow wajib dikunci.
- [ ] P4 — Market preview read, approve, dan appeal.
- [ ] P5 — Payment state/session, invoice payload/PDF, payment history/detail.
- [ ] P6 — Report list/detail/download.
- [ ] P7 — Push delivery adapter dan queue setelah credential Firebase/APNs tersedia.

Aturan tracking: item hanya boleh ditandai selesai jika route terdaftar, middleware benar, Form Request/Resource tersedia, ownership diuji, Pest test lulus, dan status canonical diperbarui.

Guardrail kerja: pada sesi berikutnya, pilih hanya item prioritas pertama yang belum dicentang di status canonical. Jangan melompat ke item berikutnya kecuali item aktif selesai atau blocker eksternal dicatat eksplisit.

## Scope Mobile App

### In Scope MVP

- Register, login, logout.
- Verifikasi email dan resend verification.
- Two-factor challenge untuk akun yang mengaktifkan 2FA.
- Forgot password dan reset password dengan deep link.
- Dashboard customer.
- List permohonan penilaian.
- Buat permohonan baru dengan multi-aset.
- Detail permohonan dan tracking status.
- Upload dokumen aset.
- Profil, billing data, avatar, dan ubah password.
- In-app notifications.
- Registrasi device token untuk push notification.

### In Scope Setelah MVP

- Offer dan negosiasi.
- Revisi dokumen.
- Kontrak digital dan onboarding tanda tangan Peruri.
- Tanda tangan kontrak.
- Midtrans payment session.
- Invoice PDF.
- Preview kajian pasar approve/appeal.
- Reports list/detail/download.
- Push notification lengkap untuk status, offer, payment, report, dan revision.

### Out of Scope Mobile

- Admin/back-office Filament.
- Reviewer panel.
- Landing page, artikel, FAQ, policy, terms. Kalau tetap diperlukan di app, gunakan WebView atau external browser.

## Prinsip Arsitektur Backend API

API mobile harus menjadi layer baru, bukan mengganti Inertia.

Target struktur:

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

Aturan:

- API controller harus tipis.
- Validasi tetap di Form Request.
- Business logic tetap di service/action.
- API response memakai Resource/JsonResource, bukan mengembalikan payload Inertia mentah tanpa kontrol.
- Route mobile harus di-prefix `/api/v1`.
- Semua endpoint protected memakai token auth, bukan session web.
- Semua response error harus JSON.
- Semua aksi customer harus authorize ownership request. Jangan hanya `find($id)`.
- Upload file harus multipart dan divalidasi MIME, extension, ukuran, dan ownership.

## Phase 0 - Keputusan dan Kontrak Teknis

Status phase: baseline decision dibuat. Masih perlu konfirmasi owner untuk vendor Peruri, Firebase, domain deep link, dan target rilis final.

Phase 0 bukan fase coding. Fase ini ada untuk mengunci arah supaya Phase 1 tidak dibangun di atas asumsi lemah.

### Keputusan Baseline

| Area | Keputusan baseline | Alasan | Status |
| --- | --- | --- | --- |
| Repo mobile | Buat repo Flutter terpisah dari repo Laravel. | Laravel repo ini adalah backend/web app. Menaruh Flutter di sini akan mencampur lifecycle build, dependency, dan release. | Disarankan fixed |
| Target rilis | Android MVP dulu, tetapi codebase Flutter tetap disiapkan cross-platform untuk iOS. | Customer Indonesia kemungkinan lebih berat Android. iOS jangan diabaikan, tapi jangan jadikan blocker MVP. | Perlu konfirmasi bisnis |
| API scope | API mobile v1 hanya untuk customer. Admin dan reviewer tetap web-only. | Scope mobile yang melebar ke admin/reviewer akan mengacaukan authorization dan timeline. | Fixed untuk v1 |
| Auth mobile | Laravel Sanctum personal access token dengan Bearer token. | Mobile native butuh token auth, bukan session/cookie Inertia. | Fixed untuk Phase 1 |
| 2FA mobile | Login password mengembalikan challenge sementara jika 2FA aktif; access token final hanya diterbitkan setelah OTP/recovery code valid. | Session-based `login.id` web tidak cocok untuk mobile API. | Fixed untuk Phase 2 |
| API response | JSON API v1 dengan `JsonResource`, pagination Laravel, dan validation error 422 standar Laravel. | Flutter butuh kontrak stabil, bukan props Inertia. | Fixed untuk Phase 1 |
| Push notification | Siapkan device token API dan tabel sejak backend MVP; pengiriman FCM/APNs boleh menyusul jika Firebase belum siap. | Schema dan endpoint murah disiapkan awal. Delivery bisa aktif setelah credential tersedia. | Perlu Firebase project |
| Deep link | Wajib disiapkan untuk email verification dan reset password. | Auth mobile akan terasa rusak jika link email selalu jatuh ke browser tanpa fallback app. | Perlu domain/app link config |
| Midtrans | MVP memakai Snap `redirect_url` dalam WebView/in-app browser dengan backend sebagai source of truth status. Native SDK dievaluasi setelah payment flow stabil. | Dokumentasi resmi Midtrans menyatakan Flutter tidak punya SDK resmi langsung; WebView/Snap dan native Android/iOS SDK adalah jalur resmi yang perlu dipilih pragmatis. | Fixed untuk MVP |
| Peruri | Jangan implement custom KYC/liveness native sebelum vendor mengonfirmasi SDK/flow resmi. MVP API harus parity dengan flow web dan menyembunyikan detail vendor dari Flutter. | Risiko legal dan vendor terlalu tinggi untuk ditebak. | Blocked by vendor confirmation |

### Kontrak API Awal yang Dianggap Cukup untuk Phase 1

Kontrak di Phase 2 dan Phase 3 dalam dokumen ini dianggap draft v0.1. Itu cukup untuk mulai Phase 1 backend foundation, dengan batasan berikut:

- Endpoint Auth, Dashboard, Appraisal read, Profile, dan Notifications menjadi prioritas pertama.
- Endpoint Peruri, Payment, Market Preview, Reports, dan transactional flow belum boleh dianggap final sebelum vendor/payment decision diuji.
- Semua endpoint protected harus pakai `auth:sanctum`, `verified`, dan `customer.role`.
- Semua endpoint yang menerima `appraisal` harus membuktikan ownership customer.
- Semua response harus JSON, termasuk error authorization, validation, unauthenticated, dan exception umum.
- API tidak boleh mengembalikan URL route Inertia sebagai navigasi utama. Mobile memakai id, status, action key, dan payload.

### Vendor Findings per 2026-06-26

Midtrans:

- Dokumentasi resmi Midtrans menempatkan Snap sebagai built-in interface untuk web/app dan bisa ditampilkan di WebView.
- Midtrans juga punya native Android/iOS Mobile SDK.
- Dokumentasi resmi Midtrans menyatakan tidak menyediakan official SDK langsung untuk Flutter/React Native/hybrid, tetapi hybrid tetap bisa memakai WebView/Snap atau Core API.
- Konsekuensi: jangan memilih package Flutter pihak ketiga sebagai default produksi sebelum ada audit maintenance, security, dan compatibility. Untuk MVP, gunakan Snap `redirect_url` dari backend.

Peruri:

- Repo backend sudah punya integrasi Peruri SIGN-IT dan KEYLA flow.
- Dokumentasi publik yang mudah diverifikasi tidak cukup untuk menyimpulkan ada SDK mobile SIGN-IT/KYC yang siap dipakai Flutter.
- Konsekuensi: Peruri harus dianggap vendor blocker. Tim harus minta dokumen resmi/API/SDK flow ke Peruri sebelum membangun kamera/liveness native.

Rujukan vendor:

- Midtrans Snap Integration Guide: `https://docs.midtrans.com/docs/snap-snap-integration-guide`
- Midtrans hybrid/non-native mobile framework guidance: `https://docs.midtrans.com/docs/does-midtrans-support-flutter-react-native-or-other-hybrid-non-native-mobile-framework`
- Midtrans Payment Overview: `https://docs.midtrans.com/docs/payment-overview`
- Peruri Digital Touch Point: `https://sign.peruri.co.id/dashboard/peruri-code/`

### Output Phase 0 yang Harus Ada Sebelum Phase 1

Minimal untuk mulai Phase 1:

- Approval menambahkan `laravel/sanctum`.
- Keputusan bahwa API v1 hanya untuk customer.
- Keputusan Bearer token Sanctum untuk mobile.
- Keputusan response JSON Resource, bukan Inertia props.
- Keputusan Midtrans MVP memakai Snap WebView/in-app browser.

Yang boleh menyusul saat Phase 1 berjalan:

- Firebase project dan credential.
- Domain deep link final.
- Konfirmasi Peruri SDK/mobile flow.
- Keputusan Android-only release date atau Android+iOS parallel release.

### Pertanyaan yang Masih Harus Dijawab Owner

1. Android MVP dulu, atau Android+iOS harus rilis bersamaan?
2. Siapa owner Firebase project dan push notification credential?
3. Domain apa yang dipakai untuk universal link/app link production?
4. Apakah Peruri memberikan SDK mobile resmi untuk KYC/liveness/KEYLA, atau mobile tetap memakai flow berbasis backend/WebView?
5. Apakah payment boleh memakai WebView untuk MVP, atau bisnis menuntut native payment UI sejak awal?
6. Apakah API mobile akan dipakai partner eksternal di masa depan, atau hanya first-party mobile app?

Jawaban blak-blakan: kalau pertanyaan 4 dan 5 belum terjawab, jangan jadikan Peruri dan payment sebagai bagian MVP pertama. Itu bukan sikap hati-hati berlebihan; itu cara menghindari timeline disandera vendor.

### Definition of Done Phase 0

- Baseline decisions di atas diterima atau dikoreksi eksplisit.
- Approval dependency Sanctum jelas.
- Owner vendor Peruri dan Midtrans jelas.
- Owner Firebase dan deep link jelas.
- Phase 1 boleh dimulai walaupun Peruri/Firebase belum selesai, selama scope Phase 1 tetap API foundation.

## Phase 1 - Persiapan Backend API

Status implementasi: selesai pada 2026-06-26 untuk fondasi awal.

Yang sudah dibuat:

- `laravel/sanctum` terpasang.
- `routes/api.php` aktif lewat `bootstrap/app.php`.
- `User` memakai `Laravel\Sanctum\HasApiTokens`.
- Alias middleware Sanctum `abilities` dan `ability` terdaftar.
- Exception untuk route `api/*` dipaksa JSON.
- Route smoke protected tersedia: `GET /api/v1/customer/status`.
- Route smoke memakai `auth:sanctum`, `abilities:mobile:customer`, `verified`, dan `customer.role`.
- `customer.role` dibuat API-safe: API request tanpa user return 401, API user non-customer return 403.
- Test fondasi tersedia di `tests/Feature/ApiFoundationTest.php`.

### 1. Install dan konfigurasi Sanctum

Status: **selesai**. Pada audit awal `laravel/sanctum` belum tersedia; dependency dan konfigurasi berikut sudah diterapkan.

Perintah historis implementasi:

```bash
composer require laravel/sanctum
php artisan install:api --no-interaction
```

Catatan:

- Verifikasi command untuk Laravel 12 saat implementasi. Jangan menebak kalau package version berubah.
- Setelah install, cek `bootstrap/app.php`, `config/sanctum.php`, dan middleware API.
- Token mobile sebaiknya personal access token dengan ability seperti `mobile:customer`.
- Jangan gunakan cookie SPA Sanctum untuk mobile native. Mobile native pakai Bearer token.

### 2. Buat route API v1

Target awal:

```php
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(...);

    Route::middleware(['auth:sanctum', 'verified', 'customer.role'])->group(function (): void {
        // customer API
    });
});
```

Perlu middleware tambahan:

- Force JSON untuk `/api/*`.
- Throttle API auth.
- Customer role middleware yang kompatibel untuk token auth.

### 3. Buat response envelope

Gunakan format konsisten:

```json
{
  "data": {},
  "meta": {},
  "message": "OK"
}
```

Untuk list pagination:

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
  }
}
```

Untuk validation error, ikuti default Laravel:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["Email wajib diisi."]
  }
}
```

Flutter harus punya parser khusus untuk 422.

### 4. Refactor payload builder agar reusable

Saat ini banyak halaman customer sudah memakai builder/service, contohnya:

- `AppraisalService`
- `CustomerAppraisalWorkflowService`
- `AppraisalRequestService`
- `CustomerPaymentViewService`
- `CustomerPaymentWorkflowService`
- `CustomerSignatureOnboardingService`
- `AppraisalMarketPreviewService`
- `AppraisalRequestRevisionSubmissionService`

Jangan buat duplikasi logic baru di API controller. Targetnya:

- Web controller tetap return `inertia(...)`.
- API controller return `JsonResource` dari data/service yang sama.
- Kalau payload builder sekarang terlalu Vue/Inertia-specific, buat presenter/resource baru untuk mobile.

## Phase 2 - Backend Auth API

Status implementasi: selesai pada 2026-06-29.

Yang sudah tersedia:

- Register/login customer dengan Sanctum token ability `mobile:customer`.
- Token expiry eksplisit default 30 hari.
- 2FA challenge cache 5 menit, single-use, OTP/recovery code, dan batas percobaan.
- Forgot/reset password JSON dengan response anti-enumeration dan token revocation setelah reset.
- Signed mobile email verification dan resend.
- `me`, logout current token, dan logout semua token mobile.
- Named rate limiter untuk auth endpoint.
- Test tersedia di `tests/Feature/MobileAuthApiTest.php`.

### Endpoint auth

| Method | Endpoint | Auth | Keterangan |
| --- | --- | --- | --- |
| POST | `/api/v1/auth/register` | Public | Buat user customer, assign role customer, kirim email verification |
| POST | `/api/v1/auth/login` | Public | Login password. Jika 2FA aktif, return challenge token, bukan access token final |
| POST | `/api/v1/auth/two-factor/verify` | Public sementara | Verifikasi OTP/recovery code, lalu issue access token |
| POST | `/api/v1/auth/forgot-password` | Public | Kirim reset link |
| POST | `/api/v1/auth/reset-password` | Public | Reset password dari token email |
| POST | `/api/v1/auth/email/verification-notification` | Sanctum | Resend verification |
| GET | `/api/v1/auth/email/verify/{id}/{hash}` | Signed | Verify email dari deep link |
| GET | `/api/v1/auth/me` | Sanctum | Current user |
| POST | `/api/v1/auth/logout` | Sanctum | Revoke current token |
| POST | `/api/v1/auth/logout-all` | Sanctum | Revoke semua token dengan ability `mobile:customer` |

### Login response contract

Jika tidak perlu 2FA:

```json
{
  "data": {
    "access_token": "...",
    "token_type": "Bearer",
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

Jika perlu 2FA:

```json
{
  "data": {
    "requires_two_factor": true,
    "challenge_token": "short-lived-token",
    "email": "customer@example.com"
  },
  "message": "Masukkan kode autentikasi."
}
```

Jangan simpan `login.id` di session untuk mobile. Buat challenge token sementara yang:

- Expired singkat, misalnya 5 menit.
- Single-use.
- Disimpan hashed di cache atau tabel khusus.
- Tidak sama dengan access token Sanctum final.

### Email verification dan deep link

Email verification route web sekarang redirect ke halaman Inertia. Untuk mobile:

- Email tetap boleh berisi universal link/app link.
- Backend tetap harus verify signed URL.
- Setelah verify, redirect ke route web success jika dibuka browser.
- Jika dibuka app, Flutter harus menangkap deep link dan memanggil endpoint verify atau membuka signed URL lalu menampilkan success.

Jangan hardcode asumsi bahwa user selalu membuka email dari device yang sama.

## Phase 3 - Backend Customer API

Status implementasi per 2026-07-04: scope MVP backend untuk dashboard, appraisal read/create/upload, profile, notification database, dan device-token registry selesai. Scope transactional setelah submit masih pending.

### Dashboard

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/dashboard` | Stats, featured request, recent requests, action center, support contact |

Ambil inspirasi dari `DashboardController::index()`, tetapi Resource harus menghapus URL route web seperti `detail_url`. Mobile butuh route key/id, bukan URL Inertia.

### Appraisal requests

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/appraisals` | List dengan filter `q`, `status`, pagination |
| GET | `/api/v1/appraisals/options` | Enum asset type, purpose, status, initial location options |
| POST | `/api/v1/appraisals` | Buat permohonan multi-aset |
| GET | `/api/v1/appraisals/{appraisal}` | Detail |
| GET | `/api/v1/appraisals/{appraisal}/tracking` | Timeline |
| POST | `/api/v1/appraisals/{appraisal}/cancellation-request` | Ajukan pembatalan |
| POST | `/api/v1/appraisals/consent/accept` | Accept consent latest |
| POST | `/api/v1/appraisals/consent/decline` | Decline consent latest |

Request create harus mendukung:

- Multi-aset.
- Cascading location: province, regency, district, village.
- Asset type.
- Purpose.
- Land/building fields.
- Koordinat lat/lng.
- Dokumen aset.

Saran implementasi upload:

- MVP: multipart submit dalam satu request jika jumlah file masih rasional.
- Lebih kuat: buat draft appraisal dulu, lalu upload dokumen per asset dengan endpoint terpisah.

Endpoint upload yang lebih aman untuk mobile:

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| POST | `/api/v1/appraisals/drafts` | Buat draft server-side |
| PUT | `/api/v1/appraisals/drafts/{draft}` | Update draft |
| POST | `/api/v1/appraisals/drafts/{draft}/assets` | Tambah asset |
| POST | `/api/v1/appraisals/drafts/{draft}/assets/{asset}/files` | Upload file asset |
| POST | `/api/v1/appraisals/drafts/{draft}/submit` | Submit final |

Kalau waktu pendek, boleh langsung `POST /appraisals`, tetapi konsekuensinya UX mobile lebih rapuh saat koneksi buruk.

### Offer dan negosiasi

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/appraisals/{appraisal}/offer` | Offer detail dan riwayat negosiasi |
| POST | `/api/v1/appraisals/{appraisal}/offer/accept` | Terima offer |
| POST | `/api/v1/appraisals/{appraisal}/offer/negotiate` | Ajukan negosiasi |
| POST | `/api/v1/appraisals/{appraisal}/offer/select` | Pilih opsi fee |
| POST | `/api/v1/appraisals/{appraisal}/offer/cancel` | Jika tetap disediakan, return behavior sama dengan web: gunakan cancellation request |

Mobile UI sebaiknya menampilkan negosiasi sebagai conversation/timeline, bukan tabel.

### Revisi dokumen

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/appraisals/{appraisal}/revisions` | Open revision batch dan item yang harus diperbaiki |
| POST | `/api/v1/appraisals/{appraisal}/revisions` | Submit file/data revisi |

Gunakan service `AppraisalRequestRevisionSubmissionService`. Pastikan response menandai field mana yang wajib, file mana yang bisa diganti, dan status submit terakhir.

### Kontrak dan Peruri

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/appraisals/{appraisal}/contract` | Contract status, readiness, available actions |
| GET | `/api/v1/appraisals/{appraisal}/contract/onboarding` | Payload onboarding tanda tangan digital |
| POST | `/api/v1/appraisals/{appraisal}/contract/onboarding/identity` | Simpan identitas dan optionally register user |
| POST | `/api/v1/appraisals/{appraisal}/contract/onboarding/register-user` | Retry register user |
| POST | `/api/v1/appraisals/{appraisal}/contract/onboarding/kyc` | Upload video/foto KYC sesuai vendor |
| POST | `/api/v1/appraisals/{appraisal}/contract/onboarding/specimen` | Upload/gambar tanda tangan |
| POST | `/api/v1/appraisals/{appraisal}/contract/onboarding/register-keyla` | Buat QR aktivasi KEYLA |
| POST | `/api/v1/appraisals/{appraisal}/contract/onboarding/refresh` | Refresh status |
| GET | `/api/v1/appraisals/{appraisal}/contract/pdf` | Stream PDF |
| POST | `/api/v1/appraisals/{appraisal}/contract/sign` | Sign contract dengan kode KEYLA |

Catatan keras:

- Jangan expose response mentah Peruri ke Flutter.
- Gunakan `CustomerSignatureOnboardingPresenter` atau Resource yang customer-safe.
- Jangan bungkus external API call Peruri dalam DB transaction panjang.
- Jika vendor gagal, data lokal yang sudah valid jangan dibuang.
- Konfirmasi SDK/liveness Peruri sebelum memilih native camera flow.

### Market preview

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/appraisals/{appraisal}/market-preview` | Preview hasil kajian |
| POST | `/api/v1/appraisals/{appraisal}/market-preview/approve` | Approve |
| POST | `/api/v1/appraisals/{appraisal}/market-preview/appeal` | Appeal dengan reason |

### Payment dan invoice

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/appraisals/{appraisal}/payment` | Payment state untuk request |
| POST | `/api/v1/appraisals/{appraisal}/payment/session` | Buat/reuse Midtrans session |
| GET | `/api/v1/appraisals/{appraisal}/invoice` | Invoice payload |
| GET | `/api/v1/appraisals/{appraisal}/invoice/pdf` | Stream invoice PDF |
| GET | `/api/v1/payments` | Riwayat pembayaran |
| GET | `/api/v1/payments/{payment}` | Detail pembayaran |

Webhook Midtrans tetap public server-to-server:

```text
POST /payments/midtrans/notification
```

Jangan pindahkan webhook menjadi endpoint yang dipanggil mobile. Mobile hanya membaca status payment setelah backend menerima callback.

### Reports

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/reports` | List laporan customer |
| GET | `/api/v1/reports/{appraisal}` | Detail laporan |
| GET | `/api/v1/reports/{appraisal}/download` | Stream PDF laporan |

### Profile dan account

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/profile` | Data profil dan billing |
| PUT | `/api/v1/profile` | Update profil |
| GET | `/api/v1/profile/location-options` | Cascading province/regency/district/village |
| PUT | `/api/v1/profile/password` | Ubah password |
| POST | `/api/v1/profile/password/verify` | Verify current password jika UI butuh |
| POST | `/api/v1/profile/avatar` | Upload avatar |
| DELETE | `/api/v1/profile/avatar` | Remove avatar |

### Notifications dan push token

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| GET | `/api/v1/notifications` | List notifications dengan unread count |
| POST | `/api/v1/notifications/{notification}/read` | Mark one as read |
| POST | `/api/v1/notifications/read-all` | Mark all as read |
| POST | `/api/v1/notifications/device-token` | Register/update FCM/APNs token |
| DELETE | `/api/v1/notifications/device-token` | Remove token saat logout/device revoke |

Perlu tabel baru, misalnya `user_device_tokens`:

- `id`
- `user_id`
- `token`
- `platform`: `android`, `ios`
- `device_id` nullable
- `app_version` nullable
- `last_seen_at`
- timestamps

Tambahkan unique index yang mencegah token duplikat lintas user.

## Enum dan Data Referensi

Backend harus expose enum sebagai payload agar Flutter tidak hardcode label.

### AppraisalStatusEnum

Nilai aktual repo:

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

Catatan: enum key di PHP bernama `ValuationOnProgress`, tetapi value-nya `valuation_in_progress`. Flutter harus mengikuti value API, bukan nama case PHP.

### AssetTypeEnum

Nilai aktual repo:

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

Catatan: ada typo/value historis `apartement`. Jangan diam-diam diganti di Flutter menjadi `apartemen` kalau backend belum migrasi. Label boleh "Apartement" atau diperbaiki via presenter, tetapi value harus tetap sesuai backend.

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

## Backend Test Plan

Gunakan Pest. Minimum test yang harus ada sebelum mobile integrasi:

### Auth API

- Register customer membuat user role customer.
- Register menolak disposable email sesuai rule existing.
- Login sukses mengembalikan token.
- Login user non-customer ditolak atau diarahkan sesuai keputusan API.
- Login user dengan 2FA mengembalikan challenge token, bukan access token final.
- 2FA verify sukses menerbitkan token.
- Logout revoke token aktif.
- Unverified user tidak bisa akses protected customer endpoint.
- Resend verification butuh auth token.
- Forgot/reset password berjalan JSON.

### Customer API

- Dashboard hanya mengembalikan data milik user login.
- List appraisals tidak leak data user lain.
- Detail appraisal user lain return 404/403.
- Create appraisal valid membuat request dan asset.
- Create appraisal invalid return 422 dengan field errors.
- Upload file menolak MIME/size tidak valid.
- Cancellation request hanya bisa untuk status yang valid.

### Contract/Peruri API

- Contract onboarding tidak bisa diakses sebelum status valid.
- Save identity menyimpan data.
- Register Peruri gagal tidak membuang identity.
- KYC/specimen upload divalidasi.
- Response tidak mengandung raw vendor error untuk customer.

### Payment API

- Session payment hanya bisa dibuat setelah `contract_signed`.
- Reuse active Midtrans session berjalan.
- Paid payment tidak bisa membuat session baru.
- Webhook signature invalid return 403.
- Webhook valid update status payment.

### Notifications API

- List notifications hanya milik user login.
- Mark read satu notification milik user.
- Mark read notification user lain ditolak.
- Device token upsert idempotent.
- Logout bisa menghapus/revoke device token.

Perintah saat implementasi:

```bash
php artisan test --compact --filter=Api
vendor/bin/pint --dirty
```

## Flutter Architecture Plan

Mobile app sebaiknya repo terpisah. Jangan taruh Flutter app di dalam Laravel repo kecuali ada alasan deployment yang jelas.

### Stack yang disarankan

- Flutter stable.
- Riverpod untuk state management.
- Dio untuk networking.
- `flutter_secure_storage` untuk token.
- `go_router` untuk routing.
- `freezed`/`json_serializable` jika tim nyaman dengan code generation.
- `image_picker` atau `camera` untuk foto/dokumen.
- `flutter_image_compress` untuk kompresi upload.
- `geolocator` + map SDK untuk location picker.
- `firebase_messaging` untuk push notification.
- `app_links` untuk deep link.
- `local_auth` untuk biometric setelah login pertama.
- PDF viewer package untuk contract/invoice/report.

Jangan pakai package karena populer saja. Setiap dependency harus menjawab kebutuhan nyata.

### Struktur folder Flutter

```text
lib/
  app/
    app.dart
    bootstrap.dart
  core/
    config/
      env.dart
    error/
      app_failure.dart
    network/
      api_client.dart
      api_config.dart
      auth_interceptor.dart
      laravel_error_parser.dart
    routing/
      app_router.dart
      app_route.dart
    storage/
      secure_token_storage.dart
    theme/
    widgets/
  features/
    auth/
      data/
        dto/
        mappers/
        repositories/
        services/
      domain/
        models/
        repositories/
      presentation/
        controllers/
        screens/
        widgets/
    dashboard/
    appraisals/
    offers/
    contract/
    payments/
    reports/
    profile/
    notifications/
```

Aturan yang harus diikuti:

- Widget tidak boleh call API langsung.
- DTO tidak boleh bocor menjadi domain model UI.
- Repository map DTO ke model domain.
- Error handling harus typed: network, unauthorized, forbidden, validation, server, unknown.
- Token injection di interceptor, bukan di tiap service.
- 401 ditangani global: clear token, redirect login, hapus device token kalau perlu.
- Loading/empty/error state harus eksplisit di tiap screen data.

### API client contract

`ApiClient` harus mengatur:

- `baseUrl` dari `--dart-define=API_BASE_URL=...`
- `Accept: application/json`
- `Content-Type: application/json`, kecuali multipart
- `Authorization: Bearer <token>`
- Timeout connect/read/write
- Debug logging tanpa token
- Laravel 422 parser
- 401 handler
- Multipart upload helper

Contoh environment:

```bash
flutter run --dart-define=API_BASE_URL=https://digipro.test/api/v1/
```

Untuk device fisik, `digipro.test` dari Herd belum tentu resolve. Siapkan host yang bisa diakses device, misalnya tunnel/dev domain/LAN IP dengan HTTPS.

### Feature order Flutter

Urutan implementasi mobile:

1. App shell, routing, theme, API client, secure storage.
2. Auth: login, register, email verification state, logout.
3. Dashboard.
4. Appraisal list/detail/tracking.
5. Create appraisal without fancy offline draft first.
6. Profile dan avatar.
7. Notifications list dan device token.
8. Upload hardening: camera, compression, retry.
9. Offer/negotiation.
10. Revisions.
11. Contract onboarding Peruri.
12. Payment Midtrans.
13. Invoice/report PDF viewer.
14. Deep link polish dan biometric.

## Mobile UX Requirements

Mobile bukan port mentah dari web.

Wajib native/mobile-first:

- Location picker dengan "gunakan lokasi saya".
- Camera capture untuk foto dokumen/aset.
- Kompresi gambar sebelum upload.
- Draft lokal untuk form permohonan panjang.
- Upload progress dan retry.
- Empty state yang jelas.
- Push notification untuk perubahan status penting.
- Deep link untuk verify email dan reset password.
- PDF viewer in-app dengan share/download.

Jangan bawa kelemahan web ke mobile:

- Jangan paksa user mengisi form multi-aset sangat panjang tanpa autosave.
- Jangan tampilkan istilah vendor Peruri mentah.
- Jangan tampilkan URL web sebagai navigasi.
- Jangan hardcode label enum di Flutter jika backend bisa expose label.

## MVP Milestone yang Realistis

### Milestone A - Backend API Foundation

Definition of done:

- Sanctum token auth berjalan.
- `/api/v1/auth/*` tersedia.
- `/api/v1/dashboard` tersedia.
- `/api/v1/appraisals` list/detail/tracking tersedia.
- `/api/v1/profile` tersedia.
- Tests Auth API dan Customer API utama pass.

### Milestone B - Mobile Foundation

Definition of done:

- Flutter app bisa login ke API real.
- Token tersimpan di secure storage.
- 401 global handling berjalan.
- Dashboard real data.
- Appraisal list/detail/tracking real data.
- Logout revoke token.
- `flutter analyze` dan `flutter test` pass.

### Milestone C - Appraisal Create dan Upload

Definition of done:

- User bisa membuat permohonan baru dari mobile.
- Multi-aset berjalan.
- Upload dokumen berjalan dengan multipart.
- Validasi Laravel 422 tampil per field.
- Draft lokal minimal ada untuk mencegah data hilang.

### Milestone D - Profile dan Notifications

Definition of done:

- Update profile dan billing.
- Upload/remove avatar.
- Device token FCM/APNs tersimpan.
- Notification list dan mark read berjalan.
- Push dasar untuk status appraisal bisa diterima.

### Milestone E - Transactional Flows

Definition of done:

- Offer accept/negotiate/select.
- Revision submit.
- Peruri onboarding minimal parity dengan web.
- Contract sign.
- Payment session.
- Invoice/report PDF.

## Risiko Utama

### 1. API layer diremehkan

Risiko terbesar bukan Flutter. Risiko terbesar adalah tim mengira backend sudah siap karena web sudah jalan. Itu salah. Inertia props bukan API contract mobile.

Mitigasi:

- Buat API resources.
- Tambah test.
- Stabilkan response sebelum Flutter integration besar.

### 2. Peruri mobile flow belum jelas

KYC/liveness dan KEYLA bisa berubah total tergantung vendor.

Mitigasi:

- Konfirmasi SDK/vendor lebih awal.
- Bungkus Peruri di service backend.
- Flutter hanya berurusan dengan customer-safe state/actions.

### 3. Midtrans UX bisa buruk

Kalau payment hanya WebView tanpa kontrol state yang baik, user akan bingung saat balik ke app.

Mitigasi:

- Backend tetap sumber kebenaran status payment.
- Mobile listen app lifecycle dan refresh status setelah payment.
- Gunakan push notification/webhook untuk update.

### 4. Upload mobile mudah gagal

Form multi-aset + file besar + koneksi mobile adalah kombinasi rawan.

Mitigasi:

- Kompresi file.
- Upload progress.
- Draft local/server-side.
- Retry.
- Batasi ukuran dengan pesan jelas.

### 5. Deep link sering dianggap detail kecil

Email verification dan reset password akan terasa rusak kalau deep link tidak dirancang sejak awal.

Mitigasi:

- Siapkan Android App Links dan iOS Universal Links.
- Buat fallback browser.
- Test link dari Gmail/Apple Mail/WhatsApp.

## Instruksi untuk Agent Berikutnya

Saat melanjutkan dari dokumen ini:

1. Jangan mulai dari Flutter UI sebelum minimal Auth API dan Appraisal read API ada.
2. Audit ulang branch terbaru karena repo aktif berubah.
3. Jika menambah backend code, ikuti Laravel conventions repo ini dan tulis Pest tests.
4. Jika menambah dependency seperti Sanctum atau Firebase, minta approval pemilik proyek.
5. Gunakan Form Request untuk validasi API.
6. Gunakan JsonResource untuk response API.
7. Jangan expose raw vendor payload Peruri/Midtrans ke mobile.
8. Jangan mengubah route web existing tanpa alasan kuat.
9. Jangan menyatukan admin/reviewer ke mobile customer API.
10. Dokumentasikan API contract yang sudah final di docs terpisah setelah implementasi dimulai.

## Checklist Handoff

Backend:

- [x] Approval dependency `laravel/sanctum`.
- [x] `routes/api.php` dibuat.
- [x] Middleware API customer siap.
- [x] Auth API selesai dan dites.
- [x] Dashboard API selesai dan dites.
- [x] Appraisal list/detail/tracking API selesai dan dites.
- [x] Appraisal create/upload API selesai dan dites melalui draft server-side.
- [x] Profile API selesai dan dites.
- [x] Notifications dan device-token registry API selesai dan dites.
- [ ] Push delivery FCM/APNs selesai dan dites; saat ini baru registry token.
- [ ] Offer/revision/contract/payment/report API selesai bertahap.

Mobile:

- [ ] Repo Flutter dibuat.
- [ ] Base architecture dibuat.
- [ ] API client Dio dibuat.
- [ ] Secure token storage dibuat.
- [ ] Auth flow dibuat.
- [ ] Dashboard dibuat.
- [ ] Appraisal list/detail/tracking dibuat.
- [ ] Create appraisal dan upload dibuat.
- [ ] Profile dibuat.
- [ ] Notifications dan push dibuat.
- [ ] Transactional flows dibuat setelah backend siap.

External:

- [ ] Peruri mobile SDK/WebView decision.
- [ ] Midtrans SDK/WebView decision.
- [ ] Firebase project tersedia.
- [ ] Deep link domain dan app link config tersedia.
- [ ] Dev API base URL bisa diakses device fisik.

## Kesimpulan

Urutan yang benar adalah backend API foundation dulu, baru Flutter integration. Web DigiPro sudah punya banyak business logic yang bisa dipakai ulang, tetapi saat ini logic itu dibungkus untuk Inertia. Pekerjaan utama adalah memisahkan kontrak data mobile dari kontrak halaman web.

Kalau project dipindah ke agent lain, jangan beri instruksi "buat aplikasi Flutter dari requirement". Instruksi yang benar adalah:

> Bangun API customer `/api/v1` berbasis Sanctum dan Resource terlebih dahulu, test ownership/security/upload/auth, lalu implement Flutter feature-by-feature mengikuti kontrak API yang sudah stabil.
