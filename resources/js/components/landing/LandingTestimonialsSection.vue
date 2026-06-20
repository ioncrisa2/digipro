<script setup>
import { Building2, FileText, Landmark, Scale, ShieldCheck } from 'lucide-vue-next'

defineProps({
  testimonials: { type: Array, required: true },
})

const credibilityPoints = [
  {
    icon: Landmark,
    title: 'Identitas KJPP jelas',
    description: 'DIGIPRO ditempatkan sebagai kanal digital proses penilaian properti oleh KJPP HJAR, bukan aplikasi lepas tanpa konteks profesi.',
  },
  {
    icon: FileText,
    title: 'Dokumen menjadi pusat proses',
    description: 'Permohonan, lampiran, catatan, kontrak, invoice, dan laporan berada dalam alur kerja yang dapat ditelusuri.',
  },
  {
    icon: ShieldCheck,
    title: 'Status dapat dipertanggungjawabkan',
    description: 'Setiap tahap memberi konteks posisi pekerjaan sehingga komunikasi dengan pemberi tugas lebih terkendali.',
  },
]
</script>

<template>
  <section id="credibility" class="scroll-mt-24 bg-[var(--landing-navy)] px-6 py-20 text-white md:py-24">
    <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[0.95fr_1.05fr] lg:items-center">
      <div class="space-y-8">
        <div class="space-y-4">
          <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--landing-gold)]">
            Kredibilitas
          </p>
          <h2 class="text-balance text-3xl font-bold leading-tight md:text-5xl">
            Dibangun untuk proses appraisal yang menuntut ketertiban dokumen.
          </h2>
          <p class="text-pretty text-base leading-8 text-slate-300 md:text-lg">
            DIGIPRO by KJPP HJAR membantu institusi finansial, klien appraisal, dan tim KJPP menjaga permohonan tetap rapi dari intake sampai laporan siap.
          </p>
        </div>

        <blockquote class="border-l-4 border-[var(--landing-gold)] pl-5 text-lg leading-8 text-slate-100">
          Platform yang baik untuk appraisal bukan yang terlihat paling ramai, tetapi yang membuat status, dokumen, dan tanggung jawab proses terbaca tanpa ambigu.
        </blockquote>
      </div>

      <div class="grid gap-4">
        <div class="grid gap-4 sm:grid-cols-3">
          <div class="rounded-2xl border border-white/10 bg-white/[0.04] p-5">
            <div class="text-3xl font-bold text-white">6</div>
            <div class="mt-2 text-sm leading-6 text-slate-300">tahap kerja dari permohonan sampai laporan</div>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/[0.04] p-5">
            <div class="text-3xl font-bold text-white">1</div>
            <div class="mt-2 text-sm leading-6 text-slate-300">portal untuk dokumen dan status appraisal</div>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/[0.04] p-5">
            <div class="text-3xl font-bold text-white">KJPP</div>
            <div class="mt-2 text-sm leading-6 text-slate-300">orientasi kerja profesi penilai properti</div>
          </div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-white/[0.04] p-5">
          <div class="flex items-center gap-3 border-b border-white/10 pb-4">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[var(--landing-gold)]/15 text-[var(--landing-gold)]">
              <Scale class="h-5 w-5" />
            </div>
            <div>
              <div class="font-semibold text-white">KJPP Henricus Judi Adrianto & Rekan</div>
              <div class="text-sm text-slate-400">Konteks profesional penilaian properti</div>
            </div>
          </div>

          <div class="mt-5 grid gap-4">
            <div
              v-for="point in credibilityPoints"
              :key="point.title"
              class="grid gap-3 sm:grid-cols-[44px_1fr]"
            >
              <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/[0.06] text-[var(--landing-gold)]">
                <component :is="point.icon" class="h-5 w-5" />
              </div>
              <div>
                <div class="font-semibold text-white">{{ point.title }}</div>
                <p class="mt-1 text-sm leading-6 text-slate-300">{{ point.description }}</p>
              </div>
            </div>
          </div>
        </div>

        <div v-if="testimonials.length" class="rounded-2xl border border-[var(--landing-gold)]/25 bg-[var(--landing-gold)]/10 p-5">
          <div class="flex items-center gap-3">
            <Building2 class="h-5 w-5 text-[var(--landing-gold)]" />
            <div class="text-sm font-semibold uppercase tracking-[0.14em] text-[var(--landing-gold)]">
              Catatan klien tersimpan di sistem konten
            </div>
          </div>
          <p class="mt-3 text-sm leading-7 text-slate-200">
            Testimoni dapat ditampilkan kembali setelah tersedia sumber asli yang layak dipublikasikan beserta izin penggunaannya.
          </p>
        </div>
      </div>
    </div>
  </section>
</template>
