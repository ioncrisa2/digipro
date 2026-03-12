<script setup>
import { computed, ref, watch } from "vue";
import { Link } from "@inertiajs/vue3";
import UserDashboardLayout from "@/layouts/UserDashboardLayout.vue";

import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  FileDown,
  Calendar,
  CreditCard,
  ArrowRight,
  Search,
  ChevronUp,
  ChevronDown,
  ArrowUpDown,
  CheckCircle2,
  Clock,
  AlertTriangle,
  ChevronRight,
  ChevronLeft,
} from "lucide-vue-next";

const props = defineProps({
  payments: { type: Array, default: () => [] },
});

const items = computed(() => props.payments || []);
const searchQuery = ref("");
const statusFilter = ref("all");
const fromDate = ref("");
const toDate = ref("");

const statusOptions = [
  { value: "all", label: "Semua Status" },
  { value: "dibayar", label: "Dibayar" },
  { value: "menunggu", label: "Menunggu" },
];

const statusMeta = (status) => {
  const s = String(status || "").toLowerCase();
  if (s.includes("dibayar")) {
    return { label: status, variant: "default", icon: CheckCircle2, tone: "text-emerald-600" };
  }
  if (s.includes("gagal")) {
    return { label: status, variant: "destructive", icon: AlertTriangle, tone: "text-rose-600" };
  }
  if (s.includes("kedaluwarsa")) {
    return { label: status, variant: "outline", icon: AlertTriangle, tone: "text-amber-700" };
  }
  if (s.includes("menunggu verifikasi")) {
    return { label: status, variant: "secondary", icon: AlertTriangle, tone: "text-amber-600" };
  }
  if (s.includes("menunggu")) {
    return { label: status, variant: "secondary", icon: Clock, tone: "text-slate-500" };
  }
  return { label: status, variant: "outline", icon: Clock, tone: "text-slate-500" };
};

const successCount = computed(() =>
  items.value.filter((item) => String(item.status || "").toLowerCase().includes("dibayar")).length
);

const pendingCount = computed(() =>
  items.value.filter((item) => String(item.status || "").toLowerCase().includes("menunggu")).length
);

const parseAmount = (value) => {
  if (!value) return 0;
  const digits = String(value).replace(/[^0-9]/g, "");
  return Number(digits || 0);
};

const toDateValue = (value) => {
  if (!value) return null;
  const d = new Date(`${value}T00:00:00`);
  return Number.isNaN(d.getTime()) ? null : d;
};

const isOverdue = (item) => {
  const status = String(item.status || "").toLowerCase();
  if (status.includes("dibayar")) return false;
  const due = toDateValue(item.due_date);
  if (!due) return false;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return due < today;
};

const filteredItems = computed(() => {
  let rows = [...items.value];
  const q = searchQuery.value.trim().toLowerCase();
  if (q) {
    rows = rows.filter((item) => {
      return [
        item.invoice_number,
        item.request_number,
        item.client,
        item.amount,
        item.bank,
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
      const due = toDateValue(item.due_date);
      if (!due) return false;
      if (from && due < from) return false;
      if (to && due > to) return false;
      return true;
    });
  }

  return rows;
});

const sortKey = ref("due_date");
const sortDir = ref("asc");

const toggleSort = (key) => {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  } else {
    sortKey.value = key;
    sortDir.value = "asc";
  }
};

const sortIcon = (key) => {
  if (sortKey.value !== key) return ArrowUpDown;
  return sortDir.value === "asc" ? ChevronUp : ChevronDown;
};

const sortedItems = computed(() => {
  const rows = [...filteredItems.value];
  const dir = sortDir.value === "asc" ? 1 : -1;
  rows.sort((a, b) => {
    const key = sortKey.value;
    if (key === "amount") {
      return (parseAmount(a.amount) - parseAmount(b.amount)) * dir;
    }
    if (key === "due_date") {
      const da = toDateValue(a.due_date)?.getTime() || 0;
      const db = toDateValue(b.due_date)?.getTime() || 0;
      return (da - db) * dir;
    }
    const va = String(a[key] || "").toLowerCase();
    const vb = String(b[key] || "").toLowerCase();
    return va.localeCompare(vb) * dir;
  });
  return rows;
});

const pageSizes = [5, 10, 20];
const pageSize = ref(10);
const currentPage = ref(1);

const totalPages = computed(() => Math.max(1, Math.ceil(sortedItems.value.length / pageSize.value)));

