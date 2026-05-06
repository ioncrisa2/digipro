<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ReviewerLayout from '@/layouts/ReviewerLayout.vue';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import AdminTableToolbar from '@/components/admin/AdminTableToolbar.vue';
import StatusBadge from '@/components/reviewer/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { formatDateTime } from '@/utils/reviewer';

const props = defineProps({
  filters: { type: Object, default: () => ({ q: '', queue: 'all', status: 'all', per_page: 12 }) },
  statusOptions: { type: Array, default: () => [] },
  queueOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({}) },
  records: { type: Object, required: true },
});

const form = reactive({
  q: props.filters.q ?? '',
  queue: props.filters.queue ?? 'all',
  status: props.filters.status ?? 'all',
});

const activeFilterCount = computed(() => {
  let count = 0;
  if (form.queue !== 'all') count += 1;
  if (form.status !== 'all') count += 1;
  return count;
});

const applyFilters = () => {
  router.get(
    route('reviewer.reviews.index'),
    {
      q: form.q || undefined,
      queue: form.queue === 'all' ? undefined : form.queue,
      status: form.status === 'all' ? undefined : form.status,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    },
  );
};

const resetFilters = () => {
  form.q = '';
  form.queue = 'all';
  form.status = 'all';
  applyFilters();
};

const applyQueueFilter = (value) => {
  form.queue = value;
  form.status = 'all';
  applyFilters();
};

const summaryCards = [
  { key: 'total', label: 'Total Queue' },
  { key: 'siap_review', label: 'Siap Review' },
  { key: 'sedang_review', label: 'Sedang Review' },
  { key: 'siap_preview', label: 'Siap Preview' },
  { key: 'total_aset', label: 'Total Aset' },
];

const columns = [
  { key: 'request', label: 'Permohonan', cellClass: 'min-w-[180px]' },
  { key: 'client', label: 'Klien', cellClass: 'min-w-[180px]' },
  { key: 'status', label: 'Status', cellClass: 'min-w-[150px]' },
  { key: 'assets', label: 'Aset', cellClass: 'w-[96px]' },
  { key: 'contract', label: 'Nomor Kontrak', cellClass: 'min-w-[180px]' },
  { key: 'action', label: 'Aksi Berikutnya', cellClass: 'min-w-[220px]' },
  { key: 'requested_at', label: 'Masuk Queue', cellClass: 'min-w-[160px]' },
];
</script>

<template>
  <Head title="Reviewer - Review Queue" />

  <ReviewerLayout title="Review Queue">
    <div class="space-y-6">
      <section>
        <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Queue Reviewer</h1>
        <p class="mt-2 text-sm text-slate-600">
          Workspace untuk membaca antrean pekerjaan, memfilter permohonan aktif, dan membuka review yang paling relevan.
        </p>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <Card v-for="card in summaryCards" :key="card.key" class="border-slate-200/80 shadow-sm">
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ card.label }}</p>
            <p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary[card.key] ?? 0 }}</p>
          </CardContent>
        </Card>
      </section>

      <section class="grid gap-4 xl:grid-cols-4">
        <button
          v-for="queue in queueOptions"
          :key="queue.value"
          type="button"
          class="rounded-2xl border p-4 text-left transition"
          :class="form.queue === queue.value ? 'border-slate-950 bg-slate-950 text-white shadow-sm' : 'border-slate-200 bg-white text-slate-900 hover:border-slate-300'"
          @click="applyQueueFilter(queue.value)"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.18em]" :class="form.queue === queue.value ? 'text-slate-300' : 'text-slate-500'">
                Antrean
              </p>
              <h2 class="mt-2 text-lg font-semibold">{{ queue.label }}</h2>
            </div>
            <div class="text-3xl font-semibold tabular-nums">{{ queue.count ?? 0 }}</div>
          </div>
          <p class="mt-3 text-sm" :class="form.queue === queue.value ? 'text-slate-300' : 'text-slate-600'">
            {{ queue.description }}
          </p>
        </button>
      </section>

      <Card class="border-slate-200/80 shadow-sm">
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Daftar Review Aktif</CardTitle>
            <p class="mt-1 text-sm text-slate-600">
              Fokuskan daftar berdasarkan antrean kerja reviewer: mulai review, lanjut valuasi, atau kirim preview.
            </p>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari nomor request, klien, atau pemohon"
            filter-title="Filter review"
            filter-description="Saring queue reviewer berdasarkan status permohonan."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; applyFilters(); }"
            @apply-filters="applyFilters"
            @reset-filters="resetFilters"
          >
            <div class="space-y-2">
              <Label for="reviewer_review_queue_filter">Antrean</Label>
              <Select v-model="form.queue">
                <SelectTrigger id="reviewer_review_queue_filter">
                  <SelectValue placeholder="Semua antrean" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Semua Antrean</SelectItem>
                  <SelectItem v-for="queue in queueOptions" :key="queue.value" :value="queue.value">
                    {{ queue.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-2">
              <Label for="reviewer_review_status_filter">Status</Label>
              <Select v-model="form.status">
                <SelectTrigger id="reviewer_review_status_filter">
                  <SelectValue placeholder="Semua status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Semua Status</SelectItem>
                  <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">
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
            :default-per-page="filters.per_page ?? 12"
            empty-text="Belum ada review yang cocok dengan filter saat ini."
          >
            <template #cell-request="{ row }">
              <Button variant="link" class="h-auto px-0 font-medium" as-child>
                <Link :href="row.detail_url">{{ row.request_number }}</Link>
              </Button>
            </template>

            <template #cell-client="{ row }">
              <div class="font-medium text-slate-950">{{ row.client_name }}</div>
            </template>

            <template #cell-status="{ row }">
              <StatusBadge :status="row.status" />
            </template>

            <template #cell-assets="{ row }">
              {{ row.assets_count }}
            </template>

            <template #cell-contract="{ row }">
              {{ row.contract_number || '-' }}
            </template>

            <template #cell-action="{ row }">
              <div class="space-y-1">
                <Button variant="link" class="h-auto px-0 text-left font-medium" as-child>
                  <Link :href="row.next_action?.url || row.detail_url">{{ row.next_action?.label || 'Buka Detail' }}</Link>
                </Button>
                <p class="text-xs text-slate-500">{{ row.next_action?.description || '-' }}</p>
              </div>
            </template>

            <template #cell-requested_at="{ row }">
              {{ formatDateTime(row.requested_at) }}
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </ReviewerLayout>
</template>
