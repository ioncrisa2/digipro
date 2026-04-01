<script setup>
import { computed, ref } from "vue";
import { Link } from "@inertiajs/vue3";
import UserDashboardLayout from "@/layouts/UserDashboardLayout.vue";
import AdminDataTable from "@/components/admin/AdminDataTable.vue";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  ArrowRight,
  Calendar,
  MapPin,
  Search,
} from "lucide-vue-next";

const props = defineProps({
  reports: { type: Array, default: () => [] },
});

const items = computed(() => props.reports || []);
const searchQuery = ref("");
const statusFilter = ref("all");
const fromDate = ref("");
const toDate = ref("");

const columns = [
  { key: "request_number", label: "Request", sortable: true, cellClass: "min-w-[180px]" },
  { key: "client", label: "Client", sortable: true, cellClass: "min-w-[180px]" },
  { key: "address", label: "Alamat", sortable: true, cellClass: "min-w-[240px]" },
  { key: "status", label: "Status", sortable: true, cellClass: "min-w-[160px]" },
  { key: "total_documents_count", label: "Arsip", sortable: true, cellClass: "min-w-[120px]" },
  { key: "readiness", label: "Kesiapan Dokumen", cellClass: "min-w-[280px]" },
  { key: "updated_at", label: "Update", sortable: true, cellClass: "min-w-[140px]" },
  { key: "actions", label: "Aksi", cellClass: "min-w-[120px] text-right" },
];

const statusOptions = computed(() => {
  const seen = new Set();
  const options = [{ value: "all", label: "Semua Status" }];

  items.value.forEach((item) => {
    const value = String(item.status_key || "").trim();
    const label = String(item.status || "").trim();

    if (!value || seen.has(value)) return;
    seen.add(value);
    options.push({ value, label: label || value });
  });

  return options;
});

const toDateValue = (value) => {
  if (!value) return null;
  const d = new Date(`${value}T00:00:00`);
  return Number.isNaN(d.getTime()) ? null : d;
};

const formatDate = (value) => {
  if (!value) return "-";

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  }).format(date);
};

const statusTone = (statusKey) => {
  const status = String(statusKey || "").toLowerCase();

  if (["completed", "report_ready"].includes(status)) return "default";
  if (["cancelled"].includes(status)) return "destructive";
  if (["contract_signed", "valuation_in_progress", "valuation_completed", "preview_ready", "report_preparation"].includes(status)) return "secondary";
  return "outline";
};

const readinessItems = (item) => [
  { key: "contract", label: "Kontrak", ready: Boolean(item.ready_contract) },
  { key: "invoice", label: "Invoice", ready: Boolean(item.ready_invoice) },
  { key: "report", label: "Laporan", ready: Boolean(item.ready_report) },
  { key: "legal", label: "Legal Final", ready: Boolean(item.ready_legal_documents) },
];

