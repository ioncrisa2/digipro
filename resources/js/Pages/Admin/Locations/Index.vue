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
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

const props = defineProps({
  resource: { type: Object, required: true },
  filters: { type: Object, default: () => ({ q: '' }) },
  filterOptions: { type: Array, default: () => [] },
  summaryCards: { type: Array, default: () => [] },
  records: { type: Object, required: true },
  indexUrl: { type: String, required: true },
  createUrl: { type: String, required: true },
});

const form = reactive({
  q: props.filters.q ?? '',
});

for (const option of props.filterOptions ?? []) {
  form[option.key] = props.filters[option.key] ?? option.defaultValue ?? 'all';
}

const submitFilters = () => {
  const payload = {
    q: form.q || undefined,
  };

  for (const option of props.filterOptions ?? []) {
    const value = form[option.key];
    const defaultValue = option.defaultValue ?? 'all';
    payload[option.key] = value === defaultValue ? undefined : value;
  }

  router.get(props.indexUrl, payload, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  form.q = '';
  for (const option of props.filterOptions ?? []) {
    form[option.key] = option.defaultValue ?? 'all';
  }
  submitFilters();
};

const activeFilterCount = computed(() => (
  (props.filterOptions ?? []).reduce((count, option) => (
    form[option.key] !== (option.defaultValue ?? 'all') ? count + 1 : count
  ), 0)
));

const columns = computed(() => {
  const baseColumns = [
    { key: 'code', label: 'Kode', cellClass: 'w-[130px]', sortable: true },
    { key: 'name', label: 'Nama', cellClass: 'min-w-[220px]', sortable: true },
    { key: 'details', label: 'Relasi', cellClass: 'min-w-[260px]' },
  ];

  if (props.resource.key !== 'villages') {
    baseColumns.push({ key: 'stats', label: 'Stat', cellClass: 'min-w-[180px]' });
  }

  baseColumns.push({ key: 'actions', label: 'Aksi', cellClass: 'min-w-[200px]' });

  return baseColumns;
});
</script>

<template>
  <Head :title="`Admin - ${resource.title}`" />

  <AdminLayout :title="resource.title">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ resource.title }}</h1>
          <p class="mt-2 text-sm text-slate-600">{{ resource.description }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button as-child>
            <Link :href="createUrl">{{ resource.create_label }}</Link>
          </Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <Card v-for="card in summaryCards" :key="card.label">
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">{{ card.label }}</p>
            <p class="mt-3 text-4xl font-semibold text-slate-950">{{ card.value }}</p>
          </CardContent>
        </Card>
      </section>

      <Card>
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Daftar {{ resource.title }}</CardTitle>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            :search-placeholder="`${resource.code_label}, nama, atau parent`"
            :filter-title="`Filter ${resource.title.toLowerCase()}`"
            :filter-description="`Saring data ${resource.title.toLowerCase()} berdasarkan relasi yang tersedia.`"
            :active-filter-count="activeFilterCount"
            :has-filter="filterOptions.length > 0"
            @search="(value) => { form.q = value; submitFilters(); }"
            @apply-filters="submitFilters"
            @reset-filters="resetFilters"
          >
            <div class="grid gap-4">
              <div
                v-for="option in filterOptions"
                :key="option.key"
                class="space-y-2"
              >
                <Label :for="`${resource.key}_${option.key}_filter`">{{ option.label }}</Label>
                <Select v-model="form[option.key]">
                  <SelectTrigger :id="`${resource.key}_${option.key}_filter`" class="w-full">
                    <SelectValue :placeholder="`Pilih ${option.label.toLowerCase()}`" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="option.defaultValue ?? 'all'">Semua {{ option.label }}</SelectItem>
                    <SelectItem
                      v-for="item in option.options"
                      :key="item.value"
                      :value="item.value"
                    >
                      {{ item.label }}
                    </SelectItem>
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
            empty-text="Tidak ada data yang cocok dengan filter saat ini."
          >
            <template #cell-code="{ row }">
              <Badge variant="secondary" class="font-mono text-[11px]">
                {{ row.code }}
              </Badge>
            </template>

            <template #cell-name="{ row }">
              <p class="font-medium text-slate-950">{{ row.name }}</p>
            </template>

            <template #cell-details="{ row }">
              <div v-if="row.details?.length" class="space-y-1">
                <p
                  v-for="detail in row.details"
                  :key="`${row.id}-${detail}`"
                  class="text-xs leading-5 text-slate-600"
                >
                  {{ detail }}
                </p>
              </div>
              <span v-else class="text-xs text-slate-400">Tidak ada relasi tambahan</span>
            </template>

            <template #cell-stats="{ row }">
              <div v-if="row.stats?.length" class="flex flex-wrap gap-2">
                <Badge
                  v-for="stat in row.stats"
                  :key="`${row.id}-${stat.label}`"
                  variant="outline"
                  class="border-slate-200 bg-slate-50 text-slate-700"
                >
                  {{ stat.label }}: {{ stat.value }}
                </Badge>
              </div>
              <span v-else class="text-xs text-slate-400">-</span>
            </template>

            <template #cell-actions="{ row }">
              <AdminEntityActions
                :edit-href="row.edit_url"
                :delete-url="row.destroy_url"
                :entity-label="resource.singular.toLowerCase()"
                :entity-name="row.name"
              />
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
