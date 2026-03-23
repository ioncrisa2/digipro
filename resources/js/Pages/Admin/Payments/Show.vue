<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { formatCurrency, formatDateTime } from '@/utils/reviewer';

const props = defineProps({
  record: {
    type: Object,
    required: true,
  },
  gatewayDetails: {
    type: Object,
    default: () => ({}),
  },
  officeBankAccountsUrl: {
    type: String,
    required: true,
  },
  indexUrl: {
    type: String,
    required: true,
  },
});

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
</script>

<template>
  <Head :title="`Admin - ${record.invoice_number}`" />

  <AdminLayout :title="record.invoice_number">
    <div class="mx-auto max-w-6xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Detail Pembayaran</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ record.invoice_number }}</h1>
          <p class="mt-2 text-sm text-slate-600">
            Detail read-only pembayaran untuk audit transaksi Midtrans dan membaca data legacy yang masih ada.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button as-child>
            <Link :href="route('admin.finance.payments.edit', record.id)">Edit Pembayaran</Link>
          </Button>


          <Button variant="outline" as-child>
            <Link :href="indexUrl">Kembali ke daftar</Link>
          </Button>


          <Button variant="outline" as-child>
            <Link :href="officeBankAccountsUrl">Rekening Kantor</Link>
          </Button>



        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <div class="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Ringkasan Pembayaran</CardTitle>
              <CardDescription>Field utama dari resource pembayaran legacy.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-2">
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Jumlah</p>
                <p class="mt-2 text-sm text-slate-900">{{ formatCurrency(record.amount) }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Status</p>
                <div class="mt-2">
                  <Badge variant="outline" :class="statusTone(record.status)">
                    {{ record.status_label }}
                  </Badge>
                </div>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Metode</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.method_label }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Gateway</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.gateway || gatewayDetails.label || '-' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Payment ID</p>
                <p class="mt-2 break-all text-sm text-slate-900">{{ record.external_payment_id || '-' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Waktu Dibayar</p>
                <p class="mt-2 text-sm text-slate-900">{{ formatDateTime(record.paid_at) }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Dibuat</p>
                <p class="mt-2 text-sm text-slate-900">{{ formatDateTime(record.created_at) }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Diubah</p>
                <p class="mt-2 text-sm text-slate-900">{{ formatDateTime(record.updated_at) }}</p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Channel Midtrans</CardTitle>
              <CardDescription>Ringkasan channel pembayaran dari metadata gateway.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-2">
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Label Channel</p>
                <p class="mt-2 text-sm text-slate-900">{{ gatewayDetails.label || '-' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Bank / Acquirer</p>
                <p class="mt-2 text-sm text-slate-900">{{ gatewayDetails.bank || gatewayDetails.acquirer || '-' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Reference</p>
                <p class="mt-2 break-all text-sm text-slate-900">{{ gatewayDetails.reference || '-' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Transaction ID</p>
                <p class="mt-2 break-all text-sm text-slate-900">{{ gatewayDetails.transaction_id || '-' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Transaction Status</p>
                <p class="mt-2 text-sm text-slate-900">{{ gatewayDetails.transaction_status || '-' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Expiry</p>
                <p class="mt-2 text-sm text-slate-900">{{ gatewayDetails.expiry_time || '-' }}</p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Metadata</CardTitle>
              <CardDescription>Metadata transaksi diringkas agar inspeksi admin lebih cepat dan mudah dibaca.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div
                v-for="line in record.metadata_lines"
                :key="line"
                class="rounded-2xl border bg-slate-50 px-4 py-3 text-sm text-slate-700"
              >
                {{ line }}
              </div>
            </CardContent>
          </Card>
        </div>

        <div class="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Permohonan Terkait</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3 text-sm text-slate-700">
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Request</p>
                <div class="mt-1">
                  <Button v-if="record.request_show_url" variant="link" class="h-auto px-0 font-medium" as-child>
                    <Link :href="record.request_show_url">{{ record.request_number }}</Link>
                  </Button>


                  <p v-else class="font-medium text-slate-950">{{ record.request_number }}</p>
                </div>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Requester</p>
                <p class="mt-1">{{ record.requester_name }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Klien</p>
                <p class="mt-1">{{ record.client_name }}</p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Bukti Pembayaran</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3 text-sm text-slate-700">
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Tipe Bukti</p>
                <p class="mt-1">{{ record.proof_type || '-' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nama File</p>
                <p class="mt-1">{{ record.proof_original_name || '-' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Mime</p>
                <p class="mt-1">{{ record.proof_mime || '-' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Ukuran</p>
                <p class="mt-1">{{ record.proof_size_label }}</p>
              </div>
              <div v-if="record.proof_url">
                <Button variant="outline" as-child>
                  <a :href="record.proof_url" target="_blank" rel="noreferrer">Buka Bukti</a>
                </Button>


              </div>
            </CardContent>
          </Card>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
