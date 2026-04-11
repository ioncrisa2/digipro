<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import AdminTableToolbar from '@/components/admin/AdminTableToolbar.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/utils/reviewer';
import { ArchiveRestore, Download, ShieldAlert, Upload } from 'lucide-vue-next';

const props = defineProps({
  filters: {
    type: Object,
    required: true,
  },
  summary: {
    type: Object,
    required: true,
  },
  records: {
    type: Object,
    required: true,
  },
  index_url: {
    type: String,
    required: true,
  },
  restore: {
    type: Object,
    required: true,
  },
});

const page = usePage();
const restoreDialogOpen = ref(false);

const filterForm = reactive({
  q: props.filters.q ?? '',
});

const restoreForm = useForm({
  backup_zip: null,
});

const restoreSummary = computed(() => page.props.flash?.backup_restore_summary ?? null);
const selectedFileName = computed(() => restoreForm.backup_zip?.name ?? '');

const summaryCards = [
  { key: 'total_requests', label: 'Request Tersimpan' },
  { key: 'client_request_uploads', label: 'Upload Client' },
  { key: 'asset_files', label: 'Dokumen & Foto Aset' },
  { key: 'revision_items', label: 'Item Revisi' },
];

const columns = [
  { key: 'request', label: 'Request', cellClass: 'min-w-[180px]' },
  { key: 'client', label: 'Client / Requester', cellClass: 'min-w-[220px]' },
  { key: 'status', label: 'Status', cellClass: 'min-w-[140px]' },
  { key: 'coverage', label: 'Cakupan Backup', cellClass: 'min-w-[220px]' },
  { key: 'updated_at', label: 'Diubah', cellClass: 'min-w-[140px]' },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[180px]' },
];

const applyFilters = () => {
  router.get(
    props.index_url,
    {
      q: filterForm.q || undefined,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    },
  );
};

const resetFilters = () => {
  filterForm.q = '';
  applyFilters();
};

const openRestoreConfirm = () => {
  if (!restoreForm.backup_zip) {
    restoreForm.setError('backup_zip', 'Pilih file ZIP backup terlebih dahulu.');
    return;
  }

  restoreDialogOpen.value = true;
};

const submitRestore = () => {
  restoreDialogOpen.value = false;

  restoreForm.post(props.restore.url, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      restoreForm.reset();
      restoreForm.clearErrors();
    },
  });
};

const statusTone = (value) => {
  switch (value) {
    case 'submitted':
      return 'bg-sky-100 text-sky-900 border-sky-200';
    case 'docs_incomplete':
      return 'bg-rose-100 text-rose-900 border-rose-200';
    case 'verified':
    case 'completed':
      return 'bg-emerald-100 text-emerald-900 border-emerald-200';
    case 'waiting_offer':
    case 'waiting_signature':
    case 'contract_signed':
      return 'bg-amber-100 text-amber-900 border-amber-200';
    case 'offer_sent':
      return 'bg-indigo-100 text-indigo-900 border-indigo-200';
    default:
      return 'bg-slate-100 text-slate-800 border-slate-200';
  }
};
</script>

