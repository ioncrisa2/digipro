<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import AdminEntityActions from '@/components/admin/AdminEntityActions.vue';
import AdminImportExportButtonGroup from '@/components/admin/AdminImportExportButtonGroup.vue';
import AdminTableToolbar from '@/components/admin/AdminTableToolbar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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
  indexUrl: { type: String, required: true },
  createUrl: { type: String, required: true },
  importUrl: { type: String, default: '' },
  exportUrl: { type: String, default: '' },
  importDefaults: { type: Object, default: () => ({ guideline_set_id: '', year: '', reference_region: 'DKI Jakarta' }) },
});

const form = reactive({
  q: props.filters.q ?? '',
  guideline_set_id: props.filters.guideline_set_id ?? 'all',
  year: props.filters.year ?? 'all',
  building_type: props.filters.building_type ?? 'all',
  building_class: props.filters.building_class ?? 'all',
});
const importDialogOpen = ref(false);

const importForm = useForm({
  guideline_set_id: props.importDefaults.guideline_set_id ? String(props.importDefaults.guideline_set_id) : '',
  year: props.importDefaults.year ?? '',
  reference_region: props.importDefaults.reference_region ?? 'DKI Jakarta',
  file: null,
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

const submitFilters = () => {
  router.get(props.indexUrl, {
    q: form.q || undefined,
    guideline_set_id: form.guideline_set_id === 'all' ? undefined : form.guideline_set_id,
    year: form.year === 'all' ? undefined : form.year,
    building_type: form.building_type === 'all' ? undefined : form.building_type,
    building_class: form.building_class === 'all' ? undefined : form.building_class,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  form.q = '';
  form.guideline_set_id = 'all';
  form.year = 'all';
  form.building_type = 'all';
  form.building_class = 'all';
  submitFilters();
};

const activeFilterCount = computed(() => {
  let count = 0;
  if (form.guideline_set_id !== 'all') count += 1;
  if (form.year !== 'all') count += 1;
  if (form.building_type !== 'all') count += 1;
  if (form.building_class !== 'all') count += 1;
  return count;
});

const formatCurrency = (value) => new Intl.NumberFormat('id-ID').format(Number(value || 0));

const submitImport = () => {
  importForm.post(props.importUrl, {
    forceFormData: true,
    preserveScroll: true,
  });
};
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
          <AdminImportExportButtonGroup
            :show-import="Boolean(importUrl)"
            :show-export="Boolean(exportUrl)"
            :export-url="exportUrl"
            @import="importDialogOpen = true"
          />
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
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Daftar MAPPI RCN</CardTitle>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari type, class, pola lantai, atau catatan"
            filter-title="Filter MAPPI RCN"
            filter-description="Saring data RCN berdasarkan guideline, tahun, tipe, dan class bangunan."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; submitFilters(); }"
            @apply-filters="submitFilters"
            @reset-filters="resetFilters"
          >
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="space-y-2">
                <Label for="mappi_guideline_filter">Guideline</Label>
                <Select v-model="form.guideline_set_id">
                  <SelectTrigger id="mappi_guideline_filter"><SelectValue placeholder="Pilih guideline" /></SelectTrigger>
                  <SelectContent><SelectItem v-for="option in guidelineSetOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="mappi_year_filter">Tahun</Label>
                <Select v-model="form.year">
                  <SelectTrigger id="mappi_year_filter"><SelectValue placeholder="Pilih tahun" /></SelectTrigger>
                  <SelectContent><SelectItem v-for="option in yearOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="mappi_building_type_filter">Building Type</Label>
                <Select v-model="form.building_type">
                  <SelectTrigger id="mappi_building_type_filter"><SelectValue placeholder="Pilih type" /></SelectTrigger>
                  <SelectContent><SelectItem v-for="option in buildingTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="mappi_building_class_filter">Building Class</Label>
                <Select v-model="form.building_class">
                  <SelectTrigger id="mappi_building_class_filter"><SelectValue placeholder="Pilih class" /></SelectTrigger>
                  <SelectContent><SelectItem v-for="option in buildingClassOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
                </Select>
              </div>
            </div>
          </AdminTableToolbar>
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

    <Dialog :open="importDialogOpen" @update:open="importDialogOpen = $event">
      <DialogContent class="sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>Import MAPPI RCN</DialogTitle>
          <DialogDescription>
            Upload file `.xlsx`, `.xls`, atau `.csv` dengan header `building_type`, `building_class`, `storey_pattern`, `rcn_value`, dan `notes`.
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-5" @submit.prevent="submitImport">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="mappi_import_guideline">Guideline Set</Label>
              <Select v-model="importForm.guideline_set_id">
                <SelectTrigger id="mappi_import_guideline"><SelectValue placeholder="Pilih guideline set" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in guidelineSetOptions.filter((item) => item.value !== 'all')" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="importForm.errors.guideline_set_id" class="text-xs text-rose-600">{{ importForm.errors.guideline_set_id }}</p>
            </div>

            <div class="space-y-2">
              <Label for="mappi_import_year">Tahun</Label>
              <Input id="mappi_import_year" v-model="importForm.year" type="number" min="2000" max="2100" />
              <p v-if="importForm.errors.year" class="text-xs text-rose-600">{{ importForm.errors.year }}</p>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="mappi_import_reference_region">Reference Region</Label>
            <Input id="mappi_import_reference_region" v-model="importForm.reference_region" type="text" />
            <p class="text-xs text-slate-500">Default workbook MAPPI RCN biasanya memakai `DKI Jakarta`, tetapi Anda tetap bisa override per batch.</p>
            <p v-if="importForm.errors.reference_region" class="text-xs text-rose-600">{{ importForm.errors.reference_region }}</p>
          </div>

          <div class="space-y-2">
            <Label for="mappi_import_file">File Excel</Label>
            <Input
              id="mappi_import_file"
              type="file"
              accept=".xlsx,.xls,.csv"
              @change="importForm.file = $event.target.files?.[0] ?? null"
            />
            <p class="text-xs text-slate-500">Alias header seperti `tipe_bangunan`, `kelas_bangunan`, `pola_lantai`, dan `nilai_rcn` juga didukung oleh importer.</p>
            <p v-if="importForm.errors.file" class="text-xs text-rose-600">{{ importForm.errors.file }}</p>
          </div>

          <DialogFooter class="gap-2 sm:justify-end">
            <Button type="button" variant="outline" @click="importDialogOpen = false">Batal</Button>
            <Button type="submit" :disabled="importForm.processing">Import Excel</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </AdminLayout>
</template>
