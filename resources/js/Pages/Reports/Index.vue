<script setup>
import { computed, ref, watch } from "vue";
import { Link } from "@inertiajs/vue3";
import UserDashboardLayout from "@/layouts/UserDashboardLayout.vue";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  FileText,
  FileDown,
  MapPin,
  Calendar,
  ArrowRight,
  Search,
  ChevronLeft,
  ChevronRight,
} from "lucide-vue-next";

const props = defineProps({
  reports: { type: Array, default: () => [] },
});

const items = computed(() => props.reports || []);
const searchQuery = ref("");
const statusFilter = ref("all");
const fromDate = ref("");
const toDate = ref("");

const statusOptions = [
  { value: "all", label: "Semua Status" },
  { value: "siap", label: "Laporan Siap" },
  { value: "menunggu", label: "Menunggu" },
  { value: "selesai", label: "Selesai" },
];

const statusTone = (status) => {
  const s = String(status || "").toLowerCase();
  if (s.includes("siap") || s.includes("selesai")) return "default";
  if (s.includes("menunggu")) return "secondary";
  return "outline";
};

const toDateValue = (value) => {
  if (!value) return null;
  const d = new Date(`${value}T00:00:00`);
  return Number.isNaN(d.getTime()) ? null : d;
};

const filteredItems = computed(() => {
  let rows = [...items.value];
  const q = searchQuery.value.trim().toLowerCase();
  if (q) {
    rows = rows.filter((item) => {
      return [
        item.request_number,
        item.client,
        item.report_type,
        item.property,
        item.address,
      ]
        .filter(Boolean)
        .some((val) => String(val).toLowerCase().includes(q));
    });
  }

  if (statusFilter.value !== "all") {
    rows = rows.filter((item) =>
      String(item.status || "").toLowerCase().includes(statusFilter.value)
    );
  }

  const from = toDateValue(fromDate.value);
  const to = toDateValue(toDate.value);
  if (from || to) {
    rows = rows.filter((item) => {
      const dt = toDateValue(item.updated_at);
      if (!dt) return false;
      if (from && dt < from) return false;
      if (to && dt > to) return false;
      return true;
    });
  }

  return rows;
});

const applyPreset = (days) => {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const from = new Date(today);
  from.setDate(from.getDate() - days);
  fromDate.value = from.toISOString().slice(0, 10);
  toDate.value = today.toISOString().slice(0, 10);
};

const applyThisMonth = () => {
  const now = new Date();
  const start = new Date(now.getFullYear(), now.getMonth(), 1);
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
  fromDate.value = start.toISOString().slice(0, 10);
  toDate.value = end.toISOString().slice(0, 10);
};

const hasActiveFilters = computed(() => {
  return Boolean(searchQuery.value || statusFilter.value !== "all" || fromDate.value || toDate.value);
});

const resetFilters = () => {
  searchQuery.value = "";
  statusFilter.value = "all";
  fromDate.value = "";
  toDate.value = "";
};

const pageSizes = [6, 9, 12];
const pageSize = ref(6);
const currentPage = ref(1);

const totalPages = computed(() => Math.max(1, Math.ceil(filteredItems.value.length / pageSize.value)));

const pagedItems = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value;
  return filteredItems.value.slice(start, start + pageSize.value);
});

watch([searchQuery, statusFilter, fromDate, toDate, pageSize], () => {
  currentPage.value = 1;
});
</script>

