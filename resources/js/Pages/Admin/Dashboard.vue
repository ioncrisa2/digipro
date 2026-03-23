<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCurrency, formatDateTime } from '@/utils/reviewer';

defineProps({
  stats: {
    type: Array,
    default: () => [],
  },
  actionItems: {
    type: Array,
    default: () => [],
  },
  paymentQueue: {
    type: Array,
    default: () => [],
  },
});

const statTone = (tone) => {
  switch (tone) {
    case 'success':
      return 'bg-emerald-100 text-emerald-900';
    case 'warning':
      return 'bg-amber-100 text-amber-900';
    case 'primary':
      return 'bg-indigo-100 text-indigo-900';
    case 'info':
      return 'bg-sky-100 text-sky-900';
    default:
      return 'bg-slate-100 text-slate-900';
  }
};

const statusTone = (value) => {
  switch (value) {
    case 'submitted':
      return 'bg-sky-100 text-sky-900 border-sky-200';
    case 'docs_incomplete':
      return 'bg-rose-100 text-rose-900 border-rose-200';
    case 'verified':
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

const actionColumns = [
  { key: 'request', label: 'Request', cellClass: 'min-w-[180px]' },
  { key: 'requester_name', label: 'Pemohon', cellClass: 'min-w-[140px]' },
  { key: 'status', label: 'Status', cellClass: 'min-w-[120px]' },
  { key: 'assets_count', label: 'Aset', cellClass: 'w-[80px]' },
];
</script>

<template>
  <Head title="Admin Dashboard" />

  <AdminLayout title="Admin Dashboard">
    <div class="space-y-6">
      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card v-for="item in stats" :key="item.key">
          <CardContent class="p-5">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">{{ item.label }}</p>
                <p class="mt-3 text-4xl font-semibold text-slate-950">{{ item.value }}</p>
                <p class="mt-2 text-sm text-slate-600">{{ item.description }}</p>
              </div>
              <div class="rounded-2xl px-3 py-1 text-xs font-semibold" :class="statTone(item.tone)">
                Live
              </div>
            </div>
          </CardContent>
        </Card>
      </section>

      <section class="grid gap-6 xl:grid-cols-[1.25fr_1fr]">
        <Card>
          <CardHeader class="pb-4">
            <div class="flex items-center justify-between gap-3">
              <div>
                <CardTitle>Permohonan Perlu Tindakan</CardTitle>
                <CardDescription>Snapshot request untuk tindak lanjut cepat di workspace admin.</CardDescription>
              </div>
              <Button variant="link" class="h-auto px-0" as-child>
                <Link :href="route('admin.appraisal-requests.index')">Lihat semua</Link>
              </Button>


            </div>
          </CardHeader>
          <CardContent>
            <AdminDataTable
              :columns="actionColumns"
              :rows="actionItems"
              empty-text="Tidak ada request yang butuh tindakan sekarang."
            >
              <template #cell-request="{ row }">
                <Button variant="link" class="h-auto px-0 font-medium" as-child>
                  <Link :href="row.show_url">{{ row.request_number }}</Link>
                </Button>


                <p class="mt-1 text-xs text-slate-500">{{ row.client_name }}</p>
              </template>

              <template #cell-status="{ row }">
                <Badge variant="outline" :class="statusTone(row.status_value)">{{ row.status_label }}</Badge>
              </template>
            </AdminDataTable>
          </CardContent>
        </Card>

        <div class="space-y-6">
          <Card>
            <CardHeader class="pb-4">
              <CardTitle>Menunggu Pembayaran</CardTitle>
              <CardDescription>Antrean kontrak yang sudah ditandatangani tetapi belum diproses lebih lanjut.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div v-for="item in paymentQueue" :key="item.id" class="rounded-2xl border p-4">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <Button variant="link" class="h-auto px-0 font-medium" as-child>
                      <Link :href="item.show_url">{{ item.request_number }}</Link>
                    </Button>


                    <p class="mt-1 text-xs text-slate-500">{{ item.requester_name }}</p>
                  </div>
                  <Badge variant="outline" class="bg-amber-100 text-amber-900 border-amber-200">
                    {{ item.offer_validity_days ? `${item.offer_validity_days} hari` : 'Belum diisi' }}
                  </Badge>
                </div>
                <div class="mt-3 flex items-center justify-between gap-3 text-sm text-slate-600">
                  <span>{{ formatCurrency(item.fee_total) }}</span>
                  <span>{{ formatDateTime(item.updated_at) }}</span>
                </div>
              </div>
              <div v-if="!paymentQueue.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
                Tidak ada antrean pembayaran.
              </div>
            </CardContent>
          </Card>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
