<script setup>
import {
  ArrowRight,
  FileCheck2,
  FilePlus2,
  Handshake,
  SearchCheck,
  ShieldCheck,
  UploadCloud,
} from 'lucide-vue-next'

defineProps({
  processSteps: { type: Array, required: true },
})

const iconMap = {
  FileCheck2,
  FilePlus2,
  Handshake,
  SearchCheck,
  ShieldCheck,
  UploadCloud,
}
</script>

<template>
  <section id="process" class="scroll-mt-24 bg-white px-6 py-20 md:py-24">
    <div class="mx-auto max-w-7xl">
      <div class="max-w-3xl space-y-4">
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--landing-gold-dark)]">
          Alur kerja
        </p>
        <h2 class="text-balance text-3xl font-bold leading-tight text-[var(--landing-navy)] md:text-5xl">
          Enam tahap appraisal dalam urutan yang mudah diaudit.
        </h2>
        <p class="text-pretty text-base leading-8 text-slate-600 md:text-lg">
          Timeline membantu pemberi tugas dan tim KJPP membaca posisi pekerjaan tanpa perlu menanyakan status secara manual.
        </p>
      </div>

      <div class="mt-12 overflow-hidden rounded-2xl border border-slate-200 bg-[var(--landing-bg)] shadow-[0_12px_30px_rgba(15,23,42,0.055)]">
        <div class="grid divide-y divide-slate-200 lg:grid-cols-6 lg:divide-x lg:divide-y-0">
          <article
            v-for="(step, index) in processSteps"
            :key="step.title"
            class="relative min-h-[250px] bg-white p-5"
          >
            <div class="flex h-full flex-col justify-between gap-8">
              <div>
                <div class="flex items-start justify-between gap-4">
                  <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-[var(--landing-gold)]/35 bg-[var(--landing-gold-soft)] text-[var(--landing-gold-dark)]">
                    <component :is="iconMap[step.icon]" v-if="iconMap[step.icon]" class="h-6 w-6" />
                  </div>
                  <div class="text-sm font-bold text-slate-300">0{{ index + 1 }}</div>
                </div>

                <h3 class="mt-6 text-xl font-bold leading-tight text-[var(--landing-navy)]">
                  {{ step.title }}
                </h3>
                <p class="mt-3 text-sm leading-7 text-slate-600">
                  {{ step.description }}
                </p>
              </div>

              <div
                v-if="index < processSteps.length - 1"
                class="hidden items-center gap-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-400 lg:flex"
              >
                Berikutnya
                <ArrowRight class="h-4 w-4 text-[var(--landing-gold-dark)]" />
              </div>
            </div>
          </article>
        </div>
      </div>
    </div>
  </section>
</template>
