export const landingFeatureDefaults = [
  {
    category: 'Pengajuan',
    icon: 'FilePlus2',
    title: 'Pengajuan Terstruktur',
    description: 'Data pemberi tugas, objek penilaian, tujuan appraisal, dan kebutuhan laporan dimasukkan dalam alur yang konsisten sejak awal.',
  },
  {
    category: 'Dokumen',
    icon: 'FolderCheck',
    title: 'Kelengkapan Dokumen',
    description: 'Berkas pendukung dikumpulkan dalam satu ruang kerja agar admin dan penilai dapat melihat konteks permohonan tanpa pencarian manual.',
  },
  {
    category: 'Admin',
    icon: 'ShieldCheck',
    title: 'Verifikasi Admin',
    description: 'Tim administrasi dapat meninjau kelengkapan, memberi catatan, dan memastikan permohonan siap masuk tahap kajian.',
  },
  {
    category: 'Penilai',
    icon: 'SearchCheck',
    title: 'Review Penilai',
    description: 'Penilai memperoleh ringkasan permohonan, dokumen, dan status pekerjaan untuk mendukung proses review yang terdokumentasi.',
  },
  {
    category: 'Komersial',
    icon: 'Handshake',
    title: 'Request & Offer',
    description: 'Permintaan, penawaran, kontrak, pembayaran, dan tindak lanjut dapat dipantau dalam tahapan yang jelas.',
  },
  {
    category: 'Monitoring',
    icon: 'ClipboardCheck',
    title: 'Status Terdokumentasi',
    description: 'Perubahan status dan milestone tersimpan sebagai jejak proses sehingga pemberi tugas memahami posisi permohonannya.',
  },
]

export const platformDetailItems = [
  {
    eyebrow: 'Dashboard operasional',
    title: 'Ringkasan permohonan, status, dan tindakan lanjutan.',
    description: 'Dashboard DIGIPRO menampilkan permohonan terbaru, jumlah pekerjaan dalam proses, serta item yang perlu segera ditindaklanjuti oleh pengguna.',
    image: '/images/landing/screenshots/digipro-dashboard.png',
    imageAlt: 'Screenshot dashboard DIGIPRO untuk memantau permohonan appraisal',
    metric: '1 tampilan',
    metricLabel: 'untuk memantau pekerjaan aktif',
  },
  {
    eyebrow: 'Form pengajuan',
    title: 'Persetujuan dan data awal terdokumentasi sebelum proses berjalan.',
    description: 'Pengajuan dimulai dari persetujuan, disclaimer, data objek, dan dokumen pendukung agar permohonan masuk ke KJPP dengan konteks yang lebih lengkap.',
    image: '/images/landing/screenshots/digipro-form-pengajuan.png',
    imageAlt: 'Screenshot form pengajuan DIGIPRO dengan persetujuan dan keaslian data',
    metric: 'Data awal',
    metricLabel: 'lebih siap untuk diverifikasi',
  },
  {
    eyebrow: 'Tracking progres',
    title: 'Milestone status memperjelas posisi pekerjaan appraisal.',
    description: 'Halaman tracking menampilkan tahap permohonan, verifikasi, penawaran, kontrak, kajian, hingga laporan selesai secara berurutan.',
    image: '/images/landing/screenshots/digipro-status-tracking.png',
    imageAlt: 'Screenshot tracking progress DIGIPRO untuk status permohonan appraisal',
    metric: '6 tahap',
    metricLabel: 'dari pengajuan hingga laporan',
  },
]

export const landingProcessSteps = [
  {
    icon: 'FilePlus2',
    title: 'Ajukan Permohonan',
    description: 'Pemberi tugas mengisi data appraisal dan kebutuhan laporan.',
  },
  {
    icon: 'UploadCloud',
    title: 'Unggah Dokumen',
    description: 'Dokumen legal, objek, dan pendukung dikirim melalui portal.',
  },
  {
    icon: 'ShieldCheck',
    title: 'Verifikasi Admin',
    description: 'Admin memeriksa kelengkapan sebelum permohonan diteruskan.',
  },
  {
    icon: 'SearchCheck',
    title: 'Review Penilai',
    description: 'Penilai meninjau data dan dokumen untuk kebutuhan kajian.',
  },
  {
    icon: 'Handshake',
    title: 'Offer',
    description: 'Penawaran, kontrak, dan pembayaran diproses secara tertata.',
  },
  {
    icon: 'FileCheck2',
    title: 'Laporan Siap',
    description: 'Hasil akhir dapat ditinjau setelah proses appraisal selesai.',
  },
]

export const buildLandingSlides = () => platformDetailItems

export const buildLandingFeatureCards = (features = []) => {
  return landingFeatureDefaults.map((fallback, index) => {
    const feature = features[index] ?? {}

    return {
      ...fallback,
      title: fallback.title,
      description: fallback.description,
      category: fallback.category,
      icon: fallback.icon,
      imageUrl: feature.image_url ?? null,
    }
  })
}
