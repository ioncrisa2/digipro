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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime } from '@/utils/reviewer';

const props = defineProps({
  filters: { type: Object, default: () => ({ q: '', guard: 'all' }) },
  guardOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, web: 0, permissions: 0, super_admins: 0 }) },
  records: { type: Object, required: true },
  canCreate: { type: Boolean, default: false },
  canDeleteAny: { type: Boolean, default: false },
  createUrl: { type: String, required: true },
});

const form = reactive({
  q: props.filters.q ?? '',
  guard: props.filters.guard ?? 'all',
});

const submitFilters = () => {
  router.get(route('admin.access-control.roles.index'), {
    q: form.q || undefined,
    guard: form.guard === 'all' ? undefined : form.guard,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  form.q = '';
  form.guard = 'all';
  submitFilters();
};

const activeFilterCount = computed(() => (form.guard !== 'all' ? 1 : 0));

const columns = [
  { key: 'name', label: 'Role', cellClass: 'min-w-[180px]' },
  { key: 'guard_name', label: 'Guard', cellClass: 'min-w-[110px]' },
  { key: 'permissions_count', label: 'Permission', cellClass: 'min-w-[110px]' },
  { key: 'updated_at', label: 'Diperbarui', cellClass: 'min-w-[140px]' },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[200px]' },
];
</script>

<template>
  <Head title="Admin - Roles" />

  <AdminLayout title="Roles">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Hak Akses</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Roles</h1>
          <p class="mt-2 text-sm text-slate-600">
            Kelola role dan permission matrix yang berjalan di backend `spatie/permission`.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button v-if="canCreate" as-child><Link :href="createUrl">Tambah Role</Link></Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Role</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Guard Web</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.web }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Permission</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.permissions }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">User Super Admin</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.super_admins }}</p></CardContent></Card>
      </section>

      <Card>
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Daftar Roles</CardTitle>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari nama role"
            filter-title="Filter role"
            filter-description="Saring role berdasarkan guard yang dipakai."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; submitFilters(); }"
            @apply-filters="submitFilters"
            @reset-filters="resetFilters"
          >
            <div class="space-y-2">
              <Label for="role_guard_filter">Guard</Label>
              <Select v-model="form.guard">
                <SelectTrigger id="role_guard_filter"><SelectValue placeholder="Pilih guard" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Semua Guard</SelectItem>
                  <SelectItem v-for="option in guardOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </AdminTableToolbar>
        </CardHeader>
        <CardContent>
          <AdminDataTable
            :columns="columns"
            :rows="records.data"
            :meta="records.meta"
            empty-text="Tidak ada role yang cocok dengan filter saat ini."
          >
            <template #cell-name="{ row }">
              <p class="font-medium text-slate-950">{{ row.name }}</p>
            </template>

            <template #cell-guard_name="{ row }">
              <Badge variant="outline">{{ row.guard_name }}</Badge>
            </template>

            <template #cell-updated_at="{ row }">
              {{ formatDateTime(row.updated_at) }}
            </template>

            <template #cell-actions="{ row }">
              <AdminEntityActions
                :detail-href="row.show_url"
                :edit-href="row.can_update ? row.edit_url : null"
                :delete-url="row.can_delete && canDeleteAny ? row.destroy_url : null"
                entity-label="role"
                :entity-name="row.name"
              />
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
