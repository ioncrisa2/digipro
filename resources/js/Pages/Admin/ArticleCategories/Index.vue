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

const props = defineProps({
  filters: { type: Object, default: () => ({ q: '', status: 'all' }) },
  statusOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, active: 0, show_in_nav: 0 }) },
  records: { type: Array, default: () => [] },
  createUrl: { type: String, required: true },
  legacyPanelUrl: { type: String, default: '/legacy-admin' },
});

const columns = [
  { key: 'name', label: 'Kategori', cellClass: 'min-w-[220px]', sortable: true },
  { key: 'slug', label: 'Slug', cellClass: 'min-w-[180px]', sortable: true },
  { key: 'status', label: 'Status', cellClass: 'min-w-[150px]' },
  { key: 'sort_order', label: 'Urutan', cellClass: 'w-[90px]', sortable: true },
  { key: 'articles_count', label: 'Artikel', cellClass: 'w-[90px]', sortable: true },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[200px]' },
];

const applyFilters = (patch = {}) => {
  router.get(route('admin.content.categories.index'), { ...props.filters, ...patch }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const destroyRecord = (item) => {
  if (!window.confirm(`Hapus kategori "${item.name}"?`)) return;
  router.delete(item.destroy_url, { preserveScroll: true });
};
</script>

<template>
  <Head title="Admin - Kategori Artikel" />

  <AdminLayout title="Kategori Artikel">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Batch 9</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Kategori Artikel</h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button as-child><Link :href="createUrl">Tambah Kategori</Link></Button>
          <Button variant="outline" as-child><a :href="legacyPanelUrl">Legacy</a></Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-3">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Aktif</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.active }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Navbar</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.show_in_nav }}</p></CardContent></Card>
      </section>

      <Card>
        <CardHeader><CardTitle>Filter</CardTitle><CardDescription>Kelola kategori blog publik.</CardDescription></CardHeader>
        <CardContent class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
          <div class="space-y-2">
            <Label for="category_q">Cari</Label>
            <Input id="category_q" :model-value="filters.q" placeholder="Nama atau slug" @change="applyFilters({ q: $event.target.value })" />
          </div>
          <div class="space-y-2">
            <Label for="category_status">Status</Label>
            <Select :model-value="filters.status" @update:model-value="applyFilters({ status: $event })">
              <SelectTrigger id="category_status"><SelectValue placeholder="Pilih status" /></SelectTrigger>
              <SelectContent><SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Daftar Kategori</CardTitle></CardHeader>
        <CardContent>
          <AdminDataTable :columns="columns" :rows="records" empty-text="Belum ada kategori.">
            <template #cell-name="{ row }">
              <div class="space-y-1">
                <p class="font-medium text-slate-950">{{ row.name }}</p>
                <p v-if="row.description" class="line-clamp-2 text-xs leading-5 text-slate-500">{{ row.description }}</p>
              </div>
            </template>

            <template #cell-status="{ row }">
              <div class="flex flex-wrap gap-2">
                <Badge variant="outline" :class="row.is_active ? 'bg-emerald-100 text-emerald-900 border-emerald-200' : 'bg-slate-100 text-slate-800 border-slate-200'">{{ row.is_active ? 'Aktif' : 'Nonaktif' }}</Badge>
                <Badge v-if="row.show_in_nav" variant="outline">Navbar</Badge>
              </div>
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
