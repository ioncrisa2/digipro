<script setup>
import { Building2, CheckCircle2, ShieldCheck, Sparkles } from 'lucide-vue-next'
</script>

<template>
  <div class="relative">
    <div class="flex items-center gap-3">
      <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/10 backdrop-blur-sm border border-white/20">
        <Building2 class="h-6 w-6 text-white" />
      </div>
      <div class="leading-tight">
        <div class="text-2xl font-semibold tracking-tight">DigiPro</div>
        <div class="text-xs text-white/70">Valuation Platform</div>
      </div>
    </div>

    <div class="mt-16 space-y-6">
      <h2 class="text-3xl font-semibold leading-tight">
        Kelola appraisal properti dengan lebih cepat dan terstruktur.
      </h2>
      <p class="text-sm text-white/70">
        Portal DigiPro membantu tim appraisal, reviewer, dan manajemen memantau proses dari awal hingga laporan final.
      </p>

      <div class="space-y-4">
        <div class="flex items-start gap-3">
          <CheckCircle2 class="w-5 h-5 text-emerald-300 mt-0.5" />
          <div>
            <div class="text-sm font-semibold">Proses lebih ringkas</div>
            <div class="text-xs text-white/70">Request, desk appraisal, dan laporan terhubung dalam satu alur kilat.</div>
          </div>
        </div>
        <div class="flex items-start gap-3">
          <ShieldCheck class="w-5 h-5 text-emerald-300 mt-0.5" />
          <div>
            <div class="text-sm font-semibold">Data aman</div>
            <div class="text-xs text-white/70">Dokumen appraisal terlindungi dengan akses berbasis peran.</div>
          </div>
        </div>
        <div class="flex items-start gap-3">
          <Sparkles class="w-5 h-5 text-emerald-300 mt-0.5" />
          <div>
            <div class="text-sm font-semibold">Siap audit</div>
            <div class="text-xs text-white/70">Setiap aktivitas terekam untuk kebutuhan legal dan SPI.</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