<template>
  <UserDashboardLayout>
    <template #title>Laporan Penilaian</template>

    <div class="w-full space-y-6">
      <div class="flex flex-col gap-2">
        <h1 class="text-2xl font-semibold text-slate-900">Laporan Penilaian</h1>
        <p class="text-sm text-slate-500">
          Unduh dokumen penawaran, laporan penilaian, dan invoice pembayaran.
        </p>
      </div>

      <Card class="shadow-sm">
        <CardContent class="p-4 space-y-4">
          <div class="space-y-3">
            <div class="relative w-full">
              <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
              <Input
                v-model="searchQuery"
                placeholder="Cari request, client, atau properti..."
                class="pl-10 h-10 bg-white border-slate-200"
              />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] gap-3 items-center">
              <Select v-model="statusFilter">
                <SelectTrigger class="w-full h-10">
                  <SelectValue placeholder="Semua Status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <div class="text-xs text-slate-500">
                Filter status untuk melihat laporan tertentu.
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <Input v-model="fromDate" type="date" class="h-10 bg-white border-slate-200" />
              <Input v-model="toDate" type="date" class="h-10 bg-white border-slate-200" />
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
            <span>Preset:</span>
            <Button variant="outline" size="sm" class="h-8 px-3" @click="applyPreset(7)">
              7 Hari
            </Button>
            <Button variant="outline" size="sm" class="h-8 px-3" @click="applyPreset(30)">
              30 Hari
            </Button>
            <Button variant="outline" size="sm" class="h-8 px-3" @click="applyThisMonth">
              Bulan Ini
            </Button>
            <Button v-if="hasActiveFilters" variant="ghost" size="sm" class="h-8 px-2" @click="resetFilters">
              Reset Filter
            </Button>
          </div>
        </CardContent>
      </Card>

      <div v-if="!pagedItems.length" class="rounded-xl border p-6 text-sm text-slate-500">
        Belum ada laporan sesuai filter.
      </div>

      <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <Card v-for="item in pagedItems" :key="item.id" class="shadow-sm">
          <CardHeader>
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <CardTitle class="text-base">{{ item.request_number }}</CardTitle>
                <CardDescription class="text-sm text-slate-500">
                  {{ item.client }} • {{ item.report_type }}
                </CardDescription>
              </div>
              <Badge :variant="statusTone(item.status)">{{ item.status }}</Badge>
            </div>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3 text-sm">
              <div class="flex items-center gap-2 text-slate-600">
                <FileText class="h-4 w-4 text-slate-400" />
                <span class="font-medium text-slate-900">{{ item.property }}</span>
              </div>
              <div class="flex items-start gap-2 text-slate-600">
                <MapPin class="h-4 w-4 text-slate-400 mt-0.5" />
                <span class="text-slate-700">{{ item.address }}</span>
              </div>
              <div class="flex items-center gap-2 text-slate-600">
                <Calendar class="h-4 w-4 text-slate-400" />
                <span class="text-slate-700">Update: {{ item.updated_at }}</span>
              </div>
            </div>

            <div class="rounded-lg border p-3 text-xs text-slate-500">
              Dokumen tersedia: Penawaran, Laporan Penilaian, Invoice Pembayaran.
            </div>

            <div class="flex items-center justify-between">
              <Button variant="outline" size="sm" disabled>
                <FileDown class="mr-2 h-4 w-4" />
                Unduh Semua
              </Button>
              <Link
                :href="route('reports.show', item.id)"
                class="text-sm text-slate-700 hover:text-slate-900 flex items-center gap-1"
              >
                Lihat Detail <ArrowRight class="h-4 w-4" />
              </Link>
            </div>
          </CardContent>
        </Card>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="text-xs text-slate-500">
          Menampilkan {{ pagedItems.length }} dari {{ filteredItems.length }} laporan
        </div>
        <div class="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            :disabled="currentPage === 1"
            @click="currentPage = Math.max(1, currentPage - 1)"
          >
            <ChevronLeft class="h-4 w-4" />
            Sebelumnya
          </Button>
          <div class="text-xs text-slate-600">Hal {{ currentPage }} dari {{ totalPages }}</div>
          <Button
            variant="outline"
            size="sm"
            :disabled="currentPage === totalPages"
            @click="currentPage = Math.min(totalPages, currentPage + 1)"
          >
            Selanjutnya
            <ChevronRight class="h-4 w-4" />
          </Button>
        </div>
      </div>
    </div>
  </UserDashboardLayout>
</template>
