<script setup>
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Plus, List } from "lucide-vue-next";

defineProps({
  onNewRequest: {
    type: Function,
    required: true,
  },
  onRequestList: {
    type: Function,
    required: true,
  },
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
      <button
        @click="onNewRequest"
        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-medium text-white transition-colors hover:bg-slate-800"
      >
        <Plus class="w-4 h-4" />
        Ajukan Permohonan Baru
      </button>

      <button
        @click="onRequestList"
        class="flex w-full items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
      >
        <List class="w-4 h-4" />
        Lihat Semua Permohonan
      </button>
    </CardContent>
  </Card>
</template>
