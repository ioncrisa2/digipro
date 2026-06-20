<script setup>
import { Link } from '@inertiajs/vue3'

import { Button } from '@/components/ui/button'
import { ArrowRight, Building2, FileText, Landmark, ShieldCheck } from 'lucide-vue-next'
</script>

<template>
  <section class="relative isolate overflow-hidden border-b border-slate-200 bg-[var(--landing-bg)]">
    <div class="mx-auto grid max-w-7xl gap-12 px-6 py-16 md:py-20 lg:grid-cols-[0.92fr_1.08fr] lg:items-center lg:py-24">
      <div class="max-w-3xl space-y-7">
        <div class="inline-flex items-center gap-3 rounded-xl border border-[var(--landing-gold)]/35 bg-white px-4 py-2 text-sm font-semibold text-[var(--landing-navy)] shadow-sm">
          <Landmark class="h-4 w-4 text-[var(--landing-gold-dark)]" />
          DIGIPRO by KJPP HJAR
        </div>

        <div class="space-y-5">
          <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">
            Platform penilaian properti
          </p>
          <h1 class="max-w-4xl text-balance text-4xl font-bold leading-[1.02] text-[var(--landing-navy)] md:text-6xl">
            Platform digital untuk proses penilaian properti yang lebih terstruktur.
          </h1>
          <p class="max-w-2xl text-pretty text-base leading-8 text-slate-700 md:text-lg">
            Ajukan permohonan, unggah dokumen, pantau status, dan kelola proses appraisal dalam satu alur kerja yang terdokumentasi.
          </p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
          <Button as-child size="lg" class="h-12 bg-[var(--landing-navy)] px-7 text-base font-semibold text-white hover:bg-slate-800">
            <Link href="/login">
              Mulai Pengajuan
              <ArrowRight class="ml-2 h-4 w-4" />
            </Link>
          </Button>
          <Button as-child variant="outline" size="lg" class="h-12 border-slate-300 bg-white px-7 text-base font-semibold text-[var(--landing-navy)] hover:bg-slate-50">
            <a href="#process">Lihat Alur Kerja</a>
          </Button>
        </div>

        <div class="grid max-w-2xl gap-3 border-t border-slate-200 pt-5 text-sm text-slate-600 sm:grid-cols-3">
          <div>
            <div class="font-semibold text-[var(--landing-navy)]">KJPP</div>
            <div>alur kerja appraisal</div>
          </div>
          <div>
            <div class="font-semibold text-[var(--landing-navy)]">Institusi finansial</div>
            <div>monitoring permohonan</div>
          </div>
          <div>
            <div class="font-semibold text-[var(--landing-navy)]">Pemberi tugas</div>
            <div>dokumen dan status</div>
          </div>
        </div>
      </div>

      <div class="relative">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_18px_44px_rgba(15,23,42,0.10)]">
          <div class="border-b border-slate-200 bg-[var(--landing-navy)] px-5 py-4 text-white">
            <div class="flex items-center justify-between gap-4">
              <div>
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/55">Ruang kerja appraisal</div>
                <div class="mt-1 text-lg font-semibold">Permohonan Penilaian</div>
              </div>
              <div class="rounded-lg border border-white/15 px-3 py-1 text-xs font-semibold text-white/80">
                Terdokumentasi
              </div>
            </div>
          </div>

          <div class="grid gap-0 lg:grid-cols-[0.92fr_1.08fr]">
            <div class="border-b border-slate-200 bg-slate-50 p-5 lg:border-b-0 lg:border-r">
              <div class="space-y-4">
                <div class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4">
                  <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[var(--landing-gold-soft)] text-[var(--landing-gold-dark)]">
                    <FileText class="h-5 w-5" />
                  </div>
                  <div>
                    <div class="text-sm font-semibold text-slate-950">Dokumen legal dan objek</div>
                    <div class="mt-1 text-xs leading-5 text-slate-500">Sertifikat, identitas, foto objek, dan dokumen pendukung.</div>
                  </div>
                </div>

                <div class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4">
                  <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                    <Building2 class="h-5 w-5" />
                  </div>
                  <div>
                    <div class="text-sm font-semibold text-slate-950">Objek penilaian properti</div>
                    <div class="mt-1 text-xs leading-5 text-slate-500">Rumah, ruko, tanah, bangunan komersial, dan agunan.</div>
                  </div>
                </div>

                <div class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4">
                  <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                    <ShieldCheck class="h-5 w-5" />
                  </div>
                  <div>
                    <div class="text-sm font-semibold text-slate-950">Review KJPP</div>
                    <div class="mt-1 text-xs leading-5 text-slate-500">Verifikasi, kajian penilai, offer, dan status laporan.</div>
                  </div>
                </div>
              </div>
            </div>

            <div class="p-5">
              <svg
                viewBox="0 0 560 420"
                role="img"
                aria-label="Ilustrasi proses appraisal properti dan dokumen"
                class="h-full min-h-[320px] w-full"
              >
                <rect x="42" y="48" width="262" height="326" rx="18" fill="#F8FAFC" stroke="#CBD5E1" />
                <rect x="70" y="84" width="120" height="14" rx="7" fill="#0B1220" />
                <rect x="70" y="118" width="180" height="10" rx="5" fill="#CBD5E1" />
                <rect x="70" y="144" width="160" height="10" rx="5" fill="#E2E8F0" />
                <rect x="70" y="190" width="196" height="76" rx="12" fill="#FFFFFF" stroke="#E2E8F0" />
                <path d="M95 238L128 210L155 232L174 217L242 254H95V238Z" fill="#C8A45D" opacity="0.82" />
                <rect x="70" y="292" width="196" height="42" rx="10" fill="#F5E8C8" stroke="#E6D09B" />
                <rect x="92" y="307" width="102" height="10" rx="5" fill="#8A6A2C" opacity="0.75" />

                <rect x="306" y="86" width="206" height="250" rx="18" fill="#0B1220" />
                <rect x="330" y="116" width="112" height="12" rx="6" fill="#FFFFFF" opacity="0.86" />
                <rect x="330" y="150" width="154" height="10" rx="5" fill="#94A3B8" opacity="0.7" />
                <rect x="330" y="178" width="126" height="10" rx="5" fill="#64748B" opacity="0.8" />
                <rect x="330" y="224" width="58" height="58" rx="12" fill="#FFFFFF" opacity="0.08" />
                <rect x="404" y="224" width="58" height="58" rx="12" fill="#FFFFFF" opacity="0.08" />
                <path d="M343 257L357 241L373 258" stroke="#C8A45D" stroke-width="9" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M418 253L431 266L450 239" stroke="#C8A45D" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" />
                <rect x="330" y="306" width="154" height="10" rx="5" fill="#C8A45D" opacity="0.95" />

                <path d="M252 102C292 96 312 111 330 140" stroke="#C8A45D" stroke-width="4" stroke-linecap="round" fill="none" />
                <path d="M331 140L315 135L326 124" stroke="#C8A45D" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                <path d="M292 316C262 326 237 318 216 292" stroke="#334155" stroke-width="4" stroke-linecap="round" fill="none" opacity="0.55" />
              </svg>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
