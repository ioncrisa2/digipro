<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import AdminEntityActions from '@/components/admin/AdminEntityActions.vue';
import AdminTableToolbar from '@/components/admin/AdminTableToolbar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
});

const form = reactive({
  q: props.filters.q ?? '',
  status: props.filters.status ?? 'all',
});

const columns = [
  { key: 'name', label: 'Kategori', cellClass: 'min-w-[220px]', sortable: true },
  { key: 'slug', label: 'Slug', cellClass: 'min-w-[180px]', sortable: true },
  { key: 'status', label: 'Status', cellClass: 'min-w-[150px]' },
  { key: 'sort_order', label: 'Urutan', cellClass: 'w-[90px]', sortable: true },
  { key: 'articles_count', label: 'Artikel', cellClass: 'w-[90px]', sortable: true },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[200px]' },
];

const submitFilters = () => {
  router.get(route('admin.content.categories.index'), {
    q: form.q || undefined,
    status: form.status === 'all' ? undefined : form.status,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  form.q = '';
  form.status = 'all';
  submitFilters();
};

const activeFilterCount = computed(() => (form.status !== 'all' ? 1 : 0));

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
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-3">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Aktif</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.active }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Navbar</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.show_in_nav }}</p></CardContent></Card>
      </section>

      <Card>
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Daftar Kategori</CardTitle>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari nama atau slug kategori"
            filter-title="Filter kategori artikel"
            filter-description="Saring kategori berdasarkan status aktif."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; submitFilters(); }"
            @apply-filters="submitFilters"
            @reset-filters="resetFilters"
          >
            <div class="space-y-2">
              <Label for="category_status_filter">Status</Label>
              <Select v-model="form.status">
                <SelectTrigger id="category_status_filter"><SelectValue placeholder="Pilih status" /></SelectTrigger>
                <SelectContent><SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
              </Select>
            </div>
          </AdminTableToolbar>
        </CardHeader>
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
              <AdminEntityActions
                :edit-href="row.edit_url"
                :delete-url="row.destroy_url"
                entity-label="kategori"
                :entity-name="row.name"
              />
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
