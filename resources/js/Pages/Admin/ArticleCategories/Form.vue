<script setup>
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
  description: props.record.description ?? '',
  sort_order: props.record.sort_order ?? 0,
  is_active: Boolean(props.record.is_active ?? true),
  show_in_nav: Boolean(props.record.show_in_nav ?? false),
  _method: isEditMode.value ? 'put' : 'post',
});

const slugify = (value) => String(value ?? '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
watch(() => form.name, (value) => {
  if (!isEditMode.value || !form.slug) form.slug = slugify(value);
});

const submit = () => form.post(props.submitUrl, { preserveScroll: true });
</script>

<template>
  <Head :title="isEditMode ? 'Admin - Edit Kategori Artikel' : 'Admin - Tambah Kategori Artikel'" />
  <AdminLayout :title="isEditMode ? 'Edit Kategori Artikel' : 'Tambah Kategori Artikel'">
    <div class="mx-auto max-w-4xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div><p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Batch 9</p><h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ isEditMode ? 'Edit Kategori Artikel' : 'Tambah Kategori Artikel' }}</h1></div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader><CardTitle>Kategori</CardTitle><CardDescription>Form kategori artikel publik.</CardDescription></CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2 md:col-span-2"><Label for="name">Nama</Label><Input id="name" v-model="form.name" /><p v-if="form.errors.name" class="text-xs text-rose-600">{{ form.errors.name }}</p></div>
            <div class="space-y-2 md:col-span-2"><Label for="slug">Slug</Label><Input id="slug" v-model="form.slug" /><p v-if="form.errors.slug" class="text-xs text-rose-600">{{ form.errors.slug }}</p></div>
            <div class="space-y-2"><Label for="sort_order">Urutan</Label><Input id="sort_order" v-model="form.sort_order" type="number" min="0" /><p v-if="form.errors.sort_order" class="text-xs text-rose-600">{{ form.errors.sort_order }}</p></div>
            <div class="space-y-2 md:col-span-2"><Label for="description">Deskripsi</Label><Textarea id="description" v-model="form.description" rows="4" /><p v-if="form.errors.description" class="text-xs text-rose-600">{{ form.errors.description }}</p></div>
            <label class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm text-slate-700"><Checkbox v-model="form.is_active" /><span>Kategori aktif</span></label>
            <label class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm text-slate-700"><Checkbox v-model="form.show_in_nav" /><span>Tampilkan di navbar</span></label>
          </CardContent>
        </Card>
        <div class="flex flex-wrap justify-end gap-2"><Button type="button" variant="outline" as-child><Link :href="indexUrl">Batal</Link></Button><Button type="submit" :disabled="form.processing">{{ isEditMode ? 'Simpan Perubahan' : 'Tambah Kategori' }}</Button></div>
      </form>
    </div>
  </AdminLayout>
</template>
