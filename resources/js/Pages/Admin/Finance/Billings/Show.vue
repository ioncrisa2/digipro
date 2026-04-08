<script setup>
import { Head, Link } from "@inertiajs/vue3";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { formatCurrency, formatDateTime } from "@/utils/reviewer";

const props = defineProps({
  record: { type: Object, required: true },
  indexUrl: { type: String, required: true },
  editUrl: { type: String, required: true },
  paymentsUrl: { type: String, required: true },
});

const summary = props.record.ringkasan_tagihan ?? {};
</script>

<template>
  <Head :title="`Admin - Tagihan ${record.request_number}`" />
  <AdminLayout :title="`Tagihan ${record.request_number}`">
    <div class="mx-auto max-w-6xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Detail Tagihan</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ record.request_number }}</h1>
          <p class="mt-2 text-sm text-slate-600">Ringkasan billing, pajak, dan dokumen keuangan untuk pekerjaan ini.</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button as-child><Link :href="editUrl">Edit Tagihan</Link></Button>
          <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nilai Jasa</p><p class="mt-3 text-2xl font-semibold text-slate-950">{{ formatCurrency(summary.nilai_jasa_dpp) }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">PPN 11%</p><p class="mt-3 text-2xl font-semibold text-slate-950">{{ formatCurrency(summary.nilai_ppn) }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Tagihan</p><p class="mt-3 text-2xl font-semibold text-slate-950">{{ formatCurrency(summary.total_tagihan) }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">PPh 23 Dipotong</p><p class="mt-3 text-2xl font-semibold text-slate-950">{{ formatCurrency(summary.nilai_pph_dipotong) }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Transfer Customer</p><p class="mt-3 text-2xl font-semibold text-slate-950">{{ formatCurrency(summary.total_transfer_customer) }}</p></CardContent></Card>
      </section>

      <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <Card>
          <CardHeader><CardTitle>Data Tagihan</CardTitle></CardHeader>
          <CardContent class="space-y-4 text-sm">
            <div class="grid gap-4 md:grid-cols-2">
              <div><p class="text-xs uppercase tracking-[0.18em] text-slate-500">Customer</p><p class="mt-1 font-medium text-slate-950">{{ record.customer_name }}</p></div>
              <div><p class="text-xs uppercase tracking-[0.18em] text-slate-500">Pemohon</p><p class="mt-1 font-medium text-slate-950">{{ record.requester_name }}</p></div>
              <div><p class="text-xs uppercase tracking-[0.18em] text-slate-500">Email Pemohon</p><p class="mt-1 text-slate-950">{{ record.requester_email }}</p></div>
              <div><p class="text-xs uppercase tracking-[0.18em] text-slate-500">Telepon Pemohon</p><p class="mt-1 text-slate-950">{{ record.requester_phone }}</p></div>
              <div class="md:col-span-2"><p class="text-xs uppercase tracking-[0.18em] text-slate-500">Nama Tagihan</p><p class="mt-1 text-slate-950">{{ summary.nama_tagihan || "-" }}</p></div>
              <div class="md:col-span-2"><p class="text-xs uppercase tracking-[0.18em] text-slate-500">Alamat Tagihan</p><p class="mt-1 whitespace-pre-line text-slate-950">{{ summary.alamat_tagihan || "-" }}</p></div>
              <div><p class="text-xs uppercase tracking-[0.18em] text-slate-500">Identitas Pajak</p><p class="mt-1 text-slate-950">{{ summary.jenis_identitas_pajak_label || "-" }}</p></div>
              <div><p class="text-xs uppercase tracking-[0.18em] text-slate-500">Nomor Identitas Pajak</p><p class="mt-1 text-slate-950">{{ summary.nomor_identitas_pajak || "-" }}</p></div>
              <div class="md:col-span-2"><p class="text-xs uppercase tracking-[0.18em] text-slate-500">Email Tagihan</p><p class="mt-1 text-slate-950">{{ summary.email_tagihan || "-" }}</p></div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>Dokumen Keuangan</CardTitle></CardHeader>
          <CardContent class="space-y-4 text-sm">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Status Dokumen</p>
                <p class="mt-1 font-medium text-slate-950">{{ summary.status_dokumen_keuangan_label }}</p>
              </div>
              <Badge variant="outline">{{ summary.status_dokumen_keuangan_label }}</Badge>
            </div>
            <div class="space-y-3 rounded-2xl border bg-slate-50 p-4">
              <div><p class="text-xs uppercase tracking-[0.18em] text-slate-500">Nomor Invoice</p><p class="mt-1 font-medium text-slate-950">{{ summary.nomor_invoice || "-" }}</p><p class="mt-1 text-xs text-slate-500">{{ summary.tanggal_invoice || "-" }}</p></div>
              <div><p class="text-xs uppercase tracking-[0.18em] text-slate-500">Nomor Faktur Pajak</p><p class="mt-1 font-medium text-slate-950">{{ summary.nomor_faktur_pajak || "-" }}</p><p class="mt-1 text-xs text-slate-500">{{ summary.tanggal_faktur_pajak || "-" }}</p></div>
              <div><p class="text-xs uppercase tracking-[0.18em] text-slate-500">Nomor Bukti Potong</p><p class="mt-1 font-medium text-slate-950">{{ summary.nomor_bukti_potong || "-" }}</p><p class="mt-1 text-xs text-slate-500">{{ summary.tanggal_bukti_potong || "-" }}</p></div>
            </div>
            <div class="flex flex-wrap gap-2">
              <Button v-if="summary.dokumen_invoice_url" variant="outline" as-child><a :href="summary.dokumen_invoice_url" target="_blank" rel="noreferrer">Buka File Invoice</a></Button>
              <Button v-if="summary.dokumen_faktur_pajak_url" variant="outline" as-child><a :href="summary.dokumen_faktur_pajak_url" target="_blank" rel="noreferrer">Buka Faktur Pajak</a></Button>
              <Button v-if="summary.dokumen_bukti_potong_url" variant="outline" as-child><a :href="summary.dokumen_bukti_potong_url" target="_blank" rel="noreferrer">Buka Bukti Potong</a></Button>
              <Button v-if="record.payment?.show_url" variant="ghost" as-child><Link :href="record.payment.show_url">Lihat Pembayaran</Link></Button>
              <Button variant="ghost" as-child><Link :href="record.request_show_url">Lihat Permohonan</Link></Button>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card v-if="record.payment">
        <CardHeader><CardTitle>Pembayaran Terakhir</CardTitle></CardHeader>
        <CardContent class="grid gap-4 md:grid-cols-4 text-sm">
          <div><p class="text-xs uppercase tracking-[0.18em] text-slate-500">Jumlah</p><p class="mt-1 font-medium text-slate-950">{{ formatCurrency(record.payment.amount) }}</p></div>
          <div><p class="text-xs uppercase tracking-[0.18em] text-slate-500">Status</p><p class="mt-1 font-medium text-slate-950">{{ record.payment.status_label }}</p></div>
          <div><p class="text-xs uppercase tracking-[0.18em] text-slate-500">Dibayar</p><p class="mt-1 font-medium text-slate-950">{{ formatDateTime(record.payment.paid_at) }}</p></div>
          <div><Button variant="outline" as-child><Link :href="record.payment.show_url">Detail Pembayaran</Link></Button></div>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
