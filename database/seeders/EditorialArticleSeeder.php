<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class EditorialArticleSeeder extends Seeder
{
    public function run(): void
    {
        $this->updateArticle(
            slug: 'ai-big-data-penilaian-properti-2026',
            categorySlug: 'market-insight',
            tagSlugs: ['appraisal', 'valuation', 'properti', 'data', 'market'],
            attributes: [
                'title' => 'Membaca Data Pembanding untuk Review Appraisal Properti',
                'excerpt' => 'Data pembanding tidak cukup dibaca dari harga per meter persegi. Penilai dan analis kredit perlu melihat sumber transaksi, kondisi objek, waktu data, serta penyesuaian yang dapat dipertanggungjawabkan.',
                'content_html' => <<<'HTML'
<p>Dalam pekerjaan appraisal, data pembanding sering menjadi titik awal diskusi antara KJPP, pemberi tugas, analis kredit, dan investor. Masalahnya, data pembanding yang terlihat rapi di tabel belum tentu layak dipakai begitu saja. Angka transaksi, listing, atau indikasi pasar perlu dibaca bersama konteks lokasi, kondisi fisik, waktu data, status legal, dan motivasi transaksi.</p>

<p>Catatan ini disusun sebagai panduan editorial KJPP HJAR untuk membaca data pembanding secara lebih disiplin. Tujuannya bukan menggantikan pertimbangan profesional penilai, melainkan membantu pembaca memahami mengapa review appraisal memerlukan dokumentasi data yang jelas.</p>

<h2>Ringkasan untuk pembaca profesional</h2>
<p>Data pembanding yang kuat biasanya memiliki tiga kualitas: sumbernya dapat dijelaskan, karakteristiknya cukup dekat dengan objek penilaian, dan penyesuaiannya bisa ditelusuri. Jika salah satu kualitas ini lemah, opini nilai masih dapat disusun, tetapi tingkat keyakinannya perlu dijelaskan secara terbuka.</p>

<blockquote>Dalam konteks agunan, pertanyaan utama bukan hanya berapa indikasi nilainya, tetapi seberapa dapat dipertanggungjawabkan data yang membentuk indikasi tersebut.</blockquote>

<h2>Mengapa data pembanding tidak bisa dibaca mentah</h2>
<p>Dua properti pada kawasan yang sama dapat memiliki harga yang berbeda karena lebar jalan, bentuk tanah, orientasi, akses, kondisi bangunan, status penguasaan, dan urgensi penjual. Karena itu, pembacaan harga per meter persegi tanpa penyesuaian sering menghasilkan kesimpulan yang terlalu cepat.</p>

<h3>Transaksi, listing, dan indikasi pasar bukan hal yang sama</h3>
<p>Transaksi menunjukkan harga yang telah terjadi. Listing menunjukkan ekspektasi penjual. Indikasi dari broker atau pelaku pasar memberi konteks, tetapi tetap perlu diverifikasi. Dalam review appraisal, ketiga jenis data tersebut dapat dipakai, namun bobotnya tidak sama.</p>

<h3>Waktu data memengaruhi relevansi</h3>
<p>Data yang terlalu lama perlu dibaca dengan hati-hati, terutama pada kawasan yang sedang berubah karena infrastruktur, perubahan zonasi, atau pergeseran aktivitas komersial. Bank Indonesia dalam Survei Harga Properti Residensial Triwulan IV 2025 mencatat pertumbuhan harga residensial primer yang terbatas, sehingga perubahan pasar tidak selalu dapat diasumsikan agresif di semua segmen.</p>

<h2>Sumber data yang perlu dicatat dalam appraisal</h2>
<p>Review yang baik tidak hanya melihat angka akhir. Ia melihat jejak data. Untuk setiap pembanding, minimal perlu dicatat sumber informasi, tanggal informasi, status transaksi atau penawaran, karakteristik properti, dan alasan data tersebut dipilih.</p>

<table>
  <thead>
    <tr>
      <th>Elemen data</th>
      <th>Pertanyaan review</th>
      <th>Risiko jika lemah</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>Sumber informasi</td>
      <td>Apakah berasal dari transaksi, listing, broker, pemilik, atau database internal?</td>
      <td>Data sulit diverifikasi dan bobot pembanding turun.</td>
    </tr>
    <tr>
      <td>Kemiripan objek</td>
      <td>Apakah lokasi, luas, akses, legalitas, dan kondisi fisiknya sebanding?</td>
      <td>Penyesuaian menjadi terlalu besar atau spekulatif.</td>
    </tr>
    <tr>
      <td>Waktu data</td>
      <td>Apakah data masih relevan terhadap tanggal penilaian?</td>
      <td>Indikasi nilai tertinggal dari kondisi pasar saat ini.</td>
    </tr>
  </tbody>
</table>

<h2>Checklist review untuk KJPP dan institusi finansial</h2>
<h3>1. Pisahkan data kuat dan data pendukung</h3>
<p>Data transaksi yang terverifikasi seharusnya memiliki bobot lebih tinggi daripada listing. Namun listing tetap berguna untuk membaca ekspektasi pasar, terutama ketika transaksi aktual terbatas.</p>

<h3>2. Jelaskan penyesuaian secara proporsional</h3>
<p>Penyesuaian tidak perlu dibuat rumit, tetapi harus dapat dijelaskan. Bila pembanding memiliki akses lebih baik, luas lebih efisien, atau kondisi bangunan lebih baru, alasan penyesuaian perlu muncul dalam kertas kerja atau ringkasan review.</p>

<h3>3. Catat keterbatasan data</h3>
<p>Pasar properti tidak selalu menyediakan data yang lengkap. Keterbatasan tersebut bukan alasan untuk mengabaikan proses, melainkan alasan untuk menuliskan asumsi dan pembatasan dengan jelas.</p>

<h2>Implikasi bagi bank dan investor properti</h2>
<p>Bagi perbankan, kualitas data pembanding memengaruhi pembacaan risiko agunan. Bagi investor, data yang terlalu optimistis dapat membuat keputusan akuisisi atau pembiayaan kehilangan margin pengaman. Karena itu, laporan appraisal yang baik harus membantu pembaca melihat logika nilai, bukan hanya menerima angka akhir.</p>

<h2>Bagaimana DIGIPRO membantu dokumentasi data</h2>
<p>DIGIPRO by KJPP HJAR menempatkan permohonan, dokumen, status, dan catatan proses dalam satu alur kerja. Dengan cara ini, pihak yang terlibat dapat melihat data awal, kelengkapan dokumen, serta perkembangan review secara lebih tertata. Platform tidak menggantikan penilaian profesional, tetapi membantu prosesnya lebih terdokumentasi.</p>

<h2>Referensi</h2>
<ul>
  <li><a href="https://www.bi.go.id/id/publikasi/laporan/Pages/SHPR_Tw_IV_2025.aspx" target="_blank" rel="noopener noreferrer">Bank Indonesia, Survei Harga Properti Residensial di Pasar Primer Triwulan IV 2025</a>.</li>
  <li><a href="https://ivsc.org/new-edition-of-the-international-valuation-standards-ivs-published/" target="_blank" rel="noopener noreferrer">IVSC, New edition of the International Valuation Standards published</a>.</li>
  <li><a href="https://www.ojk.go.id/id/regulasi/Documents/Pages/Penilaian-Kualitas-Aset-Bank-Umum/pojk%2040-2019.pdf" target="_blank" rel="noopener noreferrer">OJK, POJK 40/POJK.03/2019 tentang Penilaian Kualitas Aset Bank Umum</a>.</li>
</ul>
HTML,
                'cover_image_path' => '/images/articles/digipro-data-pembanding.svg',
                'meta_title' => 'Membaca Data Pembanding untuk Review Appraisal Properti | DIGIPRO by KJPP HJAR',
                'meta_description' => 'Panduan editorial KJPP HJAR untuk membaca data pembanding appraisal properti secara lebih disiplin dan terdokumentasi.',
            ],
        );

        $this->updateArticle(
            slug: 'regulasi-penilaian-properti-2026',
            categorySlug: 'regulasi',
            tagSlugs: ['valuation', 'properti', 'regulasi', 'banking', 'risk'],
            attributes: [
                'title' => 'Regulasi Penilaian Properti: Dampaknya bagi KJPP dan Perbankan',
                'excerpt' => 'Regulasi penilaian properti menuntut dokumentasi yang lebih rapi, pemisahan peran yang jelas, dan kualitas data yang dapat diaudit. Dampaknya terasa langsung pada KJPP, bank, dan pengguna laporan appraisal.',
                'content_html' => <<<'HTML'
<p>Regulasi penilaian properti tidak dapat dibaca hanya sebagai kewajiban administratif. Bagi KJPP dan institusi finansial, regulasi menentukan bagaimana penilaian dilakukan, bagaimana laporan disusun, dan bagaimana risiko penggunaan nilai dikelola.</p>

<p>Dalam praktik pembiayaan, laporan appraisal sering menjadi salah satu dasar penting untuk memahami nilai agunan. Karena itu, kualitas proses, independensi penilai, sumber data, dan dokumentasi kerja harus dapat dipertanggungjawabkan.</p>

<h2>Pokok perhatian dalam regulasi penilaian</h2>
<p>Ada tiga area yang perlu diperhatikan oleh pengguna jasa appraisal: legalitas penilai dan KJPP, standar pekerjaan penilaian, serta hubungan antara hasil penilaian dan manajemen risiko lembaga keuangan. Ketiganya saling terhubung.</p>

<h3>Legalitas profesi dan izin KJPP</h3>
<p>Kantor Jasa Penilai Publik berada dalam kerangka pembinaan profesi penilai publik. Kementerian Keuangan melalui PPPK menyediakan informasi profesi penilaian dan daftar KJPP aktif. Bagi pemberi tugas, pengecekan legalitas ini menjadi langkah dasar sebelum pekerjaan appraisal dimulai.</p>

<h3>Standar pekerjaan dan dokumentasi</h3>
<p>Regulasi dan standar profesi menuntut penilai menjelaskan pendekatan, data, asumsi, pembatasan, dan kesimpulan secara tertib. IVS efektif 31 Januari 2025 juga memberi perhatian kuat pada data, input, dokumentasi, dan model penilaian. Arah ini sejalan dengan kebutuhan pasar yang menuntut laporan lebih transparan.</p>

<h2>Dampak bagi KJPP</h2>
<p>Bagi KJPP, tekanan regulasi membuat proses kerja tidak cukup hanya mengandalkan pengalaman individu. KJPP perlu memiliki alur kerja yang membantu tim mencatat data masuk, memeriksa dokumen, melakukan review, dan menyimpan jejak komunikasi penting.</p>

<h3>Pemisahan tahap kerja</h3>
<p>Pemisahan antara pengajuan, verifikasi dokumen, review penilai, offer, dan penerbitan laporan membantu mengurangi ambiguitas tanggung jawab. Setiap tahap perlu memiliki status yang jelas agar pekerjaan tidak bergantung pada komunikasi informal.</p>

<h3>Kertas kerja dan audit trail</h3>
<p>Kertas kerja bukan sekadar lampiran internal. Ia menunjukkan bagaimana data digunakan, asumsi dibuat, dan opini nilai disimpulkan. Dalam konteks review, audit trail membantu menjawab pertanyaan mengapa satu data dipilih dan data lain tidak dipakai.</p>

<h2>Dampak bagi perbankan</h2>
<p>OJK dalam aturan penilaian kualitas aset bank umum menempatkan penilaian agunan sebagai bagian dari kerangka kualitas aset. Bagi bank, laporan appraisal yang kuat membantu analis kredit membaca nilai agunan secara lebih hati-hati, terutama ketika pasar bergerak terbatas atau data transaksi tidak merata.</p>

<h3>Risiko nilai agunan</h3>
<p>Risiko utama muncul ketika nilai agunan dibaca sebagai angka tunggal tanpa memperhatikan likuiditas, waktu pemasaran, legalitas, dan kondisi objek. Laporan appraisal perlu membantu bank melihat faktor yang dapat menekan realisasi nilai jika agunan harus dieksekusi.</p>

<h3>Kebutuhan dokumentasi digital</h3>
<p>Semakin banyak pihak terlibat dalam satu permohonan, semakin penting dokumentasi digital. Dokumen, status, persetujuan, dan catatan revisi perlu berada dalam satu ruang kerja agar proses appraisal tidak tersebar di percakapan terpisah.</p>

<h2>Checklist kepatuhan operasional</h2>
<table>
  <thead>
    <tr>
      <th>Area</th>
      <th>Hal yang perlu dicek</th>
      <th>Output yang diharapkan</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>Penugasan</td>
      <td>Identitas pemberi tugas, tujuan penilaian, objek, dan ruang lingkup.</td>
      <td>Instruksi kerja jelas sebelum review dimulai.</td>
    </tr>
    <tr>
      <td>Dokumen</td>
      <td>Legalitas, data objek, foto, dan dokumen pendukung.</td>
      <td>Kelengkapan dapat diverifikasi oleh admin dan penilai.</td>
    </tr>
    <tr>
      <td>Review</td>
      <td>Sumber data, asumsi, pendekatan, dan pembatasan.</td>
      <td>Kesimpulan nilai memiliki dasar yang dapat ditelusuri.</td>
    </tr>
  </tbody>
</table>

<h2>Peran DIGIPRO dalam tata kelola proses</h2>
<p>DIGIPRO by KJPP HJAR dirancang untuk membantu proses appraisal lebih tertata: permohonan diajukan melalui portal, dokumen diunggah, status dipantau, dan tahap pekerjaan dapat dilihat oleh pihak terkait. Pendekatan ini relevan bagi KJPP dan institusi finansial yang membutuhkan kontrol proses tanpa menambah kerumitan administrasi.</p>

<h2>Referensi</h2>
<ul>
  <li><a href="https://pppk.kemenkeu.go.id/in/page/informasi-profesi-penilaian" target="_blank" rel="noopener noreferrer">PPPK Kementerian Keuangan, Informasi Profesi Penilaian</a>.</li>
  <li><a href="https://peraturan.bpk.go.id/Details/137106/pmk-no-228pmk012019" target="_blank" rel="noopener noreferrer">PMK Nomor 228/PMK.01/2019 tentang Perubahan Kedua atas PMK 101/PMK.01/2014 tentang Penilai Publik</a>.</li>
  <li><a href="https://www.ojk.go.id/id/regulasi/Documents/Pages/Penilaian-Kualitas-Aset-Bank-Umum/pojk%2040-2019.pdf" target="_blank" rel="noopener noreferrer">OJK, POJK 40/POJK.03/2019 tentang Penilaian Kualitas Aset Bank Umum</a>.</li>
  <li><a href="https://ivsc.org/new-edition-of-the-international-valuation-standards-ivs-published/" target="_blank" rel="noopener noreferrer">IVSC, International Valuation Standards effective 31 January 2025</a>.</li>
</ul>
HTML,
                'cover_image_path' => '/images/articles/digipro-regulasi-appraisal.svg',
                'meta_title' => 'Regulasi Penilaian Properti untuk KJPP dan Perbankan | DIGIPRO by KJPP HJAR',
                'meta_description' => 'Analisis editorial tentang dampak regulasi penilaian properti bagi KJPP, bank, dan pengguna laporan appraisal.',
            ],
        );
    }

    /**
     * @param  array<string, string>  $attributes
     * @param  list<string>  $tagSlugs
     */
    private function updateArticle(string $slug, string $categorySlug, array $tagSlugs, array $attributes): void
    {
        $categoryId = ArticleCategory::query()
            ->where('slug', $categorySlug)
            ->value('id');

        $article = Article::query()->where('slug', $slug)->firstOrFail();
        $article->forceFill([
            ...$attributes,
            'category_id' => $categoryId,
        ])->save();

        $tagIds = Tag::query()
            ->whereIn('slug', $tagSlugs)
            ->pluck('id')
            ->all();

        $article->tags()->sync($tagIds);
    }
}
