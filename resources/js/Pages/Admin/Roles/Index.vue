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
  filters: { type: Object, default: () => ({ q: '', guard: 'all' }) },
  guardOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, web: 0, permissions: 0, super_admins: 0 }) },
  records: { type: Object, required: true },
  canCreate: { type: Boolean, default: false },
  canDeleteAny: { type: Boolean, default: false },
  createUrl: { type: String, required: true },
  legacyPanelUrl: { type: String, default: '/legacy-admin' },
});

const applyFilters = (patch = {}) => {
  router.get(route('admin.access-control.roles.index'), {
    ...props.filters,
    ...patch,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const destroyRecord = (item) => {
  if (!window.confirm(`Hapus role "${item.name}"?`)) return;
  router.delete(item.destroy_url, { preserveScroll: true });
};

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
            Kelola role dan permission matrix yang masih memakai backend `spatie/permission` dan `filament-shield`.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button v-if="canCreate" as-child><Link :href="createUrl">Tambah Role</Link></Button>
          <Button variant="outline" as-child><a :href="legacyPanelUrl">Buka di Legacy Admin</a></Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Role</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Guard Web</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.web }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Permission</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.permissions }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">User Super Admin</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.super_admins }}</p></CardContent></Card>
      </section>

      <Card>
        <CardHeader>
          <CardTitle>Filter Role</CardTitle>
          <CardDescription>Filter dasar berdasarkan nama role dan guard.</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
          <div class="space-y-2">
            <Label for="role_q">Cari</Label>
            <Input id="role_q" :model-value="filters.q" placeholder="Nama role" @change="applyFilters({ q: $event.target.value })" />
          </div>
          <div class="space-y-2">
            <Label for="role_guard">Guard</Label>
            <Select :model-value="filters.guard" @update:model-value="applyFilters({ guard: $event })">
              <SelectTrigger id="role_guard"><SelectValue placeholder="Pilih guard" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Semua Guard</SelectItem>
                <SelectItem v-for="option in guardOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Daftar Roles</CardTitle>
          <CardDescription>
            Menampilkan {{ records.meta?.from ?? 0 }}-{{ records.meta?.to ?? 0 }} dari {{ records.meta?.total ?? 0 }} role.
          </CardDescription>
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
              <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" as-child><Link :href="row.show_url">Detail</Link></Button>
                <Button v-if="row.can_update" variant="outline" size="sm" as-child><Link :href="row.edit_url">Edit</Link></Button>
                <Button v-if="row.can_delete && canDeleteAny" variant="outline" size="sm" @click="destroyRecord(row)">Hapus</Button>
                <Button v-if="row.legacy_url" variant="outline" size="sm" as-child><a :href="row.legacy_url">Legacy</a></Button>
              </div>
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
