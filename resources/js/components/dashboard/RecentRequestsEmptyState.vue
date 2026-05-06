<script setup>
import { FileText, Plus } from "lucide-vue-next";
import { Link } from "@inertiajs/vue3";

defineProps({
  newRequestHref: { type: String, required: true },
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
    <Link
      :href="newRequestHref"
      class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-900/20 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
    >
      <Plus class="w-4 h-4" />
      Buat Permohonan Baru
    </Link>
  </div>
</template>
