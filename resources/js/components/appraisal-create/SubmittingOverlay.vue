<script setup>
import { Loader2 } from "lucide-vue-next";

defineProps({
  show: {
    type: Boolean,
    default: false,
  },
});
</script>

<template>
  <div
    v-if="show"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 backdrop-blur-sm"
    aria-live="polite"
    aria-busy="true"
  >
    <div class="bg-white rounded-xl shadow-lg px-6 py-5 flex flex-col items-center gap-2">
      <Loader2 class="w-6 h-6 animate-spin text-slate-700" />
      <div class="text-sm font-medium text-slate-900">Mengirim permohonan...</div>
      <div class="text-xs text-slate-500">Mohon tunggu, proses sedang berjalan.</div>
    </div>
  </div>
</template>
