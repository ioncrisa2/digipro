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

const props = defineProps({
  resource: { type: Object, required: true },
  filters: { type: Object, default: () => ({ q: '', status: 'all' }) },
  statusOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, active: 0 }) },
  records: { type: Array, default: () => [] },
  createUrl: { type: String, required: true },
  links: { type: Array, default: () => [] },
  legacyPanelUrl: { type: String, default: '/legacy-admin' },
});

const applyFilters = (patch = {}) => {
  const routeNameMap = {
    faqs: 'admin.content.legal.faqs.index',
    features: 'admin.content.legal.features.index',
    testimonials: 'admin.content.legal.testimonials.index',
  };

  router.get(route(routeNameMap[props.resource.key]), { ...props.filters, ...patch }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const destroyRecord = (item) => {
  if (!window.confirm(`Hapus ${props.resource.singular ?? props.resource.title} ini?`)) return;
  router.delete(item.destroy_url, { preserveScroll: true });
};
</script>

<template>
  <Head :title="`Admin - ${resource.title}`" />

  <AdminLayout :title="resource.title">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Batch 9</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ resource.title }}</h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button as-child><Link :href="createUrl">{{ resource.create_label }}</Link></Button>
          <Button variant="outline" as-child><a :href="legacyPanelUrl">Legacy</a></Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Aktif</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.active }}</p></CardContent></Card>
      </section>

      <Card>
        <CardHeader><CardTitle>Filter</CardTitle><CardDescription>Kelola {{ resource.title.toLowerCase() }} dari admin Vue.</CardDescription></CardHeader>
        <CardContent class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
          <div class="space-y-2"><Label for="q">Cari</Label><Input id="q" :model-value="filters.q" placeholder="Cari data" @change="applyFilters({ q: $event.target.value })" /></div>
          <div class="space-y-2">
            <Label for="status">Status</Label>
            <Select :model-value="filters.status" @update:model-value="applyFilters({ status: $event })">
              <SelectTrigger id="status"><SelectValue placeholder="Pilih status" /></SelectTrigger>
              <SelectContent><SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      <AdminCardList :items="records" empty-text="Belum ada data." grid-class="grid gap-4 lg:grid-cols-2">
        <template #item="{ item }">
          <AdminEntityCard
            :title="item.title || item.question || item.name"
            :description="item.description || item.answer || item.quote"
          >
            <template #badges>
              <Badge variant="outline" :class="item.is_active ? 'bg-emerald-100 text-emerald-900 border-emerald-200' : 'bg-slate-100 text-slate-800 border-slate-200'">{{ item.is_active ? 'Aktif' : 'Nonaktif' }}</Badge>
            </template>

            <template #meta>
              <p v-if="item.icon">Icon: {{ item.icon }}</p>
              <p v-if="item.role">{{ item.role }}</p>
              <p>Urutan: {{ item.sort_order }}</p>
            </template>

            <template #footer>
              <Button variant="outline" size="sm" as-child><Link :href="item.edit_url">Edit</Link></Button>
              <Button variant="outline" size="sm" @click="destroyRecord(item)">Hapus</Button>
              <Button v-if="item.legacy_url" variant="outline" size="sm" as-child><a :href="item.legacy_url">Legacy</a></Button>
            </template>
          </AdminEntityCard>
        </template>
      </AdminCardList>
    </div>
  </AdminLayout>
</template>
