<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps({
  resource: { type: Object, required: true },
  mode: { type: String, required: true },
  record: { type: Object, required: true },
  indexUrl: { type: String, required: true },
  submitUrl: { type: String, required: true },
  links: { type: Array, default: () => [] },
});

const isEditMode = computed(() => props.mode === 'edit');
const form = useForm({
  title: props.record.title ?? '',
  company: props.record.company ?? '',
  version: props.record.version ?? '',
  effective_since: props.record.effective_since ?? '',
  content_html: props.record.content_html ?? '',
  is_active: Boolean(props.record.is_active ?? false),
  published_at: props.record.published_at ?? '',
  _method: isEditMode.value ? 'put' : 'post',
});

const submit = () => form.post(props.submitUrl, { preserveScroll: true });
</script>

<template>
  <Head :title="`Admin - ${isEditMode ? 'Edit' : 'Tambah'} ${resource.title}`" />
  <AdminLayout :title="`${isEditMode ? 'Edit' : 'Tambah'} ${resource.title}`">
    <div class="mx-auto max-w-5xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div><h1 class="text-3xl font-semibold tracking-tight text-slate-950">{{ isEditMode ? `Edit ${resource.title}` : `Tambah ${resource.title}` }}</h1></div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader><CardTitle>{{ resource.title }}</CardTitle><CardDescription>Dokumen legal aktif aplikasi.</CardDescription></CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2 md:col-span-2"><Label for="title">Judul</Label><Input id="title" v-model="form.title" /><p v-if="form.errors.title" class="text-xs text-rose-600">{{ form.errors.title }}</p></div>
            <div class="space-y-2"><Label for="company">Penyedia Layanan</Label><Input id="company" v-model="form.company" /><p v-if="form.errors.company" class="text-xs text-rose-600">{{ form.errors.company }}</p></div>
            <div class="space-y-2"><Label for="version">Versi</Label><Input id="version" v-model="form.version" /><p v-if="form.errors.version" class="text-xs text-rose-600">{{ form.errors.version }}</p></div>
            <div class="space-y-2"><Label for="effective_since">Berlaku Sejak</Label><Input id="effective_since" v-model="form.effective_since" type="date" /><p v-if="form.errors.effective_since" class="text-xs text-rose-600">{{ form.errors.effective_since }}</p></div>
            <div class="space-y-2"><Label for="published_at">Tanggal Publikasi</Label><Input id="published_at" v-model="form.published_at" type="datetime-local" /><p v-if="form.errors.published_at" class="text-xs text-rose-600">{{ form.errors.published_at }}</p></div>
            <label class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm text-slate-700 md:col-span-2"><Checkbox v-model="form.is_active" /><span>Aktifkan dokumen ini</span></label>
            <div class="space-y-2 md:col-span-2"><Label for="content_html">Konten HTML</Label><Textarea id="content_html" v-model="form.content_html" rows="18" /><p class="text-xs text-slate-500">Gunakan HTML valid.</p><p v-if="form.errors.content_html" class="text-xs text-rose-600">{{ form.errors.content_html }}</p></div>
          </CardContent>
        </Card>
        <div class="flex flex-wrap justify-end gap-2"><Button type="button" variant="outline" as-child><Link :href="indexUrl">Batal</Link></Button><Button type="submit" :disabled="form.processing">{{ isEditMode ? 'Simpan Perubahan' : `Tambah ${resource.title}` }}</Button></div>
      </form>
    </div>
  </AdminLayout>
</template>