const pagedItems = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value;
  return sortedItems.value.slice(start, start + pageSize.value);
});

watch([searchQuery, statusFilter, fromDate, toDate, sortKey, sortDir, pageSize], () => {
  currentPage.value = 1;
});

const hasActiveFilters = computed(() => {
  return Boolean(searchQuery.value || statusFilter.value !== "all" || fromDate.value || toDate.value);
});

const resetFilters = () => {
  searchQuery.value = "";
  statusFilter.value = "all";
  fromDate.value = "";
  toDate.value = "";
};

const applyPreset = (days) => {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const from = new Date(today);
  from.setDate(from.getDate() - days);
  const to = new Date(today);
  fromDate.value = from.toISOString().slice(0, 10);
  toDate.value = to.toISOString().slice(0, 10);
};

const applyThisMonth = () => {
  const now = new Date();
  const start = new Date(now.getFullYear(), now.getMonth(), 1);
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
  fromDate.value = start.toISOString().slice(0, 10);
  toDate.value = end.toISOString().slice(0, 10);
};

const expandedId = ref(null);
const toggleRow = (id) => {
  expandedId.value = expandedId.value === id ? null : id;
};

const selectedIds = ref([]);
const isSelected = (id) => selectedIds.value.includes(id);
const toggleSelect = (id, checked) => {
  if (checked) {
    selectedIds.value = Array.from(new Set([...selectedIds.value, id]));
  } else {
    selectedIds.value = selectedIds.value.filter((x) => x !== id);
  }
};

const pageIds = computed(() => pagedItems.value.map((item) => item.id));
const allSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every((id) => selectedIds.value.includes(id)));
const toggleSelectAll = (checked) => {
  if (checked) {
    selectedIds.value = Array.from(new Set([...selectedIds.value, ...pageIds.value]));
  } else {
    selectedIds.value = selectedIds.value.filter((id) => !pageIds.value.includes(id));
  }
};

const selectedCount = computed(() => selectedIds.value.length);
const selectedDownloadableItems = computed(() =>
  items.value.filter((item) =>
    selectedIds.value.includes(item.id) && Boolean(item?.is_paid)
  )
);

const resolveInvoicePdfUrl = (item) => {
  if (item?.invoice_pdf_url) return item.invoice_pdf_url;
  try {
    return route("appraisal.invoice.pdf", item?.id);
  } catch (_) {
    return `/permohonan-penilaian/${item?.id}/invoice/pdf`;
  }
};

const canDownloadInvoice = (item) => Boolean(item?.is_paid);

const downloadInvoice = (item) => {
  if (!canDownloadInvoice(item)) return;
  window.open(resolveInvoicePdfUrl(item), "_blank", "noopener,noreferrer");
};

const downloadSelectedInvoices = () => {
  if (!selectedDownloadableItems.value.length) return;
  selectedDownloadableItems.value.forEach((item) => {
    window.open(resolveInvoicePdfUrl(item), "_blank", "noopener,noreferrer");
  });
};
</script>

