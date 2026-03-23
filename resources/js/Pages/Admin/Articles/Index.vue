<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Pencil, Trash2 } from 'lucide-vue-next';
import AdminCardList from '@/components/admin/AdminCardList.vue';
import AdminEntityCard from '@/components/admin/AdminEntityCard.vue';
import { useAdminConfirmDialog } from '@/composables/useAdminConfirmDialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatDateTime } from '@/utils/reviewer';

const props = defineProps({
  filters: { type: Object, default: () => ({ q: '', status: 'all', category: 'all' }) },
  statusOptions: { type: Array, default: () => [] },
  categoryOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, published: 0, draft: 0, categories: 0 }) },
  records: { type: Object, required: true },
  createUrl: { type: String, required: true },
});

const { confirmDelete } = useAdminConfirmDialog();

const applyFilters = (patch = {}) => {
  router.get(route('admin.content.articles.index'), {
    ...props.filters,
    ...patch,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const destroyRecord = async (item) => {
  const confirmed = await confirmDelete({
    entityLabel: 'artikel',
    entityName: item.title,
  });

  if (!confirmed) {
    return;
  }

  router.delete(item.destroy_url, {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Admin - Artikel" />

  <AdminLayout title="Artikel">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Batch 9</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">CMS Artikel</h1>
          <p class="mt-2 text-sm text-slate-600">
            Workspace admin Vue untuk operasional artikel, kategori, dan tag dalam satu alur editorial.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button as-child><Link :href="createUrl">Tulis Artikel</Link></Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Artikel</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Published</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.published }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Draft</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.draft }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Kategori</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.categories }}</p></CardContent></Card>
      </section>

      <Card>
        <CardHeader>
          <CardTitle>Filter Artikel</CardTitle>
          <CardDescription>Filter dasar untuk editorial admin.</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr_0.8fr]">
          <div class="space-y-2">
            <Label for="article_q">Cari</Label>
            <Input id="article_q" :model-value="filters.q" placeholder="Judul, slug, atau ringkasan" @change="applyFilters({ q: $event.target.value })" />
          </div>
          <div class="space-y-2">
            <Label for="article_status">Status</Label>
            <Select :model-value="filters.status" @update:model-value="applyFilters({ status: $event })">
              <SelectTrigger id="article_status"><SelectValue placeholder="Pilih status" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="space-y-2">
            <Label for="article_category">Kategori</Label>
            <Select :model-value="filters.category" @update:model-value="applyFilters({ category: $event })">
              <SelectTrigger id="article_category"><SelectValue placeholder="Pilih kategori" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Semua Kategori</SelectItem>
                <SelectItem v-for="option in categoryOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      <AdminCardList
        :items="records.data"
        :meta="records.meta"
        empty-text="Tidak ada artikel yang cocok dengan filter saat ini."
      >
        <template #item="{ item }">
          <AdminEntityCard :title="item.title" :subtitle="item.slug" :description="item.excerpt || 'Belum ada ringkasan.'">
            <template #media>
              <div v-if="item.cover_url" class="aspect-[16/9] w-full bg-slate-100">
                <img :src="item.cover_url" :alt="item.title" class="h-full w-full object-cover" />
              </div>
            </template>

            <template #badges>
              <Badge variant="outline" :class="item.is_published ? 'bg-emerald-100 text-emerald-900 border-emerald-200' : 'bg-slate-100 text-slate-800 border-slate-200'">
                {{ item.is_published ? 'Published' : 'Draft' }}
              </Badge>
            </template>

            <template #meta>
              <p>Kategori: {{ item.category_name || '-' }}</p>
              <p>Views: {{ item.views }}</p>
              <p>Publikasi: {{ formatDateTime(item.published_at) }}</p>
            </template>

            <template #extra>
              <div class="flex flex-wrap gap-2">
                <Badge v-for="tagName in item.tag_names" :key="`${item.id}-${tagName}`" variant="secondary">{{ tagName }}</Badge>
                <span v-if="!item.tag_names.length" class="text-xs text-slate-400">Tanpa tag</span>
              </div>
            </template>

            <template #footer>
              <Button variant="outline" size="sm" as-child>
                <Link :href="item.edit_url"><Pencil class="h-4 w-4" />Edit</Link>
              </Button>
              <Button variant="outline" size="sm" as-child>
                <a :href="item.preview_url" target="_blank" rel="noreferrer"><Eye class="h-4 w-4" />Preview</a>
              </Button>
              <Button variant="destructive" size="sm" @click="destroyRecord(item)"><Trash2 class="h-4 w-4" />Hapus</Button>
            </template>
          </AdminEntityCard>
        </template>
      </AdminCardList>
    </div>
  </AdminLayout>
</template>
