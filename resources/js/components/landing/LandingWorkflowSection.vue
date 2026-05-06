<script setup>
import { ArrowRight } from 'lucide-vue-next'

defineProps({
  processSteps: { type: Array, required: true },
})
</script>

<template>
  <section id="process" class="scroll-mt-24 bg-white/70 px-6 py-24">
    <div class="mx-auto max-w-7xl space-y-14">
      <div class="max-w-3xl space-y-4">
        <h2 class="text-balance text-3xl font-semibold text-slate-900 md:text-5xl">Alur kerja dibuat lurus, ringkas, dan langsung terbaca dalam satu sapuan.</h2>
        <p class="text-pretty text-base leading-7 text-slate-600 md:text-lg">
          Setiap langkah sekarang ditampilkan menyamping dengan arah panah yang tegas, sehingga user bisa langsung membaca progres dari permohonan sampai laporan siap.
        </p>
      </div>

      <div class="overflow-hidden rounded-[2.2rem] border border-slate-200 bg-white/80 p-6 shadow-[0_22px_70px_rgba(15,23,42,0.08)] md:p-8 lg:p-10">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-stretch lg:gap-0">
          <template v-for="(step, index) in processSteps" :key="step.title">
            <article class="relative flex h-full flex-1 flex-col justify-between rounded-[1.8rem] border border-slate-200/80 bg-white/90 p-6 shadow-[0_16px_45px_rgba(15,23,42,0.05)]">
              <div class="space-y-5">
                <div class="flex items-start justify-between gap-4">
                  <div class="text-xs font-medium uppercase text-slate-500">Langkah {{ index + 1 }}</div>
                  <div class="text-4xl font-semibold text-slate-900/12 md:text-5xl">
                    0{{ index + 1 }}
                  </div>
                </div>
                <div class="space-y-3">
                  <h3 class="text-balance text-2xl font-semibold text-slate-900">{{ step.title }}</h3>
                  <p class="text-pretty text-sm leading-7 text-slate-600 md:text-base">{{ step.description }}</p>
                </div>
              </div>
            </article>

            <div
              v-if="index < processSteps.length - 1"
              class="hidden items-center justify-center lg:flex"
            >
              <div class="flex w-full items-center justify-center gap-3 px-4">
                <div class="h-px flex-1 bg-slate-200"></div>
                <div class="flex h-11 w-11 items-center justify-center rounded-full border border-sky-200 bg-white text-sky-600 shadow-[0_10px_24px_rgba(14,165,233,0.14)]">
                  <ArrowRight class="h-5 w-5" />
                </div>
                <div class="h-px flex-1 bg-slate-200"></div>
              </div>
            </div>
          </template>
        </div>

        <div class="mt-8 flex flex-col items-center gap-3 text-center lg:hidden">
          <div class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-white/80 px-4 py-2 text-sm font-medium text-sky-700 shadow-[0_10px_24px_rgba(14,165,233,0.08)]">
            <ArrowRight class="h-4 w-4" />
            Ikuti alur dari kiri ke kanan
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
