<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

const props = defineProps({
  mode: {
    type: String,
    required: true,
  },
  record: {
    type: Object,
    required: true,
  },
  submitUrl: {
    type: String,
    required: true,
  },
  indexUrl: {
    type: String,
    required: true,
  },
});

const isEditMode = props.mode === 'edit';

const form = useForm({
  bank_name: props.record.bank_name ?? '',
  account_number: props.record.account_number ?? '',
  account_holder: props.record.account_holder ?? '',
  branch: props.record.branch ?? '',
  currency: props.record.currency ?? 'IDR',
  notes: props.record.notes ?? '',
  is_active: Boolean(props.record.is_active ?? true),
  sort_order: props.record.sort_order ?? 0,
});

const submit = () => {
  if (isEditMode) {
    form.put(props.submitUrl, { preserveScroll: true });
    return;
  }

  form.post(props.submitUrl, { preserveScroll: true });
};
</script>

<template>
  <Head :title="isEditMode ? 'Admin - Edit Rekening Kantor' : 'Admin - Tambah Rekening Kantor'" />

  <AdminLayout :title="isEditMode ? 'Edit Rekening Kantor' : 'Tambah Rekening Kantor'">
    <div class="mx-auto max-w-4xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
            {{ isEditMode ? 'Edit Rekening Kantor' : 'Tambah Rekening Kantor' }}
          </h1>
          <p class="mt-2 text-sm text-slate-600">
            Operasi create dan edit rekening kantor sekarang langsung dari workspace admin Vue.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child>
            <Link :href="indexUrl">Kembali ke daftar</Link>
          </Button>



        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader>
            <CardTitle>Informasi Rekening</CardTitle>
            <CardDescription>Field rekening kantor untuk kebutuhan operasional pembayaran admin.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="bank_name">Nama Bank</Label>
              <Input id="bank_name" v-model="form.bank_name" placeholder="BCA" />
              <p v-if="form.errors.bank_name" class="text-xs text-rose-600">{{ form.errors.bank_name }}</p>
            </div>

            <div class="space-y-2">
              <Label for="account_number">Nomor Rekening</Label>
              <Input id="account_number" v-model="form.account_number" placeholder="1234567890" />
              <p v-if="form.errors.account_number" class="text-xs text-rose-600">{{ form.errors.account_number }}</p>
            </div>

            <div class="space-y-2">
              <Label for="account_holder">Nama Pemilik</Label>
              <Input id="account_holder" v-model="form.account_holder" placeholder="PT Digi Pro" />
              <p v-if="form.errors.account_holder" class="text-xs text-rose-600">{{ form.errors.account_holder }}</p>
            </div>

            <div class="space-y-2">
              <Label for="branch">Cabang</Label>
              <Input id="branch" v-model="form.branch" placeholder="Jakarta Pusat" />
              <p v-if="form.errors.branch" class="text-xs text-rose-600">{{ form.errors.branch }}</p>
            </div>

            <div class="space-y-2">
              <Label for="currency">Mata Uang</Label>
              <Input id="currency" v-model="form.currency" maxlength="10" placeholder="IDR" />
              <p v-if="form.errors.currency" class="text-xs text-rose-600">{{ form.errors.currency }}</p>
            </div>

            <div class="space-y-2">
              <Label for="sort_order">Urutan</Label>
              <Input id="sort_order" v-model="form.sort_order" type="number" min="0" />
              <p v-if="form.errors.sort_order" class="text-xs text-rose-600">{{ form.errors.sort_order }}</p>
            </div>

            <div class="space-y-3 md:col-span-2">
              <Label>Status</Label>
              <label class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm text-slate-700">
                <Checkbox v-model="form.is_active" />
                <span>Rekening aktif untuk ditampilkan di flow pembayaran</span>
              </label>
              <p v-if="form.errors.is_active" class="text-xs text-rose-600">{{ form.errors.is_active }}</p>
            </div>

            <div class="space-y-2 md:col-span-2">
              <Label for="notes">Catatan</Label>
              <Textarea id="notes" v-model="form.notes" rows="4" placeholder="Catatan internal rekening kantor" />
              <p v-if="form.errors.notes" class="text-xs text-rose-600">{{ form.errors.notes }}</p>
            </div>
          </CardContent>
        </Card>

        <div class="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" as-child>
            <Link :href="indexUrl">Batal</Link>
          </Button>


          <Button type="submit" :disabled="form.processing">
            {{ isEditMode ? 'Simpan Perubahan' : 'Tambah Rekening' }}
          </Button>

        </div>
      </form>
    </div>
  </AdminLayout>
</template>
