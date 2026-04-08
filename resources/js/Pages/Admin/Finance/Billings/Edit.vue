<script setup>
import { computed } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { formatCurrency } from "@/utils/reviewer";

const props = defineProps({
  record: { type: Object, required: true },
  statusOptions: { type: Array, default: () => [] },
  withholdingTypeOptions: { type: Array, default: () => [] },
  taxIdentityTypeOptions: { type: Array, default: () => [] },
  indexUrl: { type: String, required: true },
  showUrl: { type: String, required: true },
});

const form = useForm({
  billing_dpp_amount: props.record.billing_dpp_amount ?? "",
  billing_withholding_tax_type: props.record.billing_withholding_tax_type ?? "pph23",
  finance_billing_name: props.record.finance_billing_name ?? "",
  finance_billing_address: props.record.finance_billing_address ?? "",
  finance_tax_identity_type: props.record.finance_tax_identity_type ?? "none",
  finance_tax_identity_number: props.record.finance_tax_identity_number ?? "",
  finance_billing_email: props.record.finance_billing_email ?? "",
  billing_invoice_number: props.record.billing_invoice_number ?? "",
  billing_invoice_date: props.record.billing_invoice_date ?? "",
  tax_invoice_number: props.record.tax_invoice_number ?? "",
  tax_invoice_date: props.record.tax_invoice_date ?? "",
  withholding_receipt_number: props.record.withholding_receipt_number ?? "",
  withholding_receipt_date: props.record.withholding_receipt_date ?? "",
  finance_document_status: props.record.finance_document_status ?? "draft",
  billing_invoice_file: null,
  tax_invoice_file: null,
  withholding_receipt_file: null,
});

const parsedDpp = computed(() => Number(form.billing_dpp_amount || 0));
const nilaiPpn = computed(() => Math.round(parsedDpp.value * 0.11));
const totalTagihan = computed(() => parsedDpp.value + nilaiPpn.value);
const nilaiPph = computed(() => Math.round(parsedDpp.value * 0.02));
const totalTransfer = computed(() => Math.max(0, totalTagihan.value - nilaiPph.value));

const submit = () => {
  form.transform((data) => ({
    ...data,
    finance_tax_identity_type: data.finance_tax_identity_type === "none" ? null : data.finance_tax_identity_type,
    _method: "put",
  })).post(route("admin.finance.billings.update", props.record.id), {
    preserveScroll: true,
    forceFormData: true,
  });
};
</script>

