<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import AdminEntityActions from '@/components/admin/AdminEntityActions.vue';
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
  filters: { type: Object, default: () => ({ q: '', guideline_set_id: 'all', year: 'all', building_class: 'all' }) },
  guidelineSetOptions: { type: Array, default: () => [] },
  yearOptions: { type: Array, default: () => [] },
  buildingClassOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, guideline_sets: 0, classes: 0, active_guideline: 0 }) },
  records: { type: Object, required: true },
  createUrl: { type: String, required: true },
  importUrl: { type: String, default: '' },
  importDefaults: { type: Object, default: () => ({ guideline_set_id: '', year: '' }) },
});

const form = reactive({
  q: props.filters.q ?? '',
  guideline_set_id: props.filters.guideline_set_id ?? 'all',
  year: props.filters.year ?? 'all',
  building_class: props.filters.building_class ?? 'all',
});
const importDialogOpen = ref(false);

const importForm = useForm({
  guideline_set_id: props.importDefaults.guideline_set_id ? String(props.importDefaults.guideline_set_id) : '',
  year: props.importDefaults.year ?? '',
  file: null,
});

const columns = [
  { key: 'guideline_set_name', label: 'Guideline', cellClass: 'min-w-[220px]' },
  { key: 'year', label: 'Tahun', cellClass: 'w-[90px]', sortable: true },
  { key: 'building_class', label: 'Class', cellClass: 'min-w-[140px]' },
  { key: 'floor_count', label: 'Lantai', cellClass: 'min-w-[100px]', sortable: true },
  { key: 'il_value', label: 'IL', cellClass: 'min-w-[110px]', sortable: true },
  { key: 'updated_at', label: 'Diubah', cellClass: 'min-w-[140px]', sortable: true },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[200px]' },
];

const submitFilters = () => {
  router.get(route('admin.ref-guidelines.floor-indices.index'), {
    q: form.q || undefined,
    guideline_set_id: form.guideline_set_id === 'all' ? undefined : form.guideline_set_id,
    year: form.year === 'all' ? undefined : form.year,
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
  form.building_class = 'all';
  submitFilters();
};

const activeFilterCount = computed(() => {
  let count = 0;
  if (form.guideline_set_id !== 'all') count += 1;
  if (form.year !== 'all') count += 1;
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
  <Head title="Admin - Floor Index" />

  <AdminLayout title="Floor Index">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Pedoman Referensi</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Floor Index</h1>
          <p class="mt-2 text-sm text-slate-600">
            Referensi index lantai per guideline, class bangunan, dan jumlah lantai.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button v-if="importUrl" variant="outline" type="button" @click="importDialogOpen = true">Import</Button>
          <Button as-child><Link :href="createUrl">Tambah Floor Index</Link></Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Row</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Guideline Set</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.guideline_sets }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Class</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.classes }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Guideline Aktif</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.active_guideline }}</p></CardContent></Card>
      </section>

      <Card>
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Daftar Floor Index</CardTitle>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari class bangunan atau jumlah lantai"
            filter-title="Filter indeks lantai"
            filter-description="Saring data indeks lantai berdasarkan guideline, tahun, dan class bangunan."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; submitFilters(); }"
            @apply-filters="submitFilters"
            @reset-filters="resetFilters"
          >
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="space-y-2">
                <Label for="floor_guideline_filter">Guideline</Label>
                <Select v-model="form.guideline_set_id">
                  <SelectTrigger id="floor_guideline_filter"><SelectValue placeholder="Pilih guideline" /></SelectTrigger>
                  <SelectContent><SelectItem v-for="option in guidelineSetOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="floor_year_filter">Tahun</Label>
                <Select v-model="form.year">
                  <SelectTrigger id="floor_year_filter"><SelectValue placeholder="Pilih tahun" /></SelectTrigger>
                  <SelectContent><SelectItem v-for="option in yearOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
                </Select>
              </div>
              <div class="space-y-2 sm:col-span-2">
                <Label for="floor_class_filter">Class</Label>
                <Select v-model="form.building_class">
                  <SelectTrigger id="floor_class_filter"><SelectValue placeholder="Pilih class" /></SelectTrigger>
                  <SelectContent><SelectItem v-for="option in buildingClassOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
                </Select>
              </div>
            </div>
          </AdminTableToolbar>
        </CardHeader>
        <CardContent>
          <AdminDataTable :columns="columns" :rows="records.data" :meta="records.meta" empty-text="Belum ada floor index.">
            <template #cell-guideline_set_name="{ row }">
              <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-medium text-slate-950">{{ row.guideline_set_name }}</p>
                  <Badge v-if="row.guideline_is_active" variant="outline" class="border-emerald-200 bg-emerald-50 text-emerald-800">Aktif</Badge>
                </div>
                <p class="text-xs text-slate-500">{{ row.year }}</p>
              </div>
            </template>

            <template #cell-building_class="{ row }">
              <Badge variant="outline">{{ row.building_class }}</Badge>
            </template>

            <template #cell-il_value="{ row }">
              <Badge variant="outline" class="font-mono">{{ Number(row.il_value).toFixed(4) }}</Badge>
            </template>

            <template #cell-updated_at="{ row }">
              {{ formatDateTime(row.updated_at) }}
            </template>

            <template #cell-actions="{ row }">
              <AdminEntityActions
                :edit-href="row.edit_url"
                :delete-url="row.destroy_url"
                entity-label="indeks lantai"
                :entity-name="`${row.building_class} lantai ${row.floor_count}`"
              />
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>

    <Dialog :open="importDialogOpen" @update:open="importDialogOpen = $event">
      <DialogContent class="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>Import Floor Index</DialogTitle>
          <DialogDescription>
            Upload file `.xlsx`, `.xls`, atau `.csv` dengan header `building_class`, `floor_count`, dan `il_value`.
          </DialogDescription>
        </DialogHeader>

        <form class="space-y-5" @submit.prevent="submitImport">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="floor_import_guideline">Guideline Set</Label>
              <Select v-model="importForm.guideline_set_id">
                <SelectTrigger id="floor_import_guideline"><SelectValue placeholder="Pilih guideline set" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in guidelineSetOptions.filter((item) => item.value !== 'all')" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="importForm.errors.guideline_set_id" class="text-xs text-rose-600">{{ importForm.errors.guideline_set_id }}</p>
            </div>

            <div class="space-y-2">
              <Label for="floor_import_year">Tahun</Label>
              <Input id="floor_import_year" v-model="importForm.year" type="number" min="2000" max="2100" />
              <p v-if="importForm.errors.year" class="text-xs text-rose-600">{{ importForm.errors.year }}</p>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="floor_import_file">File Excel</Label>
            <Input
              id="floor_import_file"
              type="file"
              accept=".xlsx,.xls,.csv"
              @change="importForm.file = $event.target.files?.[0] ?? null"
            />
            <p class="text-xs text-slate-500">Gunakan format tiga kolom sederhana agar bisa langsung diproses oleh `FloorIndexImport`.</p>
            <p v-if="importForm.errors.file" class="text-xs text-rose-600">{{ importForm.errors.file }}</p>
          </div>

          <DialogFooter class="gap-2 sm:justify-end">
            <Button type="button" variant="outline" @click="importDialogOpen = false">Batal</Button>
            <Button type="submit" :disabled="importForm.processing">Import Floor Index</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </AdminLayout>
</template>
