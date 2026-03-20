<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PaginationBar from '@/components/reviewer/PaginationBar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { Badge } from '@/components/ui/badge';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { formatCurrency, formatDateTime } from '@/utils/reviewer';

const props = defineProps({
  filters: {
    type: Object,
    required: true,
  },
  statusOptions: {
    type: Array,
    default: () => [],
  },
  summary: {
    type: Object,
    required: true,
  },
  records: {
    type: Object,
    required: true,
  },
  legacyPanelUrl: {
    type: String,
    default: '/legacy-admin',
  },
});

const form = reactive({
  q: props.filters.q ?? '',
  status: props.filters.status ?? 'all',
});

const applyFilters = () => {
  router.get(
    route('admin.appraisal-requests.index'),
    {
      q: form.q || undefined,
      status: form.status === 'all' ? undefined : form.status,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    },
  );
};

const resetFilters = () => {
  form.q = '';
  form.status = 'all';
  applyFilters();
};

const statusTone = (value) => {
  switch (value) {
    case 'submitted':
      return 'bg-sky-100 text-sky-900 border-sky-200';
    case 'docs_incomplete':
      return 'bg-rose-100 text-rose-900 border-rose-200';
    case 'verified':
    case 'completed':
      return 'bg-emerald-100 text-emerald-900 border-emerald-200';
    case 'waiting_offer':
    case 'waiting_signature':
    case 'contract_signed':
      return 'bg-amber-100 text-amber-900 border-amber-200';
    case 'offer_sent':
      return 'bg-indigo-100 text-indigo-900 border-indigo-200';
    default:
      return 'bg-slate-100 text-slate-800 border-slate-200';
  }
};

const summaryCards = [
  { key: 'total', label: 'Total Request' },
  { key: 'needs_action', label: 'Butuh Tindakan' },
  { key: 'payment_pending', label: 'Menunggu Pembayaran' },
];
</script>

<template>
  <Head title="Admin - Permohonan Penilaian" />

  <AdminLayout title="Permohonan Penilaian">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Permohonan Penilaian</h1>
          <p class="mt-2 text-sm text-slate-600">
            Ini adalah pengganti awal `AppraisalRequestResource`. Detail request sudah bisa dibuka dari Vue, edit lanjutan tetap lewat legacy panel.
          </p>
        </div>
        <Button variant="outline" as-child>
          <a :href="legacyPanelUrl">Buka di Legacy Admin</a>
        </Button>
      </section>

      <section class="grid gap-4 md:grid-cols-3">
        <Card v-for="card in summaryCards" :key="card.key">
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">{{ card.label }}</p>
            <p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary[card.key] ?? 0 }}</p>
          </CardContent>
        </Card>
      </section>

      <Card>
        <CardHeader>
          <CardTitle>Filter</CardTitle>
          <CardDescription>Cari berdasarkan nomor request, nama klien, atau nama pemohon.</CardDescription>
        </CardHeader>
        <CardContent>
          <form class="grid gap-3 md:grid-cols-[1.6fr_1fr_auto_auto]" @submit.prevent="applyFilters">
            <Input v-model="form.q" placeholder="Cari request atau pemohon" />
            <Select v-model="form.status">
              <SelectTrigger>
                <SelectValue placeholder="Semua status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Semua status</SelectItem>
                <SelectItem
                  v-for="option in statusOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
            <Button type="submit">Terapkan</Button>
            <Button type="button" variant="outline" @click="resetFilters">Reset</Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-4">
          <CardTitle>Daftar Request</CardTitle>
          <CardDescription>
            Menampilkan {{ records.meta.from ?? 0 }}-{{ records.meta.to ?? 0 }} dari {{ records.meta.total ?? 0 }} request.
          </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Request</TableHead>
                  <TableHead>Klien / Pemohon</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Kontrak</TableHead>
                  <TableHead>Aset</TableHead>
                  <TableHead>Fee</TableHead>
                  <TableHead>Aksi</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="record in records.data" :key="record.id">
                  <TableCell>
                    <Button variant="link" class="h-auto px-0 font-medium" as-child>
                      <Link :href="record.show_url">{{ record.request_number }}</Link>
                    </Button>
                    <p class="mt-1 text-xs text-slate-500">{{ formatDateTime(record.requested_at) }}</p>
                  </TableCell>
                  <TableCell>
                    <p class="font-medium text-slate-900">{{ record.client_name }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ record.requester_name }}</p>
                  </TableCell>
                  <TableCell>
                    <Badge variant="outline" :class="statusTone(record.status_value)">{{ record.status_label }}</Badge>
                  </TableCell>
                  <TableCell>
                    <Badge variant="outline" :class="statusTone(record.contract_status_value)">{{ record.contract_status_label }}</Badge>
                    <p class="mt-1 text-xs text-slate-500">Nego: {{ record.negotiation_rounds_used }}</p>
                  </TableCell>
                  <TableCell>{{ record.assets_count }}</TableCell>
                  <TableCell>{{ formatCurrency(record.fee_total) }}</TableCell>
                  <TableCell>
                    <div class="flex flex-wrap gap-2">
                      <Button variant="outline" size="sm" as-child>
                        <Link :href="record.show_url">Detail</Link>
                      </Button>
                      <Button variant="ghost" size="sm" as-child>
                        <Link :href="route('admin.appraisal-requests.edit', record.id)">Edit</Link>
                      </Button>
                      <Button v-if="record.legacy_url" variant="ghost" size="sm" as-child>
                        <a :href="record.legacy_url">Legacy Admin</a>
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
                <TableRow v-if="!records.data.length">
                  <TableCell :colspan="7" class="text-center text-slate-500">Tidak ada data untuk filter ini.</TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>

          <PaginationBar :links="records.meta.links ?? []" />
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
