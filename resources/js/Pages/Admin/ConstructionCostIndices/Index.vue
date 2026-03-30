<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import AdminEntityActions from '@/components/admin/AdminEntityActions.vue';
import AdminImportExportButtonGroup from '@/components/admin/AdminImportExportButtonGroup.vue';
import AdminTableToolbar from '@/components/admin/AdminTableToolbar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
  filters: { type: Object, default: () => ({ q: '', guideline_set_id: 'all', year: 'all', province_id: 'all' }) },
  guidelineSetOptions: { type: Array, default: () => [] },
  yearOptions: { type: Array, default: () => [] },
  provinceOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, guideline_sets: 0, provinces: 0, active_guideline: 0 }) },
  records: { type: Object, required: true },
  indexUrl: { type: String, required: true },
  createUrl: { type: String, required: true },
  ikkByProvinceUrl: { type: String, default: '' },
  importUrl: { type: String, default: '' },
  exportUrl: { type: String, default: '' },
  importDefaults: { type: Object, default: () => ({ guideline_set_id: '', year: '', skip_province_rows: true, require_regency: true }) },
});

const form = reactive({
  q: props.filters.q ?? '',
  guideline_set_id: props.filters.guideline_set_id ?? 'all',
  year: props.filters.year ?? 'all',
  province_id: props.filters.province_id ?? 'all',
});
const importDialogOpen = ref(false);

const importForm = useForm({
  guideline_set_id: props.importDefaults.guideline_set_id ? String(props.importDefaults.guideline_set_id) : '',
  year: props.importDefaults.year ?? '',
  file: null,
  skip_province_rows: Boolean(props.importDefaults.skip_province_rows ?? true),
  require_regency: Boolean(props.importDefaults.require_regency ?? true),
});

const columns = [
  { key: 'guideline_set_name', label: 'Guideline', cellClass: 'min-w-[220px]' },
  { key: 'year', label: 'Tahun', cellClass: 'w-[90px]', sortable: true },
  { key: 'province_name', label: 'Provinsi', cellClass: 'min-w-[160px]' },
  { key: 'region_name', label: 'Kabupaten/Kota', cellClass: 'min-w-[220px]' },
  { key: 'ikk_value', label: 'IKK', cellClass: 'min-w-[120px]', sortable: true },
  { key: 'updated_at', label: 'Diubah', cellClass: 'min-w-[140px]', sortable: true },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[200px]' },
];

