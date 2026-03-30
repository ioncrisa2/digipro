<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import AdminEntityActions from '@/components/admin/AdminEntityActions.vue';
import AdminSortableOrderingPanel from '@/components/admin/AdminSortableOrderingPanel.vue';
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
  summary: { type: Object, default: () => ({ total: 0, active: 0, articles: 0 }) },
  records: { type: Array, default: () => [] },
  createUrl: { type: String, required: true },
  reorderUrl: { type: String, required: true },
});

const form = reactive({
  q: props.filters.q ?? '',
  status: props.filters.status ?? 'all',
});

const columns = [
  { key: 'name', label: 'Tag', cellClass: 'min-w-[220px]', sortable: true },
  { key: 'slug', label: 'Slug', cellClass: 'min-w-[180px]', sortable: true },
  { key: 'status', label: 'Status', cellClass: 'min-w-[140px]' },
  { key: 'sort_order', label: 'Urutan', cellClass: 'w-[90px]', sortable: true },
  { key: 'articles_count', label: 'Artikel', cellClass: 'w-[100px]', sortable: true },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[200px]' },
];

const submitFilters = () => {
  router.get(route('admin.content.tags.index'), {
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
const reorderItems = computed(() => props.records.map((row) => ({
  id: row.id,
  title: row.name,
  subtitle: row.slug,
  is_active: row.is_active,
})));

</script>

<template>
  <Head title="Admin - Tag Artikel" />

  <AdminLayout title="Tag Artikel">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div><h1 class="text-3xl font-semibold tracking-tight text-slate-950">Tag Artikel</h1></div>
        <div class="flex flex-wrap gap-2">
          <Button as-child><Link :href="createUrl">Tambah Tag</Link></Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-3">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Aktif</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.active }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Relasi Artikel</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.articles }}</p></CardContent></Card>
      </section>

      <AdminSortableOrderingPanel
        v-if="records.length > 1"
        title="Urutkan Tag Artikel"
        description="Drag and drop tag untuk mengubah urutan tampil dan prioritas pemilihan di admin."
        :items="reorderItems"
        :save-url="reorderUrl"
      />

      <Card>
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Daftar Tag</CardTitle>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari nama atau slug tag"
            filter-title="Filter tag artikel"
            filter-description="Saring tag berdasarkan status aktif."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; submitFilters(); }"
            @apply-filters="submitFilters"
            @reset-filters="resetFilters"
          >
            <div class="space-y-2">
              <Label for="tag_status_filter">Status</Label>
              <Select v-model="form.status">
                <SelectTrigger id="tag_status_filter"><SelectValue placeholder="Pilih status" /></SelectTrigger>
                <SelectContent><SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
              </Select>
            </div>
          </AdminTableToolbar>
        </CardHeader>
        <CardContent>
          <AdminDataTable :columns="columns" :rows="records" empty-text="Belum ada tag.">
            <template #cell-name="{ row }">
              <p class="font-medium text-slate-950">{{ row.name }}</p>
            </template>

            <template #cell-status="{ row }">
              <Badge variant="outline" :class="row.is_active ? 'bg-emerald-100 text-emerald-900 border-emerald-200' : 'bg-slate-100 text-slate-800 border-slate-200'">{{ row.is_active ? 'Aktif' : 'Nonaktif' }}</Badge>
            </template>

            <template #cell-sort_order="{ row }">
              <Badge variant="outline">{{ row.sort_order }}</Badge>
            </template>

            <template #cell-actions="{ row }">
              <AdminEntityActions
                :edit-href="row.edit_url"
                :delete-url="row.destroy_url"
                entity-label="tag"
                :entity-name="row.name"
              />
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