<template>
  <Head :title="`Admin - Edit Tagihan ${record.request_number}`" />
  <AdminLayout :title="`Edit Tagihan ${record.request_number}`">
    <div class="mx-auto max-w-6xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Edit Tagihan</h1>
          <p class="mt-2 text-sm text-slate-600">Nilai Jasa adalah DPP. Sistem menghitung PPN 11%, PPh 23 Dipotong, dan Total Transfer Customer secara otomatis.</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><Link :href="showUrl">Kembali ke detail</Link></Button>
          <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nilai Jasa</p><p class="mt-3 text-2xl font-semibold text-slate-950">{{ formatCurrency(parsedDpp) }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">PPN 11%</p><p class="mt-3 text-2xl font-semibold text-slate-950">{{ formatCurrency(nilaiPpn) }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Tagihan</p><p class="mt-3 text-2xl font-semibold text-slate-950">{{ formatCurrency(totalTagihan) }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">PPh 23 Dipotong</p><p class="mt-3 text-2xl font-semibold text-slate-950">{{ formatCurrency(nilaiPph) }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Transfer Customer</p><p class="mt-3 text-2xl font-semibold text-slate-950">{{ formatCurrency(totalTransfer) }}</p></CardContent></Card>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader><CardTitle>Ringkasan Tagihan</CardTitle></CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="billing_dpp_amount">Nilai Jasa (DPP)</Label>
              <p class="text-xs text-slate-500">Masukkan nilai jasa sebelum PPN. Total tagihan dihitung otomatis dari DPP + PPN.</p>
              <Input id="billing_dpp_amount" v-model="form.billing_dpp_amount" type="number" min="1" />
              <p v-if="form.errors.billing_dpp_amount" class="text-xs text-rose-600">{{ form.errors.billing_dpp_amount }}</p>
            </div>
            <div class="space-y-2">
              <Label for="billing_withholding_tax_type">Jenis PPh Dipotong</Label>
              <p class="text-xs text-slate-500">PPh 23 dipotong dari DPP dan mengurangi total transfer customer.</p>
              <Select v-model="form.billing_withholding_tax_type">
                <SelectTrigger id="billing_withholding_tax_type"><SelectValue placeholder="Pilih jenis PPh" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in withholdingTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>Data Lawan Transaksi</CardTitle></CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="finance_billing_name">Nama Tagihan / Penerima Laporan</Label>
              <Input id="finance_billing_name" v-model="form.finance_billing_name" />
              <p v-if="form.errors.finance_billing_name" class="text-xs text-rose-600">{{ form.errors.finance_billing_name }}</p>
            </div>
            <div class="space-y-2">
              <Label for="finance_billing_email">Email Tagihan</Label>
              <Input id="finance_billing_email" v-model="form.finance_billing_email" type="email" />
              <p v-if="form.errors.finance_billing_email" class="text-xs text-rose-600">{{ form.errors.finance_billing_email }}</p>
            </div>
            <div class="space-y-2 md:col-span-2">
              <Label for="finance_billing_address">Alamat Tagihan</Label>
              <Textarea id="finance_billing_address" v-model="form.finance_billing_address" rows="4" />
              <p v-if="form.errors.finance_billing_address" class="text-xs text-rose-600">{{ form.errors.finance_billing_address }}</p>
            </div>
            <div class="space-y-2">
              <Label for="finance_tax_identity_type">Jenis Identitas Pajak</Label>
              <Select v-model="form.finance_tax_identity_type">
                <SelectTrigger id="finance_tax_identity_type"><SelectValue placeholder="Pilih identitas" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">Tidak Ada</SelectItem>
                  <SelectItem v-for="option in taxIdentityTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-2">
              <Label for="finance_tax_identity_number">Nomor Identitas Pajak</Label>
              <Input id="finance_tax_identity_number" v-model="form.finance_tax_identity_number" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>Dokumen Keuangan</CardTitle></CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2"><Label for="billing_invoice_number">Nomor Invoice</Label><Input id="billing_invoice_number" v-model="form.billing_invoice_number" /></div>
            <div class="space-y-2"><Label for="billing_invoice_date">Tanggal Invoice</Label><Input id="billing_invoice_date" v-model="form.billing_invoice_date" type="date" /></div>
            <div class="space-y-2"><Label for="tax_invoice_number">Nomor Faktur Pajak</Label><Input id="tax_invoice_number" v-model="form.tax_invoice_number" /></div>
            <div class="space-y-2"><Label for="tax_invoice_date">Tanggal Faktur Pajak</Label><Input id="tax_invoice_date" v-model="form.tax_invoice_date" type="date" /></div>
            <div class="space-y-2"><Label for="withholding_receipt_number">Nomor Bukti Potong</Label><Input id="withholding_receipt_number" v-model="form.withholding_receipt_number" /></div>
            <div class="space-y-2"><Label for="withholding_receipt_date">Tanggal Bukti Potong</Label><Input id="withholding_receipt_date" v-model="form.withholding_receipt_date" type="date" /></div>
            <div class="space-y-2"><Label for="billing_invoice_file">File Invoice</Label><Input id="billing_invoice_file" type="file" accept="application/pdf" @input="form.billing_invoice_file = $event.target.files?.[0] ?? null" /><p v-if="form.errors.billing_invoice_file" class="text-xs text-rose-600">{{ form.errors.billing_invoice_file }}</p></div>
            <div class="space-y-2"><Label for="tax_invoice_file">File Faktur Pajak</Label><Input id="tax_invoice_file" type="file" accept="application/pdf" @input="form.tax_invoice_file = $event.target.files?.[0] ?? null" /><p v-if="form.errors.tax_invoice_file" class="text-xs text-rose-600">{{ form.errors.tax_invoice_file }}</p></div>
            <div class="space-y-2"><Label for="withholding_receipt_file">File Bukti Potong</Label><Input id="withholding_receipt_file" type="file" accept="application/pdf" @input="form.withholding_receipt_file = $event.target.files?.[0] ?? null" /><p v-if="form.errors.withholding_receipt_file" class="text-xs text-rose-600">{{ form.errors.withholding_receipt_file }}</p></div>
            <div class="space-y-2"><Label for="finance_document_status">Status Dokumen Keuangan</Label><Select v-model="form.finance_document_status"><SelectTrigger id="finance_document_status"><SelectValue placeholder="Pilih status" /></SelectTrigger><SelectContent><SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent></Select></div>
          </CardContent>
        </Card>

        <div class="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" as-child><Link :href="showUrl">Batal</Link></Button>
          <Button type="submit" :disabled="form.processing">Simpan Tagihan</Button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