const submitFilters = () => {
  router.get(props.indexUrl, {
    q: form.q || undefined,
    guideline_set_id: form.guideline_set_id === 'all' ? undefined : form.guideline_set_id,
    year: form.year === 'all' ? undefined : form.year,
    province_id: form.province_id === 'all' ? undefined : form.province_id,
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
  form.province_id = 'all';
  submitFilters();
};

const activeFilterCount = computed(() => {
  let count = 0;
  if (form.guideline_set_id !== 'all') count += 1;
  if (form.year !== 'all') count += 1;
  if (form.province_id !== 'all') count += 1;
  return count;
});

const submitImport = () => {
  importForm.post(props.importUrl, {
    forceFormData: true,
    preserveScroll: true,
  });
};

</script>

<template>
  <Head title="Admin - IKK" />

  <AdminLayout title="IKK">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Pedoman Referensi</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">IKK</h1>
          <p class="mt-2 text-sm text-slate-600">
            Construction Cost Index per kabupaten/kota untuk guideline appraisal aktif maupun historis.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button v-if="ikkByProvinceUrl" variant="outline" as-child><Link :href="ikkByProvinceUrl">Input IKK by Provinsi</Link></Button>
          <AdminImportExportButtonGroup
            :show-import="Boolean(importUrl)"
            :show-export="Boolean(exportUrl)"
            :export-url="exportUrl"
            @import="importDialogOpen = true"
          />
          <Button as-child><Link :href="createUrl">Tambah IKK</Link></Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Row</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Guideline Set</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.guideline_sets }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Provinsi</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.provinces }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Guideline Aktif</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.active_guideline }}</p></CardContent></Card>
      </section>

      <Card>
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Daftar IKK</CardTitle>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari kode atau nama kabupaten/kota"
            filter-title="Filter indeks kemahalan konstruksi"
            filter-description="Saring data IKK berdasarkan guideline, tahun, dan provinsi."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; submitFilters(); }"
            @apply-filters="submitFilters"
            @reset-filters="resetFilters"
          >
            <div class="grid gap-4">
              <div class="space-y-2">
                <Label for="ikk_guideline_filter">Guideline</Label>
                <Select v-model="form.guideline_set_id">
                  <SelectTrigger id="ikk_guideline_filter" class="w-full"><SelectValue placeholder="Pilih guideline" /></SelectTrigger>
                  <SelectContent><SelectItem v-for="option in guidelineSetOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="ikk_year_filter">Tahun</Label>
                <Select v-model="form.year">
                  <SelectTrigger id="ikk_year_filter" class="w-full"><SelectValue placeholder="Pilih tahun" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Semua Tahun</SelectItem>
                    <SelectItem v-for="option in yearOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="ikk_province_filter">Provinsi</Label>
                <Select v-model="form.province_id">
                  <SelectTrigger id="ikk_province_filter" class="w-full"><SelectValue placeholder="Pilih provinsi" /></SelectTrigger>
                  <SelectContent><SelectItem v-for="option in provinceOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
                </Select>
              </div>
            </div>
          </AdminTableToolbar>
        </CardHeader>
        <CardContent>
          <AdminDataTable :columns="columns" :rows="records.data" :meta="records.meta" empty-text="Belum ada data IKK.">
            <template #cell-guideline_set_name="{ row }">
              <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-medium text-slate-950">{{ row.guideline_set_name }}</p>
                  <Badge v-if="row.guideline_is_active" variant="outline" class="border-emerald-200 bg-emerald-50 text-emerald-800">Aktif</Badge>
                </div>
                <p class="text-xs text-slate-500">Kode: {{ row.region_code }}</p>
              </div>
            </template>

            <template #cell-region_name="{ row }">
              <div class="space-y-1">
                <p class="font-medium text-slate-950">{{ row.region_name }}</p>
                <p class="text-xs text-slate-500">{{ row.region_code }}</p>
              </div>
            </template>

            <template #cell-ikk_value="{ row }">
              <Badge variant="outline" class="font-mono">{{ Number(row.ikk_value).toFixed(4) }}</Badge>
            </template>

            <template #cell-updated_at="{ row }">
              {{ formatDateTime(row.updated_at) }}
            </template>

            <template #cell-actions="{ row }">
              <AdminEntityActions
                :edit-href="row.edit_url"
                :delete-url="row.destroy_url"
                entity-label="IKK"
                :entity-name="row.region_name"
              />
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>

    <Dialog :open="importDialogOpen" @update:open="importDialogOpen = $event">
      <DialogContent class="sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>Import Excel IKK</DialogTitle>
          <DialogDescription>
            Upload file `.xlsx`, `.xls`, atau `.csv` dengan header `kode`, `nama_provinsi_kota_kabupaten`, dan `ikk_mappi`.
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-5" @submit.prevent="submitImport">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="ikk_import_guideline">Guideline Set</Label>
              <Select v-model="importForm.guideline_set_id">
                <SelectTrigger id="ikk_import_guideline"><SelectValue placeholder="Pilih guideline set" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in guidelineSetOptions.filter((item) => item.value !== 'all')" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="importForm.errors.guideline_set_id" class="text-xs text-rose-600">{{ importForm.errors.guideline_set_id }}</p>
            </div>

            <div class="space-y-2">
              <Label for="ikk_import_year">Tahun</Label>
              <Input id="ikk_import_year" v-model="importForm.year" type="number" min="2000" max="2100" />
              <p v-if="importForm.errors.year" class="text-xs text-rose-600">{{ importForm.errors.year }}</p>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="ikk_import_file">File Excel</Label>
            <Input
              id="ikk_import_file"
              type="file"
              accept=".xlsx,.xls,.csv"
              @change="importForm.file = $event.target.files?.[0] ?? null"
            />
            <p class="text-xs text-slate-500">Baris provinsi bisa di-skip otomatis, dan import bisa dibatasi hanya untuk kode kabupaten/kota yang ada di master regency.</p>
            <p v-if="importForm.errors.file" class="text-xs text-rose-600">{{ importForm.errors.file }}</p>
          </div>

          <div class="grid gap-3 md:grid-cols-2">
            <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-3 text-sm text-slate-700">
              <Checkbox :model-value="importForm.skip_province_rows" @update:model-value="importForm.skip_province_rows = Boolean($event)" />
              <span>Skip baris provinsi</span>
            </label>
            <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-3 text-sm text-slate-700">
              <Checkbox :model-value="importForm.require_regency" @update:model-value="importForm.require_regency = Boolean($event)" />
              <span>Wajib cocok master regency</span>
            </label>
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
