<script setup>
import { Scale } from 'lucide-vue-next'

const credibilityPoints = [
  {
    number: '01',
    title: 'Satu riwayat dokumen',
    description: 'Lampiran, catatan, kontrak, invoice, dan laporan mengikuti permohonan yang sama—bukan tersebar di percakapan terpisah.',
  },
  {
    number: '02',
    title: 'Status memiliki konteks',
    description: 'Setiap perubahan tahap menunjukkan posisi pekerjaan dan tindakan yang masih dibutuhkan oleh pihak terkait.',
  },
  {
    number: '03',
    title: 'Proses tetap berada dalam lingkup KJPP',
    description: 'DIGIPRO berfungsi sebagai kanal kerja digital KJPP HJAR, dengan alur yang mengikuti kebutuhan profesi penilai properti.',
  },
]
</script>

<template>
  <section
    id="credibility"
    class="relative scroll-mt-24 overflow-hidden bg-[var(--landing-navy)] px-6 py-20 text-white md:py-28"
  >
    <div
      aria-hidden="true"
      class="pointer-events-none absolute -right-8 top-2 font-[var(--landing-font-display)] text-[clamp(6rem,16vw,15rem)] font-bold leading-none text-white/[0.025]"
    >
      KJPP
    </div>

    <div class="relative mx-auto max-w-7xl">
      <div class="grid gap-12 border-b border-white/15 pb-14 lg:grid-cols-12 lg:items-end lg:gap-10">
        <div class="lg:col-span-8">
          <p class="text-sm font-semibold text-[var(--landing-gold)]">
            Kredibilitas proses
          </p>
          <h2 class="mt-5 max-w-4xl text-balance text-3xl font-bold leading-tight md:text-5xl lg:text-6xl">
            Appraisal yang tertib dimulai dari jejak kerja yang jelas.
          </h2>
          <p class="mt-6 max-w-2xl text-pretty text-base leading-8 text-slate-300 md:text-lg">
            DIGIPRO menjaga dokumen, keputusan, dan status pekerjaan tetap berada dalam satu alur sejak permohonan diterima sampai laporan tersedia.
          </p>
        </div>

        <div class="border-t-2 border-[var(--landing-gold)] pt-5 lg:col-span-4">
          <div class="flex items-start gap-4">
            <Scale class="mt-1 size-6 shrink-0 text-[var(--landing-gold)]" aria-hidden="true" />
            <div>
              <p class="text-sm text-slate-400">Diselenggarakan dalam konteks profesional</p>
              <h3 class="mt-2 text-xl font-semibold leading-7 text-white">
                KJPP Henricus Judi Adrianto &amp; Rekan
              </h3>
              <p class="mt-2 text-sm leading-6 text-slate-400">
                Kantor Jasa Penilai Publik
              </p>
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-12 pt-14 lg:grid-cols-12 lg:gap-10">
        <div class="lg:col-span-4">
          <p class="text-sm text-[var(--landing-gold)]">Prinsip kerja</p>
          <p class="mt-5 max-w-sm text-balance font-[var(--landing-font-display)] text-2xl font-semibold leading-snug text-white md:text-3xl">
            Bukan sekadar tempat unggah berkas. Setiap dokumen harus punya posisi dalam proses.
          </p>

          <dl class="mt-10 grid grid-cols-2 gap-x-6 border-y border-white/15 py-5">
            <div>
              <dt class="text-xs text-slate-500">Rentang proses</dt>
              <dd class="mt-2 text-sm font-semibold text-slate-200">Permohonan—laporan</dd>
            </div>
            <div class="border-l border-white/15 pl-6">
              <dt class="text-xs text-slate-500">Pusat informasi</dt>
              <dd class="mt-2 text-sm font-semibold text-slate-200">Satu portal kerja</dd>
            </div>
          </dl>
        </div>

        <ol class="border-t border-white/20 lg:col-span-8">
          <li
            v-for="point in credibilityPoints"
            :key="point.number"
            class="grid gap-3 border-b border-white/15 py-7 sm:grid-cols-[3rem_0.75fr_1.25fr] sm:gap-6"
          >
            <span class="font-[var(--landing-font-display)] text-sm font-semibold tabular-nums text-[var(--landing-gold)]">
              {{ point.number }}
            </span>
            <h3 class="text-lg font-semibold leading-7 text-white">
              {{ point.title }}
            </h3>
            <p class="text-pretty text-sm leading-7 text-slate-300">
              {{ point.description }}
            </p>
          </li>
        </ol>
      </div>
    </div>
  </section>
</template>
