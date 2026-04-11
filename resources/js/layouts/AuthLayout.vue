<script setup>
import NotificationCenter from '@/components/ui/notification/NotificationCenter.vue'
import BrandLockup from '@/components/brand/BrandLockup.vue'
</script>

<template>
  <div class="auth-shell relative min-h-screen overflow-hidden bg-[#071319] text-slate-900">
    <NotificationCenter />

    <div class="pointer-events-none absolute inset-0">
      <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(34,197,94,0.18),transparent_30%),radial-gradient(circle_at_20%_20%,rgba(45,212,191,0.16),transparent_28%),radial-gradient(circle_at_80%_15%,rgba(56,189,248,0.16),transparent_24%),linear-gradient(180deg,#071319_0%,#081924_48%,#071119_100%)]"></div>
      <div class="absolute inset-0 opacity-40 [background-image:linear-gradient(rgba(148,163,184,0.08)_1px,transparent_1px),linear-gradient(90deg,rgba(148,163,184,0.08)_1px,transparent_1px)] [background-size:72px_72px] [mask-image:radial-gradient(circle_at_center,black,transparent_78%)]"></div>
      <div class="absolute -left-20 top-20 h-72 w-72 rounded-full bg-emerald-300/10 blur-3xl"></div>
      <div class="absolute -right-16 bottom-10 h-80 w-80 rounded-full bg-sky-300/10 blur-3xl"></div>
    </div>

    <main class="relative flex min-h-screen items-center justify-center px-6 py-10 sm:px-8">
      <div class="w-full max-w-[560px]">
        <div class="relative">
          <div class="absolute inset-6 rounded-[2rem] bg-emerald-300/10 blur-3xl"></div>

          <div class="relative overflow-hidden rounded-[2rem] border border-white/12 bg-white/[0.94] p-1 shadow-[0_35px_120px_rgba(2,6,23,0.55)]">
            <div class="rounded-[calc(2rem-4px)] border border-slate-200/70 bg-white/90 px-6 py-8 sm:px-9 sm:py-10">
              <div class="mb-8 flex justify-center">
                <div class="flex w-fit items-center rounded-[1.25rem] border border-emerald-950/10 bg-[#18332f] p-2 text-white shadow-[0_18px_40px_rgba(8,15,23,0.12)]">
                  <BrandLockup
                    wrapper-class="shadow-[0_10px_24px_rgba(2,6,23,0.24)]"
                    logo-class="w-[220px] sm:w-[248px]"
                  />
                </div>
              </div>

              <slot></slot>
            </div>
          </div>
        </div>

        <p class="mt-5 text-center text-xs text-white/60">
          Butuh bantuan? Hubungi support DigiPro by KJPP HJAR di support@digipro.tech
        </p>
      </div>
    </main>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600&family=Space+Grotesk:wght@400;500;600;700&display=swap');

:global(.auth-shell) {
  font-family: 'Instrument Sans', sans-serif;
}

:global(.auth-shell h1),
:global(.auth-shell h2),
:global(.auth-shell h3),
:global(.auth-shell .auth-title) {
  font-family: 'Space Grotesk', sans-serif;
}
</style>
