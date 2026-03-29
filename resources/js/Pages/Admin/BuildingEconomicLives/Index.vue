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
  filters: { type: Object, default: () => ({ q: '', guideline_item_id: 'all', year: 'all', category: 'all', building_class: 'all' }) },
  guidelineSetOptions: { type: Array, default: () => [] },
  yearOptions: { type: Array, default: () => [] },
  categoryOptions: { type: Array, default: () => [] },
  buildingClassOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, guideline_sets: 0, categories: 0, active_guideline: 0 }) },
  records: { type: Object, required: true },
  indexUrl: { type: String, required: true },
  createUrl: { type: String, required: true },
  importUrl: { type: String, default: '' },
  exportUrl: { type: String, default: '' },
  importDefaults: { type: Object, default: () => ({ guideline_item_id: '', year: '' }) },
});

const form = reactive({
  q: props.filters.q ?? '',
  guideline_item_id: props.filters.guideline_item_id ?? 'all',
  year: props.filters.year ?? 'all',
  category: props.filters.category ?? 'all',
  building_class: props.filters.building_class ?? 'all',
});
const importDialogOpen = ref(false);

const importForm = useForm({
  guideline_item_id: props.importDefaults.guideline_item_id ? String(props.importDefaults.guideline_item_id) : '',
  year: props.importDefaults.year ?? '',
  file: null,
});

const columns = [
  { key: 'guideline_set_name', label: 'Guideline', cellClass: 'min-w-[220px]' },
  { key: 'category', label: 'Kategori', cellClass: 'min-w-[180px]' },
  { key: 'building_type', label: 'Jenis', cellClass: 'min-w-[160px]' },
  { key: 'building_class', label: 'Class', cellClass: 'min-w-[140px]' },
  { key: 'storey_label', label: 'Rentang Lantai', cellClass: 'min-w-[140px]' },
  { key: 'economic_life', label: 'BEL', cellClass: 'min-w-[90px]', sortable: true },
  { key: 'updated_at', label: 'Diubah', cellClass: 'min-w-[140px]', sortable: true },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[200px]' },
];

