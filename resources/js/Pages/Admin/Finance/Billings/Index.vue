<script setup>
import { computed, reactive } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AdminLayout from "@/layouts/AdminLayout.vue";
import AdminDataTable from "@/components/admin/AdminDataTable.vue";
import AdminTableToolbar from "@/components/admin/AdminTableToolbar.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { formatCurrency, formatDateTime } from "@/utils/reviewer";

const props = defineProps({
  filters: { type: Object, default: () => ({ q: "", status: "all", doc: "all" }) },
  statusOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, draft: 0, invoice_ready: 0, complete: 0 }) },
  records: { type: Object, required: true },
});

const form = reactive({
  q: props.filters.q ?? "",
  status: props.filters.status ?? "all",
  doc: props.filters.doc ?? "all",
});

const submitFilters = () => {
  router.get(route("admin.finance.billings.index"), {
    q: form.q || undefined,
    status: form.status === "all" ? undefined : form.status,
    doc: form.doc === "all" ? undefined : form.doc,
  }, { preserveState: true, preserveScroll: true, replace: true });
};

const resetFilters = () => {
  form.q = "";
  form.status = "all";
  form.doc = "all";
  submitFilters();
};

const activeFilterCount = computed(() => (form.status !== "all" ? 1 : 0) + (form.doc !== "all" ? 1 : 0));

const statusTone = (value) => {
  switch (value) {
    case "complete":
      return "bg-emerald-100 text-emerald-900 border-emerald-200";
    case "invoice_issued":
    case "tax_invoice_recorded":
    case "withholding_recorded":
      return "bg-sky-100 text-sky-900 border-sky-200";
    default:
      return "bg-amber-100 text-amber-900 border-amber-200";
  }
};

const columns = [
  { key: "request", label: "Permohonan", cellClass: "min-w-[180px]" },
  { key: "customer", label: "Customer", cellClass: "min-w-[180px]" },
  { key: "dpp", label: "Nilai Jasa", cellClass: "min-w-[140px]" },
  { key: "gross", label: "Total Tagihan", cellClass: "min-w-[150px]" },
  { key: "net", label: "Total Transfer", cellClass: "min-w-[150px]" },
  { key: "docs", label: "Dokumen", cellClass: "min-w-[180px]" },
  { key: "status", label: "Status Dokumen", cellClass: "min-w-[160px]" },
  { key: "updated", label: "Diubah", cellClass: "min-w-[150px]" },
];
</script>

<template>
  <Head title="Admin - Tagihan" />
  <AdminLayout title="Tagihan">
    <div class="space-y-6">
      <section>
        <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Workspace Tagihan</h1>
        <p class="mt-2 text-sm text-slate-600">Input nilai jasa, breakdown pajak, dan dokumen keuangan per pekerjaan appraisal.</p>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Tagihan</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Draft</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.draft }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Invoice Terbit</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.invoice_ready }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Lengkap</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.complete }}</p></CardContent></Card>
      </section>

      <Card>
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div><CardTitle>Daftar Tagihan</CardTitle></div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari nomor request, customer, nomor invoice, nomor faktur, atau bukti potong"
            filter-title="Filter tagihan"
            filter-description="Saring data finance berdasarkan status dokumen dan kelengkapan dokumen."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; submitFilters(); }"
            @apply-filters="submitFilters"
            @reset-filters="resetFilters"
          >
            <div class="grid gap-4">
              <div class="space-y-2">
                <Label for="billing_status_filter">Status Dokumen</Label>
                <Select v-model="form.status">
                  <SelectTrigger id="billing_status_filter"><SelectValue placeholder="Pilih status" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Semua Status</SelectItem>
                    <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="billing_doc_filter">Kelengkapan Dokumen</Label>
                <Select v-model="form.doc">
                  <SelectTrigger id="billing_doc_filter"><SelectValue placeholder="Pilih dokumen" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Semua Dokumen</SelectItem>
                    <SelectItem value="invoice">Sudah Ada Invoice</SelectItem>
                    <SelectItem value="tax">Sudah Ada Faktur Pajak</SelectItem>
                    <SelectItem value="withholding">Sudah Ada Bukti Potong</SelectItem>
                    <SelectItem value="missing">Masih Ada yang Belum Lengkap</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </AdminTableToolbar>
        </CardHeader>
        <CardContent>
          <AdminDataTable :columns="columns" :rows="records.data" :meta="records.meta" empty-text="Belum ada data tagihan yang cocok dengan filter saat ini.">
            <template #cell-request="{ row }"><Button variant="link" class="h-auto px-0 font-medium" as-child><Link :href="row.show_url">{{ row.request_number }}</Link></Button></template>
            <template #cell-customer="{ row }"><div class="font-medium text-slate-950">{{ row.customer_name }}</div><div class="mt-1 text-xs text-slate-500">{{ row.requester_name }}</div></template>
            <template #cell-dpp="{ row }">{{ formatCurrency(row.nilai_jasa_dpp) }}</template>
            <template #cell-gross="{ row }">{{ formatCurrency(row.total_tagihan) }}</template>
            <template #cell-net="{ row }">{{ formatCurrency(row.total_transfer_customer) }}</template>
            <template #cell-docs="{ row }"><div class="space-y-1 text-xs text-slate-600"><div>Invoice: {{ row.nomor_invoice || "-" }}</div><div>Faktur: {{ row.nomor_faktur_pajak || "-" }}</div><div>Bukti Potong: {{ row.nomor_bukti_potong || "-" }}</div></div></template>
            <template #cell-status="{ row }"><Badge variant="outline" :class="statusTone(row.status_dokumen_keuangan)">{{ row.status_dokumen_keuangan_label }}</Badge></template>
            <template #cell-updated="{ row }"><div>{{ formatDateTime(row.updated_at) }}</div><Button variant="link" class="mt-1 h-auto px-0 text-xs" as-child><Link :href="row.edit_url">Kelola Tagihan</Link></Button></template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
