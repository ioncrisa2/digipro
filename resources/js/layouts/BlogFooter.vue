<script setup>
import { Link } from '@inertiajs/vue3'
import { ArrowRight } from 'lucide-vue-next'
import BrandLockup from '@/components/brand/BrandLockup.vue'
import NotificationCenter from '@/components/ui/notification/NotificationCenter.vue'

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
    <NotificationCenter />

    <footer class="border-t border-black/5 bg-[#f0ebe1]">
      <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-[1.4fr_0.8fr_0.8fr]">
          <div class="max-w-xl space-y-5">
            <div class="inline-flex items-center">
              <BrandLockup
                wrapper-class="border border-black/5 bg-white shadow-sm"
                logo-class="w-[220px] sm:w-[248px]"
              />
            </div>

            <h3 class="font-['Space_Grotesk'] text-2xl font-semibold tracking-tight text-slate-950">
              Insight properti, regulasi, dan pembacaan pasar.
            </h3>

            <p class="max-w-lg text-sm leading-7 text-slate-600">
              Kanal editorial DigiPro by KJPP HJAR untuk membahas valuasi properti, konteks pasar, dan hal-hal operasional yang
              relevan untuk pengguna platform.
            </p>

            <Link
              href="/"
              class="inline-flex items-center gap-2 rounded-full bg-slate-950 px-5 py-3 text-sm font-medium text-white transition hover:bg-slate-800"
            >
              Masuk ke DigiPro by KJPP HJAR
              <ArrowRight class="h-4 w-4" />
            </Link>
          </div>

          <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">
              Journal
            </p>
            <div class="mt-4 space-y-3">
              <Link
                v-for="link in footerLinks.journal"
                :key="link.label"
                :href="link.href"
                class="block text-sm text-slate-700 transition hover:text-slate-950"
              >
                {{ link.label }}
              </Link>
            </div>
          </div>

          <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">
              Platform
            </p>
            <div class="mt-4 space-y-3">
              <Link
                v-for="link in footerLinks.platform"
                :key="link.label"
                :href="link.href"
                class="block text-sm text-slate-700 transition hover:text-slate-950"
              >
                {{ link.label }}
              </Link>
            </div>
          </div>
        </div>

        <div class="mt-12 flex flex-col gap-3 border-t border-black/5 pt-6 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
          <p>© {{ currentYear }} DigiPro by KJPP HJAR. Semua hak dilindungi.</p>
          <p>Editorial surface untuk insight valuasi properti digital.</p>
        </div>
      </div>
    </footer>
  </div>
</template>
