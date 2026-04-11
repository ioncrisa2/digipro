<script setup>
import { CheckCircle2, ShieldCheck, Sparkles } from 'lucide-vue-next'
import BrandLockup from '@/components/brand/BrandLockup.vue'
</script>

<template>
  <div class="relative">
    <div class="flex items-center">
      <BrandLockup
        wrapper-class="shadow-[0_20px_40px_rgba(2,6,23,0.28)]"
        logo-class="w-[232px] sm:w-[272px]"
      />
    </div>

    <div class="mt-16 space-y-6">
      <h2 class="text-3xl font-semibold leading-tight">
        Kelola appraisal properti dengan lebih cepat dan terstruktur.
      </h2>
      <p class="text-sm text-white/70">
        Portal DigiPro by KJPP HJAR membantu tim appraisal, reviewer, dan manajemen memantau proses dari awal hingga laporan final.
      </p>

      <div class="space-y-4">
        <div class="flex items-start gap-3">
          <CheckCircle2 class="w-5 h-5 text-emerald-300 mt-0.5" />
          <div>
            <div class="text-sm font-semibold">Proses lebih ringkas</div>
            <div class="text-xs text-white/70">Request, Appraisal Review, dan laporan terhubung dalam satu alur kilat.</div>
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
