<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('company')->nullable();
            $table->string('version')->nullable();
            $table->date('effective_since')->nullable();
            $table->longText('content_html');
            $table->boolean('is_active')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        DB::table('terms_documents')->insert([
            'title' => 'Ketentuan Layanan Aplikasi Permohonan Penilaian',
            'company' => 'KJPP Henricus Judi Adrianto dan Rekan (KJPP HJAR)',
            'version' => 'v1.1',
            'effective_since' => '2026-02-01',
            'content_html' => $this->defaultContent(),
            'is_active' => true,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('terms_documents');
    }

    private function defaultContent(): string
    {
        return <<<HTML
<h3>A. Dasar Hukum dan Prinsip Umum</h3>
<p>Ketentuan ini tunduk pada hukum Republik Indonesia, termasuk prinsip sahnya perjanjian dan kekuatan mengikat perjanjian dalam KUHPerdata (antara lain konsep syarat sah perjanjian dan asas pacta sunt servanda).</p>
<p>Para pihak sepakat bahwa persetujuan yang diberikan melalui aplikasi dapat membentuk kontrak elektronik sebagaimana diakui dalam rezim PSTE (PP 71/2019).</p>
<p>Para pihak mengakui Informasi atau Dokumen Elektronik dan atau hasil cetaknya sebagai alat bukti hukum yang sah sesuai ketentuan UU ITE yang telah diubah terakhir dengan UU No. 1 Tahun 2024, khususnya ketentuan Pasal 5.</p>
<p>Pengguna memahami bahwa penyampaian dokumen palsu atau keterangan palsu dapat menimbulkan konsekuensi hukum, termasuk rezim tindak pidana dalam KUHP Nasional (UU No. 1 Tahun 2023) yang diberlakukan mulai 2 Januari 2026.</p>

<h3>B. Definisi</h3>
<ul>
  <li>KJPP: KJPP Henricus Judi Adrianto dan Rekan (KJPP HJAR), termasuk pengurus, karyawan, admin, surveyor, reviewer, dan pihak yang ditunjuk sah.</li>
  <li>Pengguna atau Klien: pihak yang menggunakan aplikasi, termasuk perwakilan badan usaha atau pihak yang diberi kuasa.</li>
  <li>Aplikasi atau Sistem Elektronik: sistem yang disediakan untuk pengajuan permohonan, pengunggahan data, penawaran atau negosiasi biaya, komunikasi status, dan penyampaian output.</li>
  <li>Permohonan: pengajuan penilaian yang dibuat Pengguna melalui aplikasi.</li>
  <li>Objek Penilaian: aset atau properti yang dimohonkan untuk dinilai.</li>
  <li>Data dan Dokumen: seluruh data, dokumen legal, foto, koordinat, dan informasi lain yang diunggah atau diinput Pengguna.</li>
  <li>Kontrak Elektronik: perjanjian para pihak yang dibuat melalui Sistem Elektronik.</li>
  <li>SPK atau Perjanjian Penugasan: dokumen penugasan resmi yang mengatur ruang lingkup, deliverables, biaya, dan ketentuan lain.</li>
  <li>Tanpa Inspeksi Lapangan (Desk atau Off-site): pelaksanaan tanpa kunjungan fisik, sehingga opini nilai disusun berdasarkan Data dan Dokumen serta sumber pendukung lain yang wajar.</li>
</ul>

<h3>C. Ruang Lingkup Layanan</h3>
<p>Aplikasi memfasilitasi pembuatan Permohonan, pengisian Data dan Dokumen, review admin, penawaran atau negosiasi biaya (jika diaktifkan), pembayaran (jika ada), pengelolaan status, serta penyampaian output sesuai SPK.</p>
<p>Aplikasi bukan jaminan persetujuan kredit atau finansial dan bukan alat untuk memastikan nilai. Output hanya sesuai tujuan penugasan dalam SPK.</p>

<h3>D. Akun, Kewenangan, dan Representasi Pengguna</h3>
<p>Pengguna menjamin identitas, status, dan kewenangan bertindak (termasuk kewenangan mewakili badan usaha) adalah benar.</p>
<p>Pengguna bertanggung jawab atas keamanan akun dan seluruh aktivitas yang terjadi pada akunnya.</p>

<h3>E. Kewajiban Pengguna atas Data dan Dokumen (Klausul Pengunci Risiko)</h3>
<p>Pengguna menyatakan dan menjamin seluruh Data dan Dokumen adalah benar, akurat, terkini, lengkap, sah, dan tidak dimanipulasi.</p>
<p>Pengguna dilarang mengunggah atau menyampaikan dokumen palsu, data menyesatkan, atau keterangan yang tidak sesuai fakta.</p>
<p>Pengguna wajib segera memperbarui data bila terdapat perubahan material (status legal, kondisi, peruntukan, penguasaan, penghunian, dan sebagainya).</p>
<p>Pengguna memahami bahwa dalam metode Tanpa Inspeksi Lapangan, opini nilai sangat bergantung pada Data dan Dokumen yang disediakan Pengguna.</p>

<h3>F. Mekanisme Review, Penawaran Biaya, dan Negosiasi</h3>
<p>Setelah Pengguna menyetujui Syarat dan Ketentuan dan mengirim Permohonan, Permohonan terekam dalam sistem sesuai alur (misal Draft atau Submitted).</p>
<p>Admin berhak meminta kelengkapan atau klarifikasi sebelum proses dilanjutkan.</p>
<p>Penawaran biaya disampaikan melalui aplikasi. Jika negosiasi diaktifkan, keputusan final mengikuti kewenangan atau otorisasi internal KJPP.</p>

<h3>G. Pembayaran, Pajak, dan Penahanan Output</h3>
<p>Pembayaran mengikuti penawaran final atau SPK.</p>
<p>KJPP berhak menahan output sampai kewajiban pembayaran terpenuhi, sepanjang diizinkan SPK dan peraturan yang berlaku.</p>

<h3>H. Tanpa Inspeksi Lapangan dan Batasan Tanggung Jawab (Core Disclaimer)</h3>
<p>Bila penugasan dilakukan Tanpa Inspeksi Lapangan, Pengguna setuju bahwa KJPP:</p>
<ul>
  <li>tidak melakukan verifikasi fisik kondisi aktual;</li>
  <li>tidak memverifikasi batas lapangan, kualitas material aktual, kerusakan tersembunyi, atau faktor lapangan lain yang memerlukan kunjungan fisik;</li>
  <li>menyusun opini nilai berdasarkan Data dan Dokumen Pengguna serta sumber pendukung yang wajar.</li>
</ul>
<p>Jika kemudian ditemukan perbedaan material antara kondisi aktual dan Data dan Dokumen, Pengguna menerima bahwa:</p>
<ul>
  <li>opini nilai dapat berubah;</li>
  <li>laporan dapat direvisi, ditarik, atau dibatalkan;</li>
  <li>risiko atau akibat penggunaan laporan karena data tidak benar berada pada Pengguna.</li>
</ul>

<h3>I. Keaslian Dokumen, Verifikasi Tambahan, dan Konsekuensi</h3>
<p>KJPP berhak melakukan verifikasi tambahan (meminta dokumen asli, klarifikasi, validasi pihak ketiga, atau inspeksi lapangan) bila diperlukan secara profesional.</p>
<p>Jika ada indikasi pemalsuan, manipulasi, atau keterangan palsu, KJPP berhak menangguhkan proses, menolak Permohonan, membatalkan output, dan atau menghentikan hubungan kontraktual.</p>
<p>Pengguna memahami perbuatan pemalsuan atau keterangan palsu berpotensi menimbulkan konsekuensi hukum dalam rezim pidana yang berlaku.</p>

<h3>J. Penolakan, Penundaan, dan Pembatalan</h3>
<p>KJPP berhak menolak, menunda, atau membatalkan Permohonan apabila:</p>
<ul>
  <li>Data dan Dokumen tidak lengkap atau tidak dapat diverifikasi secara wajar;</li>
  <li>ada dugaan pemalsuan, manipulasi, atau menyesatkan;</li>
  <li>ada konflik kepentingan atau risiko kepatuhan;</li>
  <li>kewajiban pembayaran tidak dipenuhi;</li>
  <li>objek atau tujuan tidak sesuai kebijakan profesi, internal, atau hukum.</li>
</ul>

<h3>K. Penggunaan Output untuk Pihak Ketiga</h3>
<p>Output digunakan untuk tujuan sesuai SPK.</p>
<p>Dilarang mengubah isi, memotong konteks, menghapus disclaimer atau asumsi, atau menggunakan di luar tujuan tanpa persetujuan tertulis KJPP.</p>
<p>Pelanggaran menjadi tanggung jawab Pengguna sepenuhnya.</p>

<h3>L. Kekayaan Intelektual</h3>
<p>Template, format, metodologi, dan materi KJPP adalah hak KJPP. Pengguna hanya berhak menggunakan output untuk tujuan penugasan.</p>

<h3>M. Kerahasiaan dan Pengelolaan Data</h3>
<p>KJPP melakukan upaya wajar menjaga kerahasiaan dan integritas Informasi atau Dokumen Elektronik sesuai ketentuan PSTE.</p>
<p>Pengguna memberi izin pemrosesan data untuk keperluan pelaksanaan penugasan, audit internal, dan pemenuhan kewajiban kepatuhan.</p>

<h3>N. Pembuktian, Log Sistem, dan Persetujuan Elektronik</h3>
<p>Pengguna mengakui rekaman sistem (log aktivitas, timestamp persetujuan, histori unggahan, histori penawaran atau negosiasi, bukti pembayaran) dapat digunakan sebagai alat bukti.</p>
<p>Tindakan elektronik Pengguna (klik Setuju, unggah dokumen, setujui penawaran, dan sebagainya) dapat membentuk Kontrak Elektronik yang mengikat.</p>

<h3>O. Force Majeure</h3>
<p>Kejadian di luar kendali wajar para pihak dapat menunda pelaksanaan tanpa dianggap wanprestasi.</p>

<h3>P. Batasan Ganti Rugi</h3>
<p>Sepanjang diizinkan hukum, tanggung jawab KJPP dibatasi secara wajar (misal setinggi-tingginya sebesar nilai jasa yang telah dibayar untuk penugasan terkait) dan tidak mencakup kerugian tidak langsung.</p>

<h3>Q. Perubahan Ketentuan</h3>
<p>KJPP dapat memperbarui Syarat dan Ketentuan. Versi yang berlaku adalah versi yang disetujui Pengguna pada saat Permohonan dibuat, kecuali disepakati lain.</p>

<h3>R. Hukum yang Berlaku dan Sengketa</h3>
<p>Syarat dan Ketentuan tunduk pada hukum Republik Indonesia. Sengketa diselesaikan musyawarah. Jika gagal melalui Pengadilan Negeri setempat atau forum lain yang disepakati dalam SPK.</p>

<h3>S. Pernyataan Persetujuan</h3>
<p>Dengan menekan Saya Setuju atau Setuju dan Lanjutkan, Pengguna menyatakan memahami dan menyetujui Syarat dan Ketentuan ini sebagai perjanjian yang sah dan mengikat.</p>
HTML;
    }
};
