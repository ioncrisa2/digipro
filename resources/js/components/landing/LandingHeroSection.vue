<script setup>
import { router } from '@inertiajs/vue3'

import { Button } from '@/components/ui/button'
import { ArrowRight } from 'lucide-vue-next'

defineProps({
  backgroundUrl: { type: String, required: true },
})
</script>

<template>
  <section class="relative isolate min-h-[calc(100svh-72px)] overflow-hidden">
    <div class="absolute inset-0 -z-20">
      <img
        :src="backgroundUrl"
        alt="Placeholder hero property visual"
        class="h-full w-full object-cover"
      />
    </div>
    <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.16),transparent_36%),linear-gradient(90deg,rgba(15,23,42,0.84)_0%,rgba(15,23,42,0.68)_42%,rgba(15,23,42,0.36)_70%,rgba(15,23,42,0.76)_100%)] backdrop-blur-[3px]"></div>

    <div class="mx-auto flex min-h-[calc(100svh-72px)] max-w-7xl items-end px-6 pb-16 pt-20 md:items-center md:pb-24">
      <div class="landing-fade-up max-w-3xl space-y-7 text-white">
        <div class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-sm font-medium text-white/90 backdrop-blur">
          <span class="mr-2 h-2 w-2 rounded-full bg-emerald-400"></span>
          Platform penilaian properti untuk KJPP dan institusi finansial
        </div>

        <div class="space-y-4">
          <div class="text-sm font-medium uppercase tracking-[0.32em] text-white/60">DigiPro Valuation Platform</div>
          <h1 class="max-w-4xl text-4xl font-semibold leading-[0.95] tracking-tight md:text-6xl">
            Penilaian properti yang terasa lebih cepat, lebih rapi, dan lebih mudah dipahami.
          </h1>
          <p class="max-w-2xl text-base leading-7 text-white/78 md:text-lg">
            Gunakan visual properti, workflow digital, dan dokumen legal-ready dalam satu pengalaman kerja yang lebih kuat dari proses manual.
          </p>
        </div>

        <div class="flex flex-col gap-4 sm:flex-row">
          <Button size="lg" class="h-12 bg-white px-8 text-base text-slate-950 hover:bg-white/90" @click="router.visit('/login')">
            Mulai Penilaian
            <ArrowRight class="ml-2 h-4 w-4" />
          </Button>
          <Button variant="outline" size="lg" class="h-12 border-white/30 bg-white/5 px-8 text-base text-white hover:bg-white/10" @click="router.visit('/contact')">
            More Information
          </Button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
@keyframes fade-up {
  from {
    opacity: 0;
    transform: translateY(18px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.landing-fade-up {
  animation: fade-up 0.95s ease both;
}
</style>
