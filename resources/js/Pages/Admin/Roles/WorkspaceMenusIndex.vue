<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
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

const props = defineProps({
  filters: { type: Object, default: () => ({ q: '', guard: 'all' }) },
  guardOptions: { type: Array, default: () => [] },
  records: { type: Object, required: true },
  summary: { type: Object, default: () => ({ roles: 0, sections: 0, default_reviewer_sections: 0 }) },
  roleIndexUrl: { type: String, required: true },
});

const form = reactive({
  q: props.filters.q ?? '',
  guard: props.filters.guard ?? 'all',
});

const submitFilters = () => {
  router.get(route('admin.access-control.system-menus.index'), {
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
  { key: 'workspace_sections_count', label: 'Section', cellClass: 'min-w-[110px]' },
  { key: 'workspace_sections', label: 'Menu Aktif', cellClass: 'min-w-[300px]' },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[200px]' },
];
</script>

<template>
  <Head title="Admin - Menu Sistem per Role" />

  <AdminLayout title="Menu Sistem">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Hak Akses</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Menu Sistem per Role</h1>
          <p class="mt-2 text-sm text-slate-600">
            Atur visibilitas menu reviewer, menu bersama, dan menu admin per role tanpa memilih permission mentah satu per satu.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><Link :href="roleIndexUrl">Kembali ke Roles</Link></Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-3">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Role</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.roles }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Section Menu</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.sections }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Default Reviewer</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.default_reviewer_sections }}</p></CardContent></Card>
      </section>

      <Card>
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Daftar Role</CardTitle>
            <CardDescription>Pilih role untuk mengatur section sistem yang boleh terlihat dan diakses.</CardDescription>
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
              <Label for="workspace_menu_guard_filter">Guard</Label>
              <Select v-model="form.guard">
                <SelectTrigger id="workspace_menu_guard_filter"><SelectValue placeholder="Pilih guard" /></SelectTrigger>
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
            empty-text="Belum ada role yang cocok dengan filter saat ini."
          >
            <template #cell-name="{ row }">
              <div>
                <p class="font-medium text-slate-950">{{ row.name }}</p>
                <p class="text-xs text-slate-500">{{ row.guard_name }}</p>
              </div>
            </template>

            <template #cell-guard_name="{ row }">
              <Badge variant="outline">{{ row.guard_name }}</Badge>
            </template>

            <template #cell-workspace_sections="{ row }">
              <div class="flex flex-wrap gap-2">
                <Badge v-for="section in row.workspace_sections" :key="`${row.id}-${section}`" variant="secondary">
                  {{ section }}
                </Badge>
                <span v-if="!row.workspace_sections.length" class="text-xs text-slate-400">Belum ada menu sistem aktif.</span>
              </div>
            </template>

            <template #cell-actions="{ row }">
              <div class="flex items-center justify-end gap-2">
                <Button variant="outline" size="sm" as-child>
                  <Link :href="row.role_show_url">Detail Role</Link>
                </Button>
                <Button size="sm" as-child>
                  <Link :href="row.edit_url">Atur Menu</Link>
                </Button>
              </div>
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
