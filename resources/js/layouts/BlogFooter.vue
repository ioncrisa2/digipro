<script setup>
import { Link } from '@inertiajs/vue3'
import { ArrowRight } from 'lucide-vue-next'
import BrandLockup from '@/components/brand/BrandLockup.vue'

const currentYear = new Date().getFullYear()

const footerLinks = {
  journal: [
    { label: 'Semua Artikel', href: '/artikel' },
    { label: 'Kontak', href: '/contact' },
    { label: 'Kebijakan Privasi', href: '/policy' },
  ],
  platform: [
    { label: 'Buka DigiPro by KJPP HJAR', href: '/' },
    { label: 'Syarat & Ketentuan', href: '/terms' },
  ],
}
</script>

<template>
  <div>
    <footer class="border-t border-slate-200 bg-[var(--landing-navy)] text-slate-100">
      <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-[1.4fr_0.8fr_0.8fr]">
          <div class="max-w-xl space-y-5">
            <div class="inline-flex items-center">
              <BrandLockup
                wrapper-class="border border-black/5 bg-white shadow-sm"
                logo-class="w-[220px] sm:w-[248px]"
              />
            </div>

            <h3 class="text-balance text-2xl font-bold text-white">
              Insight appraisal, regulasi, dan pembacaan pasar.
            </h3>

            <p class="max-w-lg text-pretty text-sm leading-7 text-slate-300">
              Kanal editorial DigiPro by KJPP HJAR untuk membahas valuasi properti, konteks pasar, dan hal-hal operasional yang
              relevan untuk pengguna platform.
            </p>

            <Link
              href="/"
              class="inline-flex items-center gap-2 rounded-xl bg-[var(--landing-gold)] px-5 py-3 text-sm font-semibold text-[var(--landing-navy)] transition hover:bg-[#d5b66f]"
            >
              Masuk ke DigiPro by KJPP HJAR
              <ArrowRight class="h-4 w-4" />
            </Link>
          </div>

          <div>
            <p class="text-[11px] font-semibold uppercase text-[var(--landing-gold)]">
              Journal
            </p>
            <div class="mt-4 space-y-3">
              <Link
                v-for="link in footerLinks.journal"
                :key="link.label"
                :href="link.href"
                class="block text-sm text-slate-300 transition hover:text-white"
              >
                {{ link.label }}
              </Link>
            </div>
          </div>

          <div>
            <p class="text-[11px] font-semibold uppercase text-[var(--landing-gold)]">
              Platform
            </p>
            <div class="mt-4 space-y-3">
              <Link
                v-for="link in footerLinks.platform"
                :key="link.label"
                :href="link.href"
                class="block text-sm text-slate-300 transition hover:text-white"
              >
                {{ link.label }}
              </Link>
            </div>
          </div>
        </div>

        <div class="mt-12 flex flex-col gap-3 border-t border-white/10 pt-6 text-sm text-slate-400 sm:flex-row sm:items-center sm:justify-between">
          <p>(c) {{ currentYear }} DigiPro by KJPP HJAR. Semua hak dilindungi.</p>
          <p>Kanal editorial untuk insight penilaian properti.</p>
        </div>
      </div>
    </footer>
  </div>
</template>
