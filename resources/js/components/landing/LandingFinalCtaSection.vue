<script setup>
import { router } from '@inertiajs/vue3'

import { Button } from '@/components/ui/button'
</script>

<template>
  <section class="relative overflow-hidden border-t border-slate-200 bg-slate-900 px-6 py-20 text-white md:px-10 md:py-24">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(255,255,255,0.18),_transparent_34%),radial-gradient(circle_at_bottom_right,_rgba(245,158,11,0.2),_transparent_28%)]" />

    <div class="relative mx-auto flex max-w-6xl flex-col gap-10 md:flex-row md:items-end md:justify-between">
      <div class="max-w-3xl">
        <div class="text-sm font-medium uppercase tracking-[0.28em] text-white/60">Mulai Lebih Mudah</div>
        <h2 class="mt-4 text-3xl font-semibold tracking-tight text-white md:text-5xl">
          Butuh penilaian properti yang lebih mudah diproses dan dipantau?
        </h2>
        <p class="mt-5 max-w-2xl text-base leading-7 text-white/70 md:text-lg">
          Ajukan permohonan secara online, unggah dokumen yang diperlukan, lalu pantau progresnya sampai laporan selesai.
        </p>
      </div>

      <div class="shrink-0">
        <Button
          size="lg"
          class="h-14 min-w-52 bg-white px-8 text-base font-semibold text-slate-900 hover:bg-slate-100 md:text-lg"
          @click="router.visit('/login')"
        >
          Mulai Pengajuan
        </Button>
      </div>
    </div>
  </section>
</template>
