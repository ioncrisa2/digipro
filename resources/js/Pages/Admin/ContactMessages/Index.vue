<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import AdminEntityActions from '@/components/admin/AdminEntityActions.vue';
import AdminTableToolbar from '@/components/admin/AdminTableToolbar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
  filters: { type: Object, default: () => ({ q: '', status: 'all', unread: 'all', source: 'all' }) },
  statusOptions: { type: Array, default: () => [] },
  unreadOptions: { type: Array, default: () => [] },
  sourceOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, new: 0, unread: 0, done: 0 }) },
  records: { type: Object, required: true },
});

const columns = [
  { key: 'sender', label: 'Pengirim', cellClass: 'min-w-[220px]' },
  { key: 'subject', label: 'Pesan', cellClass: 'min-w-[280px]' },
  { key: 'status', label: 'Status', cellClass: 'min-w-[140px]' },
  { key: 'source', label: 'Source', cellClass: 'min-w-[130px]', sortable: true },
  { key: 'handled', label: 'Handled By', cellClass: 'min-w-[140px]' },
  { key: 'created_at', label: 'Masuk', cellClass: 'min-w-[150px]', sortable: true },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[160px]' },
];

const form = reactive({
  q: props.filters.q ?? '',
  status: props.filters.status ?? 'all',
  unread: props.filters.unread ?? 'all',
  source: props.filters.source ?? 'all',
});

const submitFilters = () => {
  router.get(route('admin.communications.contact-messages.index'), {
    q: form.q || undefined,
    status: form.status === 'all' ? undefined : form.status,
    unread: form.unread === 'all' ? undefined : form.unread,
    source: form.source === 'all' ? undefined : form.source,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  form.q = '';
  form.status = 'all';
  form.unread = 'all';
  form.source = 'all';
  submitFilters();
};

const activeFilterCount = computed(() => {
  let count = 0;
  if (form.status !== 'all') count += 1;
  if (form.unread !== 'all') count += 1;
  if (form.source !== 'all') count += 1;
  return count;
});

const statusTone = (status) => {
  switch (status) {
    case 'new':
      return 'bg-amber-100 text-amber-900 border-amber-200';
    case 'in_progress':
      return 'bg-sky-100 text-sky-900 border-sky-200';
    case 'done':
      return 'bg-emerald-100 text-emerald-900 border-emerald-200';
    case 'archived':
      return 'bg-slate-100 text-slate-800 border-slate-200';
    default:
      return 'bg-slate-100 text-slate-800 border-slate-200';
  }
};
</script>

<template>
  <Head title="Admin - Contact Message" />

  <AdminLayout title="Contact Message">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Komunikasi</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Contact Message</h1>
          <p class="mt-2 text-sm text-slate-600">
            Inbox pesan masuk dari halaman kontak publik untuk tindak lanjut admin.
          </p>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">New</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.new }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Unread</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.unread }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Done</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.done }}</p></CardContent></Card>
      </section>

      <Card>
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Daftar Pesan</CardTitle>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari nama, email, subject, atau isi pesan"
            filter-title="Filter contact message"
            filter-description="Saring pesan berdasarkan status, unread, dan source."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; submitFilters(); }"
            @apply-filters="submitFilters"
            @reset-filters="resetFilters"
          >
            <div class="grid gap-4 sm:grid-cols-3">
              <div class="space-y-2">
                <Label for="contact_status_filter">Status</Label>
                <Select v-model="form.status">
                  <SelectTrigger id="contact_status_filter"><SelectValue placeholder="Pilih status" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="contact_unread_filter">Unread</Label>
                <Select v-model="form.unread">
                  <SelectTrigger id="contact_unread_filter"><SelectValue placeholder="Pilih unread" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in unreadOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="contact_source_filter">Source</Label>
                <Select v-model="form.source">
                  <SelectTrigger id="contact_source_filter"><SelectValue placeholder="Pilih source" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Semua Source</SelectItem>
                    <SelectItem v-for="option in sourceOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </AdminTableToolbar>
        </CardHeader>
        <CardContent>
          <AdminDataTable
            :columns="columns"
            :rows="records.data"
            :meta="records.meta"
            empty-text="Belum ada contact message yang cocok dengan filter saat ini."
          >
            <template #cell-sender="{ row }">
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <p class="font-medium text-slate-950">{{ row.name }}</p>
                  <Badge v-if="row.is_unread" variant="outline" class="border-amber-200 bg-amber-50 text-amber-800">Unread</Badge>
                </div>
                <p class="text-xs text-slate-500">{{ row.email }}</p>
              </div>
            </template>

            <template #cell-subject="{ row }">
              <div class="space-y-1">
                <p class="text-sm text-slate-900">{{ row.subject || '(Tanpa subject)' }}</p>
                <p class="line-clamp-2 text-xs leading-5 text-slate-500">{{ row.message_excerpt }}</p>
              </div>
            </template>

            <template #cell-status="{ row }">
              <Badge variant="outline" :class="statusTone(row.status)">{{ row.status_label }}</Badge>
            </template>

            <template #cell-handled="{ row }">
              <span class="text-sm text-slate-700">{{ row.handled_by_name }}</span>
            </template>

            <template #cell-created_at="{ row }">
              {{ formatDateTime(row.created_at) }}
            </template>

            <template #cell-actions="{ row }">
              <AdminEntityActions
                :detail-href="row.show_url"
                entity-label="contact message"
                :entity-name="row.subject || row.name"
              />
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
