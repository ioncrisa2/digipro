<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps({
  mode: { type: String, required: true },
  record: { type: Object, required: true },
  roleOptions: { type: Array, default: () => [] },
  submitUrl: { type: String, required: true },
  indexUrl: { type: String, required: true },
});

const isEditMode = computed(() => props.mode === 'edit');

const form = useForm({
  role: props.record.role ?? 'reviewer',
  name: props.record.name ?? '',
  position_title: props.record.position_title ?? '',
  title_suffix: props.record.title_suffix ?? '',
  certification_number: props.record.certification_number ?? '',
  is_active: props.record.is_active ?? true,
  _method: isEditMode.value ? 'put' : 'post',
});

const submit = () => {
  form.post(props.submitUrl, { preserveScroll: true });
};
</script>

<template>
  <Head :title="isEditMode ? 'Admin - Edit Penandatangan Report' : 'Admin - Tambah Penandatangan Report'" />

  <AdminLayout :title="isEditMode ? 'Edit Penandatangan Report' : 'Tambah Penandatangan Report'">
    <div class="mx-auto max-w-4xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
            {{ isEditMode ? 'Edit Penandatangan Report' : 'Tambah Penandatangan Report' }}
          </h1>
          <p class="mt-2 text-sm text-slate-600">
            Master profil ini dipakai admin saat menentukan reviewer dan penilai publik untuk report DigiPro.
          </p>
        </div>
        <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader>
            <CardTitle>Profil Penandatangan</CardTitle>
            <CardDescription>Minimal isi nama, peran, dan nomor sertifikasi/izin yang akan tampil di report.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="signer_role">Peran</Label>
              <Select v-model="form.role">
                <SelectTrigger id="signer_role"><SelectValue placeholder="Pilih peran" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in roleOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.role" class="text-xs text-rose-600">{{ form.errors.role }}</p>
            </div>

            <div class="space-y-2">
              <Label for="signer_name">Nama</Label>
              <Input id="signer_name" v-model="form.name" />
              <p v-if="form.errors.name" class="text-xs text-rose-600">{{ form.errors.name }}</p>
            </div>

            <div class="space-y-2">
              <Label for="signer_position">Jabatan / Peran Tampil</Label>
              <Input id="signer_position" v-model="form.position_title" placeholder="Contoh: Reviewer Bersertifikasi" />
              <p v-if="form.errors.position_title" class="text-xs text-rose-600">{{ form.errors.position_title }}</p>
            </div>

            <div class="space-y-2">
              <Label for="signer_suffix">Gelar / Suffix</Label>
              <Input id="signer_suffix" v-model="form.title_suffix" placeholder="Contoh: MAPPI (Cert)" />
              <p v-if="form.errors.title_suffix" class="text-xs text-rose-600">{{ form.errors.title_suffix }}</p>
            </div>

            <div class="space-y-2">
              <Label for="signer_certification_number">Nomor Sertifikasi / Izin</Label>
              <Input id="signer_certification_number" v-model="form.certification_number" placeholder="Contoh: MAPPI-12345 / P-6789" />
              <p v-if="form.errors.certification_number" class="text-xs text-rose-600">{{ form.errors.certification_number }}</p>
            </div>

            <div class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm text-slate-700">
              <Checkbox :model-value="form.is_active" @update:model-value="form.is_active = Boolean($event)" />
              <span>Profil aktif dan bisa dipilih pada report</span>
            </div>
          </CardContent>
        </Card>

        <div class="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" as-child><Link :href="indexUrl">Batal</Link></Button>
          <Button type="submit" :disabled="form.processing">
            {{ isEditMode ? 'Simpan Perubahan' : 'Tambah Profil' }}
          </Button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
