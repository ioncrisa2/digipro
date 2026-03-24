<script setup>
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps({
  mode: { type: String, required: true },
  record: { type: Object, required: true },
  submitUrl: { type: String, required: true },
  indexUrl: { type: String, required: true },
});

const isEditMode = computed(() => props.mode === 'edit');
const form = useForm({
  name: props.record.name ?? '',
  slug: props.record.slug ?? '',
  is_active: Boolean(props.record.is_active ?? true),
  _method: isEditMode.value ? 'put' : 'post',
});

const slugify = (value) => String(value ?? '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
watch(() => form.name, (value) => {
  if (!isEditMode.value || !form.slug) form.slug = slugify(value);
});

const submit = () => form.post(props.submitUrl, { preserveScroll: true });
</script>

<template>
  <Head :title="isEditMode ? 'Admin - Edit Tag Artikel' : 'Admin - Tambah Tag Artikel'" />
  <AdminLayout :title="isEditMode ? 'Edit Tag Artikel' : 'Tambah Tag Artikel'">
    <div class="mx-auto max-w-3xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div><h1 class="text-3xl font-semibold tracking-tight text-slate-950">{{ isEditMode ? 'Edit Tag Artikel' : 'Tambah Tag Artikel' }}</h1></div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader><CardTitle>Tag</CardTitle><CardDescription>Form tag artikel publik.</CardDescription></CardHeader>
          <CardContent class="grid gap-6">
            <div class="space-y-2"><Label for="name">Nama</Label><Input id="name" v-model="form.name" /><p v-if="form.errors.name" class="text-xs text-rose-600">{{ form.errors.name }}</p></div>
            <div class="space-y-2"><Label for="slug">Slug</Label><Input id="slug" v-model="form.slug" /><p v-if="form.errors.slug" class="text-xs text-rose-600">{{ form.errors.slug }}</p></div>
            <label class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm text-slate-700"><Checkbox v-model="form.is_active" /><span>Tag aktif</span></label>
          </CardContent>
        </Card>
        <div class="flex flex-wrap justify-end gap-2"><Button type="button" variant="outline" as-child><Link :href="indexUrl">Batal</Link></Button><Button type="submit" :disabled="form.processing">{{ isEditMode ? 'Simpan Perubahan' : 'Tambah Tag' }}</Button></div>
      </form>
    </div>
  </AdminLayout>
</template>
