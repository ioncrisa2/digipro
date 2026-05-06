<script setup>
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Plus, List } from "lucide-vue-next";
import { Link } from "@inertiajs/vue3";

defineProps({
  newRequestHref: { type: String, required: true },
  requestListHref: { type: String, required: true },
});
</script>

<template>
  <Card class="rounded-[2rem] border-slate-200 shadow-none">
    <CardHeader class="border-b border-slate-200 px-6 py-5">
      <div class="space-y-1">
        <CardTitle class="text-lg font-semibold text-slate-900">
          Aksi Cepat
        </CardTitle>
        <p class="text-sm text-slate-500">
          Buka daftar request atau mulai permohonan baru dari satu tempat.
        </p>
      </div>
    </CardHeader>
    <CardContent class="space-y-3 p-6">
      <Link
        :href="newRequestHref"
        class="flex min-h-11 w-full items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-medium text-white transition-colors hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-900/20 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
      >
        <Plus class="w-4 h-4" />
        Ajukan Permohonan Baru
      </Link>

      <Link
        :href="requestListHref"
        class="flex min-h-11 w-full items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-900/15 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
      >
        <List class="w-4 h-4" />
        Lihat Semua Permohonan
      </Link>
    </CardContent>
  </Card>
</template>
