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
import {
  Card,
  CardContent,
  CardDescription,
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
  legacyPanelUrl: { type: String, default: '/legacy-admin' },
});

const applyFilters = (patch = {}) => {
  router.get(props.indexUrl, {
    ...props.filters,
    ...patch,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const destroyRecord = (item) => {
  if (!window.confirm(`Hapus ${props.resource.singular} "${item.name}"?`)) {
    return;
  }

  router.delete(item.destroy_url, {
    preserveScroll: true,
  });
};

const columns = [
  { key: 'code', label: 'Kode', cellClass: 'w-[130px]', sortable: true },
  { key: 'name', label: 'Nama', cellClass: 'min-w-[220px]', sortable: true },
  { key: 'details', label: 'Relasi', cellClass: 'min-w-[260px]' },
  { key: 'stats', label: 'Stat', cellClass: 'min-w-[180px]' },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[200px]' },
];
</script>

<template>
  <Head :title="`Admin - ${resource.title}`" />

  <AdminLayout :title="resource.title">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Batch 10</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ resource.title }}</h1>
          <p class="mt-2 text-sm text-slate-600">{{ resource.description }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button as-child>
            <Link :href="createUrl">{{ resource.create_label }}</Link>
          </Button>
          <Button variant="outline" as-child>
            <a :href="legacyPanelUrl">Buka di Legacy Admin</a>
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
        <CardHeader>
          <CardTitle>Filter</CardTitle>
          <CardDescription>Cari dan sempitkan data {{ resource.title.toLowerCase() }}.</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-4 xl:grid-cols-4">
          <div class="space-y-2" :class="filterOptions.length ? 'xl:col-span-2' : 'xl:col-span-4'">
            <Label :for="`${resource.key}_q`">Cari</Label>
            <Input
              :id="`${resource.key}_q`"
              :model-value="filters.q"
              :placeholder="`${resource.code_label}, nama, atau parent`"
              @change="applyFilters({ q: $event.target.value })"
            />
          </div>

          <div
            v-for="option in filterOptions"
            :key="option.key"
            class="space-y-2"
          >
            <Label :for="`${resource.key}_${option.key}`">{{ option.label }}</Label>
            <Select
              :model-value="filters[option.key] ?? option.defaultValue ?? 'all'"
              @update:model-value="applyFilters({ [option.key]: $event })"
            >
              <SelectTrigger :id="`${resource.key}_${option.key}`">
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
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Daftar {{ resource.title }}</CardTitle>
          <CardDescription>
            Menampilkan {{ records.meta?.from ?? 0 }}-{{ records.meta?.to ?? 0 }} dari {{ records.meta?.total ?? 0 }} data.
          </CardDescription>
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
              <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" as-child>
                  <Link :href="row.edit_url">Edit</Link>
                </Button>
                <Button variant="outline" size="sm" @click="destroyRecord(row)">Hapus</Button>
                <Button v-if="row.legacy_url" variant="outline" size="sm" as-child>
                  <a :href="row.legacy_url">Legacy</a>
                </Button>
              </div>
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
