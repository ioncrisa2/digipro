<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import AdminEntityActions from '@/components/admin/AdminEntityActions.vue';
import AdminTableToolbar from '@/components/admin/AdminTableToolbar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps({
  filters: { type: Object, default: () => ({ q: '', role: 'all', active: 'all' }) },
  roleOptions: { type: Array, default: () => [] },
  activeOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, reviewers: 0, public_appraisers: 0, active: 0 }) },
  records: { type: Object, required: true },
  createUrl: { type: String, required: true },
});

const form = reactive({
  q: props.filters.q ?? '',
  role: props.filters.role ?? 'all',
  active: props.filters.active ?? 'all',
});

const submitFilters = () => {
  router.get(route('admin.master-data.report-signers.index'), {
    q: form.q || undefined,
    role: form.role === 'all' ? undefined : form.role,
    active: form.active === 'all' ? undefined : form.active,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  form.q = '';
  form.role = 'all';
  form.active = 'all';
  submitFilters();
};

const activeFilterCount = computed(() => {
  let count = 0;
  if (form.role !== 'all') count += 1;
  if (form.active !== 'all') count += 1;
  return count;
});

const columns = [
  { key: 'name', label: 'Profil', cellClass: 'min-w-[220px]' },
  { key: 'role', label: 'Peran', cellClass: 'min-w-[160px]' },
  { key: 'credential', label: 'Sertifikasi', cellClass: 'min-w-[220px]' },
  { key: 'status', label: 'Status', cellClass: 'min-w-[120px]' },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[180px]' },
];
</script>

<template>
  <Head title="Admin - Penandatangan Report" />

  <AdminLayout title="Penandatangan Report">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Penandatangan Report</h1>
          <p class="mt-2 text-sm text-slate-600">
            Master profil reviewer dan penilai publik yang dipilih admin untuk blok otorisasi report DigiPro by KJPP HJAR.
          </p>
        </div>
        <Button as-child>
          <Link :href="createUrl">Tambah Profil</Link>
        </Button>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Profil</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Reviewer</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.reviewers }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Penilai Publik</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.public_appraisers }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Aktif</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.active }}</p></CardContent></Card>
      </section>

      <Card>
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Daftar Profil</CardTitle>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari nama atau nomor sertifikasi"
            filter-title="Filter profil"
            filter-description="Saring berdasarkan peran dan status aktif."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; submitFilters(); }"
            @apply-filters="submitFilters"
            @reset-filters="resetFilters"
          >
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="space-y-2">
                <Label for="signer_role_filter">Peran</Label>
                <Select v-model="form.role">
                  <SelectTrigger id="signer_role_filter"><SelectValue placeholder="Pilih peran" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Semua Peran</SelectItem>
                    <SelectItem v-for="option in roleOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="signer_active_filter">Status</Label>
                <Select v-model="form.active">
                  <SelectTrigger id="signer_active_filter"><SelectValue placeholder="Pilih status" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in activeOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
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
            empty-text="Belum ada profil penandatangan report."
          >
            <template #cell-name="{ row }">
              <p class="font-medium text-slate-950">
                {{ row.name }}<span v-if="row.title_suffix">, {{ row.title_suffix }}</span>
              </p>
              <p class="mt-1 text-xs text-slate-500">{{ row.position_title || '-' }}</p>
            </template>

            <template #cell-role="{ row }">
              <Badge variant="secondary">{{ row.role_label }}</Badge>
            </template>

            <template #cell-credential="{ row }">
              <span class="text-sm text-slate-700">{{ row.certification_number || '-' }}</span>
            </template>

            <template #cell-status="{ row }">
              <Badge variant="outline" :class="row.is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-700'">
                {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
              </Badge>
            </template>

            <template #cell-actions="{ row }">
              <AdminEntityActions
                :detail-href="null"
                :edit-href="row.edit_url"
                :delete-url="row.destroy_url"
                entity-label="profil penandatangan"
                :entity-name="row.name"
              />
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
