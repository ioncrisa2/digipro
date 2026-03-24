<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import AdminEntityActions from '@/components/admin/AdminEntityActions.vue';
import AdminTableToolbar from '@/components/admin/AdminTableToolbar.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatDateTime } from '@/utils/reviewer';

const props = defineProps({
  filters: { type: Object, default: () => ({ q: '', code: 'all' }) },
  codeOptions: { type: Array, default: () => [] },
  records: { type: Object, required: true },
  links: { type: Array, default: () => [] },
});

const form = reactive({
  q: props.filters.q ?? '',
  code: props.filters.code ?? 'all',
});

const columns = [
  { key: 'user_name', label: 'Pengguna', cellClass: 'min-w-[220px]', sortable: true },
  { key: 'document_title', label: 'Dokumen', cellClass: 'min-w-[220px]' },
  { key: 'code', label: 'Kode', cellClass: 'min-w-[130px]', sortable: true },
  { key: 'accepted_at', label: 'Disetujui', cellClass: 'min-w-[150px]', sortable: true },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[170px]' },
];

const submitFilters = () => {
  router.get(route('admin.content.legal.user-consents.index'), {
    q: form.q || undefined,
    code: form.code === 'all' ? undefined : form.code,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  form.q = '';
  form.code = 'all';
  submitFilters();
};

const activeFilterCount = computed(() => (form.code !== 'all' ? 1 : 0));
</script>

<template>
  <Head title="Admin - Audit Consent" />

  <AdminLayout title="Audit Consent">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Batch 9</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Persetujuan Pengguna</h1>
        </div>
      </section>

      <Card>
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Riwayat Persetujuan</CardTitle>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari nama, email, kode, atau versi"
            filter-title="Filter audit consent"
            filter-description="Saring riwayat persetujuan berdasarkan kode dokumen consent."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; submitFilters(); }"
            @apply-filters="submitFilters"
            @reset-filters="resetFilters"
          >
            <div class="space-y-2">
              <Label for="consent_code_filter">Kode</Label>
              <Select v-model="form.code">
                <SelectTrigger id="consent_code_filter"><SelectValue placeholder="Pilih kode" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Semua Kode</SelectItem>
                  <SelectItem v-for="option in codeOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </AdminTableToolbar>
        </CardHeader>
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
              <AdminEntityActions
                :detail-href="row.show_url"
                entity-label="audit consent"
                :entity-name="row.document_title"
              />
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