const submitFilters = () => {
  router.get(props.indexUrl, {
    q: form.q || undefined,
    guideline_item_id: form.guideline_item_id === 'all' ? undefined : form.guideline_item_id,
    year: form.year === 'all' ? undefined : form.year,
    category: form.category === 'all' ? undefined : form.category,
    building_class: form.building_class === 'all' ? undefined : form.building_class,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  form.q = '';
  form.guideline_item_id = 'all';
  form.year = 'all';
  form.category = 'all';
  form.building_class = 'all';
  submitFilters();
};

const activeFilterCount = computed(() => {
  let count = 0;
  if (form.guideline_item_id !== 'all') count += 1;
  if (form.year !== 'all') count += 1;
  if (form.category !== 'all') count += 1;
  if (form.building_class !== 'all') count += 1;
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
  <Head title="Admin - BEL" />

  <AdminLayout title="BEL">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Pedoman Referensi</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">BEL</h1>
          <p class="mt-2 text-sm text-slate-600">
            Building Economic Life berdasarkan guideline, kategori bangunan, class, dan rentang lantai.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <AdminImportExportButtonGroup
            :show-import="Boolean(importUrl)"
            :show-export="Boolean(exportUrl)"
            :export-url="exportUrl"
            @import="importDialogOpen = true"
          />
          <Button as-child><Link :href="createUrl">Tambah BEL</Link></Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Row</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Guideline Set</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.guideline_sets }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Kategori</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.categories }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Guideline Aktif</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.active_guideline }}</p></CardContent></Card>
      </section>

      <Card>
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Daftar BEL</CardTitle>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari kategori, sub kategori, jenis, atau class"
            filter-title="Filter BEL"
            filter-description="Saring data BEL berdasarkan guideline, tahun, kategori, dan class."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; submitFilters(); }"
            @apply-filters="submitFilters"
            @reset-filters="resetFilters"
          >
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="space-y-2">
                <Label for="bel_guideline_filter">Guideline</Label>
                <Select v-model="form.guideline_item_id">
                  <SelectTrigger id="bel_guideline_filter"><SelectValue placeholder="Pilih guideline" /></SelectTrigger>
                  <SelectContent><SelectItem v-for="option in guidelineSetOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="bel_year_filter">Tahun</Label>
                <Select v-model="form.year">
                  <SelectTrigger id="bel_year_filter"><SelectValue placeholder="Pilih tahun" /></SelectTrigger>
                  <SelectContent><SelectItem v-for="option in yearOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="bel_category_filter">Kategori</Label>
                <Select v-model="form.category">
                  <SelectTrigger id="bel_category_filter"><SelectValue placeholder="Pilih kategori" /></SelectTrigger>
                  <SelectContent><SelectItem v-for="option in categoryOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="bel_class_filter">Class</Label>
                <Select v-model="form.building_class">
                  <SelectTrigger id="bel_class_filter"><SelectValue placeholder="Pilih class" /></SelectTrigger>
                  <SelectContent><SelectItem v-for="option in buildingClassOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
                </Select>
              </div>
            </div>
          </AdminTableToolbar>
        </CardHeader>
        <CardContent>
          <AdminDataTable :columns="columns" :rows="records.data" :meta="records.meta" empty-text="Belum ada data BEL.">
            <template #cell-guideline_set_name="{ row }">
              <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-medium text-slate-950">{{ row.guideline_set_name }}</p>
                  <Badge v-if="row.guideline_is_active" variant="outline" class="border-emerald-200 bg-emerald-50 text-emerald-800">Aktif</Badge>
                </div>
                <p class="text-xs text-slate-500">{{ row.year }}</p>
              </div>
            </template>

            <template #cell-category="{ row }">
              <div class="space-y-1">
                <p class="font-medium text-slate-950">{{ row.category }}</p>
                <p v-if="row.sub_category" class="text-xs text-slate-500">{{ row.sub_category }}</p>
              </div>
            </template>

            <template #cell-building_type="{ row }">
              {{ row.building_type || '-' }}
            </template>

            <template #cell-building_class="{ row }">
              <Badge variant="outline">{{ row.building_class || 'DEFAULT' }}</Badge>
            </template>

            <template #cell-storey_label="{ row }">
              {{ row.storey_label }}
            </template>

            <template #cell-economic_life="{ row }">
              <Badge variant="outline" class="font-mono">{{ row.economic_life }}</Badge>
            </template>

            <template #cell-updated_at="{ row }">
              {{ formatDateTime(row.updated_at) }}
            </template>

            <template #cell-actions="{ row }">
              <AdminEntityActions
                :edit-href="row.edit_url"
                :delete-url="row.destroy_url"
                entity-label="BEL"
                :entity-name="row.category"
              />
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>

    <Dialog :open="importDialogOpen" @update:open="importDialogOpen = $event">
      <DialogContent class="sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>Import BEL</DialogTitle>
          <DialogDescription>
            Upload file `.xlsx`, `.xls`, atau `.csv` dengan header `category`, `sub_category`, `building_type`, `building_class`, `storey_min`, `storey_max`, dan `economic_life`.
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-5" @submit.prevent="submitImport">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="bel_import_guideline">Guideline Set</Label>
              <Select v-model="importForm.guideline_item_id">
                <SelectTrigger id="bel_import_guideline"><SelectValue placeholder="Pilih guideline set" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in guidelineSetOptions.filter((item) => item.value !== 'all')" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="importForm.errors.guideline_item_id" class="text-xs text-rose-600">{{ importForm.errors.guideline_item_id }}</p>
            </div>

            <div class="space-y-2">
              <Label for="bel_import_year">Tahun</Label>
              <Input id="bel_import_year" v-model="importForm.year" type="number" min="2000" max="2100" />
              <p v-if="importForm.errors.year" class="text-xs text-rose-600">{{ importForm.errors.year }}</p>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="bel_import_file">File Excel</Label>
            <Input
              id="bel_import_file"
              type="file"
              accept=".xlsx,.xls,.csv"
              @change="importForm.file = $event.target.files?.[0] ?? null"
            />
            <p class="text-xs text-slate-500">File boleh menyertakan `guideline_item_id` dan `year`, tetapi jika kosong akan memakai nilai dari dialog ini.</p>
            <p v-if="importForm.errors.file" class="text-xs text-rose-600">{{ importForm.errors.file }}</p>
          </div>

          <DialogFooter class="gap-2 sm:justify-end">
            <Button type="button" variant="outline" @click="importDialogOpen = false">Batal</Button>
            <Button type="submit" :disabled="importForm.processing">Import BEL</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </AdminLayout>
</template>
