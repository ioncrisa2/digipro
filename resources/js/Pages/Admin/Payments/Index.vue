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
import { formatCurrency, formatDateTime } from '@/utils/reviewer';

const props = defineProps({
  filters: {
    type: Object,
    default: () => ({ q: '', status: 'all', method: 'all' }),
  },
  statusOptions: {
    type: Array,
    default: () => [],
  },
  methodOptions: {
    type: Array,
    default: () => [],
  },
  summary: {
    type: Object,
    default: () => ({ total: 0, pending: 0, paid: 0, active_bank_accounts: 0 }),
  },
  records: {
    type: Object,
    required: true,
  },
  officeBankAccountsUrl: {
    type: String,
    required: true,
  },
});

const applyFilters = (patch = {}) => {
  router.get(route('admin.finance.payments.index'), {
    ...props.filters,
    ...patch,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const statusTone = (status) => {
  switch (status) {
    case 'paid':
      return 'bg-emerald-100 text-emerald-900 border-emerald-200';
    case 'pending':
      return 'bg-amber-100 text-amber-900 border-amber-200';
    case 'failed':
    case 'rejected':
      return 'bg-rose-100 text-rose-900 border-rose-200';
    case 'expired':
      return 'bg-slate-100 text-slate-800 border-slate-200';
    case 'refunded':
      return 'bg-indigo-100 text-indigo-900 border-indigo-200';
    default:
      return 'bg-slate-100 text-slate-800 border-slate-200';
  }
};

const columns = [
  { key: 'invoice', label: 'Invoice', cellClass: 'min-w-[170px]' },
  { key: 'request', label: 'Permohonan', cellClass: 'min-w-[180px]' },
  { key: 'client', label: 'Klien', cellClass: 'min-w-[180px]' },
  { key: 'method', label: 'Metode', cellClass: 'min-w-[130px]' },
  { key: 'status', label: 'Status', cellClass: 'min-w-[120px]' },
  { key: 'amount', label: 'Jumlah', cellClass: 'min-w-[120px]' },
  { key: 'paid', label: 'Dibayar', cellClass: 'min-w-[150px]' },
];
</script>

<template>
  <Head title="Admin - Pembayaran" />

  <AdminLayout title="Pembayaran">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Batch 8</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Workspace Keuangan</h1>
          <p class="mt-2 text-sm text-slate-600">
            List pembayaran admin untuk operasional baca dan audit cepat.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child>
            <Link :href="officeBankAccountsUrl">Lihat Rekening Kantor</Link>
          </Button>


        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card>
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Pembayaran</p>
            <p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Menunggu</p>
            <p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.pending }}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Dibayar</p>
            <p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.paid }}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Rekening Aktif</p>
            <p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.active_bank_accounts }}</p>
          </CardContent>
        </Card>
      </section>

      <Card>
        <CardHeader>
          <CardTitle>Filter Pembayaran</CardTitle>
          <CardDescription>Filter ringan untuk mengecek transaksi Midtrans atau data legacy tanpa membuka panel lama.</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr_0.8fr]">
          <div class="space-y-2">
            <Label for="payment_q">Cari</Label>
            <Input
              id="payment_q"
              :model-value="filters.q"
              type="text"
              placeholder="Cari invoice, request, payment ID, atau nama klien"
              @change="applyFilters({ q: $event.target.value })"
            />
          </div>

          <div class="space-y-2">
            <Label for="payment_status">Status</Label>
            <Select :model-value="filters.status" @update:model-value="applyFilters({ status: $event })">
              <SelectTrigger id="payment_status">
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

          <div class="space-y-2">
            <Label for="payment_method">Metode</Label>
            <Select :model-value="filters.method" @update:model-value="applyFilters({ method: $event })">
              <SelectTrigger id="payment_method">
                <SelectValue placeholder="Pilih metode" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="option in methodOptions"
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
          <CardTitle>Daftar Pembayaran</CardTitle>
          <CardDescription>List pembayaran utama admin. Edit transaksi Midtrans-safe sudah tersedia tanpa buka legacy.</CardDescription>
        </CardHeader>
        <CardContent>
          <AdminDataTable
            :columns="columns"
            :rows="records.data"
            :meta="records.meta"
            empty-text="Tidak ada pembayaran yang cocok dengan filter saat ini."
          >
            <template #cell-invoice="{ row }">
              <Button variant="link" class="h-auto px-0 font-medium" as-child>
                <Link :href="row.show_url">{{ row.invoice_number }}</Link>
              </Button>


              <p class="mt-1 text-xs text-slate-500">{{ row.external_payment_id || '-' }}</p>
            </template>

            <template #cell-request="{ row }">
              <Button v-if="row.request_show_url" variant="link" class="h-auto px-0 font-medium" as-child>
                <Link :href="row.request_show_url">{{ row.request_number }}</Link>
              </Button>


              <span v-else>{{ row.request_number }}</span>
              <p class="mt-1 text-xs text-slate-500">{{ row.requester_name }}</p>
            </template>

            <template #cell-client="{ row }">
              <p class="font-medium text-slate-950">{{ row.client_name }}</p>
              <p class="mt-1 text-xs text-slate-500">{{ row.bank_label || '-' }}</p>
            </template>

            <template #cell-method="{ row }">
              <p>{{ row.method_label }}</p>
              <p class="mt-1 text-xs text-slate-500">{{ row.reference || row.gateway || '-' }}</p>
            </template>

            <template #cell-status="{ row }">
              <Badge variant="outline" :class="statusTone(row.status)">
                {{ row.status_label }}
              </Badge>
            </template>

            <template #cell-amount="{ row }">
              {{ formatCurrency(row.amount) }}
            </template>

            <template #cell-paid="{ row }">
              <p>{{ formatDateTime(row.paid_at) }}</p>
              <Button variant="link" class="mt-1 h-auto px-0 text-xs" as-child>
                <Link :href="row.edit_url">Edit pembayaran</Link>
              </Button>


            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
