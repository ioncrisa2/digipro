<script setup>
import {
  ClipboardCheck,
  FilePlus2,
  FolderCheck,
  Handshake,
  SearchCheck,
  ShieldCheck,
} from 'lucide-vue-next'

defineProps({
  featureCards: { type: Array, required: true },
})

const iconMap = {
  ClipboardCheck,
  FilePlus2,
  FolderCheck,
  Handshake,
  SearchCheck,
  ShieldCheck,
}
</script>

<template>
  <section id="features" class="scroll-mt-24 border-b border-slate-200 bg-white px-6 py-20 md:py-24">
    <div class="mx-auto max-w-7xl">
      <div class="grid gap-8 lg:grid-cols-[0.72fr_1.28fr] lg:items-end">
        <div class="space-y-4">
          <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--landing-gold-dark)]">
            Fitur utama
          </p>
          <h2 class="text-balance text-3xl font-bold leading-tight text-[var(--landing-navy)] md:text-5xl">
            Alur appraisal dibuat jelas dari permohonan sampai laporan.
          </h2>
        </div>
        <p class="max-w-2xl text-pretty text-base leading-8 text-slate-600 md:text-lg lg:justify-self-end">
          DIGIPRO mengurangi koordinasi tersebar dengan membagi proses penilaian ke tahap yang dapat diverifikasi, ditinjau, dan dipantau oleh pihak terkait.
        </p>
      </div>

      <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <article
          v-for="feature in featureCards"
          :key="feature.title"
          class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.045)] transition-colors duration-150 hover:border-[var(--landing-gold)]/55 motion-reduce:transition-none"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-[var(--landing-navy)]">
              <component :is="iconMap[feature.icon]" v-if="iconMap[feature.icon]" class="h-5 w-5" />
            </div>
            <div class="rounded-lg border border-[var(--landing-gold)]/35 bg-[var(--landing-gold-soft)] px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-[var(--landing-gold-dark)]">
              {{ feature.category }}
            </div>
          </div>

          <h3 class="mt-6 text-xl font-bold text-[var(--landing-navy)]">
            {{ feature.title }}
          </h3>
          <p class="mt-3 text-sm leading-7 text-slate-600">
            {{ feature.description }}
          </p>
        </article>
      </div>
    </div>
  </section>
</template>
