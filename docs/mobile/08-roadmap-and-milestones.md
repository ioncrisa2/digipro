# Roadmap and Milestones — DigiPro Customer Mobile

## Status aktual per 2026-07-04

| Task | Status | Bukti |
| --- | --- | --- |
| Task 0 — Audit repo dan kontrak | **In progress** | Baseline dan target contract tersedia; vendor/deep-link decisions masih terbuka. |
| Task 1 — Backend API Foundation | **Complete** | Sanctum, `routes/api.php`, JSON error handling, protected status endpoint, dan Pest foundation test tersedia. |
| Task 2 — Auth API | **Complete** | Sepuluh route Auth tersedia dan `MobileAuthApiTest` lulus. |
| Task 3 — Customer Read API | **Completed** | Dashboard, Appraisal list/options/detail/tracking, Profile read, dan Notifications list tersedia serta diuji. |
| Task 3A — Appraisal Write API | **Complete** | Draft, multi-aset, upload/delete file, consent accept, dan submit tersedia serta diuji. |
| Task 3B — Profile dan Notifications API | **Complete untuk registry/database** | Profile mutation, password, avatar, mark read, dan registry device token tersedia; push delivery belum ada. |
| Task 4 — Flutter Foundation | **Ready** | ApiClient dan Auth integration boleh mulai di repo Flutter terpisah. |
| Task 5 — Auth Flutter | **Ready** | Auth API real sudah tersedia. |
| Task 6–9 — Flutter MVP utama | **Backend ready** | Dashboard, appraisal read/write, profile, dan database notifications dapat diintegrasikan ke API real. |
| Task 10 — Transactional flow | **Backend pending** | Offer, revision, contract, market preview, payment, invoice, dan report belum memiliki route mobile. |

Detail endpoint aktual ada di `API-IMPLEMENTATION-STATUS.md`. Jangan menilai progress dari banyaknya dokumen atau UI mockup.

## Urutan kerja yang benar

Jangan mulai dari Flutter UI sebelum Auth API dan Appraisal read API minimal tersedia.

## Task 0 — Audit repo dan kontrak

Output:

- Branch terbaru diaudit.
- Endpoint web customer dan service terkait dicatat.
- API contract final untuk phase berjalan dikunci.
- Decision blocker dicatat: Firebase, deep link, Peruri, Midtrans.

DoD:

- Tidak ada asumsi endpoint liar.
- Tidak ada fitur mobile yang mulai tanpa kontrak data.

## Task 1 — Backend API Foundation

Status: **Complete** untuk foundation awal.

Output:

- Sanctum token auth berjalan.
- `routes/api.php` aktif.
- Prefix `/api/v1` tersedia.
- Middleware customer API berjalan.
- Force JSON untuk API error.
- Smoke endpoint protected tersedia.
- Pest test foundation pass.

## Task 2 — Auth API

Status: **Complete**.

Output:

- Register.
- Login.
- 2FA challenge.
- Forgot/reset password.
- Email verification/resend.
- Me.
- Logout.

DoD:

- Token diterbitkan untuk customer valid.
- 2FA tidak menerbitkan access token sebelum verify.
- Non-customer ditolak.
- Unverified user ditangani.
- Tests pass.

## Task 3 — Customer Read API

Status: **Complete** untuk scope read.

Output:

- Dashboard.
- Appraisal list.
- Appraisal detail.
- Tracking.
- Profile read.
- Notifications list.

DoD:

- Ownership aman.
- Response tidak berisi URL Inertia sebagai navigasi utama.
- JsonResource dipakai.
- Tests pass.

## Task 4 — Flutter Foundation

Output:

- Repo Flutter dibuat.
- Theme token dibuat.
- App shell dibuat.
- Routing `go_router` dibuat.
- API client Dio dibuat.
- Secure token storage dibuat.
- Error parser Laravel dibuat.
- Global 401 handling dibuat.

DoD:

- `flutter analyze` pass.
- Smoke test/widget test minimal tersedia.
- UI foundation tidak generik AI.

## Task 5 — Auth Flutter

Output:

- Login.
- Register.
- 2FA.
- Email verification state.
- Forgot/reset password.
- Logout.

