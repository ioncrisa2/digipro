<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ReviewerLayout from '@/layouts/ReviewerLayout.vue';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import AdminTableToolbar from '@/components/admin/AdminTableToolbar.vue';
import StatusBadge from '@/components/reviewer/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { formatArea, formatCurrency } from '@/utils/reviewer';

const props = defineProps({
  filters: { type: Object, default: () => ({ q: '', status: 'all', needs_adjustment: false, per_page: 12 }) },
  statusOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({}) },
  records: { type: Object, required: true },
});

const form = reactive({
  q: props.filters.q ?? '',
  status: props.filters.status ?? 'all',
  needs_adjustment: Boolean(props.filters.needs_adjustment),
});

const activeFilterCount = computed(() => {
  let count = 0;

  if (form.status !== 'all') count += 1;
  if (form.needs_adjustment) count += 1;

  return count;
});

const applyFilters = () => {
  router.get(
    route('reviewer.assets.index'),
    {
      q: form.q || undefined,
      status: form.status === 'all' ? undefined : form.status,
      needs_adjustment: form.needs_adjustment ? 1 : undefined,
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
  form.status = 'all';
  form.needs_adjustment = false;
  applyFilters();
};

const summaryCards = [
  { key: 'total', label: 'Aset aktif', caption: 'Dalam workspace' },
  { key: 'butuh_penyesuaian', label: 'Perlu adjust', caption: 'Belum lengkap' },
  { key: 'sudah_punya_range', label: 'Ada range', caption: 'Range pasar siap' },
  { key: 'siap_nilai_final', label: 'Nilai final', caption: 'Sudah terisi' },
];

const columns = [
  { key: 'request', label: 'Permohonan', cellClass: 'min-w-[170px]' },
  { key: 'address', label: 'Alamat Aset', cellClass: 'min-w-[240px]' },
  { key: 'asset_type', label: 'Jenis', cellClass: 'min-w-[120px]' },
  { key: 'area', label: 'LT / LB', cellClass: 'min-w-[120px]' },
  { key: 'comparables', label: 'Pembanding', cellClass: 'min-w-[150px]' },
  { key: 'range', label: 'Range Pasar', cellClass: 'min-w-[180px]' },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[220px]' },
];
</script>

<template>
  <Head title="Reviewer - Aset" />

  <ReviewerLayout title="Aset Reviewer">
    <div class="space-y-5">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-3xl">
          <h1 class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">Aset Reviewer</h1>
          <p class="mt-2 text-sm leading-6 text-slate-600">
            Kelola aset yang perlu penyesuaian harga tanah, BTB bangunan, range pasar, dan nilai final.
          </p>
        </div>

        <div class="grid gap-2 rounded-2xl border border-slate-200/80 bg-white p-2 shadow-sm sm:grid-cols-4 lg:min-w-[640px]">
          <div
            v-for="card in summaryCards"
            :key="card.key"
            class="rounded-xl px-3 py-2.5"
          >
            <div class="text-2xl font-semibold leading-none text-slate-950">{{ summary[card.key] ?? 0 }}</div>
            <div class="mt-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ card.label }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ card.caption }}</div>
          </div>
        </div>
      </section>

      <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-200/80 p-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 class="text-base font-semibold text-slate-950">Daftar Aset</h2>
            <p class="mt-1 text-sm text-slate-500">
              {{ records.meta?.total ?? 0 }} aset ditemukan. Gunakan filter hanya saat daftar mulai panjang.
            </p>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari alamat aset atau nomor request"
            filter-title="Filter aset reviewer"
            filter-description="Saring aset berdasarkan status request dan pekerjaan yang masih perlu penyesuaian."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; applyFilters(); }"
            @apply-filters="applyFilters"
            @reset-filters="resetFilters"
          >
            <div class="space-y-4">
              <div class="space-y-2">
                <Label for="reviewer_asset_status_filter">Status</Label>
                <Select v-model="form.status">
                  <SelectTrigger id="reviewer_asset_status_filter">
                    <SelectValue placeholder="Semua status" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Semua Status</SelectItem>
                    <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-3 text-sm text-slate-700">
                <Checkbox
                  :model-value="form.needs_adjustment"
                  @update:model-value="(value) => { form.needs_adjustment = Boolean(value); }"
                />
                <span>Hanya tampilkan aset yang masih perlu penyesuaian</span>
              </label>
            </div>
          </AdminTableToolbar>
        </div>

        <div class="p-4">
          <AdminDataTable
            :columns="columns"
            :rows="records.data"
            :meta="records.meta"
            :default-per-page="filters.per_page ?? 12"
            empty-text="Belum ada aset reviewer yang cocok dengan filter saat ini."
          >
            <template #cell-request="{ row }">
              <div class="space-y-2">
                <div class="font-medium text-slate-950">{{ row.request_number }}</div>
                <StatusBadge :status="row.request_status" />
              </div>
            </template>

            <template #cell-address="{ row }">
              <Button variant="link" class="h-auto px-0 text-left font-medium" as-child>
                <Link :href="row.detail_url">{{ row.address }}</Link>
              </Button>
            </template>

            <template #cell-asset_type="{ row }">
              {{ row.asset_type?.label || '-' }}
            </template>

            <template #cell-area="{ row }">
              {{ formatArea(row.land_area) }} / {{ formatArea(row.building_area) }}
            </template>

            <template #cell-comparables="{ row }">
              {{ row.selected_comparables_count }} dipilih / {{ row.comparables_count }} total
            </template>

            <template #cell-range="{ row }">
              <div>{{ formatCurrency(row.estimated_value_low) }} - {{ formatCurrency(row.estimated_value_high) }}</div>
              <div class="mt-1 text-xs text-slate-500">Nilai final: {{ formatCurrency(row.market_value_final) }}</div>
            </template>

            <template #cell-actions="{ row }">
              <div class="flex flex-wrap gap-3">
                <Button variant="link" class="h-auto px-0" as-child>
                  <Link :href="row.detail_url">Detail</Link>
                </Button>
                <Button variant="link" class="h-auto px-0" as-child>
                  <Link :href="row.land_adjustment_url || row.adjustment_url">Adjust Harga Tanah</Link>
                </Button>
                <Button v-if="row.has_btb && row.btb_url" variant="link" class="h-auto px-0" as-child>
                  <Link :href="row.btb_url">BTB Bangunan</Link>
                </Button>
              </div>
            </template>
          </AdminDataTable>
        </div>
      </section>
    </div>
  </ReviewerLayout>
</template>
