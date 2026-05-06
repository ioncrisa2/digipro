<script setup>
import { Link } from '@inertiajs/vue3'

import { Button } from '@/components/ui/button'
import { ArrowRight } from 'lucide-vue-next'

defineProps({
  backgroundUrl: { type: String, required: true },
})
</script>

<template>
  <section class="relative isolate min-h-[calc(100dvh-72px)] overflow-hidden">
    <div class="absolute inset-0 -z-20">
      <img
        :src="backgroundUrl"
        alt="Placeholder hero property visual"
        class="h-full w-full object-cover"
      />
    </div>
    <div class="absolute inset-0 -z-10 bg-slate-950/70"></div>

    <div class="mx-auto flex min-h-[calc(100dvh-72px)] max-w-7xl items-end px-6 pb-16 pt-20 md:items-center md:pb-24">
      <div class="max-w-3xl space-y-7 text-white">
        <div class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-sm font-medium text-white/90">
          <span class="mr-2 h-2 w-2 rounded-full bg-emerald-400"></span>
          Platform penilaian properti untuk KJPP dan institusi finansial
        </div>

        <div class="space-y-4">
          <div class="text-sm font-medium uppercase text-white/60">DigiPro by KJPP HJAR</div>
          <h1 class="max-w-4xl text-balance text-4xl font-semibold leading-[0.95] md:text-6xl">
            Penilaian properti yang terasa lebih cepat, lebih rapi, dan lebih mudah dipahami.
          </h1>
          <p class="max-w-2xl text-pretty text-base leading-7 text-white/78 md:text-lg">
            Gunakan visual properti, workflow digital, dan dokumen legal-ready dalam satu pengalaman kerja yang lebih kuat dari proses manual.
          </p>
        </div>

        <div class="flex flex-col gap-4 sm:flex-row">
          <Button as-child size="lg" class="h-12 bg-white px-8 text-base text-slate-950 hover:bg-white/90">
            <Link href="/login">
              Mulai Penilaian
              <ArrowRight class="ml-2 h-4 w-4" />
            </Link>
          </Button>
          <Button as-child variant="outline" size="lg" class="h-12 border-white/30 bg-white/5 px-8 text-base text-white hover:bg-white/10">
            <Link href="/contact">Info lebih lanjut</Link>
          </Button>
        </div>
      </div>
    </div>
  </section>
</template>
