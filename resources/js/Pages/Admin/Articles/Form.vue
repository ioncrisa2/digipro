<script setup>
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import AdminRichTextEditor from '@/components/admin/AdminRichTextEditor.vue';
import ImageUpload from '@/components/admin/ImageUpload.vue';
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
  mode: { type: String, required: true },
  record: { type: Object, required: true },
  categoryOptions: { type: Array, default: () => [] },
  tagOptions: { type: Array, default: () => [] },
  imageUploadUrl: { type: String, default: '' },
  submitUrl: { type: String, required: true },
  indexUrl: { type: String, required: true },
});

const isEditMode = computed(() => props.mode === 'edit');

const form = useForm({
  title: props.record.title ?? '',
  slug: props.record.slug ?? '',
  excerpt: props.record.excerpt ?? '',
  content_html: props.record.content_html ?? '',
  cover_image: null,
  meta_title: props.record.meta_title ?? '',
  meta_description: props.record.meta_description ?? '',
  category_id: props.record.category_id ?? '__none',
  tag_ids: Array.isArray(props.record.tag_ids) ? [...props.record.tag_ids] : [],
  is_published: Boolean(props.record.is_published ?? false),
  published_at: props.record.published_at ?? '',
  _method: isEditMode.value ? 'put' : 'post',
});

const slugify = (value) => String(value ?? '')
  .toLowerCase()
  .trim()
  .replace(/[^a-z0-9]+/g, '-')
  .replace(/^-+|-+$/g, '');

watch(() => form.title, (value) => {
  if (!isEditMode.value || !form.slug) {
    form.slug = slugify(value);
  }
});

const toggleTag = (tagId) => {
  const value = String(tagId);
  if (form.tag_ids.includes(value)) {
    form.tag_ids = form.tag_ids.filter((item) => item !== value);
    return;
  }

  form.tag_ids = [...form.tag_ids, value];
};

const submit = () => {
  form.post(props.submitUrl, {
    forceFormData: true,
    preserveScroll: true,
  });
};
</script>

<template>
  <Head :title="isEditMode ? 'Admin - Edit Artikel' : 'Admin - Tulis Artikel'" />

  <AdminLayout :title="isEditMode ? 'Edit Artikel' : 'Tulis Artikel'">
    <div class="mx-auto max-w-6xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ isEditMode ? 'Edit Artikel' : 'Tulis Artikel' }}</h1>
          <p class="mt-2 text-sm text-slate-600">
            Form editorial artikel dengan editor rich text untuk penulisan dan penyuntingan konten publik.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button type="button" variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
          <Button v-if="record.preview_url" type="button" variant="outline" as-child><a :href="record.preview_url" target="_blank" rel="noreferrer">Preview</a></Button>
        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader>
            <CardTitle>Artikel</CardTitle>
            <CardDescription>Field inti artikel dan relasinya.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2 md:col-span-2">
              <Label for="title">Judul</Label>
              <Input id="title" v-model="form.title" placeholder="Judul artikel" />
              <p v-if="form.errors.title" class="text-xs text-rose-600">{{ form.errors.title }}</p>
            </div>

            <div class="space-y-2 md:col-span-2">
              <Label for="slug">Slug</Label>
              <Input id="slug" v-model="form.slug" placeholder="judul-artikel" readonly class="bg-slate-50 text-slate-500" />
              <p class="text-xs text-slate-500">Slug dibuat otomatis dari judul artikel.</p>
              <p v-if="form.errors.slug" class="text-xs text-rose-600">{{ form.errors.slug }}</p>
            </div>

            <div class="space-y-2 md:col-span-2">
              <Label for="cover_image">Cover</Label>
              <ImageUpload
                v-model="form.cover_image"
                :multiple="false"
                :existing="record.cover_url ? [{ url: record.cover_url, name: 'Cover saat ini' }] : []"
                title="Upload cover artikel"
                description="Pilih satu gambar utama untuk artikel. Preview akan tampil langsung di area ini."
              />
              <p v-if="form.errors.cover_image" class="text-xs text-rose-600">{{ form.errors.cover_image }}</p>
            </div>

            <div class="space-y-2 md:col-span-2">
              <Label for="excerpt">Ringkasan</Label>
              <Textarea id="excerpt" v-model="form.excerpt" rows="4" placeholder="Ringkasan singkat artikel" />
              <p v-if="form.errors.excerpt" class="text-xs text-rose-600">{{ form.errors.excerpt }}</p>
            </div>

            <div class="space-y-2">
              <Label for="category_id">Kategori</Label>
                <Select v-model="form.category_id">
                  <SelectTrigger id="category_id"><SelectValue placeholder="Pilih kategori" /></SelectTrigger>
                  <SelectContent>
                  <SelectItem value="__none">Tanpa kategori</SelectItem>
                  <SelectItem v-for="option in categoryOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                  </SelectContent>
                </Select>
              <p v-if="form.errors.category_id" class="text-xs text-rose-600">{{ form.errors.category_id }}</p>
            </div>

            <div class="space-y-2">
              <Label for="published_at">Tanggal Publikasi</Label>
              <Input id="published_at" v-model="form.published_at" type="datetime-local" />
              <p v-if="form.errors.published_at" class="text-xs text-rose-600">{{ form.errors.published_at }}</p>
            </div>

            <div class="space-y-3 md:col-span-2">
              <Label>Tag</Label>
              <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <label v-for="tag in tagOptions" :key="tag.value" class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm text-slate-700">
                  <Checkbox :model-value="form.tag_ids.includes(tag.value)" @update:model-value="toggleTag(tag.value)" />
                  <span>{{ tag.label }}</span>
                </label>
              </div>
              <p v-if="form.errors.tag_ids" class="text-xs text-rose-600">{{ form.errors.tag_ids }}</p>
            </div>

            <div class="space-y-3 md:col-span-2">
              <Label>Publikasi</Label>
              <label class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm text-slate-700">
                <Checkbox v-model="form.is_published" />
                <span>Tampilkan artikel ke publik</span>
              </label>
            </div>

            <div class="space-y-2 md:col-span-2">
              <AdminRichTextEditor
                id="content_html"
                v-model="form.content_html"
                label="Konten"
                placeholder="Tulis isi artikel di sini..."
                help="Gunakan heading, list, kutipan, dan link untuk menyusun artikel yang mudah dibaca."
                :image-upload-url="imageUploadUrl"
                :error="form.errors.content_html"
              />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>SEO</CardTitle></CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="meta_title">Meta Title</Label>
              <Input id="meta_title" v-model="form.meta_title" placeholder="Meta title" />
              <p v-if="form.errors.meta_title" class="text-xs text-rose-600">{{ form.errors.meta_title }}</p>
            </div>
            <div class="space-y-2">
              <Label for="meta_description">Meta Description</Label>
              <Textarea id="meta_description" v-model="form.meta_description" rows="4" placeholder="Meta description" />
              <p v-if="form.errors.meta_description" class="text-xs text-rose-600">{{ form.errors.meta_description }}</p>
            </div>
          </CardContent>
        </Card>

        <div class="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" as-child><Link :href="indexUrl">Batal</Link></Button>
          <Button type="submit" :disabled="form.processing">{{ isEditMode ? 'Simpan Perubahan' : 'Publikasikan Draft' }}</Button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
