<script setup>
import { Smartphone, TrendingUp, Zap, ShieldCheck } from 'lucide-vue-next'

defineProps({
  featureCards: { type: Array, required: true },
})

const iconMap = {
  TrendingUp,
  Zap,
  ShieldCheck,
  Smartphone,
}
</script>

<template>
  <section id="features" class="scroll-mt-24 px-6 py-24">
    <div class="mx-auto max-w-7xl space-y-14">
      <div class="max-w-3xl space-y-4">
        <h2 class="text-3xl font-semibold tracking-tight md:text-5xl">Fitur tetap ada, tapi sekarang setiap narasi punya visual pendukung.</h2>
        <p class="text-base leading-7 text-slate-600 md:text-lg">
          Struktur ini sudah siap untuk dipasangi upload image per fitur. Sementara itu saya pakai placeholder agar section ini tidak lagi hanya berupa blok kata-kata.
        </p>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <article
          v-for="(feature, index) in featureCards"
          :key="feature.title"
          class="grid gap-5 overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/75 p-4 shadow-[0_20px_60px_rgba(15,23,42,0.08)] backdrop-blur md:grid-cols-[1.02fr_0.98fr]"
        >
          <div class="overflow-hidden rounded-[1.5rem] bg-slate-900">
            <img
              :src="feature.image"
              :alt="feature.title"
              class="h-full min-h-[240px] w-full object-cover"
              loading="lazy"
            />
          </div>
          <div class="flex flex-col justify-between gap-6 p-2">
            <div class="space-y-4">
              <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-900/8 text-slate-900">
                  <component :is="iconMap[feature.icon]" v-if="iconMap[feature.icon]" class="h-6 w-6" />
                </div>
                <div class="text-xs font-medium uppercase tracking-[0.24em] text-slate-500">Feature {{ index + 1 }}</div>
              </div>
              <h3 class="text-2xl font-semibold tracking-tight text-slate-900">{{ feature.title }}</h3>
              <p class="text-sm leading-7 text-slate-600 md:text-base">{{ feature.description }}</p>
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>
</template>
