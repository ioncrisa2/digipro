<script setup>
import { computed, reactive } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AdminLayout from "@/layouts/AdminLayout.vue";
import AdminDataTable from "@/components/admin/AdminDataTable.vue";
import AdminTableToolbar from "@/components/admin/AdminTableToolbar.vue";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

const props = defineProps({
  filters: { type: Object, required: true },
  reviewStatusOptions: { type: Array, default: () => [] },
  statusBeforeOptions: { type: Array, default: () => [] },
  summary: { type: Object, required: true },
  records: { type: Object, required: true },
});

const form = reactive({
  q: props.filters.q ?? "",
  review_status: props.filters.review_status ?? "all",
  status_before: props.filters.status_before ?? "all",
});

const applyFilters = () => {
  router.get(
    route("admin.appraisal-requests.cancellations.index"),
    {
      q: form.q || undefined,
      review_status: form.review_status === "all" ? undefined : form.review_status,
      status_before: form.status_before === "all" ? undefined : form.status_before,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    }
  );
};

const resetFilters = () => {
  form.q = "";
  form.review_status = "all";
  form.status_before = "all";
  applyFilters();
};

const activeFilterCount = computed(() => {
  let count = 0;
  if (form.review_status !== "all") count += 1;
  if (form.status_before !== "all") count += 1;
  return count;
});

const summaryCards = [
  { key: "total", label: "Total Pengajuan" },
  { key: "pending", label: "Menunggu Review" },
  { key: "in_progress", label: "Sedang Dihubungi" },
  { key: "reviewed", label: "Selesai Direview" },
];

const columns = [
  { key: "request", label: "Request", cellClass: "min-w-[180px]" },
  { key: "customer", label: "Customer", cellClass: "min-w-[180px]" },
  { key: "status_before", label: "Status Sebelum", cellClass: "min-w-[180px]" },
  { key: "review_status", label: "Review", cellClass: "min-w-[160px]" },
  { key: "reason", label: "Alasan", cellClass: "min-w-[260px]" },
  { key: "actions", label: "Aksi", cellClass: "min-w-[120px]" },
];

const reviewTone = (status) => {
  switch (status) {
    case "pending":
      return "bg-amber-100 text-amber-900 border-amber-200";
    case "in_progress":
      return "bg-sky-100 text-sky-900 border-sky-200";
    case "approved":
      return "bg-emerald-100 text-emerald-900 border-emerald-200";
    case "rejected":
      return "bg-rose-100 text-rose-900 border-rose-200";
    default:
      return "bg-slate-100 text-slate-800 border-slate-200";
  }
};
</script>

<template>
  <Head title="Admin - Pembatalan Request" />

  <AdminLayout title="Pembatalan Request">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Pembatalan Request</h1>
          <p class="mt-2 text-sm text-slate-600">
            Queue review permohonan pembatalan customer sebelum keputusan final dibuat oleh admin.
          </p>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-4">
        <Card v-for="card in summaryCards" :key="card.key">
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">{{ card.label }}</p>
            <p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary[card.key] ?? 0 }}</p>
          </CardContent>
        </Card>
      </section>

      <Card>
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Daftar Pengajuan</CardTitle>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari request, customer, atau nomor telepon"
            filter-title="Filter pembatalan request"
            filter-description="Saring queue berdasarkan status review dan status request sebelum pembatalan."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; applyFilters(); }"
            @apply-filters="applyFilters"
            @reset-filters="resetFilters"
          >
            <div class="space-y-2">
              <Label for="review_status_filter">Review Status</Label>
              <Select v-model="form.review_status">
                <SelectTrigger id="review_status_filter">
                  <SelectValue placeholder="Semua status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Semua status</SelectItem>
                  <SelectItem
                    v-for="option in reviewStatusOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="status_before_filter">Status Sebelum</Label>
              <Select v-model="form.status_before">
                <SelectTrigger id="status_before_filter">
                  <SelectValue placeholder="Semua status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Semua status</SelectItem>
                  <SelectItem
                    v-for="option in statusBeforeOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </AdminTableToolbar>
        </CardHeader>
        <CardContent>
          <AdminDataTable
            :columns="columns"
            :rows="records.data"
            :meta="records.meta"
            empty-text="Belum ada pengajuan pembatalan yang cocok dengan filter saat ini."
          >
            <template #cell-request="{ row }">
              <Button variant="link" class="h-auto px-0 font-medium" as-child>
                <Link :href="row.show_url">{{ row.request_number }}</Link>
              </Button>
              <p class="mt-1 text-xs text-slate-500">{{ row.requested_at }}</p>
            </template>

            <template #cell-customer="{ row }">
              <p class="font-medium text-slate-900">{{ row.customer_name }}</p>
              <p class="mt-1 text-xs text-slate-500">{{ row.phone_snapshot }}</p>
            </template>

            <template #cell-status_before="{ row }">
              <Badge variant="outline">{{ row.status_before_request_label }}</Badge>
            </template>

            <template #cell-review_status="{ row }">
              <Badge variant="outline" :class="reviewTone(row.review_status)">
                {{ row.review_status_label }}
              </Badge>
            </template>

            <template #cell-reason="{ row }">
              <p class="text-sm text-slate-700">{{ row.reason_excerpt }}</p>
            </template>

            <template #cell-actions="{ row }">
              <Button variant="outline" size="sm" as-child>
                <Link :href="row.show_url">Review</Link>
              </Button>
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
