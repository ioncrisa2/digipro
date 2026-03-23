<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import AdminEntityActions from '@/components/admin/AdminEntityActions.vue';
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
import { formatDateTime } from '@/utils/reviewer';

const props = defineProps({
  filters: {
    type: Object,
    default: () => ({ q: '', status: 'all' }),
  },
  statusOptions: {
    type: Array,
    default: () => [],
  },
  summary: {
    type: Object,
    default: () => ({ total: 0, active: 0, inactive: 0 }),
  },
  records: {
    type: Array,
    default: () => [],
  },
  createUrl: {
    type: String,
    required: true,
  },
  paymentsUrl: {
    type: String,
    required: true,
  },
});

const applyFilters = (patch = {}) => {
  router.get(route('admin.finance.office-bank-accounts.index'), {
    ...props.filters,
    ...patch,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const columns = [
  { key: 'bank', label: 'Bank', cellClass: 'min-w-[180px]' },
  { key: 'owner', label: 'Pemilik', cellClass: 'min-w-[180px]' },
  { key: 'status', label: 'Status', cellClass: 'min-w-[110px]' },
  { key: 'meta', label: 'Meta', cellClass: 'min-w-[120px]' },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[200px]' },
];
</script>

<template>
  <Head title="Admin - Rekening Kantor" />

  <AdminLayout title="Rekening Kantor">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Batch 8</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Rekening Kantor</h1>
          <p class="mt-2 text-sm text-slate-600">
            Daftar rekening kantor untuk operasional keuangan admin.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button as-child>
            <Link :href="createUrl">Tambah Rekening</Link>
          </Button>


          <Button variant="outline" as-child>
            <Link :href="paymentsUrl">Kembali ke Pembayaran</Link>
          </Button>


        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-3">
        <Card>
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Rekening</p>
            <p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Aktif</p>
            <p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.active }}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nonaktif</p>
            <p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.inactive }}</p>
          </CardContent>
        </Card>
      </section>

      <Card>
        <CardHeader>
          <CardTitle>Filter Rekening</CardTitle>
          <CardDescription>Filter dasar untuk membaca rekening aktif/nonaktif tanpa membuka resource legacy.</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
          <div class="space-y-2">
            <Label for="bank_q">Cari</Label>
            <Input
              id="bank_q"
              :model-value="filters.q"
              type="text"
              placeholder="Cari bank, nomor rekening, atau nama pemilik"
              @change="applyFilters({ q: $event.target.value })"
            />
          </div>

          <div class="space-y-2">
            <Label for="bank_status">Status</Label>
            <Select :model-value="filters.status" @update:model-value="applyFilters({ status: $event })">
              <SelectTrigger id="bank_status">
                <SelectValue placeholder="Pilih status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="option in statusOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Daftar Rekening</CardTitle>
          <CardDescription>CRUD rekening kantor sekarang berjalan di workspace admin Vue.</CardDescription>
        </CardHeader>
        <CardContent>
          <AdminDataTable
            :columns="columns"
            :rows="records"
            empty-text="Tidak ada rekening kantor yang cocok dengan filter saat ini."
          >
            <template #cell-bank="{ row }">
              <p class="font-medium text-slate-950">{{ row.bank_name }}</p>
              <p class="mt-1 text-xs text-slate-500">{{ row.account_number }}</p>
            </template>

            <template #cell-owner="{ row }">
              <p>{{ row.account_holder }}</p>
              <p class="mt-1 text-xs text-slate-500">{{ row.branch || '-' }}</p>
              <p v-if="row.notes" class="mt-1 text-xs text-slate-500 line-clamp-2">{{ row.notes }}</p>
            </template>

            <template #cell-status="{ row }">
              <Badge variant="outline" :class="row.is_active ? 'bg-emerald-100 text-emerald-900 border-emerald-200' : 'bg-slate-100 text-slate-800 border-slate-200'">
                {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
              </Badge>
            </template>

            <template #cell-meta="{ row }">
              <p>{{ row.currency }}</p>
              <p class="mt-1 text-xs text-slate-500">Urutan: {{ row.sort_order }}</p>
              <p class="mt-1 text-xs text-slate-500">{{ formatDateTime(row.updated_at) }}</p>
            </template>

            <template #cell-actions="{ row }">
              <AdminEntityActions
                :edit-href="row.edit_url"
                :delete-url="row.destroy_url"
                entity-label="rekening kantor"
                :entity-name="`${row.bank_name} (${row.account_number})`"
              />
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
