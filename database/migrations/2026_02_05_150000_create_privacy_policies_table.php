<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_policies', function (Blueprint $table) {
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

        DB::table('privacy_policies')->insert([
            'title' => 'Kebijakan Privasi',
            'company' => 'DigiPro',
            'version' => 'v1.0',
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
        Schema::dropIfExists('privacy_policies');
    }

    private function defaultContent(): string
    {
        return <<<HTML
<h3>1. Pendahuluan</h3>
<p>DigiPro menghormati privasi Anda dan berkomitmen melindungi data pribadi. Kebijakan ini menjelaskan bagaimana kami mengumpulkan, menggunakan, dan menyimpan data saat Anda menggunakan layanan kami.</p>

<h3>2. Data yang Dikumpulkan</h3>
<p>Kami dapat mengumpulkan data berikut:</p>
<ul>
  <li><strong>Data Identitas:</strong> nama, email, dan informasi akun.</li>
  <li><strong>Data Kontak:</strong> alamat korespondensi, nomor telepon, dan email.</li>
  <li><strong>Data Properti:</strong> foto, sertifikat, dokumen pajak, dan informasi objek penilaian.</li>
  <li><strong>Data Transaksi:</strong> detail pembayaran dan bukti transaksi.</li>
</ul>

<h3>3. Penggunaan Data</h3>
<p>Data digunakan untuk:</p>
<ul>
  <li>Memproses permohonan penilaian dan menyusun laporan.</li>
  <li>Memberikan pembaruan status, notifikasi, dan layanan pelanggan.</li>
  <li>Meningkatkan kualitas layanan, keamanan, dan kepatuhan.</li>
</ul>

<h3>4. Keamanan Data</h3>
<p>Kami menerapkan langkah keamanan yang wajar untuk melindungi data dari akses tidak sah, perubahan, atau pengungkapan. Akses data dibatasi hanya untuk pihak yang memerlukan.</p>

<h3>5. Penyimpanan dan Retensi</h3>
<p>Data disimpan selama diperlukan untuk tujuan layanan, kepatuhan hukum, dan audit. Setelah tidak diperlukan, data dapat dihapus sesuai kebijakan retensi.</p>

<h3>6. Hak Pengguna</h3>
<p>Anda dapat meminta akses, koreksi, atau penghapusan data sesuai peraturan yang berlaku. Silakan hubungi tim kami untuk permintaan tersebut.</p>

<h3>7. Perubahan Kebijakan</h3>
<p>Kami dapat memperbarui kebijakan ini dari waktu ke waktu. Versi terbaru akan tersedia di aplikasi dan berlaku sejak tanggal efektif.</p>
HTML;
    }
};