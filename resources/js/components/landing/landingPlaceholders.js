export const createLandingPlaceholderImage = ({
  title,
  subtitle = 'Image Placeholder',
  palette = ['#0f172a', '#1e293b', '#f59e0b'],
}) => {
  const [base, surface, accent] = palette

  const svg = `
    <svg xmlns="http://www.w3.org/2000/svg" width="1600" height="900" viewBox="0 0 1600 900" fill="none">
      <defs>
        <linearGradient id="bg" x1="0" y1="0" x2="1600" y2="900" gradientUnits="userSpaceOnUse">
          <stop stop-color="${base}" />
          <stop offset="1" stop-color="${surface}" />
        </linearGradient>
        <linearGradient id="glass" x1="260" y1="210" x2="1340" y2="690" gradientUnits="userSpaceOnUse">
          <stop stop-color="rgba(255,255,255,0.18)" />
          <stop offset="1" stop-color="rgba(255,255,255,0.06)" />
        </linearGradient>
      </defs>
      <rect width="1600" height="900" fill="url(#bg)" />
      <circle cx="1270" cy="170" r="210" fill="${accent}" fill-opacity="0.24" />
      <circle cx="300" cy="760" r="260" fill="#ffffff" fill-opacity="0.08" />
      <rect x="190" y="140" width="1220" height="620" rx="36" fill="url(#glass)" stroke="rgba(255,255,255,0.16)" />
      <rect x="260" y="210" width="470" height="18" rx="9" fill="rgba(255,255,255,0.14)" />
      <rect x="260" y="252" width="360" height="18" rx="9" fill="rgba(255,255,255,0.10)" />
      <rect x="260" y="330" width="480" height="230" rx="24" fill="rgba(255,255,255,0.08)" />
      <rect x="790" y="230" width="330" height="140" rx="24" fill="rgba(255,255,255,0.10)" />
      <rect x="1148" y="230" width="192" height="140" rx="24" fill="${accent}" fill-opacity="0.24" />
      <rect x="790" y="410" width="550" height="150" rx="24" fill="rgba(255,255,255,0.08)" />
      <rect x="790" y="596" width="270" height="92" rx="22" fill="rgba(255,255,255,0.10)" />
      <rect x="1070" y="596" width="270" height="92" rx="22" fill="rgba(255,255,255,0.08)" />
      <text x="260" y="674" fill="white" fill-opacity="0.96" font-family="Arial, Helvetica, sans-serif" font-size="52" font-weight="700">${title}</text>
      <text x="260" y="722" fill="white" fill-opacity="0.68" font-family="Arial, Helvetica, sans-serif" font-size="26">${subtitle}</text>
    </svg>
  `.trim()

  return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`
}

export const landingHeroFallback = createLandingPlaceholderImage({
  title: 'DigiPro by KJPP HJAR',
  subtitle: 'Replace this with the main hero property photo',
  palette: ['#20150d', '#3b2b1b', '#d97706'],
})

export const landingProcessSteps = [
  {
    title: 'Ajukan Permohonan',
    description: 'Pengguna mengisi permohonan dengan data yang valid dan sesuai kebutuhan penilaian.',
  },
  {
    title: 'Verifikasi Admin',
    description: 'Admin memverifikasi dokumen yang diajukan sebelum permohonan diproses lebih lanjut.',
  },
  {
    title: 'Appraisal Review',
    description: 'Reviewer melakukan review terhadap permohonan yang masuk.',
  },
  {
    title: 'Laporan Siap',
    description: 'Laporan penilaian siap ditinjau dan diunduh setelah proses selesai.',
  },
]

export const landingTestimonialAvatarFallback = createLandingPlaceholderImage({
  title: 'User Photo',
  subtitle: 'Replace with testimonial profile image',
  palette: ['#312e81', '#4338ca', '#f59e0b'],
})

const featurePlaceholderPalettes = [
  ['#1d2838', '#304357', '#fb923c'],
  ['#142739', '#1f425d', '#38bdf8'],
  ['#1f2333', '#363a52', '#f472b6'],
  ['#17231a', '#2b4131', '#4ade80'],
]

const featurePlaceholderTitles = [
  'Digital Intake',
  'Audit Trail',
  'Mobile Workspace',
  'Valuation Speed',
]

export const buildLandingSlides = (platformPreviewImages = []) => [
  {
    title: 'Request & Offer dalam hitungan jam',
    description: 'Buat permintaan, unggah dokumen, dan dapatkan penawaran tanpa proses manual berulang.',
    points: ['Form digital terstruktur', 'Kelengkapan otomatis', 'Estimasi cepat'],
    image: platformPreviewImages?.[0] || createLandingPlaceholderImage({
      title: 'Request & Offer',
      subtitle: 'Property onboarding visual placeholder',
      palette: ['#172033', '#273449', '#f59e0b'],
    }),
    imageAlt: 'Placeholder visual request dan offer',
  },
  {
    title: 'Appraisal Review dengan hasil kilat',
    description: 'Penilaian tanpa inspeksi lapangan mempercepat proses dari dokumen masuk hingga opini nilai.',
    points: ['Tanpa jadwal survei lapangan', 'Validasi dokumen cepat', 'Turnaround laporan lebih singkat'],
    image: platformPreviewImages?.[1] || createLandingPlaceholderImage({
      title: 'Appraisal Review',
      subtitle: 'Replace with stock image that matches the narrative',
      palette: ['#10231f', '#17332d', '#34d399'],
    }),
    imageAlt: 'Placeholder visual appraisal review',
  },
  {
    title: 'Laporan legal-ready, mudah diunduh',
    description: 'Laporan penilaian, invoice, dan dokumen pendukung siap diakses kapan saja.',
    points: ['Format resmi KJPP', 'Audit trail', 'Download aman'],
    image: platformPreviewImages?.[2] || createLandingPlaceholderImage({
      title: 'Legal-Ready Report',
      subtitle: 'Replace with report and document stock image',
      palette: ['#23153a', '#38215c', '#a78bfa'],
    }),
    imageAlt: 'Placeholder visual laporan penilaian',
  },
]

export const buildLandingFeatureCards = (features = []) => {
  return features.map((feature, index) => ({
    ...feature,
    image: feature.image_url || createLandingPlaceholderImage({
      title: featurePlaceholderTitles[index] ?? feature.title,
      subtitle: 'Replace with stock image or uploaded feature image',
      palette: featurePlaceholderPalettes[index % featurePlaceholderPalettes.length],
    }),
  }))
}
