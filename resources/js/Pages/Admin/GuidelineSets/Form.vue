<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Checkbox } from '@/components/ui/checkbox';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/AdminLayout.vue';

const props = defineProps({
  mode: { type: String, required: true },
  record: { type: Object, required: true },
  submitUrl: { type: String, required: true },
  indexUrl: { type: String, required: true },
});

const isEditMode = computed(() => props.mode === 'edit');

const form = useForm({
  name: props.record.name ?? '',
  year: props.record.year ?? '',
  description: props.record.description ?? '',
  is_active: Boolean(props.record.is_active ?? false),
  _method: isEditMode.value ? 'put' : 'post',
});

const submit = () => {
  form.post(props.submitUrl, { preserveScroll: true });
};
</script>

<template>
  <Head :title="isEditMode ? 'Admin - Edit Guideline Set' : 'Admin - Tambah Guideline Set'" />

  <AdminLayout :title="isEditMode ? 'Edit Guideline Set' : 'Tambah Guideline Set'">
    <div class="mx-auto max-w-4xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Pedoman Referensi</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
            {{ isEditMode ? 'Edit Guideline Set' : 'Tambah Guideline Set' }}
          </h1>
          <p class="mt-2 text-sm text-slate-600">
            Set aktif akan menjadi acuan default untuk proses appraisal baru.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader>
            <CardTitle>Data Guideline</CardTitle>
            <CardDescription>Identitas dasar guideline set.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="guideline_name">Nama</Label>
              <Input id="guideline_name" v-model="form.name" />
              <p v-if="form.errors.name" class="text-xs text-rose-600">{{ form.errors.name }}</p>
            </div>

            <div class="space-y-2">
              <Label for="guideline_year">Tahun</Label>
              <Input id="guideline_year" v-model="form.year" type="number" min="2000" max="2100" />
              <p v-if="form.errors.year" class="text-xs text-rose-600">{{ form.errors.year }}</p>
            </div>

            <div class="space-y-2 md:col-span-2">
              <Label for="guideline_description">Deskripsi</Label>
              <Textarea id="guideline_description" v-model="form.description" class="min-h-[110px]" />
              <p v-if="form.errors.description" class="text-xs text-rose-600">{{ form.errors.description }}</p>
            </div>

            <div class="space-y-3 md:col-span-2">
              <label class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm text-slate-700">
                <Checkbox :model-value="form.is_active" @update:model-value="form.is_active = Boolean($event)" />
                <div>
                  <p class="font-medium text-slate-950">Jadikan guideline aktif</p>
                  <p class="text-xs text-slate-500">Jika aktif, guideline aktif lain akan dinonaktifkan otomatis.</p>
                </div>
              </label>
              <p v-if="form.errors.is_active" class="text-xs text-rose-600">{{ form.errors.is_active }}</p>
            </div>
          </CardContent>
        </Card>

        <div class="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" as-child><Link :href="indexUrl">Batal</Link></Button>
          <Button type="submit" :disabled="form.processing">
            {{ isEditMode ? 'Simpan Perubahan' : 'Tambah Guideline Set' }}
          </Button>

        </div>
      </form>
    </div>
  </AdminLayout>
</template>
