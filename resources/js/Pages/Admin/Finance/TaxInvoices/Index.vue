<script setup>
import { Head, Link } from "@inertiajs/vue3";
import AdminLayout from "@/layouts/AdminLayout.vue";
import AdminDataTable from "@/components/admin/AdminDataTable.vue";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { formatCurrency, formatDateTime } from "@/utils/reviewer";

const props = defineProps({
  title: { type: String, required: true },
  description: { type: String, required: true },
  records: { type: Object, required: true },
});

const columns = [
  { key: "request", label: "Permohonan", cellClass: "min-w-[180px]" },
  { key: "customer", label: "Customer", cellClass: "min-w-[180px]" },
  { key: "invoice", label: "Nomor Invoice", cellClass: "min-w-[160px]" },
  { key: "tax", label: "Nomor Faktur Pajak", cellClass: "min-w-[180px]" },
  { key: "gross", label: "Total Tagihan", cellClass: "min-w-[150px]" },
  { key: "updated", label: "Diubah", cellClass: "min-w-[150px]" },
];
</script>

<template>
  <Head :title="`Admin - ${title}`" />
  <AdminLayout :title="title">
    <div class="space-y-6">
      <section><h1 class="text-3xl font-semibold tracking-tight text-slate-950">{{ title }}</h1><p class="mt-2 text-sm text-slate-600">{{ description }}</p></section>
      <Card>
        <CardHeader><CardTitle>Daftar Dokumen</CardTitle></CardHeader>
        <CardContent>
          <AdminDataTable :columns="columns" :rows="records.data" :meta="records.meta" empty-text="Belum ada dokumen yang tercatat.">
            <template #cell-request="{ row }"><Button variant="link" class="h-auto px-0 font-medium" as-child><Link :href="row.show_url">{{ row.request_number }}</Link></Button></template>
            <template #cell-customer="{ row }">{{ row.customer_name }}</template>
            <template #cell-invoice="{ row }">{{ row.nomor_invoice || "-" }}</template>
            <template #cell-tax="{ row }">{{ row.nomor_faktur_pajak || "-" }}</template>
            <template #cell-gross="{ row }">{{ formatCurrency(row.total_tagihan) }}</template>
            <template #cell-updated="{ row }">{{ formatDateTime(row.updated_at) }}</template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
