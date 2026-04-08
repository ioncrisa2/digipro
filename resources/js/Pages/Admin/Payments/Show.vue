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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
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
  indexUrl: {
    type: String,
    required: true,
  },
});

const billingSummary = props.record.ringkasan_tagihan ?? null;

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
            Detail read-only pembayaran untuk audit transaksi Midtrans dan penelusuran data historis.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button v-if="record.edit_url" as-child>
            <Link :href="record.edit_url">Edit Pembayaran</Link>
          </Button>
          <Button variant="outline" as-child>
            <Link :href="indexUrl">Kembali ke daftar</Link>
          </Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card>
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Jumlah</p>
            <p class="mt-3 text-3xl font-semibold text-slate-950">{{ formatCurrency(record.amount) }}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Status</p>
            <div class="mt-3">
              <Badge variant="outline" :class="statusTone(record.status)">
                {{ record.status_label }}
              </Badge>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Metode</p>
            <p class="mt-3 text-base font-medium text-slate-950">{{ record.method_label }}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Payment ID</p>
            <p class="mt-3 break-all text-sm text-slate-950">{{ record.external_payment_id || '-' }}</p>
          </CardContent>
        </Card>
      </section>

      <Card>
        <CardHeader>
          <CardTitle>Audit Pembayaran</CardTitle>
          <CardDescription>
            Detail transaksi dipisah ke beberapa tab agar inspeksi admin lebih cepat dan tetap mudah dibaca.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Tabs default-value="summary" class="space-y-6">
            <TabsList class="h-auto w-full justify-start gap-1 rounded-xl border bg-muted/50 p-1.5 flex-wrap">
              <TabsTrigger value="summary" class="rounded-lg px-4 py-2 text-sm data-[state=active]:bg-background data-[state=active]:shadow-sm">Ringkasan</TabsTrigger>
              <TabsTrigger value="gateway" class="rounded-lg px-4 py-2 text-sm data-[state=active]:bg-background data-[state=active]:shadow-sm">Gateway</TabsTrigger>
              <TabsTrigger value="metadata" class="rounded-lg px-4 py-2 text-sm data-[state=active]:bg-background data-[state=active]:shadow-sm">Metadata</TabsTrigger>
              <TabsTrigger value="proof" class="rounded-lg px-4 py-2 text-sm data-[state=active]:bg-background data-[state=active]:shadow-sm">Bukti</TabsTrigger>
              <TabsTrigger value="request" class="rounded-lg px-4 py-2 text-sm data-[state=active]:bg-background data-[state=active]:shadow-sm">Permohonan</TabsTrigger>
            </TabsList>

            <TabsContent value="summary" class="space-y-6">
              <div v-if="billingSummary" class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nilai Jasa</p>
                  <p class="mt-2 text-sm font-semibold text-slate-950">{{ formatCurrency(billingSummary.nilai_jasa_dpp) }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">PPN 11%</p>
                  <p class="mt-2 text-sm font-semibold text-slate-950">{{ formatCurrency(billingSummary.nilai_ppn) }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Tagihan</p>
                  <p class="mt-2 text-sm font-semibold text-slate-950">{{ formatCurrency(billingSummary.total_tagihan) }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">PPh 23 Dipotong</p>
                  <p class="mt-2 text-sm font-semibold text-slate-950">{{ formatCurrency(billingSummary.nilai_pph_dipotong) }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Transfer Customer</p>
                  <p class="mt-2 text-sm font-semibold text-slate-950">{{ formatCurrency(billingSummary.total_transfer_customer) }}</p>
                </div>
              </div>

              <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Invoice</p>
                  <p class="mt-2 text-sm font-medium text-slate-950">{{ record.invoice_number }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Gateway</p>
                  <p class="mt-2 text-sm text-slate-950">{{ record.gateway || gatewayDetails.label || '-' }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Waktu Dibayar</p>
                  <p class="mt-2 text-sm text-slate-950">{{ formatDateTime(record.paid_at) }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Dibuat</p>
                  <p class="mt-2 text-sm text-slate-950">{{ formatDateTime(record.created_at) }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Diubah</p>
                  <p class="mt-2 text-sm text-slate-950">{{ formatDateTime(record.updated_at) }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Tipe Bukti</p>
                  <p class="mt-2 text-sm text-slate-950">{{ record.proof_type || '-' }}</p>
                </div>
              </div>
            </TabsContent>

            <TabsContent value="gateway" class="space-y-6">
              <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Label Channel</p>
                  <p class="mt-2 text-sm text-slate-950">{{ gatewayDetails.label || '-' }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Bank / Acquirer</p>
                  <p class="mt-2 text-sm text-slate-950">{{ gatewayDetails.bank || gatewayDetails.acquirer || '-' }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Transaction Status</p>
                  <p class="mt-2 text-sm text-slate-950">{{ gatewayDetails.transaction_status || '-' }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Reference</p>
                  <p class="mt-2 break-all text-sm text-slate-950">{{ gatewayDetails.reference || '-' }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Transaction ID</p>
                  <p class="mt-2 break-all text-sm text-slate-950">{{ gatewayDetails.transaction_id || '-' }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Expiry</p>
                  <p class="mt-2 text-sm text-slate-950">{{ gatewayDetails.expiry_time || '-' }}</p>
                </div>
              </div>
            </TabsContent>

            <TabsContent value="metadata" class="space-y-3">
              <div class="overflow-hidden rounded-2xl border bg-slate-950">
                <pre class="overflow-x-auto p-4 text-xs leading-6 text-slate-100">{{ record.metadata_json || '{\n  "message": "Metadata kosong"\n}' }}</pre>
              </div>
            </TabsContent>

            <TabsContent value="proof" class="space-y-6">
              <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nama File</p>
                  <p class="mt-2 text-sm text-slate-950">{{ record.proof_original_name || '-' }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Mime</p>
                  <p class="mt-2 text-sm text-slate-950">{{ record.proof_mime || '-' }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Ukuran</p>
                  <p class="mt-2 text-sm text-slate-950">{{ record.proof_size_label }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Tipe Bukti</p>
                  <p class="mt-2 text-sm text-slate-950">{{ record.proof_type || '-' }}</p>
                </div>
              </div>

              <div v-if="record.proof_url">
                <Button variant="outline" as-child>
                  <a :href="record.proof_url" target="_blank" rel="noreferrer">Buka Bukti</a>
                </Button>
              </div>
            </TabsContent>

            <TabsContent value="request" class="space-y-4">
              <div class="rounded-2xl border bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Request</p>
                <div class="mt-2">
                  <Button v-if="record.request_show_url" variant="link" class="h-auto px-0 font-medium" as-child>
                    <Link :href="record.request_show_url">{{ record.request_number }}</Link>
                  </Button>
                  <p v-else class="font-medium text-slate-950">{{ record.request_number }}</p>
                </div>
              </div>
              <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Requester</p>
                  <p class="mt-2 text-sm text-slate-950">{{ record.requester_name }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Klien</p>
                  <p class="mt-2 text-sm text-slate-950">{{ record.client_name }}</p>
                </div>
              </div>
            </TabsContent>
          </Tabs>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
