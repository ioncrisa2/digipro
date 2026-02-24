<script setup>
import { Link } from "@inertiajs/vue3";
import UserDashboardLayout from "@/layouts/UserDashboardLayout.vue";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { FileText, FileDown, MapPin, Calendar, ArrowLeft } from "lucide-vue-next";

const props = defineProps({
  report: { type: Object, required: true },
});

const statusTone = (status) => {
  const s = String(status || "").toLowerCase();
  if (s.includes("siap") || s.includes("selesai")) return "default";
  if (s.includes("menunggu")) return "secondary";
  return "outline";
};
</script>

<template>
  <UserDashboardLayout>
    <template #title>Detail Laporan</template>

    <div class="max-w-5xl space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <Link :href="route('reports.index')" class="text-slate-600 hover:text-slate-900">
            <ArrowLeft class="h-5 w-5" />
          </Link>
          <div>
            <h1 class="text-2xl font-semibold text-slate-900">Detail Laporan</h1>
            <p class="text-sm text-slate-500">
              {{ report.request_number }} • {{ report.client }}
            </p>
          </div>
        </div>
        <Badge :variant="statusTone(report.status)">{{ report.status }}</Badge>
      </div>

      <Card class="shadow-sm">
        <CardHeader>
          <CardTitle class="text-base">Informasi Laporan</CardTitle>
          <CardDescription>Ringkasan detail permohonan penilaian</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-3 text-sm">
            <div class="flex items-center gap-2 text-slate-600">
              <FileText class="h-4 w-4 text-slate-400" />
              <span class="font-medium text-slate-900">{{ report.property }}</span>
            </div>
            <div class="flex items-start gap-2 text-slate-600">
              <MapPin class="h-4 w-4 text-slate-400 mt-0.5" />
              <span class="text-slate-700">{{ report.address }}</span>
            </div>
            <div class="flex items-center gap-2 text-slate-600">
              <Calendar class="h-4 w-4 text-slate-400" />
              <span class="text-slate-700">Update: {{ report.updated_at }}</span>
            </div>
          </div>

          <Separator />

          <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div
              v-for="doc in report.documents"
              :key="doc.type"
              class="rounded-xl border border-l-4 border-l-slate-900 p-4 space-y-2"
            >
              <div class="text-xs text-slate-500 uppercase tracking-wide">{{ doc.label }}</div>
              <div class="font-medium text-slate-900">{{ doc.name }}</div>
              <div class="text-xs text-slate-500">{{ doc.size }}</div>
              <Button variant="outline" size="sm" class="mt-2 w-full" disabled>
                <FileDown class="mr-2 h-4 w-4" />
                Unduh
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </UserDashboardLayout>
</template>
