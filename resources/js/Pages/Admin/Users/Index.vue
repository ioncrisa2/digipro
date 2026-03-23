<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
  legacyPanelUrl: { type: String, default: '/legacy-admin' },
});

const applyFilters = (patch = {}) => {
  router.get(route('admin.master-data.users.index'), {
    ...props.filters,
    ...patch,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

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
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Batch 10</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">User Terdaftar</h1>
          <p class="mt-2 text-sm text-slate-600">
            Workspace admin Vue untuk melihat dan mengelola user terdaftar tanpa membuka resource Filament.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button v-if="canCreate" as-child>
            <Link :href="createUrl">Tambah User</Link>
          </Button>
          <Button variant="outline" as-child>
            <a :href="legacyPanelUrl">Buka di Legacy Admin</a>
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
        <CardHeader>
          <CardTitle>Filter User</CardTitle>
          <CardDescription>Filter dasar berdasarkan nama, email, role, dan status verifikasi.</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-4 xl:grid-cols-[1.3fr_0.8fr_0.8fr]">
          <div class="space-y-2">
            <Label for="user_q">Cari</Label>
            <Input id="user_q" :model-value="filters.q" placeholder="Nama atau email" @change="applyFilters({ q: $event.target.value })" />
          </div>
          <div class="space-y-2">
            <Label for="user_role">Role</Label>
            <Select :model-value="filters.role" @update:model-value="applyFilters({ role: $event })">
              <SelectTrigger id="user_role"><SelectValue placeholder="Pilih role" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Semua Role</SelectItem>
                <SelectItem v-for="option in roleOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label for="user_verified">Verifikasi</Label>
            <Select :model-value="filters.verified" @update:model-value="applyFilters({ verified: $event })">
              <SelectTrigger id="user_verified"><SelectValue placeholder="Pilih status" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="option in verifiedOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Daftar User</CardTitle>
          <CardDescription>
            Menampilkan {{ records.meta?.from ?? 0 }}-{{ records.meta?.to ?? 0 }} dari {{ records.meta?.total ?? 0 }} user.
          </CardDescription>
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
              <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" as-child><Link :href="row.show_url">Detail</Link></Button>
                <Button variant="outline" size="sm" as-child><Link :href="row.edit_url">Edit</Link></Button>
                <Button v-if="row.legacy_url" variant="outline" size="sm" as-child><a :href="row.legacy_url">Legacy</a></Button>
              </div>
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
