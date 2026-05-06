<script setup>
import { Button } from '@/components/ui/button'
import { CheckCircle2, ArrowLeft, ArrowRightCircle } from 'lucide-vue-next'

defineProps({
  slides: { type: Array, required: true },
  activeSlide: { type: Number, required: true },
  currentSlide: { type: Object, required: true },
})

const emit = defineEmits(['previous', 'next', 'go'])
</script>

<template>
  <section id="showcase" class="scroll-mt-24 bg-[var(--landing-invert)] px-6 py-24 text-white">
    <div class="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[0.82fr_1.18fr] lg:items-end">
      <div class="space-y-6">
        <h2 class="max-w-lg text-balance text-3xl font-semibold md:text-5xl">
          Lihat bagaimana proses penilaian berjalan lebih rapi, cepat, dan mudah dipantau.
        </h2>
        <p class="max-w-xl text-pretty text-base leading-7 text-slate-300 md:text-lg">
          DigiPro by KJPP HJAR membantu permohonan, Verifikasi Dokumen, Appraisal Review, hingga laporan akhir
          berjalan dalam satu alur digital yang lebih terstruktur.
        </p>

        <div class="flex items-center gap-3">
          <Button aria-label="Slide sebelumnya" variant="outline" size="icon" class="border-white/20 bg-white/5 text-white hover:bg-white/10" @click="emit('previous')">
            <ArrowLeft class="h-4 w-4" />
          </Button>
          <Button aria-label="Slide berikutnya" variant="outline" size="icon" class="border-white/20 bg-white/5 text-white hover:bg-white/10" @click="emit('next')">
            <ArrowRightCircle class="h-4 w-4" />
          </Button>
          <div class="flex items-center gap-1 pl-2">
            <button
              v-for="(slide, idx) in slides"
              :key="slide.title"
              type="button"
              class="flex h-11 w-11 items-center justify-center rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:ring-offset-2 focus-visible:ring-offset-[#111827]"
              :aria-label="`Buka slide ${idx + 1}`"
              :aria-current="idx === activeSlide ? 'true' : 'false'"
              @click="emit('go', idx)"
            >
              <span
                class="h-2.5 w-8 rounded-full transition-[background-color] duration-150 motion-reduce:transition-none"
                :class="idx === activeSlide ? 'bg-cyan-300' : 'bg-white/20'"
              />
            </button>
          </div>
        </div>
      </div>

      <div class="rounded-[2rem] border border-white/10 bg-white/5 p-4 shadow-[0_30px_120px_rgba(0,0,0,0.45)]">
        <transition name="slide-fade" mode="out-in">
          <div :key="currentSlide.title" class="relative overflow-hidden rounded-[1.5rem] border border-white/10 bg-slate-900/70">
            <div class="absolute inset-0">
              <img
                :src="currentSlide.image"
                :alt="currentSlide.imageAlt"
                class="h-full min-h-[520px] w-full object-cover"
                loading="lazy"
              />
            </div>
            <div class="absolute inset-0 bg-slate-950/70"></div>
            <div class="relative min-h-[520px]">
              <div class="absolute bottom-4 left-1/2 w-[calc(100%-2rem)] -translate-x-1/2 rounded-[1.5rem] border border-white/12 bg-slate-950/72 p-6 text-center shadow-[0_24px_70px_rgba(0,0,0,0.3)] md:bottom-6 md:right-6 md:left-auto md:w-[min(420px,42%)] md:translate-x-0 md:text-left">
                <div class="space-y-4">
                  <div class="text-xs font-medium uppercase text-cyan-300/80">Pratinjau Platform</div>
                  <h3 class="text-balance text-2xl font-semibold text-white md:text-3xl">{{ currentSlide.title }}</h3>
                  <p class="text-pretty text-sm leading-7 text-slate-200 md:text-base">{{ currentSlide.description }}</p>
                </div>

                <ul class="mt-6 space-y-3 text-sm text-slate-100">
                  <li
                    v-for="point in currentSlide.points"
                    :key="point"
                    class="flex items-start gap-3 justify-center md:justify-start"
                  >
                    <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-cyan-300" />
                    <span>{{ point }}</span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </transition>
      </div>
    </div>
  </section>
</template>

<style scoped>
.slide-fade-enter-active,
.slide-fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.slide-fade-enter-from,
.slide-fade-leave-to {
  opacity: 0;
  transform: translateY(10px);
}

@media (prefers-reduced-motion: reduce) {
  .slide-fade-enter-active,
  .slide-fade-leave-active {
    transition: none;
  }
}
</style>
