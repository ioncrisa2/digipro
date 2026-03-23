<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
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
  filters: { type: Object, default: () => ({ q: '', guideline_set_id: 'all', year: 'all', base_region: 'all', group: 'all' }) },
  guidelineSetOptions: { type: Array, default: () => [] },
  yearOptions: { type: Array, default: () => [] },
  baseRegionOptions: { type: Array, default: () => [] },
  groupOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, guideline_sets: 0, groups: 0, active_guideline: 0 }) },
  records: { type: Object, required: true },
  createUrl: { type: String, required: true },
  legacyPanelUrl: { type: String, default: '/legacy-admin/ref-guidelines/cost-elements' },
});

const columns = [
  { key: 'guideline_set_name', label: 'Guideline', cellClass: 'min-w-[200px]' },
  { key: 'year', label: 'Tahun', cellClass: 'w-[90px]', sortable: true },
  { key: 'group', label: 'Group', cellClass: 'min-w-[150px]' },
  { key: 'element_name', label: 'Element', cellClass: 'min-w-[240px]' },
  { key: 'unit_cost', label: 'Biaya', cellClass: 'min-w-[140px]', sortable: true },
  { key: 'updated_at', label: 'Diubah', cellClass: 'min-w-[140px]', sortable: true },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[200px]' },
];

const applyFilters = (patch = {}) => {
  router.get(route('admin.ref-guidelines.cost-elements.index'), { ...props.filters, ...patch }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const destroyRecord = (item) => {
  if (!window.confirm(`Hapus cost element "${item.element_name}"?`)) return;
  router.delete(item.destroy_url, { preserveScroll: true });
};

const formatCurrency = (value) => new Intl.NumberFormat('id-ID').format(Number(value || 0));
</script>

<template>
  <Head title="Admin - Cost Elements" />

  <AdminLayout title="Cost Elements">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Pedoman Referensi</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Cost Elements</h1>
          <p class="mt-2 text-sm text-slate-600">
            Referensi elemen biaya konstruksi yang dipakai engine BTB reviewer.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button as-child><Link :href="createUrl">Tambah Cost Element</Link></Button>
          <Button variant="outline" as-child><a :href="legacyPanelUrl">Buka di Legacy Admin</a></Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Row</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Guideline Set</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.guideline_sets }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Group</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.groups }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Guideline Aktif</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.active_guideline }}</p></CardContent></Card>
      </section>

      <Card>
        <CardHeader><CardTitle>Filter</CardTitle><CardDescription>Filter data berdasarkan guideline, tahun, base region, group, dan kata kunci.</CardDescription></CardHeader>
        <CardContent class="grid gap-4 xl:grid-cols-[1.4fr_1fr_0.7fr_1fr_1fr]">
          <div class="space-y-2">
            <Label for="cost_q">Cari</Label>
            <Input id="cost_q" :model-value="filters.q" placeholder="Group, kode, nama elemen, atau bangunan" @change="applyFilters({ q: $event.target.value })" />
          </div>
          <div class="space-y-2">
            <Label for="cost_guideline">Guideline</Label>
            <Select :model-value="filters.guideline_set_id" @update:model-value="applyFilters({ guideline_set_id: $event })">
              <SelectTrigger id="cost_guideline"><SelectValue placeholder="Pilih guideline" /></SelectTrigger>
              <SelectContent><SelectItem v-for="option in guidelineSetOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label for="cost_year">Tahun</Label>
            <Select :model-value="filters.year" @update:model-value="applyFilters({ year: $event })">
              <SelectTrigger id="cost_year"><SelectValue placeholder="Pilih tahun" /></SelectTrigger>
              <SelectContent><SelectItem v-for="option in yearOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label for="cost_base_region">Base Region</Label>
            <Select :model-value="filters.base_region" @update:model-value="applyFilters({ base_region: $event })">
              <SelectTrigger id="cost_base_region"><SelectValue placeholder="Pilih base region" /></SelectTrigger>
              <SelectContent><SelectItem v-for="option in baseRegionOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label for="cost_group">Group</Label>
            <Select :model-value="filters.group" @update:model-value="applyFilters({ group: $event })">
              <SelectTrigger id="cost_group"><SelectValue placeholder="Pilih group" /></SelectTrigger>
              <SelectContent><SelectItem v-for="option in groupOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Daftar Cost Elements</CardTitle>
          <CardDescription>
            Menampilkan {{ records.meta?.from ?? 0 }}-{{ records.meta?.to ?? 0 }} dari {{ records.meta?.total ?? 0 }} data.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <AdminDataTable :columns="columns" :rows="records.data" :meta="records.meta" empty-text="Belum ada cost element.">
            <template #cell-guideline_set_name="{ row }">
              <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-medium text-slate-950">{{ row.guideline_set_name }}</p>
                  <Badge v-if="row.guideline_is_active" variant="outline" class="border-emerald-200 bg-emerald-50 text-emerald-800">Aktif</Badge>
                </div>
                <p class="text-xs text-slate-500">{{ row.base_region }}</p>
              </div>
            </template>

            <template #cell-group="{ row }">
              <div class="space-y-1">
                <Badge variant="outline">{{ row.group }}</Badge>
                <p class="text-xs text-slate-500">{{ row.element_code }}</p>
              </div>
            </template>

            <template #cell-element_name="{ row }">
              <div class="space-y-1">
                <p class="font-medium text-slate-950">{{ row.element_name }}</p>
                <p class="text-xs text-slate-500">
                  {{ row.building_type || '-' }} / {{ row.building_class || '-' }} / {{ row.storey_pattern || '-' }}
                </p>
              </div>
            </template>

            <template #cell-unit_cost="{ row }">
              <div class="space-y-1">
                <p class="font-medium text-slate-950">Rp {{ formatCurrency(row.unit_cost) }}</p>
                <p class="text-xs text-slate-500">per {{ row.unit }}</p>
              </div>
            </template>

            <template #cell-updated_at="{ row }">
              {{ formatDateTime(row.updated_at) }}
            </template>

            <template #cell-actions="{ row }">
              <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" as-child><Link :href="row.edit_url">Edit</Link></Button>
                <Button variant="outline" size="sm" @click="destroyRecord(row)">Hapus</Button>
                <Button v-if="row.legacy_url" variant="outline" size="sm" as-child><a :href="row.legacy_url">Legacy</a></Button>
              </div>
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
