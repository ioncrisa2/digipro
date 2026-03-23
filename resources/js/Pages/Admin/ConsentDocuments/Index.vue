<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminCardList from '@/components/admin/AdminCardList.vue';
import AdminEntityCard from '@/components/admin/AdminEntityCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatDateTime } from '@/utils/reviewer';

const props = defineProps({
  filters: { type: Object, default: () => ({ q: '', status: 'all' }) },
  statusOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, draft: 0, published: 0 }) },
  records: { type: Array, default: () => [] },
  createUrl: { type: String, required: true },
  links: { type: Array, default: () => [] },
  legacyPanelUrl: { type: String, default: '/legacy-admin' },
});

const applyFilters = (patch = {}) => {
  router.get(route('admin.content.legal.consent.index'), { ...props.filters, ...patch }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const destroyRecord = (item) => {
  if (!window.confirm(`Hapus dokumen consent "${item.title}"?`)) return;
  router.delete(item.destroy_url, { preserveScroll: true });
};

const publishRecord = (item) => {
  if (!window.confirm(`Publish dokumen consent "${item.title}"?`)) return;
  router.post(item.publish_url, {}, { preserveScroll: true });
};
</script>

<template>
  <Head title="Admin - Consent Document" />

  <AdminLayout title="Consent Document">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div><p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Batch 9</p><h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Consent Document</h1></div>
        <div class="flex flex-wrap gap-2">
          <Button as-child><Link :href="createUrl">Tambah Consent</Link></Button>
          <Button variant="outline" as-child><a :href="legacyPanelUrl">Legacy</a></Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-3">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Draft</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.draft }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Published</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.published }}</p></CardContent></Card>
      </section>

      <Card>
        <CardHeader><CardTitle>Filter</CardTitle><CardDescription>Kelola dokumen consent dan workflow publish.</CardDescription></CardHeader>
        <CardContent class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
          <div class="space-y-2"><Label for="q">Cari</Label><Input id="q" :model-value="filters.q" placeholder="Judul, code, versi" @change="applyFilters({ q: $event.target.value })" /></div>
          <div class="space-y-2">
            <Label for="status">Status</Label>
            <Select :model-value="filters.status" @update:model-value="applyFilters({ status: $event })">
              <SelectTrigger id="status"><SelectValue placeholder="Pilih status" /></SelectTrigger>
              <SelectContent><SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      <AdminCardList :items="records" empty-text="Belum ada dokumen consent." grid-class="grid gap-4 lg:grid-cols-2">
        <template #item="{ item }">
          <AdminEntityCard :title="item.title" :subtitle="`${item.code} · ${item.version}`">
            <template #badges>
              <Badge variant="outline">{{ item.status }}</Badge>
            </template>

            <template #meta>
              <p>Sections: {{ item.sections_count }}</p>
              <p>Publish: {{ formatDateTime(item.published_at) }}</p>
              <p>Hash: {{ item.hash || '-' }}</p>
            </template>

            <template #footer>
              <Button v-if="item.can_edit" variant="outline" size="sm" as-child><Link :href="item.edit_url">Edit</Link></Button>
              <Button v-if="item.can_publish" variant="outline" size="sm" @click="publishRecord(item)">Publish</Button>
              <Button v-if="item.can_delete" variant="outline" size="sm" @click="destroyRecord(item)">Hapus</Button>
              <Button v-if="item.legacy_url" variant="outline" size="sm" as-child><a :href="item.legacy_url">Legacy</a></Button>
            </template>
          </AdminEntityCard>
        </template>
      </AdminCardList>
    </div>
  </AdminLayout>
</template>
