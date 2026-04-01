<script setup>
import { CheckCircle2 } from 'lucide-vue-next'

defineProps({
  testimonials: { type: Array, required: true },
  testimonialIndex: { type: Number, required: true },
  currentTestimonial: { type: Object, default: null },
})

const emit = defineEmits(['go'])
</script>

<template>
  <section id="testimonials" class="scroll-mt-24 bg-slate-950 px-6 py-24 text-white">
    <div class="mx-auto grid max-w-6xl gap-10 lg:grid-cols-[0.72fr_1.28fr] lg:items-center">
      <div class="space-y-4">
        <div class="text-sm font-medium uppercase tracking-[0.28em] text-amber-300/80">Trusted By Professionals</div>
        <h2 class="text-3xl font-semibold tracking-tight md:text-5xl">Testimonial tetap dipertahankan, sekarang ditambah visual user.</h2>
        <p class="text-base leading-7 text-slate-300 md:text-lg">
          Placeholder avatar ini nanti bisa Anda ganti dengan gambar user yang akan disertakan.
        </p>
      </div>

      <div class="rounded-[2rem] border border-white/10 bg-white/5 p-5 shadow-[0_30px_100px_rgba(0,0,0,0.35)] backdrop-blur">
        <transition name="fade-slide" mode="out-in">
          <div v-if="currentTestimonial" :key="currentTestimonial.name + currentTestimonial.quote" class="grid gap-6 md:grid-cols-[220px_1fr] md:items-center">
            <div class="overflow-hidden rounded-[1.6rem] border border-white/10 bg-slate-900/60">
              <img
                :src="currentTestimonial.avatar"
                :alt="currentTestimonial.name"
                class="h-[260px] w-full object-cover"
                loading="lazy"
              />
            </div>

            <div class="space-y-6 p-2">
              <div class="flex items-center gap-2 text-amber-300">
                <CheckCircle2 class="h-5 w-5" />
                <span class="text-sm font-medium uppercase tracking-[0.18em]">Client Voice</span>
              </div>
              <p class="text-lg leading-8 text-slate-100 md:text-2xl">"{{ currentTestimonial.quote }}"</p>
              <div>
                <div class="text-lg font-semibold text-white">{{ currentTestimonial.name }}</div>
                <div class="text-sm text-slate-400">{{ currentTestimonial.role }}</div>
              </div>
            </div>
          </div>
        </transition>

        <div class="mt-6 flex items-center justify-center gap-2">
          <button
            v-for="(t, idx) in testimonials"
            :key="t.name + idx"
            class="h-2.5 rounded-full transition-all"
            :class="idx === testimonialIndex ? 'w-10 bg-white' : 'w-3 bg-white/35'"
            @click="emit('go', idx)"
          ></button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: opacity 0.45s ease, transform 0.45s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(10px);
}
</style>