<template>
  <Head title="Admin - Backup" />

  <AdminLayout title="Backup">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-3xl">
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Recovery Workspace</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Backup Request Appraisal</h1>
          <p class="mt-2 text-sm text-slate-600">
            Backup dibuat per request, on-demand, langsung diunduh, dan tidak disimpan sebagai arsip permanen di server.
            Restore hanya menerima ZIP hasil sistem sendiri dan hanya boleh dijalankan saat request aslinya sudah hilang.
          </p>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card v-for="card in summaryCards" :key="card.key">
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">{{ card.label }}</p>
            <p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary[card.key] ?? 0 }}</p>
          </CardContent>
        </Card>
      </section>

      <Card class="border-amber-200/80 bg-amber-50/70">
        <CardHeader class="gap-3">
          <div class="flex items-start gap-3">
            <div class="rounded-2xl border border-amber-200 bg-white p-2 text-amber-700">
              <ArchiveRestore class="h-5 w-5" />
            </div>
            <div class="space-y-1">
              <CardTitle>Restore Backup</CardTitle>
              <CardDescription class="text-amber-900/80">
                Restore membuat ulang request appraisal beserta aset, file, histori revisi, payment metadata, negotiation metadata,
                dan field change log ke ID baru. Tidak ada merge atau overwrite.
              </CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent class="space-y-4">
          <Alert class="border-amber-300 bg-white/90 text-amber-950">
            <ShieldAlert class="h-4 w-4" />
            <AlertTitle>Aksi berisiko tinggi</AlertTitle>
            <AlertDescription>
              Restore ditolak total jika `request_number` dari backup masih ada di database. File hasil restore akan ditulis ulang ke path canonical baru.
            </AlertDescription>
          </Alert>

          <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
            <div class="space-y-2">
              <Label for="backup_restore_zip">Upload ZIP Backup</Label>
              <Input
                id="backup_restore_zip"
                type="file"
                :accept="restore.accept"
                @change="restoreForm.backup_zip = $event.target.files?.[0] ?? null"
              />
              <p class="text-xs text-slate-600">
                Maksimal {{ restore.max_upload_mb }} MB. Hanya ZIP dengan `backup_type = appraisal_request_v1` dan `schema_version = 1`.
              </p>
              <p v-if="selectedFileName" class="text-xs font-medium text-slate-700">File terpilih: {{ selectedFileName }}</p>
              <p v-if="restoreForm.errors.backup_zip" class="text-xs text-rose-600">{{ restoreForm.errors.backup_zip }}</p>
            </div>

            <Button type="button" class="gap-2" :disabled="restoreForm.processing" @click="openRestoreConfirm">
              <Upload class="h-4 w-4" />
              {{ restoreForm.processing ? 'Memproses Restore...' : 'Restore dari ZIP' }}
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card v-if="restoreSummary" class="border-emerald-200/80 bg-emerald-50/70">
        <CardHeader>
          <CardTitle>Restore Berhasil</CardTitle>
          <CardDescription>
            Request {{ restoreSummary.request_number }} berhasil dibuat ulang sebagai data appraisal baru.
          </CardDescription>
        </CardHeader>
        <CardContent class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
          <div class="rounded-2xl border border-emerald-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-emerald-700">Request</p>
            <p class="mt-2 text-lg font-semibold text-slate-950">{{ restoreSummary.request_number }}</p>
          </div>
          <div class="rounded-2xl border border-emerald-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-emerald-700">Aset</p>
            <p class="mt-2 text-lg font-semibold text-slate-950">{{ restoreSummary.assets_count }}</p>
          </div>
          <div class="rounded-2xl border border-emerald-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-emerald-700">File Request</p>
            <p class="mt-2 text-lg font-semibold text-slate-950">{{ restoreSummary.request_files_count }}</p>
          </div>
          <div class="rounded-2xl border border-emerald-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-emerald-700">File Aset</p>
            <p class="mt-2 text-lg font-semibold text-slate-950">{{ restoreSummary.asset_files_count }}</p>
          </div>
          <div class="rounded-2xl border border-emerald-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-emerald-700">Item Revisi</p>
            <p class="mt-2 text-lg font-semibold text-slate-950">{{ restoreSummary.revision_items_count }}</p>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Daftar Request untuk Backup</CardTitle>
            <CardDescription>
              Cari request appraisal lalu unduh ZIP snapshot untuk recovery lokal per request.
            </CardDescription>
          </div>
          <AdminTableToolbar
            :search-value="filterForm.q"
            search-placeholder="Cari nomor request, client, atau requester"
            :has-filter="false"
            @search="(value) => { filterForm.q = value; applyFilters(); }"
            @reset-filters="resetFilters"
          />
        </CardHeader>
        <CardContent>
          <AdminDataTable
            :columns="columns"
            :rows="records.data"
            :meta="records.meta"
            empty-text="Belum ada request yang bisa dibackup."
          >
            <template #cell-request="{ row }">
              <div class="space-y-1">
                <Button variant="link" class="h-auto px-0 font-medium" as-child>
                  <Link :href="row.detail_url">{{ row.request_number }}</Link>
                </Button>
                <p class="text-xs text-slate-500">{{ formatDateTime(row.requested_at) }}</p>
              </div>
            </template>

            <template #cell-client="{ row }">
              <div class="space-y-1">
                <p class="font-medium text-slate-950">{{ row.client_name }}</p>
                <p class="text-xs text-slate-500">{{ row.requester_name }}</p>
              </div>
            </template>

            <template #cell-status="{ row }">
              <Badge variant="outline" :class="statusTone(row.status_value)">{{ row.status_label }}</Badge>
            </template>

            <template #cell-coverage="{ row }">
              <div class="space-y-1 text-xs text-slate-600">
                <p>Aset: <span class="font-medium text-slate-900">{{ row.assets_count }}</span></p>
                <p>File request: <span class="font-medium text-slate-900">{{ row.request_files_count }}</span></p>
                <p>File aset: <span class="font-medium text-slate-900">{{ row.asset_files_count }}</span></p>
                <p>Batch revisi: <span class="font-medium text-slate-900">{{ row.revision_batches_count }}</span></p>
              </div>
            </template>

            <template #cell-updated_at="{ row }">
              {{ formatDateTime(row.updated_at) }}
            </template>

            <template #cell-actions="{ row }">
              <div class="flex flex-wrap gap-2">
                <Button size="sm" class="gap-2" as-child>
                  <a :href="row.download_url">
                    <Download class="h-4 w-4" />
                    Download Backup
                  </a>
                </Button>
                <Button size="sm" variant="outline" as-child>
                  <Link :href="row.detail_url">Lihat Request</Link>
                </Button>
              </div>
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>

    <AlertDialog :open="restoreDialogOpen" @update:open="restoreDialogOpen = $event">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Konfirmasi Restore Backup</AlertDialogTitle>
          <AlertDialogDescription class="text-left">
            Backup akan di-restore hanya jika request dengan `request_number` yang sama tidak lagi ada di database.
            Sistem tidak akan merge, overwrite, atau memakai path file lama.
            Pastikan file yang diunggah adalah ZIP resmi hasil download dari workspace ini.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel :disabled="restoreForm.processing">Batal</AlertDialogCancel>
          <AlertDialogAction :disabled="restoreForm.processing" @click="submitRestore">
            Lanjut Restore
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </AdminLayout>
</template>