DoD:

- Token tersimpan di secure storage.
- Validation 422 tampil per field.
- 401 global redirect login.
- Keyboard/safe area benar.

## Task 6 — Dashboard Flutter

Output:

- Dashboard real API.
- Action center.
- Stats.
- Recent appraisals.
- Notification action.

DoD:

- Loading/refreshing/empty/error/offline handled.
- Tidak seperti AI dashboard generic.
- CTA utama jelas.

## Task 7 — Appraisal List/Detail/Tracking

Output:

- List + search + filter.
- Detail.
- Timeline tracking.
- Pull-to-refresh.
- Pagination/load more.

DoD:

- Tidak ada tabel desktop.
- Status label + tone benar.
- Ownership error handled.

## Task 8 — Create Appraisal dan Upload

Status backend: **Complete**. Status Flutter: **Pending**.

Output:

- Step flow.
- Multi-aset.
- Location picker.
- Camera/gallery/file picker.
- Draft lokal.
- Upload progress/retry.
- Submit final.

DoD:

- Input tidak hilang saat kembali step.
- 422 tampil per field.
- Upload gagal bisa retry.
- Draft tidak hilang saat app ditutup.

## Task 9 — Profile dan Notifications

Status backend: **Complete** untuk profile, database notifications, dan registry device token. Push delivery provider masih **Pending**. Status Flutter: **Pending**.

Output:

- Profile edit.
- Billing data.
- Avatar upload/remove.
- Password change.
- Notifications list.
- Device token registration.

DoD:

- Permission notification tidak diminta prematur.
- Avatar upload punya progress/error.
- Mark read berjalan.

## Task 10 — Transactional Flows Setelah MVP

Status backend dan Flutter: **Pending**. Urutan backend wajib: offer → cancellation/revision → contract → market preview → payment/invoice → reports → push delivery.

Output:

- Offer accept/negotiate/select.
- Revision submit.
- Contract onboarding Peruri.
- Contract sign.
- Payment session.
- Invoice/report PDF.

DoD:

- Peruri customer-safe.
- Payment status backend source of truth.
- PDF viewer/test link bekerja.

## Milestone A — Backend API Foundation

Selesai jika:

- Sanctum token auth berjalan.
- `/api/v1/auth/*` tersedia.
- `/api/v1/dashboard` tersedia.
- `/api/v1/appraisals` list/detail/tracking tersedia.
- `/api/v1/profile` tersedia.
- Tests Auth API dan Customer API utama pass.

## Milestone B — Mobile Foundation

Selesai jika:

- Flutter app bisa login ke API real.
- Token tersimpan di secure storage.
- 401 global handling berjalan.
- Dashboard real data.
- Appraisal list/detail/tracking real data.
- Logout revoke token.
- `flutter analyze` dan `flutter test` pass.

## Milestone C — Appraisal Create dan Upload

Selesai jika:

- User bisa membuat permohonan baru dari mobile.
- Multi-aset berjalan.
- Upload dokumen berjalan dengan multipart.
- Validasi Laravel 422 tampil per field.
- Draft lokal minimal ada.

## Milestone D — Profile dan Notifications

Selesai jika:

- Update profile dan billing.
- Upload/remove avatar.
- Device token FCM/APNs tersimpan.
- Notification list dan mark read berjalan.
- Push dasar untuk status appraisal bisa diterima.

## Milestone E — Transactional Flows

Selesai jika:

- Offer accept/negotiate/select.
- Revision submit.
- Peruri onboarding minimal parity dengan web.
- Contract sign.
- Payment session.
- Invoice/report PDF.

## Owner decisions pending

- Android MVP dulu atau Android+iOS bersama.
- Owner Firebase project dan credential.
- Domain universal link/app link production.
- Peruri SDK/mobile flow resmi.
- Payment WebView MVP atau native UI.
- API mobile hanya first-party atau akan dibuka untuk partner eksternal.
- Asset app icon/splash compact resmi.
- Space Grotesk di auth atau seluruh mobile Instrument Sans.
- Dark mode masuk roadmap atau tidak.
- Tablet navigation rail atau tetap bottom navigation.
