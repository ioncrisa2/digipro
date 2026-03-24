<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const props = defineProps({
  mode: { type: String, required: true },
  record: { type: Object, required: true },
  indexUrl: { type: String, required: true },
  submitUrl: { type: String, required: true },
  links: { type: Array, default: () => [] },
});

const isEditMode = computed(() => props.mode === 'edit');
const form = useForm({
  code: props.record.code ?? 'appraisal_request_consent',
  version: props.record.version ?? '',
  title: props.record.title ?? '',
  status: props.record.status ?? 'draft',
  checkbox_label: props.record.checkbox_label ?? '',
  sections_json: props.record.sections_json ?? '',
  _method: isEditMode.value ? 'put' : 'post',
});

const submit = () => form.post(props.submitUrl, { preserveScroll: true });
</script>

<template>
  <Head :title="`Admin - ${isEditMode ? 'Edit' : 'Tambah'} Consent Document`" />
  <AdminLayout :title="`${isEditMode ? 'Edit' : 'Tambah'} Consent Document`">
    <div class="mx-auto max-w-5xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div><h1 class="text-3xl font-semibold tracking-tight text-slate-950">{{ isEditMode ? 'Edit Consent Document' : 'Tambah Consent Document' }}</h1></div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader><CardTitle>Informasi Dokumen</CardTitle><CardDescription>Draft consent disimpan dan dipublikasikan langsung dari workspace admin Vue.</CardDescription></CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2"><Label for="code">Kode</Label><Input id="code" v-model="form.code" /><p v-if="form.errors.code" class="text-xs text-rose-600">{{ form.errors.code }}</p></div>
            <div class="space-y-2"><Label for="version">Versi</Label><Input id="version" v-model="form.version" /><p v-if="form.errors.version" class="text-xs text-rose-600">{{ form.errors.version }}</p></div>
            <div class="space-y-2 md:col-span-2"><Label for="title">Judul</Label><Input id="title" v-model="form.title" /><p v-if="form.errors.title" class="text-xs text-rose-600">{{ form.errors.title }}</p></div>
            <div class="space-y-2">
              <Label for="status">Status</Label>
              <Select v-model="form.status">
                <SelectTrigger id="status"><SelectValue placeholder="Pilih status" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="draft">Draft</SelectItem>
                  <SelectItem value="archived">Arsip</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.status" class="text-xs text-rose-600">{{ form.errors.status }}</p>
            </div>
            <div class="space-y-2 md:col-span-2"><Label for="checkbox_label">Label Checkbox</Label><Input id="checkbox_label" v-model="form.checkbox_label" /><p v-if="form.errors.checkbox_label" class="text-xs text-rose-600">{{ form.errors.checkbox_label }}</p></div>
            <div class="space-y-2 md:col-span-2"><Label for="sections_json">Sections JSON</Label><Textarea id="sections_json" v-model="form.sections_json" rows="16" /><p class="text-xs text-slate-500">Format: array of object `{ heading, lead, items[] }`.</p><p v-if="form.errors.sections_json" class="text-xs text-rose-600">{{ form.errors.sections_json }}</p></div>
          </CardContent>
        </Card>
        <div class="flex flex-wrap justify-end gap-2"><Button type="button" variant="outline" as-child><Link :href="indexUrl">Batal</Link></Button><Button type="submit" :disabled="form.processing">{{ isEditMode ? 'Simpan Perubahan' : 'Tambah Consent' }}</Button></div>
      </form>
    </div>
  </AdminLayout>
</template>
