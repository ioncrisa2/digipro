<script setup>
import { Link } from '@inertiajs/vue3'
import BrandLockup from '@/components/brand/BrandLockup.vue'
</script>

<template>
  <div>
    <footer class="bg-[var(--landing-navy)] text-slate-100 py-10 px-6">
      <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-8">
        <div>
          <BrandLockup
            wrapper-class="shadow-[0_18px_40px_rgba(15,23,42,0.22)]"
            logo-class="w-[220px] sm:w-[248px]"
          />
          <p class="text-base leading-7 text-slate-300 mt-3">
            Platform digital untuk pengajuan, dokumen, status, dan review proses penilaian properti bersama KJPP Henricus Judi Adrianto & Rekan.
          </p>
          <p class="text-sm text-slate-500 mt-4">(c) 2026 DigiPro by KJPP HJAR. Seluruh hak dilindungi.</p>
        </div>

        <div>
          <div class="text-sm font-semibold uppercase tracking-[0.14em] text-[var(--landing-gold)]">Navigasi</div>
          <div class="mt-3 flex flex-col gap-2 text-base">
            <Link href="/" class="hover:text-white">Beranda</Link>
            <Link href="/contact" class="hover:text-white">Kontak</Link>
            <Link href="/login" class="hover:text-white">Masuk</Link>
          </div>
        </div>

        <div>
          <div class="text-sm font-semibold uppercase tracking-[0.14em] text-[var(--landing-gold)]">Legal</div>
          <div class="mt-3 flex flex-col gap-2 text-base">
            <Link href="/policy" class="hover:text-white">Kebijakan Privasi</Link>
            <Link href="/terms" class="hover:text-white">Syarat & Ketentuan</Link>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>
