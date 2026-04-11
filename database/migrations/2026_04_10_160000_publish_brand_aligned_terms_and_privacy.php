<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('terms_documents')
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'updated_at' => $now,
            ]);

        DB::table('terms_documents')->insert([
            'title' => 'Ketentuan Layanan Aplikasi Permohonan Penilaian',
            'company' => 'DigiPro by KJPP HJAR',
            'version' => 'v1.2',
            'effective_since' => '2026-04-10',
            'content_html' => $this->termsContent(),
            'is_active' => true,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('privacy_policies')
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'updated_at' => $now,
            ]);

        DB::table('privacy_policies')->insert([
            'title' => 'Kebijakan Privasi',
            'company' => 'DigiPro by KJPP HJAR',
            'version' => 'v1.1',
            'effective_since' => '2026-04-10',
            'content_html' => $this->privacyContent(),
            'is_active' => true,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        $now = now();

        DB::table('terms_documents')
            ->where('version', 'v1.2')
            ->where('company', 'DigiPro by KJPP HJAR')
            ->delete();

        DB::table('terms_documents')
            ->where('version', 'v1.1')
            ->update([
                'is_active' => true,
                'updated_at' => $now,
            ]);

        DB::table('privacy_policies')
            ->where('version', 'v1.1')
            ->where('company', 'DigiPro by KJPP HJAR')
            ->delete();

        DB::table('privacy_policies')
            ->where('version', 'v1.0')
            ->update([
                'is_active' => true,
                'updated_at' => $now,
            ]);
    }

    private function termsContent(): string
    {
        return <<<'HTML'
<h3>A. Identitas Platform dan Penyedia Layanan</h3>
<p>DigiPro by KJPP HJAR adalah platform digital resmi yang digunakan dalam proses permohonan, pengumpulan data, komunikasi status, penawaran biaya, pembayaran, dan penyampaian output penilaian atau kajian pasar properti.</p>
<p>Platform ini dimiliki dan atau dioperasikan oleh KJPP Henricus Judi Adrianto dan Rekan (KJPP HJAR) sebagai kantor jasa penilai publik yang berlisensi sesuai ketentuan yang berlaku.</p>
<p>Dengan menggunakan platform ini, Pengguna memahami bahwa identitas produk yang tampil kepada publik adalah DigiPro by KJPP HJAR, sedangkan pihak penyedia jasa profesional, pelaksana penugasan, dan pihak kontraktual tetap KJPP HJAR kecuali dinyatakan lain secara tertulis.</p>

<h3>B. Dasar Hukum dan Prinsip Umum</h3>
<p>Ketentuan ini tunduk pada hukum Republik Indonesia, termasuk prinsip sahnya perjanjian dan kekuatan mengikat perjanjian dalam KUHPerdata, rezim Kontrak Elektronik dan Sistem Elektronik, serta ketentuan Informasi dan Dokumen Elektronik yang berlaku.</p>
<p>Para pihak mengakui bahwa persetujuan, unggahan dokumen, histori aktivitas, timestamp, log sistem, dan tindakan elektronik lain yang dilakukan melalui DigiPro by KJPP HJAR dapat menimbulkan akibat hukum dan dapat dipergunakan sebagai alat bukti yang sah sepanjang diperbolehkan oleh peraturan perundang-undangan.</p>

<h3>C. Definisi</h3>
<ul>
  <li><strong>KJPP</strong>: KJPP Henricus Judi Adrianto dan Rekan (KJPP HJAR), termasuk pengurus, penilai publik, reviewer, admin, karyawan, atau pihak yang ditunjuk secara sah.</li>
  <li><strong>Platform</strong>: DigiPro by KJPP HJAR sebagai sistem elektronik resmi yang dipakai dalam proses layanan.</li>
  <li><strong>Pengguna atau Klien</strong>: pihak yang menggunakan Platform, baik untuk dirinya sendiri maupun mewakili perorangan, badan usaha, lembaga, atau pihak lain yang sah.</li>
  <li><strong>Permohonan</strong>: pengajuan layanan yang dibuat Pengguna melalui Platform.</li>
  <li><strong>Objek Penilaian</strong>: aset atau properti yang dimohonkan untuk dinilai atau dikaji.</li>
  <li><strong>Data dan Dokumen</strong>: seluruh data, foto, file, legalitas, informasi objek, informasi pihak terkait, dan keterangan lain yang diinput atau diunggah Pengguna.</li>
  <li><strong>Kontrak Elektronik</strong>: perjanjian atau persetujuan yang terbentuk melalui tindakan elektronik pada Platform.</li>
  <li><strong>SPK atau Perjanjian Penugasan</strong>: dokumen penugasan resmi yang mengatur ruang lingkup, deliverables, biaya, asumsi, pembatasan, dan ketentuan pelaksanaan layanan.</li>
  <li><strong>Tanpa Inspeksi Lapangan</strong>: metode penugasan desk atau off-site tanpa kunjungan fisik ke lokasi objek, sehingga analisis disusun berdasarkan Data dan Dokumen serta sumber pendukung lain yang dinilai layak.</li>
</ul>

<h3>D. Ruang Lingkup Platform dan Layanan</h3>
<p>DigiPro by KJPP HJAR berfungsi sebagai platform digital untuk memfasilitasi pembuatan Permohonan, pengisian dan pengunggahan Data dan Dokumen, review administratif, penawaran atau negosiasi biaya, pembayaran, pengelolaan status, komunikasi proses, dan penyampaian output sesuai SPK.</p>
<p>Platform bukan jaminan persetujuan kredit, pembiayaan, investasi, atau transaksi tertentu. Output yang diterbitkan melalui Platform hanya berlaku sesuai tujuan penugasan yang disetujui dalam SPK atau dokumen penugasan terkait.</p>

<h3>E. Akun, Kewenangan, dan Representasi Pengguna</h3>
<p>Pengguna menjamin bahwa identitas, status, kewenangan bertindak, dan kewenangan mewakili pihak lain adalah benar, sah, dan dapat dipertanggungjawabkan.</p>
<p>Pengguna bertanggung jawab penuh atas keamanan akun, kerahasiaan kredensial, serta seluruh aktivitas yang dilakukan melalui akun tersebut.</p>

<h3>F. Kewajiban Pengguna atas Data dan Dokumen</h3>
<p>Pengguna menyatakan dan menjamin bahwa seluruh Data dan Dokumen yang disampaikan melalui DigiPro by KJPP HJAR adalah benar, akurat, terkini, lengkap, sah, dan tidak dimanipulasi.</p>
<p>Pengguna dilarang mengunggah atau menyampaikan dokumen palsu, data menyesatkan, atau keterangan yang tidak sesuai fakta.</p>
<p>Pengguna wajib memperbarui data bila terdapat perubahan material yang dapat memengaruhi hasil penugasan, termasuk aspek legal, fisik, penguasaan, peruntukan, penghunian, atau kondisi pasar tertentu yang diketahui Pengguna.</p>

<h3>G. Review, Penawaran, dan Pembayaran</h3>
<p>Setelah Pengguna menyetujui ketentuan yang berlaku dan mengirim Permohonan, data akan diproses sesuai alur internal KJPP HJAR melalui Platform.</p>
<p>Admin atau pihak yang berwenang berhak meminta kelengkapan atau klarifikasi sebelum penugasan dilanjutkan.</p>
<p>Penawaran biaya, negosiasi, pembayaran, dan status lanjutan mengikuti mekanisme pada Platform serta otorisasi internal KJPP HJAR.</p>

<h3>H. Tanpa Inspeksi Lapangan dan Pembatasan Reliance</h3>
<p>Apabila penugasan dilakukan tanpa inspeksi lapangan, Pengguna memahami dan menyetujui bahwa KJPP HJAR tidak melakukan verifikasi fisik langsung atas kondisi aktual objek, batas lapangan, kualitas material, kerusakan tersembunyi, ataupun faktor lapangan lain yang memerlukan kunjungan fisik.</p>
<p>Dalam metode ini, analisis dan output sangat bergantung pada kualitas Data dan Dokumen yang disediakan Pengguna serta sumber pendukung lain yang dianggap layak.</p>
<p>Jika kemudian ditemukan perbedaan material antara kondisi aktual dengan Data dan Dokumen yang diserahkan, Pengguna menerima bahwa hasil dapat direvisi, ditarik, dibatalkan, atau dinyatakan tidak lagi relevan.</p>

<h3>I. Verifikasi Tambahan, Penolakan, dan Pembatalan</h3>
<p>KJPP HJAR berhak melakukan verifikasi tambahan, meminta dokumen asli, meminta klarifikasi, melakukan validasi pihak ketiga, atau meminta inspeksi lapangan jika dipandang perlu secara profesional.</p>
<p>KJPP HJAR berhak menolak, menunda, membatalkan, atau menghentikan penugasan apabila terdapat data yang tidak lengkap, tidak dapat diverifikasi secara wajar, terdapat dugaan pemalsuan atau manipulasi, ada konflik kepentingan, tujuan penggunaan tidak sesuai, atau kewajiban pembayaran tidak dipenuhi.</p>

<h3>J. Penggunaan Output dan Pihak Ketiga</h3>
<p>Output hanya boleh digunakan sesuai tujuan penugasan dalam SPK atau dokumen penugasan yang berlaku.</p>
<p>Dilarang mengubah isi, memotong konteks, menghapus disclaimer, menghapus asumsi, atau menggunakan output di luar tujuan yang disetujui tanpa persetujuan tertulis dari KJPP HJAR.</p>
<p>KJPP HJAR tidak bertanggung jawab atas penggunaan oleh pihak ketiga di luar lingkup yang disetujui.</p>

<h3>K. Kekayaan Intelektual</h3>
<p>Platform, template, format dokumen, metodologi, struktur proses, materi, dan konten yang disediakan melalui DigiPro by KJPP HJAR merupakan milik KJPP HJAR atau pihak yang secara sah memberikan hak penggunaannya.</p>

<h3>L. Kerahasiaan dan Pengelolaan Data</h3>
<p>KJPP HJAR melakukan upaya yang wajar untuk menjaga kerahasiaan, integritas, dan keamanan Informasi atau Dokumen Elektronik yang diproses melalui Platform.</p>
<p>Pengguna memberi izin pemrosesan data untuk keperluan pelaksanaan penugasan, audit internal, kepatuhan, peningkatan layanan, dan kebutuhan hukum yang sah.</p>

<h3>M. Persetujuan Elektronik dan Log Sistem</h3>
<p>Pengguna mengakui bahwa log aktivitas, histori unggahan, histori persetujuan, histori penawaran atau negosiasi, bukti pembayaran, dan timestamp sistem dapat digunakan sebagai bukti pelaksanaan proses melalui Platform.</p>

<h3>N. Batasan Tanggung Jawab</h3>
<p>Sepanjang diizinkan hukum, tanggung jawab KJPP HJAR dibatasi secara wajar sesuai nilai jasa yang telah dibayar untuk penugasan terkait dan tidak mencakup kerugian tidak langsung, kerugian lanjutan, atau kerugian akibat penggunaan output di luar lingkup yang disetujui.</p>

<h3>O. Perubahan Ketentuan</h3>
<p>KJPP HJAR dapat memperbarui Syarat dan Ketentuan ini dari waktu ke waktu. Versi yang mengikat adalah versi yang disetujui Pengguna pada saat Permohonan dibuat, kecuali ditentukan lain secara tertulis.</p>

<h3>P. Hukum yang Berlaku dan Sengketa</h3>
<p>Syarat dan Ketentuan ini tunduk pada hukum Republik Indonesia. Sengketa diselesaikan terlebih dahulu melalui musyawarah. Jika tidak tercapai penyelesaian, sengketa diselesaikan melalui forum yang ditentukan dalam SPK atau forum lain yang sah menurut hukum.</p>

<h3>Q. Pernyataan Persetujuan</h3>
<p>Dengan menekan tombol persetujuan, mengirim Permohonan, mengunggah Data dan Dokumen, atau melanjutkan proses pada DigiPro by KJPP HJAR, Pengguna menyatakan telah membaca, memahami, dan menyetujui ketentuan ini sebagai perjanjian yang sah dan mengikat.</p>
HTML;
    }

    private function privacyContent(): string
    {
        return <<<'HTML'
<h3>1. Identitas Platform</h3>
<p>DigiPro by KJPP HJAR adalah platform digital resmi yang dimiliki dan atau dioperasikan oleh KJPP Henricus Judi Adrianto dan Rekan (KJPP HJAR) untuk mendukung proses layanan penilaian atau kajian pasar properti.</p>
<p>Kebijakan ini menjelaskan bagaimana data pribadi dan data penugasan diproses saat Anda menggunakan platform tersebut.</p>

<h3>2. Jenis Data yang Dikumpulkan</h3>
<p>Kami dapat mengumpulkan dan memproses data berikut:</p>
<ul>
  <li><strong>Data Identitas:</strong> nama, email, nomor telepon, informasi akun, dan data perwakilan.</li>
  <li><strong>Data Kontak dan Penagihan:</strong> alamat, informasi perusahaan, NPWP, dan data administratif terkait transaksi.</li>
  <li><strong>Data Objek dan Dokumen:</strong> foto properti, sertifikat, PBB, IMB atau PBG, dokumen legal, peta, koordinat, dan informasi objek penilaian.</li>
  <li><strong>Data Transaksi:</strong> detail pembayaran, invoice, bukti pembayaran, dan status transaksi.</li>
  <li><strong>Data Aktivitas Sistem:</strong> log aktivitas, histori persetujuan, histori unggahan, dan metadata penggunaan platform.</li>
</ul>

<h3>3. Tujuan Penggunaan Data</h3>
<p>Data digunakan untuk:</p>
<ul>
  <li>memproses Permohonan dan melaksanakan penugasan;</li>
  <li>melakukan verifikasi administratif dan review internal;</li>
  <li>menyusun dokumen, laporan, invoice, notifikasi, dan komunikasi status;</li>
  <li>memenuhi kewajiban kepatuhan, audit, pengelolaan risiko, dan keamanan sistem;</li>
  <li>meningkatkan kualitas layanan dan pengalaman pengguna.</li>
</ul>

<h3>4. Dasar Pemrosesan dan Tanggung Jawab</h3>
<p>Pemrosesan dilakukan sejauh diperlukan untuk pelaksanaan hubungan layanan, pemenuhan kewajiban hukum, kepentingan sah operasional, dan persetujuan yang diberikan melalui Platform.</p>
<p>Untuk keperluan layanan profesional yang diberikan melalui DigiPro by KJPP HJAR, KJPP HJAR bertindak sebagai pihak yang bertanggung jawab atas pemrosesan data dalam lingkup pelaksanaan penugasan.</p>

<h3>5. Pengungkapan Data</h3>
<p>Data hanya dibagikan kepada pihak yang memang memerlukan akses untuk pelaksanaan layanan, seperti admin internal, reviewer, penilai publik, penyedia infrastruktur teknologi, penyedia pembayaran, atau pihak lain yang relevan secara sah.</p>
<p>Kami tidak menjual data pribadi Pengguna. Pengungkapan kepada pihak ketiga dilakukan sebatas kebutuhan operasional, kepatuhan, atau kewajiban hukum yang berlaku.</p>

<h3>6. Keamanan Data</h3>
<p>Kami menerapkan langkah keamanan teknis dan organisatoris yang wajar untuk mencegah akses tanpa izin, perubahan tidak sah, kehilangan data, atau pengungkapan yang tidak semestinya.</p>
<p>Akses ke data dibatasi berdasarkan fungsi dan kewenangan.</p>

<h3>7. Retensi Data</h3>
<p>Data disimpan selama diperlukan untuk pelaksanaan layanan, kebutuhan administrasi, audit, kepatuhan hukum, penyelesaian sengketa, atau tujuan sah lain yang relevan.</p>
<p>Setelah masa retensi berakhir dan data tidak lagi diperlukan, data dapat dihapus, dianonimkan, atau dibatasi pemrosesannya sesuai kebijakan internal dan ketentuan yang berlaku.</p>

<h3>8. Hak Pengguna</h3>
<p>Sepanjang dimungkinkan oleh hukum, Pengguna dapat mengajukan permintaan akses, koreksi, pembaruan, atau penghapusan data, serta meminta informasi mengenai pemrosesan data yang dilakukan melalui platform.</p>

<h3>9. Cookies, Log, dan Data Teknis</h3>
<p>Platform dapat menggunakan data teknis, log aktivitas, session, dan mekanisme serupa untuk keamanan, autentikasi, kestabilan sistem, analitik operasional, dan peningkatan layanan.</p>

<h3>10. Perubahan Kebijakan</h3>
<p>Kebijakan Privasi ini dapat diperbarui dari waktu ke waktu. Versi terbaru akan ditampilkan pada platform beserta tanggal efektifnya.</p>

<h3>11. Kontak</h3>
<p>Untuk pertanyaan mengenai privasi, pemrosesan data, atau permintaan terkait data Anda, silakan hubungi tim DigiPro by KJPP HJAR melalui kanal kontak resmi yang tersedia pada platform.</p>
HTML;
    }
};
