<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import AdminEntityActions from '@/components/admin/AdminEntityActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
  filters: { type: Object, default: () => ({ q: '', guideline_set_id: 'all', year: 'all', building_type: 'all', building_class: 'all' }) },
  guidelineSetOptions: { type: Array, default: () => [] },
  yearOptions: { type: Array, default: () => [] },
  buildingTypeOptions: { type: Array, default: () => [] },
  buildingClassOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, guideline_sets: 0, building_types: 0, active_guideline: 0 }) },
  records: { type: Object, required: true },
  createUrl: { type: String, required: true },
});

const columns = [
  { key: 'guideline_set_name', label: 'Guideline', cellClass: 'min-w-[220px]' },
  { key: 'year', label: 'Tahun', cellClass: 'w-[90px]', sortable: true },
  { key: 'building_type', label: 'Building Type', cellClass: 'min-w-[180px]' },
  { key: 'building_class', label: 'Building Class', cellClass: 'min-w-[150px]' },
  { key: 'rcn_value', label: 'RCN', cellClass: 'min-w-[140px]', sortable: true },
  { key: 'updated_at', label: 'Diubah', cellClass: 'min-w-[140px]', sortable: true },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[200px]' },
];

const applyFilters = (patch = {}) => {
  router.get(route('admin.ref-guidelines.mappi-rcn-standards.index'), { ...props.filters, ...patch }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const formatCurrency = (value) => new Intl.NumberFormat('id-ID').format(Number(value || 0));
</script>

<template>
  <Head title="Admin - MAPPI RCN" />

  <AdminLayout title="MAPPI RCN">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Pedoman Referensi</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">MAPPI RCN</h1>
          <p class="mt-2 text-sm text-slate-600">
            Standar Total Biaya Pembangunan Baru MAPPI untuk referensi DKI Jakarta.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button as-child><Link :href="createUrl">Tambah MAPPI RCN</Link></Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Row</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Guideline Set</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.guideline_sets }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Building Type</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.building_types }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Guideline Aktif</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.active_guideline }}</p></CardContent></Card>
      </section>

      <Card>
        <CardHeader><CardTitle>Filter</CardTitle><CardDescription>Filter berdasarkan guideline, tahun, building type, building class, dan kata kunci.</CardDescription></CardHeader>
        <CardContent class="grid gap-4 xl:grid-cols-[1.4fr_1fr_0.7fr_1fr_1fr]">
          <div class="space-y-2">
            <Label for="mappi_q">Cari</Label>
            <Input id="mappi_q" :model-value="filters.q" placeholder="Type, class, storey pattern, atau notes" @change="applyFilters({ q: $event.target.value })" />
          </div>
          <div class="space-y-2">
            <Label for="mappi_guideline">Guideline</Label>
            <Select :model-value="filters.guideline_set_id" @update:model-value="applyFilters({ guideline_set_id: $event })">
              <SelectTrigger id="mappi_guideline"><SelectValue placeholder="Pilih guideline" /></SelectTrigger>
              <SelectContent><SelectItem v-for="option in guidelineSetOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label for="mappi_year">Tahun</Label>
            <Select :model-value="filters.year" @update:model-value="applyFilters({ year: $event })">
              <SelectTrigger id="mappi_year"><SelectValue placeholder="Pilih tahun" /></SelectTrigger>
              <SelectContent><SelectItem v-for="option in yearOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label for="mappi_building_type">Building Type</Label>
            <Select :model-value="filters.building_type" @update:model-value="applyFilters({ building_type: $event })">
              <SelectTrigger id="mappi_building_type"><SelectValue placeholder="Pilih type" /></SelectTrigger>
              <SelectContent><SelectItem v-for="option in buildingTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label for="mappi_building_class">Building Class</Label>
            <Select :model-value="filters.building_class" @update:model-value="applyFilters({ building_class: $event })">
              <SelectTrigger id="mappi_building_class"><SelectValue placeholder="Pilih class" /></SelectTrigger>
              <SelectContent><SelectItem v-for="option in buildingClassOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Daftar MAPPI RCN</CardTitle>
          <CardDescription>
            Menampilkan {{ records.meta?.from ?? 0 }}-{{ records.meta?.to ?? 0 }} dari {{ records.meta?.total ?? 0 }} data.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <AdminDataTable :columns="columns" :rows="records.data" :meta="records.meta" empty-text="Belum ada MAPPI RCN.">
            <template #cell-guideline_set_name="{ row }">
              <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-medium text-slate-950">{{ row.guideline_set_name }}</p>
                  <Badge v-if="row.guideline_is_active" variant="outline" class="border-emerald-200 bg-emerald-50 text-emerald-800">Aktif</Badge>
                </div>
                <p class="text-xs text-slate-500">{{ row.reference_region }}</p>
              </div>
            </template>

            <template #cell-building_type="{ row }">
              <div class="space-y-1">
                <p class="font-medium text-slate-950">{{ row.building_type }}</p>
                <p class="text-xs text-slate-500">{{ row.storey_pattern || '-' }}</p>
              </div>
            </template>

            <template #cell-building_class="{ row }">
              <Badge variant="outline">{{ row.building_class || '-' }}</Badge>
            </template>

            <template #cell-rcn_value="{ row }">
              <div class="space-y-1">
                <p class="font-medium text-slate-950">Rp {{ formatCurrency(row.rcn_value) }}</p>
                <p class="text-xs text-slate-500">reference region {{ row.reference_region }}</p>
              </div>
            </template>

            <template #cell-updated_at="{ row }">
              {{ formatDateTime(row.updated_at) }}
            </template>

            <template #cell-actions="{ row }">
              <AdminEntityActions
                :edit-href="row.edit_url"
                :delete-url="row.destroy_url"
                entity-label="MAPPI RCN"
                :entity-name="row.building_type"
              />
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
