<script setup>
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
  statusOptions: {
    type: Array,
    default: () => [],
  },
  indexUrl: {
    type: String,
    required: true,
  },
});

const form = useForm({
  amount: props.record.amount ?? '',
  status: props.record.status ?? 'pending',
  gateway: props.record.gateway ?? 'midtrans',
  external_payment_id: props.record.external_payment_id ?? '',
  paid_at: props.record.paid_at ?? '',
  metadata_json: props.record.metadata_json ?? '',
});

const submit = () => {
  form.put(route('admin.finance.payments.update', props.record.id), {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head :title="`Admin - Edit ${record.invoice_number}`" />

  <AdminLayout :title="`Edit ${record.invoice_number}`">
    <div class="mx-auto max-w-5xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Batch 8B</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ record.invoice_number }}</h1>
          <p class="mt-2 text-sm text-slate-600">
            Edit pembayaran untuk flow Midtrans aktif. Metode pembayaran dibaca dari record dan tidak diubah dari sini.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child>
            <Link :href="record.show_url">Kembali ke detail</Link>
          </Button>


          <Button variant="outline" as-child>
            <Link :href="indexUrl">Kembali ke daftar</Link>
          </Button>



        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader>
            <CardTitle>Ringkasan</CardTitle>
            <CardDescription>Informasi terkait request dan identitas pembayaran.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div>
              <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Permohonan</p>
              <div class="mt-2 text-sm text-slate-900">
                <Link
                  v-if="record.request_show_url"
                  :href="record.request_show_url"
                  class="font-medium text-slate-950 underline-offset-4 hover:underline"
                >
                  {{ record.request_number }}
                </Link>
                <span v-else>{{ record.request_number }}</span>
              </div>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Metode</p>
              <p class="mt-2 text-sm text-slate-900">{{ record.method_label }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Requester</p>
              <p class="mt-2 text-sm text-slate-900">{{ record.requester_name }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Klien</p>
              <p class="mt-2 text-sm text-slate-900">{{ record.client_name }}</p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Update Pembayaran</CardTitle>
            <CardDescription>Field aktif untuk operasi admin harian tanpa membuka resource legacy.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="amount">Jumlah (Rp)</Label>
              <Input id="amount" v-model="form.amount" type="number" min="1" />
              <p v-if="form.errors.amount" class="text-xs text-rose-600">{{ form.errors.amount }}</p>
            </div>

            <div class="space-y-2">
              <Label for="status">Status</Label>
              <Select v-model="form.status">
                <SelectTrigger id="status">
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
              <p v-if="form.errors.status" class="text-xs text-rose-600">{{ form.errors.status }}</p>
            </div>

            <div class="space-y-2">
              <Label for="gateway">Gateway</Label>
              <Input id="gateway" v-model="form.gateway" placeholder="midtrans" />
              <p v-if="form.errors.gateway" class="text-xs text-rose-600">{{ form.errors.gateway }}</p>
            </div>

            <div class="space-y-2">
              <Label for="external_payment_id">Payment ID</Label>
              <Input id="external_payment_id" v-model="form.external_payment_id" placeholder="MID-..." />
              <p v-if="form.errors.external_payment_id" class="text-xs text-rose-600">{{ form.errors.external_payment_id }}</p>
            </div>

            <div class="space-y-2 md:col-span-2">
              <Label for="paid_at">Waktu Dibayar</Label>
              <Input id="paid_at" v-model="form.paid_at" type="datetime-local" />
              <p v-if="form.errors.paid_at" class="text-xs text-rose-600">{{ form.errors.paid_at }}</p>
            </div>

            <div class="space-y-2 md:col-span-2">
              <Label for="metadata_json">Metadata Gateway (JSON)</Label>
              <Textarea
                id="metadata_json"
                v-model="form.metadata_json"
                rows="12"
                placeholder="{&quot;invoice_number&quot;:&quot;INV-...&quot;}"
              />
              <p class="text-xs text-slate-500">
                Gunakan JSON valid. Field ini tetap ada untuk audit channel Midtrans dan koreksi metadata.
              </p>
              <p v-if="form.errors.metadata_json" class="text-xs text-rose-600">{{ form.errors.metadata_json }}</p>
            </div>
          </CardContent>
        </Card>

        <div class="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" as-child>
            <Link :href="record.show_url">Batal</Link>
          </Button>


          <Button type="submit" :disabled="form.processing">
            Simpan Perubahan
          </Button>

        </div>
      </form>
    </div>
  </AdminLayout>
</template>
