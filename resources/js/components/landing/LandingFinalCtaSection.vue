<script setup>
import { router } from '@inertiajs/vue3'

import { Button } from '@/components/ui/button'
</script>

<template>
  <section class="px-6 py-24 text-center">
    <div class="mx-auto max-w-3xl rounded-[2.2rem] border border-slate-200 bg-gradient-to-br from-white to-slate-100 px-8 py-14 shadow-[0_30px_90px_rgba(15,23,42,0.08)]">
      <div class="text-sm font-medium uppercase tracking-[0.28em] text-slate-500">Final CTA</div>
      <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 md:text-5xl">Siap beralih ke workflow penilaian yang lebih visual dan terstruktur?</h2>
      <p class="mx-auto mt-5 max-w-2xl text-base leading-7 text-slate-600 md:text-lg">
        Struktur baru ini menjaga narasi lama tetap hidup, tetapi menambahkan image slots yang jauh lebih siap untuk materi visual final Anda.
      </p>
      <Button size="lg" class="mt-10 h-14 bg-slate-900 px-8 text-lg hover:bg-slate-800" @click="router.visit('/login')">
        Mulai Sekarang
      </Button>
    </div>
  </section>
</template>