<template>
  <UserDashboardLayout>
    <template #title>Pembayaran</template>

    <div class="w-full space-y-6">
      <div class="flex flex-col gap-2">
        <h1 class="text-2xl font-semibold text-slate-900">Pembayaran</h1>
        <p class="text-sm text-slate-500">
          Pantau status pembayaran Midtrans dan unduh invoice transaksi yang sudah lunas.
        </p>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Card class="shadow-sm">
          <CardContent class="p-5 flex items-center justify-between">
            <div>
              <div class="text-xs uppercase tracking-wide text-slate-500">Pembayaran Berhasil</div>
              <div class="text-2xl font-semibold text-slate-900">{{ successCount }}</div>
            </div>
            <div class="h-10 w-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center">
              <CreditCard class="h-5 w-5" />
            </div>
          </CardContent>
        </Card>
        <Card class="shadow-sm">
          <CardContent class="p-5 flex items-center justify-between">
            <div>
              <div class="text-xs uppercase tracking-wide text-slate-500">Pembayaran Pending</div>
              <div class="text-2xl font-semibold text-slate-900">{{ pendingCount }}</div>
            </div>
            <div class="h-10 w-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center">
              <Calendar class="h-5 w-5" />
            </div>
          </CardContent>
        </Card>
      </div>

      <Card class="shadow-sm">
        <CardContent class="p-4 space-y-4">
          <div class="space-y-3">
            <div class="relative w-full">
              <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
              <Input
                v-model="searchQuery"
                placeholder="Cari invoice, request, atau client..."
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
                Filter status untuk melihat pembayaran tertentu.
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

          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-xs text-slate-500">
              <span>Status:</span>
              <Badge variant="default" class="gap-1">
                <CheckCircle2 class="h-3.5 w-3.5" /> Dibayar
              </Badge>
              <Badge variant="secondary" class="gap-1">
                <Clock class="h-3.5 w-3.5" /> Menunggu
              </Badge>
              <Badge variant="secondary" class="gap-1">
                <AlertTriangle class="h-3.5 w-3.5" /> Menunggu Verifikasi
              </Badge>
            </div>

            <div class="flex items-center gap-2 text-xs text-slate-500">
              <span>Rows:</span>
              <Select v-model="pageSize">
                <SelectTrigger class="h-8 w-20">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="size in pageSizes" :key="size" :value="size">
                    {{ size }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-xs text-slate-500">
              Dipilih: <span class="font-semibold text-slate-900">{{ selectedCount }}</span>
            </div>
            <div class="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                :disabled="selectedDownloadableItems.length === 0"
                @click="downloadSelectedInvoices"
              >
                <FileDown class="mr-2 h-4 w-4" /> Unduh Invoice
              </Button>
              <Button variant="outline" size="sm" :disabled="selectedCount === 0">
                Export CSV
              </Button>
            </div>
          </div>

          <div v-if="!pagedItems.length" class="rounded-lg border p-6 text-sm text-slate-500">
            Belum ada pembayaran sesuai filter.
          </div>

          <div v-else class="hidden lg:block overflow-x-auto max-h-[520px] overflow-y-auto">
            <Table class="w-full">
              <TableHeader>
                <TableRow class="bg-slate-50/90 sticky top-0 z-10">
                  <TableHead class="w-10">
                    <Checkbox
                      :model-value="allSelected"
                      @update:modelValue="toggleSelectAll"
                    />
                  </TableHead>
                  <TableHead class="font-semibold">
                    <button class="inline-flex items-center gap-1" @click="toggleSort('invoice_number')">
                      Invoice
                      <component :is="sortIcon('invoice_number')" class="h-3.5 w-3.5 text-slate-400" />
                    </button>
                  </TableHead>
                  <TableHead class="font-semibold">
                    <button class="inline-flex items-center gap-1" @click="toggleSort('request_number')">
                      Request
                      <component :is="sortIcon('request_number')" class="h-3.5 w-3.5 text-slate-400" />
                    </button>
                  </TableHead>
                  <TableHead class="font-semibold">
                    <button class="inline-flex items-center gap-1" @click="toggleSort('client')">
                      Client
                      <component :is="sortIcon('client')" class="h-3.5 w-3.5 text-slate-400" />
                    </button>
                  </TableHead>
                  <TableHead class="font-semibold">
                    <button class="inline-flex items-center gap-1" @click="toggleSort('amount')">
                      Jumlah
                      <component :is="sortIcon('amount')" class="h-3.5 w-3.5 text-slate-400" />
                    </button>
                  </TableHead>
                  <TableHead class="font-semibold">
                    <button class="inline-flex items-center gap-1" @click="toggleSort('due_date')">
                      Jatuh Tempo
                      <component :is="sortIcon('due_date')" class="h-3.5 w-3.5 text-slate-400" />
                    </button>
                  </TableHead>
                  <TableHead class="font-semibold">
                    <button class="inline-flex items-center gap-1" @click="toggleSort('status')">
                      Status
                      <component :is="sortIcon('status')" class="h-3.5 w-3.5 text-slate-400" />
                    </button>
                  </TableHead>
                  <TableHead class="text-right font-semibold">Aksi</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <template v-for="item in pagedItems" :key="item.id">
                  <TableRow :class="isOverdue(item) ? 'bg-red-50/60' : ''">
                    <TableCell>
                      <Checkbox
                        :model-value="isSelected(item.id)"
                        @update:modelValue="(val) => toggleSelect(item.id, val)"
                      />
                    </TableCell>
                    <TableCell class="font-medium text-slate-900">
                      <button class="inline-flex items-center gap-2" @click="toggleRow(item.id)">
                        <component
                          :is="expandedId === item.id ? ChevronDown : ChevronRight"
                          class="h-4 w-4 text-slate-400"
                        />
                        {{ item.invoice_number }}
                      </button>
                    </TableCell>
                    <TableCell class="text-slate-700">{{ item.request_number }}</TableCell>
                    <TableCell class="text-slate-700">{{ item.client }}</TableCell>
                    <TableCell class="text-slate-700">{{ item.amount }}</TableCell>
                    <TableCell class="text-slate-700">{{ item.due_date }}</TableCell>
                    <TableCell>
                      <Badge :variant="statusMeta(item.status).variant" class="gap-1">
                        <component :is="statusMeta(item.status).icon" class="h-3.5 w-3.5" />
                        {{ statusMeta(item.status).label }}
                      </Badge>
                    </TableCell>
                    <TableCell class="text-right">
                      <div class="flex items-center justify-end gap-2">
                        <Button
                          variant="outline"
                          size="sm"
                          :disabled="!canDownloadInvoice(item)"
                          @click="downloadInvoice(item)"
                        >
                          <FileDown class="mr-2 h-4 w-4" />
                          Unduh
                        </Button>
                        <Link
                          :href="route('payments.show', item.id)"
                          class="text-sm text-slate-700 hover:text-slate-900 flex items-center gap-1"
                        >
                          Detail <ArrowRight class="h-4 w-4" />
                        </Link>
                      </div>
                    </TableCell>
                  </TableRow>
                  <TableRow v-if="expandedId === item.id">
                    <TableCell :colspan="8" class="bg-slate-50/60">
                      <div class="grid grid-cols-1 gap-3 md:grid-cols-3 text-sm">
                        <div>
                          <div class="text-xs text-slate-500">Metode</div>
                          <div class="font-medium text-slate-900">{{ item.method }}</div>
                        </div>
                        <div>
                          <div class="text-xs text-slate-500">Bank</div>
                          <div class="font-medium text-slate-900">{{ item.bank }}</div>
                        </div>
                        <div>
                          <div class="text-xs text-slate-500">Virtual Account</div>
                          <div class="font-mono text-slate-900">{{ item.va }}</div>
                        </div>
                      </div>
                      <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2 text-sm">
                        <div>
                          <div class="text-xs text-slate-500">Order ID</div>
                          <div class="font-medium text-slate-900 break-all">{{ item.order_id || "-" }}</div>
                        </div>
                        <div>
                          <div class="text-xs text-slate-500">Channel</div>
                          <div class="font-medium text-slate-900">{{ item.gateway_details?.label || item.method }}</div>
                        </div>
                      </div>
                      <div class="mt-3 text-xs text-slate-500">
                        Dokumen: Invoice Pembayaran. Bukti upload hanya tersedia untuk histori manual lama.
                      </div>
                    </TableCell>
                  </TableRow>
                </template>
              </TableBody>
            </Table>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-xs text-slate-500">
              Menampilkan {{ pagedItems.length }} dari {{ sortedItems.length }} pembayaran
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
        </CardContent>
      </Card>

      <div class="lg:hidden space-y-3">
        <Card v-for="item in pagedItems" :key="`card-${item.id}`" class="shadow-sm">
          <CardContent class="p-4 space-y-2">
            <div class="flex items-center justify-between">
              <div class="font-semibold text-slate-900">{{ item.invoice_number }}</div>
              <Badge :variant="statusMeta(item.status).variant" class="gap-1">
                <component :is="statusMeta(item.status).icon" class="h-3.5 w-3.5" />
                {{ statusMeta(item.status).label }}
              </Badge>
            </div>
            <div class="text-sm text-slate-600">{{ item.client }} - {{ item.request_number }}</div>
            <div class="text-sm text-slate-700">{{ item.amount }}</div>
            <div class="text-xs text-slate-500">Jatuh tempo: {{ item.due_date }}</div>
            <div class="flex items-center justify-between pt-2">
              <Button
                variant="outline"
                size="sm"
                :disabled="!canDownloadInvoice(item)"
                @click="downloadInvoice(item)"
              >
                <FileDown class="mr-2 h-4 w-4" />
                Unduh
              </Button>
              <Link
                :href="route('payments.show', item.id)"
                class="text-sm text-slate-700 hover:text-slate-900 flex items-center gap-1"
              >
                Detail <ArrowRight class="h-4 w-4" />
              </Link>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </UserDashboardLayout>
</template>
