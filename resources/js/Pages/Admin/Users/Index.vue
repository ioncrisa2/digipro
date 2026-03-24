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
  filters: { type: Object, default: () => ({ q: '', role: 'all', verified: 'all' }) },
  roleOptions: { type: Array, default: () => [] },
  verifiedOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, verified: 0, admins: 0, reviewers: 0 }) },
  records: { type: Object, required: true },
  canCreate: { type: Boolean, default: false },
  createUrl: { type: String, required: true },
});

const form = reactive({
  q: props.filters.q ?? '',
  role: props.filters.role ?? 'all',
  verified: props.filters.verified ?? 'all',
});

const submitFilters = () => {
  router.get(route('admin.master-data.users.index'), {
    q: form.q || undefined,
    role: form.role === 'all' ? undefined : form.role,
    verified: form.verified === 'all' ? undefined : form.verified,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  form.q = '';
  form.role = 'all';
  form.verified = 'all';
  submitFilters();
};

const activeFilterCount = computed(() => {
  let count = 0;
  if (form.role !== 'all') count += 1;
  if (form.verified !== 'all') count += 1;
  return count;
});

const columns = [
  { key: 'user', label: 'User', cellClass: 'min-w-[220px]' },
  { key: 'roles', label: 'Role', cellClass: 'min-w-[200px]' },
  { key: 'status', label: 'Status', cellClass: 'min-w-[120px]' },
  { key: 'created_at', label: 'Terdaftar', cellClass: 'min-w-[140px]' },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[180px]' },
];
</script>

<template>
  <Head title="Admin - User Terdaftar" />

  <AdminLayout title="User Terdaftar">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">User Terdaftar</h1>
          <p class="mt-2 text-sm text-slate-600">
            Workspace admin Vue untuk melihat dan mengelola user terdaftar dari satu panel yang konsisten.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button v-if="canCreate" as-child>
            <Link :href="createUrl">Tambah User</Link>
          </Button>


        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total User</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Verified</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.verified }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Admin</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.admins }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Reviewer</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.reviewers }}</p></CardContent></Card>
      </section>

      <Card>
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Daftar User</CardTitle>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari nama atau email"
            filter-title="Filter user"
            filter-description="Saring user berdasarkan role dan status verifikasi."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; submitFilters(); }"
            @apply-filters="submitFilters"
            @reset-filters="resetFilters"
          >
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="space-y-2">
                <Label for="user_role_filter">Role</Label>
                <Select v-model="form.role">
                  <SelectTrigger id="user_role_filter"><SelectValue placeholder="Pilih role" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Semua Role</SelectItem>
                    <SelectItem v-for="option in roleOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="user_verified_filter">Verifikasi</Label>
                <Select v-model="form.verified">
                  <SelectTrigger id="user_verified_filter"><SelectValue placeholder="Pilih status" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in verifiedOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
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
            empty-text="Tidak ada user yang cocok dengan filter saat ini."
          >
            <template #cell-user="{ row }">
              <p class="font-medium text-slate-950">{{ row.name }}</p>
              <p class="mt-1 text-xs text-slate-500">{{ row.email }}</p>
            </template>

            <template #cell-roles="{ row }">
              <div class="flex flex-wrap gap-2">
                <Badge v-for="roleName in row.role_names" :key="`${row.id}-${roleName}`" variant="secondary">{{ roleName }}</Badge>
                <span v-if="!row.role_names.length" class="text-xs text-slate-400">Tanpa role</span>
              </div>
            </template>

            <template #cell-status="{ row }">
              <Badge variant="outline" :class="row.is_verified ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-700'">
                {{ row.is_verified ? 'Verified' : 'Belum Verified' }}
              </Badge>
            </template>

            <template #cell-created_at="{ row }">
              {{ formatDateTime(row.created_at) }}
            </template>

            <template #cell-actions="{ row }">
              <AdminEntityActions
                :detail-href="row.show_url"
                :edit-href="row.edit_url"
                entity-label="user"
                :entity-name="row.name"
              />
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
