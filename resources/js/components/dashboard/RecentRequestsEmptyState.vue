<script setup>
import { FileText, Plus } from "lucide-vue-next";

defineProps({
  onNewRequest: {
    type: Function,
    required: true,
  },
});
</script>

<template>
  <div class="p-12 text-center">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 mb-4">
      <FileText class="w-8 h-8 text-slate-400" />
    </div>
    <h3 class="text-lg font-medium text-slate-900 mb-2">
      Belum Ada Permohonan
    </h3>
    <p class="text-sm text-slate-500 mb-6">
      Anda belum memiliki permohonan penilaian. Mulai dengan membuat permohonan baru.
    </p>
    <button
      @click="onNewRequest"
      class="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 text-white text-sm font-medium rounded-lg hover:bg-slate-800 transition"
    >
      <Plus class="w-4 h-4" />
      Buat Permohonan Baru
    </button>
  </div>
</template>
