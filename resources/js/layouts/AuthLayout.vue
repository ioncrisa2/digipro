<script setup>
import NotificationCenter from '@/components/ui/notification/NotificationCenter.vue'
import BrandLockup from '@/components/brand/BrandLockup.vue'
</script>

<template>
  <div class="auth-shell min-h-dvh bg-slate-950 text-slate-50">
    <NotificationCenter />

    <a
      href="#auth-content"
      class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-3 focus:py-2 focus:text-sm focus:font-semibold focus:text-slate-950 focus:shadow"
    >
      Lewati ke konten
    </a>

    <main
      id="auth-content"
      tabindex="-1"
      class="mx-auto flex min-h-dvh max-w-7xl items-center justify-center px-6 pt-[calc(env(safe-area-inset-top)+3rem)] pb-[calc(env(safe-area-inset-bottom)+3rem)] sm:px-8"
    >
      <div class="w-full max-w-[560px]">
        <div class="rounded-3xl border border-white/10 bg-white p-1 shadow-[0_24px_80px_rgba(2,6,23,0.45)]">
          <div class="rounded-[1.4rem] bg-white px-6 py-8 sm:px-10 sm:py-10">
            <div class="mb-8 flex justify-center">
              <BrandLockup
                wrapper-class="border border-slate-200 bg-white shadow-sm"
                logo-class="w-[220px] sm:w-[248px]"
              />
            </div>

            <slot></slot>
          </div>
        </div>

        <p class="mt-5 text-center text-xs text-white/60">
          Butuh bantuan? Hubungi support DigiPro by KJPP HJAR di support@digipro.tech
        </p>
      </div>
    </main>
  </div>
</template>
