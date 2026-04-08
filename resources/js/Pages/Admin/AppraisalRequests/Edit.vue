<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
  record: {
    type: Object,
    required: true,
  },
  reportTypeOptions: {
    type: Array,
    default: () => [],
  },
  contractStatusOptions: {
    type: Array,
    default: () => [],
  },
});

const form = useForm({
  client_name: props.record.client_name ?? '',
  report_type: props.record.report_type ?? '',
  contract_sequence: props.record.contract_sequence ?? '',
  contract_date: props.record.contract_date ?? '',
  contract_status: props.record.contract_status ?? '',
  valuation_duration_days: props.record.valuation_duration_days ?? '',
  offer_validity_days: props.record.offer_validity_days ?? '',
  billing_dpp_amount: props.record.billing_dpp_amount ?? '',
  user_request_note: props.record.user_request_note ?? '',
  notes: props.record.notes ?? '',
});

const formatIDR = (value) => new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR" }).format(Number(value || 0));
const billingDpp = computed(() => Number(form.billing_dpp_amount || 0));
const billingVat = computed(() => Math.round(billingDpp.value * 0.11));
const billingGross = computed(() => billingDpp.value + billingVat.value);
const billingPph = computed(() => Math.round(billingDpp.value * 0.02));
const billingNet = computed(() => Math.max(0, billingGross.value - billingPph.value));

const contractNumberPreview = computed(() => {
  const raw = String(form.contract_sequence ?? '').replace(/\D+/g, '');
  if (!raw) return '-';

  const now = new Date();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const year = String(now.getFullYear());
  const padded = raw.padStart(5, '0');

  return `${padded}/AGR/DP/${month}/${year}`;
});

