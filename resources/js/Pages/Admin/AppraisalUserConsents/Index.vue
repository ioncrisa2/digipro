<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatDateTime } from '@/utils/reviewer';

const props = defineProps({
  filters: { type: Object, default: () => ({ q: '', code: 'all' }) },
  codeOptions: { type: Array, default: () => [] },
  records: { type: Object, required: true },
  links: { type: Array, default: () => [] },
  legacyPanelUrl: { type: String, default: '/legacy-admin' },
});

const columns = [
  { key: 'user_name', label: 'Pengguna', cellClass: 'min-w-[220px]', sortable: true },
  { key: 'document_title', label: 'Dokumen', cellClass: 'min-w-[220px]' },
  { key: 'code', label: 'Kode', cellClass: 'min-w-[130px]', sortable: true },
  { key: 'accepted_at', label: 'Disetujui', cellClass: 'min-w-[150px]', sortable: true },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[170px]' },
];

const applyFilters = (patch = {}) => {
  router.get(route('admin.content.legal.user-consents.index'), { ...props.filters, ...patch }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};
</script>

<template>
  <Head title="Admin - Audit Consent" />

  <AdminLayout title="Audit Consent">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div><p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Batch 9</p><h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Persetujuan Pengguna</h1></div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><a :href="legacyPanelUrl">Legacy</a></Button>
        </div>
      </section>

      <Card>
        <CardHeader><CardTitle>Filter</CardTitle><CardDescription>Audit persetujuan pengguna ke dokumen consent.</CardDescription></CardHeader>
        <CardContent class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
          <div class="space-y-2"><Label for="q">Cari</Label><Input id="q" :model-value="filters.q" placeholder="Nama, email, code, versi" @change="applyFilters({ q: $event.target.value })" /></div>
          <div class="space-y-2">
            <Label for="code">Kode</Label>
            <Select :model-value="filters.code" @update:model-value="applyFilters({ code: $event })">
              <SelectTrigger id="code"><SelectValue placeholder="Pilih kode" /></SelectTrigger>
              <SelectContent><SelectItem value="all">Semua Kode</SelectItem><SelectItem v-for="option in codeOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Riwayat Persetujuan</CardTitle></CardHeader>
        <CardContent>
          <AdminDataTable
            :columns="columns"
            :rows="records.data"
            :meta="records.meta"
            empty-text="Belum ada data persetujuan."
          >
            <template #cell-user_name="{ row }">
              <div class="space-y-1">
                <p class="font-medium text-slate-950">{{ row.user_name }}</p>
                <p class="text-xs text-slate-500">{{ row.user_email }}</p>
              </div>
            </template>

            <template #cell-document_title="{ row }">
              <div class="space-y-1">
                <p class="text-sm text-slate-900">{{ row.document_title }}</p>
                <p class="text-xs text-slate-500">{{ row.version }} · {{ row.ip || '-' }}</p>
              </div>
            </template>

            <template #cell-accepted_at="{ row }">
              {{ formatDateTime(row.accepted_at) }}
            </template>

            <template #cell-actions="{ row }">
              <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" as-child><Link :href="row.show_url">Detail</Link></Button>
                <Button v-if="row.legacy_url" variant="outline" size="sm" as-child><a :href="row.legacy_url">Legacy</a></Button>
              </div>
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