const filteredItems = computed(() => {
  let rows = [...items.value];
  const q = searchQuery.value.trim().toLowerCase();

  if (q) {
    rows = rows.filter((item) =>
      [
        item.request_number,
        item.client,
        item.report_type,
        item.address,
      ]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(q))
    );
  }

  if (statusFilter.value !== "all") {
    rows = rows.filter((item) => String(item.status_key || "") === statusFilter.value);
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

const hasActiveFilters = computed(() => {
  return Boolean(searchQuery.value || statusFilter.value !== "all" || fromDate.value || toDate.value);
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

const resetFilters = () => {
  searchQuery.value = "";
  statusFilter.value = "all";
  fromDate.value = "";
  toDate.value = "";
};
</script>

<template>
  <UserDashboardLayout>
    <template #title>Dokumen</template>

    <div class="w-full space-y-6">
      <div class="flex flex-col gap-2">
        <h1 class="text-2xl font-semibold text-slate-900">Dokumen</h1>
        <p class="max-w-3xl text-sm text-slate-500">
          Arsip seluruh file permohonan Anda. Halaman ini menampilkan upload customer, foto aset,
          kontrak, invoice, laporan penilaian, serta dokumen legal final yang tersedia.
        </p>
      </div>

      <Card class="shadow-sm">
        <CardContent class="space-y-4 p-4">
          <div class="space-y-3">
            <div class="relative w-full">
              <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <Input
                v-model="searchQuery"
                placeholder="Cari nomor request, client, jenis laporan, atau alamat..."
                class="h-10 border-slate-200 bg-white pl-10"
              />
            </div>

            <div class="grid grid-cols-1 gap-3 lg:grid-cols-[220px_1fr_1fr]">
              <Select v-model="statusFilter">
                <SelectTrigger class="h-10">
                  <SelectValue placeholder="Semua Status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </SelectItem>
                </SelectContent>
              </Select>

              <Input v-model="fromDate" type="date" class="h-10 border-slate-200 bg-white" />
              <Input v-model="toDate" type="date" class="h-10 border-slate-200 bg-white" />
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
            <span>Preset:</span>
            <Button variant="outline" size="sm" class="h-8 px-3" @click="applyPreset(7)">7 Hari</Button>
            <Button variant="outline" size="sm" class="h-8 px-3" @click="applyPreset(30)">30 Hari</Button>
            <Button variant="outline" size="sm" class="h-8 px-3" @click="applyThisMonth">Bulan Ini</Button>
            <Button v-if="hasActiveFilters" variant="ghost" size="sm" class="h-8 px-2" @click="resetFilters">
              Reset Filter
            </Button>
          </div>
        </CardContent>
      </Card>

      <div v-if="!filteredItems.length" class="rounded-xl border border-dashed bg-white p-8 text-sm text-slate-500">
        Belum ada dokumen yang cocok dengan filter aktif.
      </div>

      <Card v-else class="shadow-sm">
        <CardContent class="p-4">
          <AdminDataTable
            :columns="columns"
            :rows="filteredItems"
            :default-per-page="10"
            empty-text="Belum ada dokumen yang cocok dengan filter aktif."
          >
            <template #cell-request_number="{ row }">
              <div>
                <div class="font-medium text-slate-900">{{ row.request_number }}</div>
                <div class="text-xs text-slate-500">{{ row.report_type }}</div>
              </div>
            </template>

            <template #cell-address="{ row }">
              <div class="flex items-start gap-2 text-slate-700">
                <MapPin class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                <span class="line-clamp-2">{{ row.address || "-" }}</span>
              </div>
            </template>

            <template #cell-status="{ row }">
              <Badge :variant="statusTone(row.status_key)">{{ row.status }}</Badge>
            </template>

            <template #cell-total_documents_count="{ row }">
              <div class="space-y-1 text-sm text-slate-700">
                <div>Upload: {{ row.customer_documents_count }}</div>
                <div>Foto: {{ row.customer_photos_count }}</div>
                <div>Sistem: {{ row.system_documents_count }}</div>
                <div class="font-medium text-slate-900">Total: {{ row.total_documents_count }}</div>
              </div>
            </template>

            <template #cell-readiness="{ row }">
              <div class="flex flex-wrap gap-2">
                <Badge
                  v-for="flag in readinessItems(row)"
                  :key="flag.key"
                  :variant="flag.ready ? 'default' : 'outline'"
                >
                  {{ flag.label }} {{ flag.ready ? "Siap" : "Belum" }}
                </Badge>
              </div>
            </template>

            <template #cell-updated_at="{ row }">
              <div class="flex items-center gap-2 text-slate-700">
                <Calendar class="h-4 w-4 shrink-0 text-slate-400" />
                <span>{{ formatDate(row.updated_at) }}</span>
              </div>
            </template>

            <template #cell-actions="{ row }">
              <div class="flex justify-end">
                <Button as-child variant="outline" size="sm">
                  <Link :href="route('reports.show', row.id)">
                    Lihat
                    <ArrowRight class="ml-2 h-4 w-4" />
                  </Link>
                </Button>
              </div>
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </UserDashboardLayout>
</template>
