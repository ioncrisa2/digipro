<script setup>
import { Link } from '@inertiajs/vue3'

import { Button } from '@/components/ui/button'
</script>

<template>
  <section class="relative overflow-hidden border-t border-slate-200 bg-[var(--landing-navy)] px-6 py-16 text-white md:px-10 md:py-20">
    <div class="relative mx-auto flex max-w-6xl flex-col gap-10 md:flex-row md:items-center md:justify-between">
      <div class="max-w-3xl">
        <div class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--landing-gold)]">Mulai pengajuan</div>
        <h2 class="mt-4 text-balance text-3xl font-bold leading-tight text-white md:text-5xl">
          Siapkan proses appraisal dalam alur kerja yang terdokumentasi.
        </h2>
        <p class="mt-5 max-w-2xl text-pretty text-base leading-7 text-white/70 md:text-lg">
          Masuk ke portal untuk mengajukan permohonan, mengirim dokumen, dan memantau status pekerjaan bersama KJPP HJAR.
        </p>
      </div>

      <div class="shrink-0">
        <Button
          as-child
          size="lg"
          class="h-14 min-w-52 bg-[var(--landing-gold)] px-8 text-base font-semibold text-[var(--landing-navy)] hover:bg-[#d5b66f] md:text-lg"
        >
          <Link href="/login">Mulai Pengajuan</Link>
        </Button>
      </div>
    </div>
  </section>
</template>
