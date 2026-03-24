<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import AdminEntityActions from '@/components/admin/AdminEntityActions.vue';
import AdminTableToolbar from '@/components/admin/AdminTableToolbar.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { formatCurrency, formatDateTime } from '@/utils/reviewer';

const props = defineProps({
  filters: {
    type: Object,
    required: true,
  },
  statusOptions: {
    type: Array,
    default: () => [],
  },
  summary: {
    type: Object,
    required: true,
  },
  records: {
    type: Object,
    required: true,
  },
});

const form = reactive({
  q: props.filters.q ?? '',
  status: props.filters.status ?? 'all',
});

const applyFilters = () => {
  router.get(
    route('admin.appraisal-requests.index'),
    {
      q: form.q || undefined,
      status: form.status === 'all' ? undefined : form.status,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    },
  );
};

const resetFilters = () => {
  form.q = '';
  form.status = 'all';
  applyFilters();
};

const activeFilterCount = computed(() => {
  let count = 0;

  if (form.status !== 'all') count += 1;

  return count;
});

const statusTone = (value) => {
  switch (value) {
    case 'submitted':
      return 'bg-sky-100 text-sky-900 border-sky-200';
    case 'docs_incomplete':
      return 'bg-rose-100 text-rose-900 border-rose-200';
    case 'verified':
    case 'completed':
      return 'bg-emerald-100 text-emerald-900 border-emerald-200';
    case 'waiting_offer':
    case 'waiting_signature':
    case 'contract_signed':
      return 'bg-amber-100 text-amber-900 border-amber-200';
    case 'offer_sent':
      return 'bg-indigo-100 text-indigo-900 border-indigo-200';
    default:
      return 'bg-slate-100 text-slate-800 border-slate-200';
  }
};

const summaryCards = [
  { key: 'total', label: 'Total Request' },
  { key: 'needs_action', label: 'Butuh Tindakan' },
  { key: 'payment_pending', label: 'Menunggu Pembayaran' },
];

const columns = [
  { key: 'request', label: 'Request', cellClass: 'min-w-[180px]' },
  { key: 'client', label: 'Klien / Pemohon', cellClass: 'min-w-[180px]' },
  { key: 'status', label: 'Status', cellClass: 'min-w-[140px]' },
  { key: 'contract', label: 'Kontrak', cellClass: 'min-w-[140px]' },
  { key: 'assets_count', label: 'Aset', cellClass: 'w-[80px]' },
  { key: 'fee_total', label: 'Fee', cellClass: 'min-w-[120px]' },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[200px]' },
];
</script>

<template>
  <Head title="Admin - Permohonan Penilaian" />

  <AdminLayout title="Permohonan Penilaian">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Permohonan Penilaian</h1>
          <p class="mt-2 text-sm text-slate-600">
            Workspace operasional admin untuk membaca, memfilter, dan menindaklanjuti permohonan penilaian.
          </p>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-3">
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
            <CardTitle>Daftar Request</CardTitle>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari request atau pemohon"
            filter-title="Filter permohonan"
            filter-description="Saring data berdasarkan status request."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; applyFilters(); }"
            @apply-filters="applyFilters"
            @reset-filters="resetFilters"
          >
            <div class="space-y-2">
              <Label for="request_status_filter">Status</Label>
              <Select v-model="form.status">
                <SelectTrigger id="request_status_filter">
                  <SelectValue placeholder="Semua status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Semua status</SelectItem>
                  <SelectItem
                    v-for="option in statusOptions"
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
            empty-text="Tidak ada data untuk filter ini."
          >
            <template #cell-request="{ row }">
              <Button variant="link" class="h-auto px-0 font-medium" as-child>
                <Link :href="row.show_url">{{ row.request_number }}</Link>
              </Button>


              <p class="mt-1 text-xs text-slate-500">{{ formatDateTime(row.requested_at) }}</p>
            </template>

            <template #cell-client="{ row }">
              <p class="font-medium text-slate-900">{{ row.client_name }}</p>
              <p class="mt-1 text-xs text-slate-500">{{ row.requester_name }}</p>
            </template>

            <template #cell-status="{ row }">
              <Badge variant="outline" :class="statusTone(row.status_value)">{{ row.status_label }}</Badge>
            </template>

            <template #cell-contract="{ row }">
              <Badge variant="outline" :class="statusTone(row.contract_status_value)">{{ row.contract_status_label }}</Badge>
              <p class="mt-1 text-xs text-slate-500">Nego: {{ row.negotiation_rounds_used }}</p>
            </template>

            <template #cell-fee_total="{ row }">
              {{ formatCurrency(row.fee_total) }}
            </template>

            <template #cell-actions="{ row }">
              <AdminEntityActions
                :detail-href="row.show_url"
                :edit-href="route('admin.appraisal-requests.edit', row.id)"
                entity-label="permohonan"
                :entity-name="row.request_number"
              />
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
