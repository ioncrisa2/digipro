<script setup>
import { Link } from "@inertiajs/vue3";

const year = new Date().getFullYear();
</script>

<template>
  <footer class="border-t border-slate-200 bg-white px-4 py-4 text-xs text-slate-500 lg:px-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
        DIGIPRO (c) {{ year }}. All rights reserved.
      </div>
      <div class="flex flex-wrap items-center gap-4">
        <Link href="/terms" class="hover:text-slate-700">Syarat & Ketentuan</Link>
        <Link href="/policy" class="hover:text-slate-700">Kebijakan Privasi</Link>
        <Link href="/contact" class="hover:text-slate-700">Kontak</Link>
      </div>
    </div>
  </footer>
</template>
