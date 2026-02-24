<script setup>
import NotificationCenter from '@/components/ui/notification/NotificationCenter.vue'
import AuthHero from '@/components/auth/AuthHero.vue'
</script>

<template>
  <div class="auth-shell min-h-screen bg-[#f7f4ef] text-slate-900">
    <NotificationCenter />
    <div class="min-h-screen grid lg:grid-cols-[1.1fr_0.9fr]">
      <aside class="hidden lg:flex flex-col justify-between p-10 bg-slate-900 text-white relative overflow-hidden">
        <div class="absolute -top-32 right-0 h-72 w-72 rounded-full bg-emerald-500/20 blur-3xl"></div>
        <div class="absolute -bottom-32 left-0 h-72 w-72 rounded-full bg-sky-400/20 blur-3xl"></div>

        <AuthHero />

        <div class="relative text-xs text-white/60">
          DigiPro (c) 2026. All rights reserved.
        </div>
      </aside>

      <main class="flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-md">
          <div class="rounded-2xl border border-slate-200 bg-white/95 shadow-xl shadow-slate-200/40 p-8">
            <slot></slot>
          </div>
          <p class="text-xs text-slate-500 text-center mt-6">
            Butuh bantuan? Hubungi support@digipro.tech
          </p>
        </div>
      </main>
    </div>
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
