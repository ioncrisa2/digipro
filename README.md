# DigiPro by KJPP HJAR

Platform penilaian properti end-to-end untuk KJPP dan institusi finansial. Portal pelanggan (Inertia + Vue) menangani permohonan hingga pembayaran, sementara back-office Filament memudahkan tim internal mengelola appraisal, konten, dan master data. Panel reviewer terpisah mendukung proses valuasi.

## Fitur Utama
- **Portal pelanggan**: registrasi & verifikasi email (Laravel Fortify), dashboard status, permohonan penilaian multi-aset, unggah dokumen, mock laporan, notifikasi in-app.
- **Negosiasi penawaran & kontrak**: terima/tolak/nego (maks 3 putaran), opsi fee, riwayat negosiasi, tanda tangan kontrak, unduh kontrak PDF, pembatalan permohonan.
- **Pembayaran & invoice**: penomoran kontrak/invoice, unduh PDF, unggah bukti transfer, verifikasi admin, referensi rekening kantor.
- **Konten publik**: landing page, artikel/blog, FAQ, testimoni, fitur produk, kebijakan privasi & syarat ketentuan, formulir kontak (throttle).
- **Back-office Filament (/admin)**: dashboard KPI, manajemen permohonan & aset, penawaran, pembayaran, consent documents, CMS artikel & kategori/tag, FAQ/feature/testimonial, terms/privacy, contact messages, rekening kantor, master lokasi (provinsi–desa), ref guidelines, pengguna & role/permission (Filament Shield).
- **Panel reviewer (/reviewer)**: guard khusus `reviewer` untuk appraisal review dan comparable data.

## Teknologi
- Laravel 12 (PHP 8.2+), Composer 2
- Inertia + Vue 3, Vite, Tailwind CSS 4, Pinia, Ziggy
- Filament 3, Filament Shield, Filament Breezy
- Queue & session: database driver
- PDF (barryvdh/laravel-dompdf), Excel (maatwebsite/excel)
- Lokasi Indonesia sinkron API EMSIFA via perintah artisan

## Prasyarat
- PHP ≥ 8.2 dengan ekstensi pdo_mysql / sqlite & fileinfo
- Composer 2.x
- Node.js ≥ 20 & npm
- Database MySQL 8+ (atau SQLite untuk percobaan cepat)
- Akses internet sekali untuk sinkronisasi data wilayah

## Setup Lokal (Windows/Unix)
1) Salin env: `cp .env.example .env` (Windows: `copy .env.example .env`) lalu set `APP_NAME="DigiPro by KJPP HJAR"`, `APP_URL`, kredensial DB & mail.
2) Pasang dependensi PHP: `composer install`
3) Generate key: `php artisan key:generate`
4) Migrasi & seeder dasar: `php artisan migrate --seed`
5) Isi master lokasi Indonesia: `php artisan app:sync-data-wilayah`
6) Buat symlink storage: `php artisan storage:link`
7) Pasang dependensi frontend: `npm install`
8) Jalankan mode dev:
   - Cepat: `composer run dev` (serve + queue:listen + Vite)
   - Manual: `php artisan serve`, `php artisan queue:work`, `npm run dev`

## Perintah Berguna
- Buat super admin Filament: `php artisan shield:super-admin email@domain.com`
- Bangun aset produksi: `npm run build` dan `npm run build:filament`
- Jalankan uji otomatis (Pest): `php artisan test`
- Sinkron lokasi ulang bila perlu: `php artisan app:sync-data-wilayah`

## Panel & Akses
- Portal pelanggan: `/` → `/dashboard` (butuh email terverifikasi).
- Admin panel: `/admin` (guard `web`, butuh role dari Filament Shield, contoh `super_admin` atau `panel_user`).
- Reviewer panel: `/reviewer` (guard `reviewer`).
- Role “customer” dipakai untuk pengguna portal; role dibuat/dikelola via menu Roles di Filament.

## Catatan Implementasi
- Penyimpanan file & bukti bayar memakai disk `local`; pastikan `storage:link` sudah dibuat jika perlu diakses publik.
- Queue menggunakan koneksi `database`; jalankan `queue:work` minimal satu proses pada environment non-dev.
- Template PDF kontrak & invoice ada di `resources/views/pdfs`.
- Command sinkronisasi lokasi menulis ribuan entri; jalankan sekali setelah migrasi atau saat ada pembaruan wilayah.

## Lisensi
MIT. Lihat berkas lisensi bawaan Laravel jika diperlukan.
