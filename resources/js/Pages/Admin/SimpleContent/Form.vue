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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const props = defineProps({
  resource: { type: Object, required: true },
  mode: { type: String, required: true },
  record: { type: Object, required: true },
  indexUrl: { type: String, required: true },
  submitUrl: { type: String, required: true },
  links: { type: Array, default: () => [] },
  iconOptions: { type: Array, default: () => [] },
});

const isEditMode = computed(() => props.mode === 'edit');
  const form = useForm({
  question: props.record.question ?? '',
  answer: props.record.answer ?? '',
  icon: props.record.icon ?? '__none',
  title: props.record.title ?? '',
  description: props.record.description ?? '',
  name: props.record.name ?? '',
  role: props.record.role ?? '',
  quote: props.record.quote ?? '',
  sort_order: props.record.sort_order ?? 0,
  is_active: Boolean(props.record.is_active ?? true),
  _method: isEditMode.value ? 'put' : 'post',
});

const submit = () => form.post(props.submitUrl, { preserveScroll: true });
</script>

<template>
  <Head :title="`Admin - ${isEditMode ? 'Edit' : 'Tambah'} ${resource.singular}`" />
  <AdminLayout :title="`${isEditMode ? 'Edit' : 'Tambah'} ${resource.singular}`">
    <div class="mx-auto max-w-4xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div><h1 class="text-3xl font-semibold tracking-tight text-slate-950">{{ isEditMode ? `Edit ${resource.singular}` : `Tambah ${resource.singular}` }}</h1></div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader><CardTitle>{{ resource.title }}</CardTitle><CardDescription>Form {{ resource.title.toLowerCase() }} di workspace admin Vue.</CardDescription></CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <template v-if="resource.key === 'faqs'">
              <div class="space-y-2 md:col-span-2"><Label for="question">Pertanyaan</Label><Input id="question" v-model="form.question" /><p v-if="form.errors.question" class="text-xs text-rose-600">{{ form.errors.question }}</p></div>
              <div class="space-y-2 md:col-span-2"><Label for="answer">Jawaban</Label><Textarea id="answer" v-model="form.answer" rows="6" /><p v-if="form.errors.answer" class="text-xs text-rose-600">{{ form.errors.answer }}</p></div>
            </template>

            <template v-else-if="resource.key === 'features'">
              <div class="space-y-2">
                <Label for="icon">Icon</Label>
                <Select v-model="form.icon">
                  <SelectTrigger id="icon"><SelectValue placeholder="Pilih icon" /></SelectTrigger>
                  <SelectContent><SelectItem value="__none">Tanpa icon</SelectItem><SelectItem v-for="option in iconOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
                </Select>
                <p v-if="form.errors.icon" class="text-xs text-rose-600">{{ form.errors.icon }}</p>
              </div>
              <div class="space-y-2 md:col-span-2"><Label for="title">Judul</Label><Input id="title" v-model="form.title" /><p v-if="form.errors.title" class="text-xs text-rose-600">{{ form.errors.title }}</p></div>
              <div class="space-y-2 md:col-span-2"><Label for="description">Deskripsi</Label><Textarea id="description" v-model="form.description" rows="5" /><p v-if="form.errors.description" class="text-xs text-rose-600">{{ form.errors.description }}</p></div>
            </template>

            <template v-else>
              <div class="space-y-2"><Label for="name">Nama</Label><Input id="name" v-model="form.name" /><p v-if="form.errors.name" class="text-xs text-rose-600">{{ form.errors.name }}</p></div>
              <div class="space-y-2"><Label for="role">Peran</Label><Input id="role" v-model="form.role" /><p v-if="form.errors.role" class="text-xs text-rose-600">{{ form.errors.role }}</p></div>
              <div class="space-y-2 md:col-span-2"><Label for="quote">Testimoni</Label><Textarea id="quote" v-model="form.quote" rows="5" /><p v-if="form.errors.quote" class="text-xs text-rose-600">{{ form.errors.quote }}</p></div>
            </template>

            <div class="space-y-2"><Label for="sort_order">Urutan</Label><Input id="sort_order" v-model="form.sort_order" type="number" min="0" /><p v-if="form.errors.sort_order" class="text-xs text-rose-600">{{ form.errors.sort_order }}</p></div>
            <label class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm text-slate-700"><Checkbox v-model="form.is_active" /><span>Aktif</span></label>
          </CardContent>
        </Card>
        <div class="flex flex-wrap justify-end gap-2"><Button type="button" variant="outline" as-child><Link :href="indexUrl">Batal</Link></Button><Button type="submit" :disabled="form.processing">{{ isEditMode ? 'Simpan Perubahan' : `Tambah ${resource.singular}` }}</Button></div>
      </form>
    </div>
  </AdminLayout>
</template>
