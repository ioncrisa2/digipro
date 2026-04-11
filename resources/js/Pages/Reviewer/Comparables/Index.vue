<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ReviewerLayout from '@/layouts/ReviewerLayout.vue';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import AdminTableToolbar from '@/components/admin/AdminTableToolbar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { formatArea, formatCurrency, formatPercent } from '@/utils/reviewer';

const props = defineProps({
  filters: { type: Object, default: () => ({ q: '', asset_id: '', is_selected: 'all', per_page: 15 }) },
  summary: { type: Object, default: () => ({}) },
  records: { type: Object, required: true },
});

const form = reactive({
  q: props.filters.q ?? '',
  asset_id: props.filters.asset_id ?? '',
  is_selected: props.filters.is_selected ?? 'all',
});

const activeFilterCount = computed(() => {
  let count = 0;

  if (form.asset_id) count += 1;
  if (form.is_selected !== 'all') count += 1;

  return count;
});

const applyFilters = () => {
  router.get(
    route('reviewer.comparables.index'),
    {
      q: form.q || undefined,
      asset_id: form.asset_id || undefined,
      is_selected: form.is_selected === 'all' ? undefined : form.is_selected,
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
  form.asset_id = '';
  form.is_selected = 'all';
  applyFilters();
};

const summaryCards = [
  { key: 'total', label: 'Total Comparable' },
  { key: 'dipakai', label: 'Dipakai' },
  { key: 'perlu_penyesuaian', label: 'Perlu Penyesuaian' },
  { key: 'diperbarui_hari_ini', label: 'Diperbarui Hari Ini' },
];

const columns = [
  { key: 'asset', label: 'Aset', cellClass: 'min-w-[220px]' },
  { key: 'external', label: 'Comparable', cellClass: 'min-w-[170px]' },
  { key: 'selected', label: 'Dipakai', cellClass: 'w-[110px]' },
  { key: 'shape', label: 'LT / LB', cellClass: 'min-w-[120px]' },
  { key: 'adjustment', label: 'Penyesuaian', cellClass: 'min-w-[180px]' },
  { key: 'value', label: 'Nilai', cellClass: 'min-w-[180px]' },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[180px]' },
];
</script>

<template>
  <Head title="Reviewer - Comparable" />

  <ReviewerLayout title="Comparable">
    <div class="space-y-6">
      <section>
        <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Database Comparable</h1>
        <p class="mt-2 text-sm text-slate-600">
          Telusuri pembanding yang sudah dipakai reviewer, baca konteks asetnya, dan masuk ke penyesuaian tanpa pindah-pindah layar.
        </p>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card v-for="card in summaryCards" :key="card.key" class="border-slate-200/80 shadow-sm">
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ card.label }}</p>
            <p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary[card.key] ?? 0 }}</p>
          </CardContent>
        </Card>
      </section>

      <Card class="border-slate-200/80 shadow-sm">
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Daftar Comparable</CardTitle>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari ext id, peruntukan, atau alamat aset"
            filter-title="Filter comparable"
            filter-description="Saring pembanding berdasarkan aset dan status pemakaian."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; applyFilters(); }"
            @apply-filters="applyFilters"
            @reset-filters="resetFilters"
          >
            <div class="grid gap-4">
              <div class="space-y-2">
                <Label for="reviewer_comparable_asset_filter">ID Aset</Label>
                <Input
                  id="reviewer_comparable_asset_filter"
                  v-model="form.asset_id"
                  type="number"
                  min="1"
                  placeholder="Masukkan ID aset"
                />
              </div>

              <div class="space-y-2">
                <Label for="reviewer_comparable_status_filter">Status Pemakaian</Label>
                <Select v-model="form.is_selected">
                  <SelectTrigger id="reviewer_comparable_status_filter">
                    <SelectValue placeholder="Semua comparable" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Semua Comparable</SelectItem>
                    <SelectItem value="1">Dipakai</SelectItem>
                    <SelectItem value="0">Tidak Dipakai</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </AdminTableToolbar>
        </CardHeader>
        <CardContent>
          <AdminDataTable
            :columns="columns"
            :rows="records.data"
            :meta="records.meta"
            :default-per-page="filters.per_page ?? 15"
            empty-text="Belum ada comparable yang cocok dengan filter saat ini."
          >
            <template #cell-asset="{ row }">
              <div class="space-y-1">
                <Button variant="link" class="h-auto px-0 text-left font-medium" as-child>
                  <Link :href="row.asset_detail_url">{{ row.asset_address }}</Link>
                </Button>
                <div class="text-xs text-slate-500">{{ row.request_number }} • Asset {{ row.appraisal_asset_id }}</div>
              </div>
            </template>

            <template #cell-external="{ row }">
              <div class="space-y-1">
                <Button variant="link" class="h-auto px-0 font-medium" as-child>
                  <Link :href="row.detail_url">{{ row.external_id }}</Link>
                </Button>
                <div class="text-xs text-slate-500">{{ row.raw_peruntukan || '-' }}</div>
              </div>
            </template>

            <template #cell-selected="{ row }">
              <Badge :variant="row.is_selected ? 'default' : 'outline'">
                {{ row.is_selected ? 'Dipakai' : 'Tidak' }}
              </Badge>
            </template>

            <template #cell-shape="{ row }">
              {{ formatArea(row.raw_land_area) }} / {{ formatArea(row.raw_building_area) }}
            </template>

            <template #cell-adjustment="{ row }">
              <div>{{ formatPercent(row.total_adjustment_percent ?? 0) }}</div>
              <div class="mt-1 text-xs text-slate-500">{{ row.land_adjustments_count }} faktor</div>
            </template>

            <template #cell-value="{ row }">
              <div>{{ formatCurrency(row.adjusted_unit_value) }}</div>
              <div class="mt-1 text-xs text-slate-500">Indikasi {{ formatCurrency(row.indication_value) }}</div>
            </template>

            <template #cell-actions="{ row }">
              <div class="flex flex-wrap gap-3">
                <Button variant="link" class="h-auto px-0" as-child>
                  <Link :href="row.detail_url">Detail</Link>
                </Button>
                <Button variant="link" class="h-auto px-0" as-child>
                  <Link :href="row.adjustment_url">Adjust Harga Tanah</Link>
                </Button>
              </div>
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </ReviewerLayout>
</template>
