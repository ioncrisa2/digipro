<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import AdminEntityActions from '@/components/admin/AdminEntityActions.vue';
import AdminImportExportButtonGroup from '@/components/admin/AdminImportExportButtonGroup.vue';
import AdminTableToolbar from '@/components/admin/AdminTableToolbar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatDateTime } from '@/utils/reviewer';

const props = defineProps({
  filters: { type: Object, default: () => ({ q: '', status: 'all' }) },
  statusOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, active: 0, valuation_settings: 0, ikk_rows: 0 }) },
  records: { type: Object, required: true },
  indexUrl: { type: String, required: true },
  createUrl: { type: String, required: true },
  exportUrl: { type: String, default: '' },
});

const form = reactive({
  q: props.filters.q ?? '',
  status: props.filters.status ?? 'all',
});

const columns = [
  { key: 'name', label: 'Guideline', cellClass: 'min-w-[220px]', sortable: true },
  { key: 'year', label: 'Tahun', cellClass: 'w-[90px]', sortable: true },
  { key: 'status', label: 'Status', cellClass: 'min-w-[120px]' },
  { key: 'coverage', label: 'Coverage', cellClass: 'min-w-[260px]' },
  { key: 'updated_at', label: 'Diubah', cellClass: 'min-w-[140px]', sortable: true },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[200px]' },
];

const submitFilters = () => {
  router.get(props.indexUrl, {
    q: form.q || undefined,
    status: form.status === 'all' ? undefined : form.status,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  form.q = '';
  form.status = 'all';
  submitFilters();
};

const activeFilterCount = computed(() => (form.status !== 'all' ? 1 : 0));

</script>

<template>
  <Head title="Admin - Guideline Sets" />

  <AdminLayout title="Guideline Sets">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Pedoman Referensi</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Guideline Sets</h1>
          <p class="mt-2 text-sm text-slate-600">
            Fondasi acuan referensi appraisal. Set aktif dipakai oleh flow request dan reviewer.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <AdminImportExportButtonGroup
            :show-export="Boolean(exportUrl)"
            :export-url="exportUrl"
          />
          <Button as-child><Link :href="createUrl">Tambah Guideline Set</Link></Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Aktif</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.active }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Valuation Settings</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.valuation_settings }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">IKK Rows</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.ikk_rows }}</p></CardContent></Card>
      </section>

      <Card>
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Daftar Guideline Set</CardTitle>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari nama atau deskripsi set pedoman"
            filter-title="Filter set pedoman"
            filter-description="Saring guideline set berdasarkan status aktif."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; submitFilters(); }"
            @apply-filters="submitFilters"
            @reset-filters="resetFilters"
          >
            <div class="space-y-2">
              <Label for="guideline_status_filter">Status</Label>
              <Select v-model="form.status">
                <SelectTrigger id="guideline_status_filter"><SelectValue placeholder="Pilih status" /></SelectTrigger>
                <SelectContent><SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
              </Select>
            </div>
          </AdminTableToolbar>
        </CardHeader>
        <CardContent>
          <AdminDataTable :columns="columns" :rows="records.data" :meta="records.meta" empty-text="Belum ada guideline set.">
            <template #cell-name="{ row }">
              <div class="space-y-1">
                <p class="font-medium text-slate-950">{{ row.name }}</p>
                <p v-if="row.description" class="line-clamp-2 text-xs leading-5 text-slate-500">{{ row.description }}</p>
              </div>
            </template>

            <template #cell-status="{ row }">
              <Badge variant="outline" :class="row.is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-700'">
                {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
              </Badge>
            </template>

            <template #cell-coverage="{ row }">
              <div class="flex flex-wrap gap-2">
                <Badge variant="outline">IKK: {{ row.construction_cost_indexes_count }}</Badge>
                <Badge variant="outline">Cost: {{ row.cost_elements_count }}</Badge>
                <Badge variant="outline">IL: {{ row.floor_indexes_count }}</Badge>
                <Badge variant="outline">RCN: {{ row.mappi_rcn_standards_count }}</Badge>
              </div>
            </template>

            <template #cell-updated_at="{ row }">
              {{ formatDateTime(row.updated_at) }}
            </template>

            <template #cell-actions="{ row }">
              <AdminEntityActions
                :edit-href="row.edit_url"
                :delete-url="row.destroy_url"
                entity-label="set pedoman"
                :entity-name="row.name"
              />
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
