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
  <Card class="shadow-sm">
    <CardHeader class="border-b bg-slate-50/50">
      <CardTitle class="text-lg font-semibold text-slate-900">
        Aksi Cepat
      </CardTitle>
    </CardHeader>
    <CardContent class="p-6 space-y-3">
      <button
        @click="onNewRequest"
        class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm"
      >
        <Plus class="w-4 h-4" />
        Ajukan Permohonan Baru
      </button>

      <button
        @click="onRequestList"
        class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-white border border-slate-200 text-slate-700 text-sm font-medium hover:bg-slate-50 transition-colors"
      >
        <List class="w-4 h-4" />
        Lihat Semua Permohonan
      </button>
    </CardContent>
  </Card>
</template>
