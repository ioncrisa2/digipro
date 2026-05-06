<script setup>
import { Link } from '@inertiajs/vue3'
import BrandLockup from '@/components/brand/BrandLockup.vue'
</script>

<template>
  <div>
    <footer class="bg-slate-900 text-slate-100 py-8 px-6">
      <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-8">
        <div>
          <BrandLockup
            wrapper-class="shadow-[0_18px_40px_rgba(15,23,42,0.22)]"
            logo-class="w-[220px] sm:w-[248px]"
          />
          <p class="text-base text-slate-300 mt-2">
            Produk digital resmi untuk proses penilaian properti oleh KJPP Henricus Judi Adrianto & Rekan.
          </p>
          <p class="text-sm text-slate-500 mt-4">(c) 2026 DigiPro by KJPP HJAR. All rights reserved.</p>
        </div>

        <div>
          <div class="text-sm uppercase text-slate-400">Navigasi</div>
          <div class="mt-3 flex flex-col gap-2 text-base">
            <Link href="/" class="hover:text-white">Beranda</Link>
            <Link href="/contact" class="hover:text-white">Kontak</Link>
            <Link href="/login" class="hover:text-white">Login</Link>
          </div>
        </div>

        <div>
          <div class="text-sm uppercase text-slate-400">Legal</div>
          <div class="mt-3 flex flex-col gap-2 text-base">
            <Link href="/policy" class="hover:text-white">Kebijakan Privasi</Link>
            <Link href="/terms" class="hover:text-white">Syarat & Ketentuan</Link>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>
