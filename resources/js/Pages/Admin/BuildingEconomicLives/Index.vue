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
  filters: { type: Object, default: () => ({ q: '', guideline_item_id: 'all', year: 'all', category: 'all', building_class: 'all' }) },
  guidelineSetOptions: { type: Array, default: () => [] },
  yearOptions: { type: Array, default: () => [] },
  categoryOptions: { type: Array, default: () => [] },
  buildingClassOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, guideline_sets: 0, categories: 0, active_guideline: 0 }) },
  records: { type: Object, required: true },
  createUrl: { type: String, required: true },
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

const applyFilters = (patch = {}) => {
  router.get(route('admin.ref-guidelines.building-economic-lives.index'), { ...props.filters, ...patch }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
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
        <CardHeader><CardTitle>Filter</CardTitle><CardDescription>Filter berdasarkan guideline, tahun, kategori, class, dan kata kunci.</CardDescription></CardHeader>
        <CardContent class="grid gap-4 xl:grid-cols-[1.3fr_1fr_0.7fr_1fr_1fr]">
          <div class="space-y-2">
            <Label for="bel_q">Cari</Label>
            <Input id="bel_q" :model-value="filters.q" placeholder="Kategori, sub kategori, jenis, atau class" @change="applyFilters({ q: $event.target.value })" />
          </div>
          <div class="space-y-2">
            <Label for="bel_guideline">Guideline</Label>
            <Select :model-value="filters.guideline_item_id" @update:model-value="applyFilters({ guideline_item_id: $event })">
              <SelectTrigger id="bel_guideline"><SelectValue placeholder="Pilih guideline" /></SelectTrigger>
              <SelectContent><SelectItem v-for="option in guidelineSetOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label for="bel_year">Tahun</Label>
            <Select :model-value="filters.year" @update:model-value="applyFilters({ year: $event })">
              <SelectTrigger id="bel_year"><SelectValue placeholder="Pilih tahun" /></SelectTrigger>
              <SelectContent><SelectItem v-for="option in yearOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label for="bel_category">Kategori</Label>
            <Select :model-value="filters.category" @update:model-value="applyFilters({ category: $event })">
              <SelectTrigger id="bel_category"><SelectValue placeholder="Pilih kategori" /></SelectTrigger>
              <SelectContent><SelectItem v-for="option in categoryOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label for="bel_class">Class</Label>
            <Select :model-value="filters.building_class" @update:model-value="applyFilters({ building_class: $event })">
              <SelectTrigger id="bel_class"><SelectValue placeholder="Pilih class" /></SelectTrigger>
              <SelectContent><SelectItem v-for="option in buildingClassOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Daftar BEL</CardTitle>
          <CardDescription>
            Menampilkan {{ records.meta?.from ?? 0 }}-{{ records.meta?.to ?? 0 }} dari {{ records.meta?.total ?? 0 }} data.
          </CardDescription>
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
  </AdminLayout>
</template>
