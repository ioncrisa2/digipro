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
  filters: { type: Object, default: () => ({ q: '', guideline_set_id: 'all', year: 'all', province_id: 'all' }) },
  guidelineSetOptions: { type: Array, default: () => [] },
  yearOptions: { type: Array, default: () => [] },
  provinceOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, guideline_sets: 0, provinces: 0, active_guideline: 0 }) },
  records: { type: Object, required: true },
  createUrl: { type: String, required: true },
  legacyPanelUrl: { type: String, default: '/legacy-admin/ref-guidelines/construction-cost-indices' },
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

const applyFilters = (patch = {}) => {
  router.get(route('admin.ref-guidelines.construction-cost-indices.index'), { ...props.filters, ...patch }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const destroyRecord = (item) => {
  if (!window.confirm(`Hapus IKK "${item.region_name}"?`)) return;
  router.delete(item.destroy_url, { preserveScroll: true });
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
          <Button as-child><Link :href="createUrl">Tambah IKK</Link></Button>
          <Button variant="outline" as-child><a :href="legacyPanelUrl">Buka di Legacy Admin</a></Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Row</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Guideline Set</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.guideline_sets }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Provinsi</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.provinces }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Guideline Aktif</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.active_guideline }}</p></CardContent></Card>
      </section>

      <Card>
        <CardHeader><CardTitle>Filter</CardTitle><CardDescription>Filter berdasarkan guideline, tahun, provinsi, dan kabupaten/kota.</CardDescription></CardHeader>
        <CardContent class="grid gap-4 xl:grid-cols-[1.2fr_1fr_0.7fr_1fr]">
          <div class="space-y-2">
            <Label for="ikk_q">Cari</Label>
            <Input id="ikk_q" :model-value="filters.q" placeholder="Kode atau nama kabupaten/kota" @change="applyFilters({ q: $event.target.value })" />
          </div>
          <div class="space-y-2">
            <Label for="ikk_guideline">Guideline</Label>
            <Select :model-value="filters.guideline_set_id" @update:model-value="applyFilters({ guideline_set_id: $event })">
              <SelectTrigger id="ikk_guideline"><SelectValue placeholder="Pilih guideline" /></SelectTrigger>
              <SelectContent><SelectItem v-for="option in guidelineSetOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label for="ikk_year">Tahun</Label>
            <Select :model-value="filters.year" @update:model-value="applyFilters({ year: $event })">
              <SelectTrigger id="ikk_year"><SelectValue placeholder="Pilih tahun" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Semua Tahun</SelectItem>
                <SelectItem v-for="option in yearOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label for="ikk_province">Provinsi</Label>
            <Select :model-value="filters.province_id" @update:model-value="applyFilters({ province_id: $event })">
              <SelectTrigger id="ikk_province"><SelectValue placeholder="Pilih provinsi" /></SelectTrigger>
              <SelectContent><SelectItem v-for="option in provinceOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Daftar IKK</CardTitle>
          <CardDescription>
            Menampilkan {{ records.meta?.from ?? 0 }}-{{ records.meta?.to ?? 0 }} dari {{ records.meta?.total ?? 0 }} data.
          </CardDescription>
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