const submit = () => {
  form.put(route('admin.appraisal-requests.update', props.record.id), {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head :title="`Admin - Edit ${record.request_number}`" />

  <AdminLayout :title="`Edit ${record.request_number}`">
    <div class="mx-auto max-w-5xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Edit Request</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ record.request_number }}</h1>
          <p class="mt-2 text-sm text-slate-600">
            Perbarui informasi dasar, kontrak, dan nilai jasa permohonan penilaian dari workspace admin.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child>
            <Link :href="route('admin.appraisal-requests.show', record.id)">Kembali ke detail</Link>
          </Button>


        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader>
            <CardTitle>Informasi Dasar</CardTitle>
            <CardDescription>Field aman yang dipakai admin untuk pembaruan dasar permohonan.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-6">
            <div class="grid gap-6 md:grid-cols-2">
              <div class="space-y-2">
                <Label for="client_name">Pemberi Tugas / Klien</Label>
                <Input id="client_name" v-model="form.client_name" placeholder="Kosongkan jika sama dengan pemohon" />
                <p v-if="form.errors.client_name" class="text-xs text-red-500">{{ form.errors.client_name }}</p>
              </div>

              <div class="space-y-2">
                <Label for="report_type">Jenis Laporan</Label>
                <Select v-model="form.report_type">
                  <SelectTrigger id="report_type">
                    <SelectValue placeholder="Pilih jenis laporan" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="option in reportTypeOptions"
                      :key="option.value"
                      :value="option.value"
                    >
                      {{ option.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="form.errors.report_type" class="text-xs text-red-500">{{ form.errors.report_type }}</p>
              </div>
            </div>

            <div class="space-y-2">
              <Label for="user_request_note">Catatan dari User</Label>
              <Textarea
                id="user_request_note"
                v-model="form.user_request_note"
                rows="5"
                placeholder="Ringkasan kebutuhan atau catatan dari user"
              />
              <p v-if="form.errors.user_request_note" class="text-xs text-red-500">{{ form.errors.user_request_note }}</p>
            </div>

            <div class="space-y-2">
              <Label for="notes">Catatan Internal</Label>
              <Textarea
                id="notes"
                v-model="form.notes"
                rows="6"
                placeholder="Catatan internal admin atau reviewer"
              />
              <p v-if="form.errors.notes" class="text-xs text-red-500">{{ form.errors.notes }}</p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Kontrak & Penawaran</CardTitle>
            <CardDescription>Nomor penawaran dibentuk otomatis dari nomor urut dengan format backend yang konsisten.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-6">
            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
              <div class="space-y-2">
                <Label for="contract_sequence">No. Penawaran</Label>
                <Input id="contract_sequence" v-model="form.contract_sequence" type="number" min="1" placeholder="1" />
                <p v-if="form.errors.contract_sequence" class="text-xs text-red-500">{{ form.errors.contract_sequence }}</p>
              </div>

              <div class="space-y-2">
                <Label for="contract_date">Tanggal Kontrak</Label>
                <Input id="contract_date" v-model="form.contract_date" type="date" />
                <p v-if="form.errors.contract_date" class="text-xs text-red-500">{{ form.errors.contract_date }}</p>
              </div>

              <div class="space-y-2">
                <Label for="contract_status">Status Kontrak</Label>
                <Select v-model="form.contract_status">
                  <SelectTrigger id="contract_status">
                    <SelectValue placeholder="Pilih status kontrak" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="option in contractStatusOptions"
                      :key="option.value"
                      :value="option.value"
                    >
                      {{ option.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="form.errors.contract_status" class="text-xs text-red-500">{{ form.errors.contract_status }}</p>
              </div>

              <div class="space-y-2 md:col-span-2 xl:col-span-3">
                <Label>Preview Nomor Penawaran</Label>
                <div class="rounded-xl border bg-slate-50 px-4 py-3 text-sm font-medium text-slate-900">
                  {{ contractNumberPreview }}
                </div>
              </div>

              <div class="space-y-2">
                <Label for="valuation_duration_days">Jangka Waktu Pelaksanaan</Label>
                <Input id="valuation_duration_days" v-model="form.valuation_duration_days" type="number" min="1" placeholder="30" />
                <p v-if="form.errors.valuation_duration_days" class="text-xs text-red-500">{{ form.errors.valuation_duration_days }}</p>
              </div>

              <div class="space-y-2">
                <Label for="offer_validity_days">Masa Berlaku Penawaran</Label>
                <Input id="offer_validity_days" v-model="form.offer_validity_days" type="number" min="1" placeholder="14" />
                <p v-if="form.errors.offer_validity_days" class="text-xs text-red-500">{{ form.errors.offer_validity_days }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Ringkasan Tagihan</CardTitle>
            <CardDescription>Input utama admin adalah Nilai Jasa (DPP). Sistem menghitung PPN 11%, PPh 23 Dipotong, dan Total Transfer Customer secara otomatis.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-6">
            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-5">
              <div class="space-y-2">
                <Label for="billing_dpp_amount">Nilai Jasa (DPP)</Label>
                <Input id="billing_dpp_amount" v-model="form.billing_dpp_amount" type="number" min="1" placeholder="15000000" />
                <p v-if="form.errors.billing_dpp_amount" class="text-xs text-red-500">{{ form.errors.billing_dpp_amount }}</p>
              </div>
              <div class="space-y-2"><Label>PPN 11%</Label><div class="rounded-xl border bg-slate-50 px-4 py-3 text-sm font-medium text-slate-900">{{ formatIDR(billingVat) }}</div></div>
              <div class="space-y-2"><Label>Total Tagihan</Label><div class="rounded-xl border bg-slate-50 px-4 py-3 text-sm font-medium text-slate-900">{{ formatIDR(billingGross) }}</div></div>
              <div class="space-y-2"><Label>PPh 23 Dipotong</Label><div class="rounded-xl border bg-slate-50 px-4 py-3 text-sm font-medium text-slate-900">{{ formatIDR(billingPph) }}</div></div>
              <div class="space-y-2"><Label>Total Transfer Customer</Label><div class="rounded-xl border bg-slate-50 px-4 py-3 text-sm font-medium text-slate-900">{{ formatIDR(billingNet) }}</div></div>
            </div>
            <div class="rounded-xl border bg-slate-50 px-4 py-3 text-sm text-slate-700">
              Nilai Jasa (DPP) adalah nilai jasa sebelum pajak. Total Tagihan dihitung dari DPP + PPN, lalu Total Transfer Customer dikurangi PPh 23 Dipotong.
            </div>
            <div class="rounded-xl border bg-slate-50 px-4 py-3 text-sm font-medium text-slate-900">
              Pelunasan penuh via payment gateway
            </div>
            <div v-if="record.ringkasan_tagihan" class="grid gap-4 md:grid-cols-3">
              <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Nomor Invoice Saat Ini</p>
                <p class="mt-2 font-medium text-slate-950">{{ record.ringkasan_tagihan.nomor_invoice || '-' }}</p>
              </div>
              <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Status Dokumen</p>
                <p class="mt-2 font-medium text-slate-950">{{ record.ringkasan_tagihan.status_dokumen_keuangan_label || '-' }}</p>
              </div>
              <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Nama Tagihan</p>
                <p class="mt-2 font-medium text-slate-950">{{ record.ringkasan_tagihan.nama_tagihan || '-' }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <div class="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" as-child>
            <Link :href="route('admin.appraisal-requests.show', record.id)">Batal</Link>
          </Button>


          <Button type="submit" :disabled="form.processing">
            Simpan Perubahan
          </Button>

        </div>
      </form>
    </div>
  </AdminLayout>
</template>
