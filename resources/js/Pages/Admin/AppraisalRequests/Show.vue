<script setup>
import { computed, defineAsyncComponent, reactive, ref, toRefs } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Download, ExternalLink, Eye, FileText, Search, X } from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { formatArea, formatCurrency, formatDateTime, formatNumber } from '@/utils/reviewer';

const ReviewerFilePreview = defineAsyncComponent(() => import('@/components/reviewer/ReviewerFilePreview.vue'));

const props = defineProps({
  record: {
    type: Object,
    required: true,
  },
  requester: {
    type: Object,
    required: true,
  },
  availableActions: {
    type: Array,
    default: () => [],
  },
  requestFiles: {
    type: Array,
    default: () => [],
  },
  assets: {
    type: Array,
    default: () => [],
  },
  payments: {
    type: Array,
    default: () => [],
  },
  negotiations: {
    type: Array,
    default: () => [],
  },
  negotiationActionOptions: {
    type: Array,
    default: () => [],
  },
  negotiationSummary: {
    type: Object,
    default: () => ({
      total: 0,
      counter_requests: 0,
      offers_sent: 0,
      accepted: 0,
      cancelled: 0,
    }),
  },
  offerAction: {
    type: Object,
    default: null,
  },
  approveLatestNegotiationAction: {
    type: Object,
    default: null,
  },
  paymentVerification: {
    type: Object,
    default: null,
  },
  revisionWorkspace: {
    type: Object,
    default: () => ({
      state: {
        can_create: false,
        message: null,
      },
      create_url: null,
      target_options: [],
      batches: [],
    }),
  },
});

const {
  record,
  requester,
  availableActions,
  requestFiles,
  assets,
  payments,
  negotiations,
  negotiationActionOptions,
  negotiationSummary,
  offerAction,
  approveLatestNegotiationAction,
  paymentVerification,
  revisionWorkspace,
} = toRefs(props);

const activeTab = ref('ringkasan');

const offerForm = useForm({
  fee_total: offerAction.value?.defaults?.fee_total ?? '',
  contract_sequence: offerAction.value?.defaults?.contract_sequence ?? '',
  offer_validity_days: offerAction.value?.defaults?.offer_validity_days ?? '',
});

const negotiationFilters = reactive({
  action: 'all',
  q: '',
});
const revisionDialogOpen = ref(false);
const revisionReviewDialogOpen = ref(false);
const revisionFilePreviewOpen = ref(false);
const confirmDialogOpen = ref(false);
const selectedRevisionTarget = ref(null);
const selectedReviewItem = ref(null);
const selectedPreviewFile = ref(null);
const selectedPreviewLabel = ref('');
let confirmDialogAction = null;
const confirmDialogState = reactive({
  title: 'Konfirmasi Aksi',
  description: '',
  confirmLabel: 'Lanjutkan',
  cancelLabel: 'Batal',
  confirmVariant: 'default',
});
const revisionForm = useForm({
  admin_note: '',
  items: [
    {
      target_key: '',
      issue_note: '',
    },
  ],
});
const revisionReviewForm = useForm({
  review_note: '',
});

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

const revisionStatusTone = (value) => {
  switch (value) {
    case 'open':
      return 'bg-amber-100 text-amber-900 border-amber-200';
    case 'submitted':
      return 'bg-sky-100 text-sky-900 border-sky-200';
    case 'reviewed':
      return 'bg-emerald-100 text-emerald-900 border-emerald-200';
    case 'cancelled':
      return 'bg-slate-100 text-slate-800 border-slate-200';
    default:
      return 'bg-slate-100 text-slate-800 border-slate-200';
  }
};

const revisionItemTone = (value) => {
  switch (value) {
    case 'pending':
      return 'bg-amber-100 text-amber-900 border-amber-200';
    case 'reuploaded':
      return 'bg-sky-100 text-sky-900 border-sky-200';
    case 'approved':
      return 'bg-emerald-100 text-emerald-900 border-emerald-200';
    case 'rejected':
      return 'bg-rose-100 text-rose-900 border-rose-200';
    default:
      return 'bg-slate-100 text-slate-800 border-slate-200';
  }
};

const negotiationToneClass = (tone) => {
  switch (tone) {
    case 'warning':
      return 'bg-amber-100 text-amber-900 border-amber-200';
    case 'success':
      return 'bg-emerald-100 text-emerald-900 border-emerald-200';
    case 'danger':
      return 'bg-rose-100 text-rose-900 border-rose-200';
    case 'info':
      return 'bg-sky-100 text-sky-900 border-sky-200';
    default:
      return 'bg-slate-100 text-slate-800 border-slate-200';
  }
};

const paymentStatusLabel = (status) => {
  switch (status) {
    case 'paid':
      return 'Dibayar';
    case 'pending':
      return 'Menunggu';
    case 'failed':
      return 'Gagal';
    case 'expired':
      return 'Kedaluwarsa';
    case 'rejected':
      return 'Ditolak';
    case 'refunded':
      return 'Refund';
    default:
      return status || '-';
  }
};

const isImageFile = (file) => String(file?.mime || '').startsWith('image/');

const openRevisionFilePreview = (file, label) => {
  if (!file?.url) {
    return;
  }

  selectedPreviewFile.value = file;
  selectedPreviewLabel.value = label || file.original_name || 'Preview File';
  revisionFilePreviewOpen.value = true;
};

const closeRevisionFilePreview = () => {
  revisionFilePreviewOpen.value = false;
  selectedPreviewFile.value = null;
  selectedPreviewLabel.value = '';
};

const openConfirmDialog = ({
  title = 'Konfirmasi Aksi',
  description = '',
  confirmLabel = 'Lanjutkan',
  cancelLabel = 'Batal',
  confirmVariant = 'default',
  onConfirm,
}) => {
  confirmDialogState.title = title;
  confirmDialogState.description = description;
  confirmDialogState.confirmLabel = confirmLabel;
  confirmDialogState.cancelLabel = cancelLabel;
  confirmDialogState.confirmVariant = confirmVariant;
  confirmDialogAction = typeof onConfirm === 'function' ? onConfirm : null;
  confirmDialogOpen.value = true;
};

const closeConfirmDialog = () => {
  confirmDialogOpen.value = false;
  confirmDialogAction = null;
};

const submitConfirmDialog = () => {
  const callback = confirmDialogAction;
  closeConfirmDialog();
  callback?.();
};

const runAction = (action) => {
  if (action.disabled) {
    return;
  }
  openConfirmDialog({
    title: action.label,
    description: action.message,
    confirmLabel: action.label,
    confirmVariant: action.variant === 'destructive' ? 'destructive' : 'default',
    onConfirm: () => {
      router.post(action.url, {}, {
        preserveScroll: true,
      });
    },
  });
};

const offerContractNumberPreview = computed(() => {
  const raw = String(offerForm.contract_sequence ?? '').replace(/\D+/g, '');
  if (!raw) return '-';

  const now = new Date();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const year = String(now.getFullYear());

  return `${raw.padStart(5, '0')}/AGR/DP/${month}/${year}`;
});

const submitOffer = () => {
  if (!offerAction.value?.url) {
    return;
  }

  offerForm.post(offerAction.value.url, {
    preserveScroll: true,
  });
};

const approveLatestNegotiation = () => {
  if (!approveLatestNegotiationAction.value?.url) {
    return;
  }
  openConfirmDialog({
    title: approveLatestNegotiationAction.value.label || 'Setujui Negosiasi',
    description: approveLatestNegotiationAction.value.message,
    confirmLabel: approveLatestNegotiationAction.value.label || 'Setujui',
    onConfirm: () => {
      router.post(approveLatestNegotiationAction.value.url, {}, {
        preserveScroll: true,
      });
    },
  });
};

const verifyPayment = () => {
  if (!paymentVerification.value?.action_url) {
    return;
  }
  openConfirmDialog({
    title: 'Verifikasi Pembayaran',
    description: 'Pembayaran sudah valid. Lanjutkan request ini ke proses valuasi?',
    confirmLabel: 'Verifikasi Pembayaran',
    onConfirm: () => {
      router.post(paymentVerification.value.action_url, {}, {
        preserveScroll: true,
      });
    },
  });
};

const contractFiles = computed(() => (requestFiles.value ?? []).filter((file) => file.type === 'contract_signed_pdf'));
const revisionState = computed(() => revisionWorkspace.value?.state ?? {
  can_create: false,
  message: null,
});
const revisionTargetOptions = computed(() => revisionWorkspace.value?.target_options ?? []);
const revisionBatches = computed(() => revisionWorkspace.value?.batches ?? []);
const openRevisionBatch = computed(() => revisionBatches.value.find((batch) => batch.status === 'open') ?? null);
const openRevisionItemMap = computed(() => Object.fromEntries(
  (openRevisionBatch.value?.items ?? []).map((item) => [item.target_key, item]),
));
const requestRevisionOptions = computed(() => revisionTargetOptions.value.filter((option) => option.item_type === 'request_file'));
const requestExistingRevisionOptions = computed(() => Object.fromEntries(
  requestRevisionOptions.value
    .filter((option) => option.kind === 'existing' && option.original_request_file_id)
    .map((option) => [option.original_request_file_id, option]),
));
const assetDocumentRevisionOptions = computed(() => revisionTargetOptions.value.filter((option) => option.item_type === 'asset_document'));
const assetPhotoRevisionOptions = computed(() => revisionTargetOptions.value.filter((option) => option.item_type === 'asset_photo'));
const showContractTab = computed(() => {
  return record.value?.contract_status_value === 'signed'
    || contractFiles.value.length > 0
    || Boolean(record.value?.contract_number && record.value.contract_number !== '-');
});

const filteredNegotiations = computed(() => {
  const query = String(negotiationFilters.q || '').trim().toLowerCase();

  return (negotiations.value ?? []).filter((entry) => {
    if (negotiationFilters.action !== 'all' && entry.action_value !== negotiationFilters.action) {
      return false;
    }

    if (!query) {
      return true;
    }

    const haystacks = [
      entry.action_label,
      entry.actor_name,
      entry.reason,
      entry.round ? `putaran ${entry.round}` : '',
    ]
      .filter(Boolean)
      .map((value) => String(value).toLowerCase());

    return haystacks.some((value) => value.includes(query));
  });
});

const hasNegotiationHistory = computed(() => (negotiations.value ?? []).length > 0);
const latestNegotiationEntry = computed(() => {
  const entries = negotiations.value ?? [];
  return entries.length ? entries[entries.length - 1] : null;
});
const showInitialOfferShortcut = computed(() => (
  activeTab.value !== 'negosiasi'
  && Boolean(offerAction.value)
  && !hasNegotiationHistory.value
  && offerAction.value?.label === 'Kirim Penawaran'
));
const negotiationStatusSummary = computed(() => {
  if (!hasNegotiationHistory.value) {
    return {
      title: 'Belum Ada Penawaran',
      description: 'Admin belum pernah mengirim penawaran harga. Kirim penawaran awal agar user bisa meninjau fee dan melanjutkan proses.',
      tone: 'border-slate-200 bg-slate-50 text-slate-700',
    };
  }

  if (approveLatestNegotiationAction.value) {
    return {
      title: 'Menunggu Keputusan Admin',
      description: 'User sudah mengajukan keberatan fee. Anda bisa menyetujui harga user atau merespons dengan counter offer baru.',
      tone: 'border-amber-200 bg-amber-50 text-amber-900',
    };
  }

  if (record.value?.status_value === 'offer_sent') {
    return {
      title: 'Menunggu Respon User',
      description: 'Penawaran aktif sudah dikirim. User dapat menyetujui penawaran atau mengajukan keberatan fee selama kuota negosiasi masih tersedia.',
      tone: 'border-sky-200 bg-sky-50 text-sky-900',
    };
  }

  if (record.value?.status_value === 'waiting_signature') {
    return {
      title: 'Negosiasi Selesai',
      description: 'Fee sudah final dan request masuk ke tahap tanda tangan kontrak. Tab ini sekarang berfungsi sebagai histori negosiasi.',
      tone: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    };
  }

  return {
    title: 'Riwayat Negosiasi Aktif',
    description: 'Pantau event negosiasi dan gunakan panel aksi di bawah untuk merespons status terkini.',
    tone: 'border-slate-200 bg-slate-50 text-slate-700',
  };
});

const openNegotiationTab = () => {
  activeTab.value = 'negosiasi';
};

const resetRevisionForm = () => {
  revisionForm.clearErrors();
  revisionForm.admin_note = '';
  revisionForm.items.splice(0, revisionForm.items.length, {
    target_key: '',
    issue_note: '',
  });
  selectedRevisionTarget.value = null;
};

const targetRevisionStatus = (targetKey) => openRevisionItemMap.value[targetKey] ?? null;

const assetMissingDocumentOptions = (assetId) => assetDocumentRevisionOptions.value.filter((option) => option.kind === 'missing' && option.appraisal_asset_id === assetId);
const assetMissingPhotoOptions = (assetId) => assetPhotoRevisionOptions.value.filter((option) => option.kind === 'missing' && option.appraisal_asset_id === assetId);
const assetExistingDocumentOption = (fileId) => assetDocumentRevisionOptions.value.find((option) => option.kind === 'existing' && option.original_asset_file_id === fileId) ?? null;
const assetExistingPhotoOption = (fileId) => assetPhotoRevisionOptions.value.find((option) => option.kind === 'existing' && option.original_asset_file_id === fileId) ?? null;

const openRevisionDialog = (target) => {
  if (!target || !revisionState.value.can_create) {
    return;
  }

  selectedRevisionTarget.value = target;
  revisionForm.clearErrors();
  revisionForm.admin_note = '';
  revisionForm.items.splice(0, revisionForm.items.length, {
    target_key: target.key,
    issue_note: '',
  });
  revisionDialogOpen.value = true;
};

const approveRevisionItem = (item) => {
  if (!item?.approve_url) {
    return;
  }
  openConfirmDialog({
    title: 'Setujui Revisi',
    description: 'Setujui file revisi ini sebagai dokumen aktif?',
    confirmLabel: 'Setujui Revisi',
    onConfirm: () => {
      router.post(item.approve_url, {}, {
        preserveScroll: true,
      });
    },
  });
};

const openRejectRevisionDialog = (item) => {
  if (!item?.reject_url) {
    return;
  }

  selectedReviewItem.value = item;
  revisionReviewForm.clearErrors();
  revisionReviewForm.review_note = '';
  revisionReviewDialogOpen.value = true;
};

const closeRejectRevisionDialog = () => {
  revisionReviewDialogOpen.value = false;
  selectedReviewItem.value = null;
  revisionReviewForm.reset();
  revisionReviewForm.clearErrors();
};

const submitRejectedRevision = () => {
  if (!selectedReviewItem.value?.reject_url) {
    return;
  }

  revisionReviewForm.post(selectedReviewItem.value.reject_url, {
    preserveScroll: true,
    onSuccess: () => {
      closeRejectRevisionDialog();
    },
  });
};

const closeRevisionDialog = () => {
  revisionDialogOpen.value = false;
  resetRevisionForm();
};

const submitRevisionItem = () => {
  if (!revisionWorkspace.value?.create_url || !revisionState.value.can_create) {
    return;
  }

  revisionForm.post(revisionWorkspace.value.create_url, {
    preserveScroll: true,
    onSuccess: () => {
      closeRevisionDialog();
    },
  });
};
</script>

<template>
  <Head :title="`Admin - ${record.request_number}`" />

  <AdminLayout :title="record.request_number">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Request Detail</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ record.request_number }}</h1>
          <div class="mt-3 flex flex-wrap gap-2">
            <Badge variant="outline" :class="statusTone(record.status_value)">{{ record.status_label }}</Badge>
            <Badge variant="outline" :class="statusTone(record.contract_status_value)">{{ record.contract_status_label }}</Badge>
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button v-if="showInitialOfferShortcut" type="button" @click="openNegotiationTab">
            Kirim Penawaran
          </Button>

          <Button
            v-for="action in availableActions"
            :key="action.key"
            :variant="action.variant"
            :disabled="action.disabled"
            :title="action.disabled_reason || null"
            @click="runAction(action)"
          >
            {{ action.label }}
          </Button>

          <Button variant="outline" as-child>
            <Link :href="route('admin.appraisal-requests.index')"><ArrowLeft class="h-4 w-4" />Kembali ke daftar</Link>
          </Button>



        </div>
      </section>

      <section class="rounded-2xl border bg-slate-50/80 p-2">
        <div class="flex flex-wrap gap-2">
          <Button type="button" :variant="activeTab === 'ringkasan' ? 'default' : 'ghost'" @click="activeTab = 'ringkasan'">Ringkasan</Button>
          <Button type="button" :variant="activeTab === 'aset' ? 'default' : 'ghost'" @click="activeTab = 'aset'">Aset</Button>
          <Button type="button" :variant="activeTab === 'dokumen' ? 'default' : 'ghost'" @click="activeTab = 'dokumen'">Dokumen</Button>
          <Button type="button" :variant="activeTab === 'negosiasi' ? 'default' : 'ghost'" @click="activeTab = 'negosiasi'">Negosiasi</Button>
          <Button v-if="showContractTab" type="button" :variant="activeTab === 'kontrak' ? 'default' : 'ghost'" @click="activeTab = 'kontrak'">Kontrak</Button>
          <Button type="button" :variant="activeTab === 'pembayaran' ? 'default' : 'ghost'" @click="activeTab = 'pembayaran'">Pembayaran</Button>
        </div>
      </section>

      <section :class="activeTab === 'ringkasan' ? 'grid gap-6 xl:grid-cols-[1.1fr_0.9fr]' : 'space-y-6'">
        <div class="space-y-6">
          <Card v-if="activeTab === 'ringkasan'">
            <CardHeader>
              <CardTitle>Ringkasan Permohonan</CardTitle>
              <CardDescription>Data inti request untuk verifikasi dan tindak lanjut admin.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-2">
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Tujuan</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.purpose_label }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Jenis Laporan</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.report_type_label }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Guideline</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.guideline_set }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Tanggal Request</p>
                <p class="mt-2 text-sm text-slate-900">{{ formatDateTime(record.requested_at) }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Tanggal Verifikasi</p>
                <p class="mt-2 text-sm text-slate-900">{{ formatDateTime(record.verified_at) }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Klien</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.client_name }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nomor Kontrak</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.contract_number }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Tanggal Kontrak</p>
                <p class="mt-2 text-sm text-slate-900">{{ formatDateTime(record.contract_date) }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Durasi Valuasi</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.valuation_duration_days ? `${record.valuation_duration_days} hari` : '-' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Masa Berlaku Offer</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.offer_validity_days ? `${record.offer_validity_days} hari` : '-' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Fee</p>
                <p class="mt-2 text-sm text-slate-900">{{ formatCurrency(record.fee_total) }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Skema Pembayaran</p>
                <p class="mt-2 text-sm text-slate-900">Pelunasan penuh via payment gateway</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Harapan Fee Terakhir</p>
                <p class="mt-2 text-sm text-slate-900">{{ formatCurrency(record.latest_expected_fee) }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Catatan Negosiasi Terakhir</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.latest_negotiation_reason || '-' }}</p>
              </div>
            </CardContent>
          </Card>

          <Card v-if="activeTab === 'dokumen'">
            <CardHeader>
              <CardTitle>Permintaan Revisi Dokumen</CardTitle>
              <CardDescription>Gunakan tombol revisi pada setiap dokumen atau foto untuk meminta customer mengunggah ulang item yang perlu diperbaiki.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-5">
              <div
                :class="[
                  'rounded-2xl border px-4 py-3 text-sm',
                  revisionState.can_create
                    ? 'border-sky-200 bg-sky-50 text-sky-900'
                    : 'border-amber-200 bg-amber-50 text-amber-900',
                ]"
              >
                {{ revisionState.message || 'Klik tombol revisi pada item yang perlu diperbaiki customer.' }}
              </div>

              <div class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold text-slate-950">Riwayat Batch Revisi</p>
                    <p class="text-xs text-slate-500">Catatan revisi terdahulu tetap disimpan agar histori dokumen bisa diaudit.</p>
                  </div>
                  <Badge variant="outline" class="bg-slate-100 text-slate-800 border-slate-200">
                    {{ revisionBatches.length }} batch
                  </Badge>
                </div>

                <div v-for="batch in revisionBatches" :key="batch.id" class="rounded-2xl border p-4">
                  <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                    <div class="flex flex-wrap items-center gap-2">
                      <p class="font-medium text-slate-950">Batch #{{ batch.id }}</p>
                      <Badge variant="outline" :class="revisionStatusTone(batch.status)">{{ batch.status_label }}</Badge>
                      </div>
                      <p class="mt-1 text-xs text-slate-500">
                        Dibuat oleh {{ batch.creator_name }} • {{ formatDateTime(batch.created_at) }}
                      </p>
                    </div>
                  </div>

                  <div v-if="batch.admin_note" class="mt-4 rounded-2xl border bg-slate-50 p-3 text-sm text-slate-700">
                    {{ batch.admin_note }}
                  </div>

                  <div class="mt-4 space-y-3">
                    <div v-for="item in batch.items" :key="item.id" class="rounded-2xl border bg-slate-50 p-4">
                      <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                          <p class="font-medium text-slate-950">{{ item.target_label }}</p>
                          <p v-if="item.asset_address" class="mt-1 text-xs text-slate-500">{{ item.asset_address }}</p>
                        </div>
                        <Badge variant="outline" :class="revisionItemTone(item.status)">{{ item.status_label }}</Badge>
                      </div>

                      <p class="mt-3 text-sm text-slate-700">{{ item.issue_note }}</p>
                      <div v-if="item.review_note" class="mt-3 rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-900">
                        <p class="font-medium">Catatan Review Admin</p>
                        <p class="mt-1">{{ item.review_note }}</p>
                      </div>

                      <div class="mt-3 grid gap-3 xl:grid-cols-2">
                        <div class="rounded-2xl border bg-white p-3">
                          <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">File Asal</p>
                          <div v-if="item.original_file" class="mt-2 space-y-1">
                            <button
                              type="button"
                              class="block w-full overflow-hidden rounded-2xl border bg-slate-50 text-left transition hover:border-slate-300"
                              @click="openRevisionFilePreview(item.original_file, `${item.target_label} - File Asal`)"
                            >
                              <template v-if="isImageFile(item.original_file)">
                                <div class="relative">
                                  <img
                                    :src="item.original_file.url"
                                    :alt="item.original_file.original_name"
                                    class="h-48 w-full object-cover"
                                  />
                                  <div class="absolute right-3 top-3 rounded-full bg-black/70 px-3 py-1 text-xs font-medium text-white">
                                    Lihat Foto
                                  </div>
                                </div>
                              </template>
                              <template v-else>
                                <div class="flex h-48 items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200">
                                  <div class="flex flex-col items-center gap-3 text-center text-slate-600">
                                    <FileText class="h-12 w-12" />
                                    <p class="text-sm font-medium">Preview Dokumen Asal</p>
                                    <p class="max-w-xs text-xs text-slate-500">
                                      Klik untuk membuka file yang sebelumnya diunggah customer.
                                    </p>
                                  </div>
                                </div>
                              </template>

                              <div class="space-y-1 p-4">
                                <p class="text-sm font-semibold text-slate-950">{{ item.original_file.original_name }}</p>
                                <p class="text-xs text-slate-500">{{ item.original_file.type_label }}</p>
                                <p class="text-xs text-slate-500">{{ item.original_file.mime || '-' }} - {{ formatDateTime(item.original_file.created_at) }}</p>
                                <div class="pt-1 text-xs font-medium text-slate-700">
                                  <span class="inline-flex items-center gap-1">
                                    <Eye class="h-3.5 w-3.5" />
                                    Buka Preview
                                  </span>
                                </div>
                              </div>
                            </button>
                          </div>
                          <p v-else class="mt-2 text-sm text-slate-500">Belum ada file asal. Customer harus mengunggah dokumen yang diminta.</p>
                        </div>

                        <div class="rounded-2xl border bg-white p-3">
                          <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">File Pengganti</p>
                          <div v-if="item.replacement_file" class="mt-2 space-y-1">
                            <button
                              type="button"
                              class="block w-full overflow-hidden rounded-2xl border bg-slate-50 text-left transition hover:border-slate-300"
                              @click="openRevisionFilePreview(item.replacement_file, `${item.target_label} - File Revisi`)"
                            >
                              <template v-if="isImageFile(item.replacement_file)">
                                <div class="relative">
                                  <img
                                    :src="item.replacement_file.url"
                                    :alt="item.replacement_file.original_name"
                                    class="h-48 w-full object-cover"
                                  />
                                  <div class="absolute right-3 top-3 rounded-full bg-black/70 px-3 py-1 text-xs font-medium text-white">
                                    Lihat Foto
                                  </div>
                                </div>
                              </template>
                              <template v-else>
                                <div class="flex h-48 items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200">
                                  <div class="flex flex-col items-center gap-3 text-center text-slate-600">
                                    <FileText class="h-12 w-12" />
                                    <p class="text-sm font-medium">Preview Dokumen Revisi</p>
                                    <p class="max-w-xs text-xs text-slate-500">
                                      Klik untuk membuka file revisi yang terakhir diunggah customer.
                                    </p>
                                  </div>
                                </div>
                              </template>

                              <div class="space-y-1 p-4">
                                <p class="text-sm font-semibold text-slate-950">{{ item.replacement_file.original_name }}</p>
                                <p class="text-xs text-slate-500">{{ item.replacement_file.type_label }}</p>
                                <p class="text-xs text-slate-500">{{ item.replacement_file.mime || '-' }} - {{ formatDateTime(item.replacement_file.created_at) }}</p>
                                <div class="pt-1 text-xs font-medium text-slate-700">
                                  <span class="inline-flex items-center gap-1">
                                    <Eye class="h-3.5 w-3.5" />
                                    Buka Preview
                                  </span>
                                </div>
                              </div>
                            </button>
                          </div>
                          <p v-else class="mt-2 text-sm text-slate-500">Customer belum mengunggah file revisi.</p>
                        </div>
                      </div>

                      <div v-if="item.can_approve || item.can_reject" class="mt-4 flex flex-wrap justify-end gap-2">
                        <Button
                          v-if="item.can_reject"
                          type="button"
                          variant="outline"
                          @click="openRejectRevisionDialog(item)"
                        >
                          Minta Revisi Lagi
                        </Button>
                        <Button
                          v-if="item.can_approve"
                          type="button"
                          @click="approveRevisionItem(item)"
                        >
                          Setujui Revisi
                        </Button>
                      </div>
                    </div>
                  </div>
                </div>

                <div v-if="!revisionBatches.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
                  Belum ada batch revisi dokumen untuk request ini.
                </div>
              </div>
            </CardContent>
          </Card>

          <Card v-if="activeTab === 'dokumen' && requestFiles.length">
            <CardHeader>
              <CardTitle>Dokumen Request</CardTitle>
              <CardDescription>File request level umum yang memang tersimpan di permohonan ini.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                Admin hanya memverifikasi dokumen yang diunggah customer. Jika ada dokumen yang salah atau tidak cocok,
                gunakan aksi status untuk meminta customer melakukan revisi.
              </div>

              <div v-for="file in requestFiles" :key="file.id" class="rounded-2xl border p-4">
                <button
                  type="button"
                  class="block w-full overflow-hidden rounded-2xl border bg-slate-50 text-left transition hover:border-slate-300"
                  @click="openRevisionFilePreview(file, `Dokumen Request - ${file.type_label}`)"
                >
                  <template v-if="isImageFile(file)">
                    <div class="relative">
                      <img
                        :src="file.url"
                        :alt="file.original_name"
                        class="h-48 w-full object-cover"
                      />
                      <div class="absolute right-3 top-3 rounded-full bg-black/70 px-3 py-1 text-xs font-medium text-white">
                        Lihat Foto
                      </div>
                    </div>
                  </template>
                  <template v-else>
                    <div class="flex h-48 items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200">
                      <div class="flex flex-col items-center gap-3 text-center text-slate-600">
                        <FileText class="h-12 w-12" />
                        <p class="text-sm font-medium">Preview Dokumen Request</p>
                        <p class="max-w-xs text-xs text-slate-500">
                          Klik untuk membuka dokumen yang diunggah customer pada level request.
                        </p>
                      </div>
                    </div>
                  </template>

                  <div class="space-y-1 p-4">
                    <p class="text-sm font-semibold text-slate-950">{{ file.original_name }}</p>
                    <p class="text-xs text-slate-500">{{ file.type_label }} - {{ file.size_label }}</p>
                    <p class="text-xs text-slate-500">{{ file.mime || '-' }} - {{ formatDateTime(file.created_at) }}</p>
                    <div class="pt-1 text-xs font-medium text-slate-700">
                      <span class="inline-flex items-center gap-1">
                        <Eye class="h-3.5 w-3.5" />
                        Buka Preview
                      </span>
                    </div>
                  </div>
                </button>

                <div v-if="requestExistingRevisionOptions[file.id] && targetRevisionStatus(requestExistingRevisionOptions[file.id].key)" class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                  <p class="font-medium">Item sudah diminta revisi</p>
                  <p class="mt-1">{{ targetRevisionStatus(requestExistingRevisionOptions[file.id].key).issue_note }}</p>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                  <Button variant="outline" size="sm" @click="openRevisionFilePreview(file, `Dokumen Request - ${file.type_label}`)">
                    <Eye class="h-4 w-4" />
                    Lihat Dokumen
                  </Button>
                  <Button variant="outline" size="sm" as-child>
                    <a :href="file.url" target="_blank" rel="noreferrer">Buka File</a>
                  </Button>
                  <Button
                    v-if="requestExistingRevisionOptions[file.id]"
                    variant="outline"
                    size="sm"
                    :disabled="Boolean(targetRevisionStatus(requestExistingRevisionOptions[file.id].key)) || !revisionState.can_create"
                    @click="openRevisionDialog(requestExistingRevisionOptions[file.id])"
                  >
                    Revisi
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card v-if="activeTab === 'aset' || activeTab === 'dokumen'">
            <CardHeader>
              <CardTitle>Aset Terkait</CardTitle>
              <CardDescription>
                {{ activeTab === 'aset'
                  ? 'Informasi inti aset yang diajukan customer untuk appraisal.'
                  : 'Dokumen dan foto aset yang diajukan customer untuk diverifikasi.' }}
              </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
              <div v-for="asset in assets" :key="asset.id" class="rounded-3xl border p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Aset #{{ asset.order }}</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-950">{{ asset.address }}</h3>
                    <p class="mt-2 text-sm text-slate-600">
                      {{ asset.asset_type_label }}
                      <span v-if="asset.peruntukan_label">- {{ asset.peruntukan_label }}</span>
                      <span v-if="asset.asset_code">- Kode: {{ asset.asset_code }}</span>
                    </p>
                  </div>
                  <div class="space-y-3">
                    <div class="grid gap-2 text-right text-sm text-slate-600">
                      <p>Range nilai: {{ formatCurrency(asset.estimated_value_low) }} - {{ formatCurrency(asset.estimated_value_high) }}</p>
                      <p>Market value: {{ formatCurrency(asset.market_value_final) }}</p>
                    </div>
                  </div>
                </div>

                <div v-if="activeTab === 'aset'" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Lokasi</p>
                    <div class="mt-2 space-y-1 text-sm text-slate-700">
                      <p>{{ asset.village_name || '-' }}</p>
                      <p>{{ asset.district_name || '-' }}</p>
                      <p>{{ asset.regency_name || '-' }}</p>
                      <p>{{ asset.province_name || '-' }}</p>
                    </div>
                  </div>
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Karakteristik Tanah</p>
                    <div class="mt-2 space-y-1 text-sm text-slate-700">
                      <p>Dokumen: {{ asset.title_document_label || '-' }}</p>
                      <p>Bentuk: {{ asset.land_shape_label || '-' }}</p>
                      <p>Posisi: {{ asset.land_position_label || '-' }}</p>
                      <p>Kondisi: {{ asset.land_condition_label || '-' }}</p>
                      <p>Topografi: {{ asset.topography_label || '-' }}</p>
                    </div>
                  </div>
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Ukuran dan Bangunan</p>
                    <div class="mt-2 space-y-1 text-sm text-slate-700">
                      <p>Luas tanah: {{ formatArea(asset.land_area) }}</p>
                      <p>Luas bangunan: {{ formatArea(asset.building_area) }}</p>
                      <p>Lantai: {{ asset.building_floors ?? '-' }}</p>
                      <p>Tahun bangun: {{ asset.build_year ?? '-' }}</p>
                      <p>Tahun renovasi: {{ asset.renovation_year ?? '-' }}</p>
                    </div>
                  </div>
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Akses</p>
                    <div class="mt-2 space-y-1 text-sm text-slate-700">
                      <p>Lebar muka: {{ asset.frontage_width ? `${formatNumber(asset.frontage_width, 2)} m` : '-' }}</p>
                      <p>Lebar jalan: {{ asset.access_road_width ? `${formatNumber(asset.access_road_width, 2)} m` : '-' }}</p>
                      <p>Latitude: {{ asset.coordinates_lat ?? '-' }}</p>
                      <p>Longitude: {{ asset.coordinates_lng ?? '-' }}</p>
                    </div>
                    <div class="mt-3" v-if="asset.maps_link">
                      <Button variant="outline" size="sm" as-child>
                        <a :href="asset.maps_link" target="_blank" rel="noreferrer">Buka Maps</a>
                      </Button>


                    </div>
                  </div>
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nilai Final</p>
                    <div class="mt-2 space-y-1 text-sm text-slate-700">
                      <p>Nilai tanah: {{ formatCurrency(asset.land_value_final) }}</p>
                      <p>Nilai bangunan: {{ formatCurrency(asset.building_value_final) }}</p>
                      <p>Nilai pasar: {{ formatCurrency(asset.market_value_final) }}</p>
                    </div>
                  </div>
                </div>

                <div v-if="activeTab === 'dokumen'" class="mt-5 space-y-6">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Dokumen Aset</p>
                    <div class="mt-3 space-y-3">
                      <div v-for="file in asset.documents" :key="file.id" class="rounded-2xl border bg-slate-50 p-4">
                        <button
                          type="button"
                          class="block w-full overflow-hidden rounded-2xl border bg-white text-left transition hover:border-slate-300"
                          @click="openRevisionFilePreview(file, `Dokumen Aset - ${file.type_label}`)"
                        >
                          <template v-if="isImageFile(file)">
                            <div class="relative">
                              <img
                                :src="file.url"
                                :alt="file.original_name"
                                class="h-48 w-full object-cover"
                              />
                              <div class="absolute right-3 top-3 rounded-full bg-black/70 px-3 py-1 text-xs font-medium text-white">
                                Lihat Foto
                              </div>
                            </div>
                          </template>
                          <template v-else>
                            <div class="flex h-48 items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200">
                              <div class="flex flex-col items-center gap-3 text-center text-slate-600">
                                <FileText class="h-12 w-12" />
                                <p class="text-sm font-medium">Preview Dokumen Aset</p>
                                <p class="max-w-xs text-xs text-slate-500">
                                  Klik untuk membuka dokumen aset yang sedang diverifikasi.
                                </p>
                              </div>
                            </div>
                          </template>

                          <div class="space-y-1 p-4">
                            <p class="text-sm font-semibold text-slate-950">{{ file.original_name }}</p>
                            <p class="text-xs text-slate-500">{{ file.type_label }} - {{ file.size_label }}</p>
                            <p class="text-xs text-slate-500">{{ file.mime || '-' }} - {{ formatDateTime(file.created_at) }}</p>
                            <div class="pt-1 text-xs font-medium text-slate-700">
                              <span class="inline-flex items-center gap-1">
                                <Eye class="h-3.5 w-3.5" />
                                Buka Preview
                              </span>
                            </div>
                          </div>
                        </button>

                        <div
                          v-if="assetExistingDocumentOption(file.id) && targetRevisionStatus(assetExistingDocumentOption(file.id).key)"
                          class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                        >
                          <p class="font-medium">Item sudah diminta revisi</p>
                          <p class="mt-1">{{ targetRevisionStatus(assetExistingDocumentOption(file.id).key).issue_note }}</p>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                          <Button variant="outline" size="sm" @click="openRevisionFilePreview(file, `Dokumen Aset - ${file.type_label}`)">
                            <Eye class="h-4 w-4" />
                            Lihat Dokumen
                          </Button>
                          <Button variant="outline" size="sm" as-child>
                            <a :href="file.url" target="_blank" rel="noreferrer">Buka Dokumen</a>
                          </Button>
                          <Button
                            v-if="assetExistingDocumentOption(file.id)"
                            variant="outline"
                            size="sm"
                            :disabled="Boolean(targetRevisionStatus(assetExistingDocumentOption(file.id).key)) || !revisionState.can_create"
                            @click="openRevisionDialog(assetExistingDocumentOption(file.id))"
                          >
                            Revisi
                          </Button>
                        </div>
                      </div>
                      <div
                        v-for="option in assetMissingDocumentOptions(asset.id)"
                        :key="option.key"
                        class="rounded-2xl border border-dashed p-4"
                      >
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                          <div>
                            <p class="font-medium text-slate-950">{{ option.label }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ option.description }}</p>
                            <div v-if="targetRevisionStatus(option.key)" class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                              <p class="font-medium">Permintaan revisi aktif</p>
                              <p class="mt-1">{{ targetRevisionStatus(option.key).issue_note }}</p>
                            </div>
                          </div>
                          <div class="flex flex-wrap gap-2">
                            <Button
                              variant="outline"
                              size="sm"
                              :disabled="Boolean(targetRevisionStatus(option.key)) || !revisionState.can_create"
                              @click="openRevisionDialog(option)"
                            >
                              Revisi
                            </Button>
                          </div>
                        </div>
                      </div>
                      <div v-if="!asset.documents.length && !assetMissingDocumentOptions(asset.id).length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
                        Tidak ada dokumen aset.
                      </div>
                    </div>
                  </div>

                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Foto Aset</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                      <div
                        v-for="photo in asset.photos"
                        :key="photo.id"
                        class="overflow-hidden rounded-2xl border bg-slate-50"
                      >
                        <button
                          type="button"
                          class="block w-full text-left"
                          @click="openRevisionFilePreview(photo, `Foto Aset - ${photo.type_label}`)"
                        >
                          <div class="relative">
                            <img :src="photo.url" :alt="photo.original_name" class="h-40 w-full object-cover" />
                            <div class="absolute right-3 top-3 rounded-full bg-black/70 px-3 py-1 text-xs font-medium text-white">
                              Lihat Foto
                            </div>
                          </div>
                        </button>
                        <div class="space-y-3 p-3">
                          <div>
                            <p class="text-sm font-medium text-slate-950">{{ photo.type_label }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ photo.original_name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ photo.size_label }} - {{ formatDateTime(photo.created_at) }}</p>
                            <div class="pt-2 text-xs font-medium text-slate-700">
                              <span class="inline-flex items-center gap-1">
                                <Eye class="h-3.5 w-3.5" />
                                Buka Preview
                              </span>
                            </div>
                          </div>
                          <div
                            v-if="assetExistingPhotoOption(photo.id) && targetRevisionStatus(assetExistingPhotoOption(photo.id).key)"
                            class="rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                          >
                            <p class="font-medium">Item sudah diminta revisi</p>
                            <p class="mt-1">{{ targetRevisionStatus(assetExistingPhotoOption(photo.id).key).issue_note }}</p>
                          </div>
                          <div class="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm" @click="openRevisionFilePreview(photo, `Foto Aset - ${photo.type_label}`)">
                              <Search class="h-4 w-4" />
                              Lihat Foto
                            </Button>
                            <Button variant="outline" size="sm" as-child>
                              <a :href="photo.url" target="_blank" rel="noreferrer">Buka File</a>
                            </Button>
                            <Button
                              v-if="assetExistingPhotoOption(photo.id)"
                              variant="outline"
                              size="sm"
                              :disabled="Boolean(targetRevisionStatus(assetExistingPhotoOption(photo.id).key)) || !revisionState.can_create"
                              @click="openRevisionDialog(assetExistingPhotoOption(photo.id))"
                            >
                              Revisi
                            </Button>
                          </div>
                        </div>
                      </div>
                      <div
                        v-for="option in assetMissingPhotoOptions(asset.id)"
                        :key="option.key"
                        class="rounded-2xl border border-dashed p-4 sm:col-span-2"
                      >
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                          <div>
                            <p class="font-medium text-slate-950">{{ option.label }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ option.description }}</p>
                            <div v-if="targetRevisionStatus(option.key)" class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                              <p class="font-medium">Permintaan revisi aktif</p>
                              <p class="mt-1">{{ targetRevisionStatus(option.key).issue_note }}</p>
                            </div>
                          </div>
                          <div class="flex flex-wrap gap-2">
                            <Button
                              variant="outline"
                              size="sm"
                              :disabled="Boolean(targetRevisionStatus(option.key)) || !revisionState.can_create"
                              @click="openRevisionDialog(option)"
                            >
                              Revisi
                            </Button>
                          </div>
                        </div>
                      </div>
                      <div v-if="!asset.photos.length && !assetMissingPhotoOptions(asset.id).length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500 sm:col-span-2">
                        Tidak ada foto aset.
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="!assets.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
                Belum ada aset.
              </div>
            </CardContent>
          </Card>
        </div>

        <div class="space-y-6">
          <Card v-if="activeTab === 'ringkasan'">
            <CardHeader>
              <CardTitle>Pemohon</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3 text-sm text-slate-700">
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nama</p>
                <p class="mt-1">{{ requester.name }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Email</p>
                <p class="mt-1">{{ requester.email }}</p>
              </div>
            </CardContent>
          </Card>

          <Card v-if="activeTab === 'kontrak'">
            <CardHeader>
              <CardTitle>Kontrak</CardTitle>
              <CardDescription>Ringkasan status kontrak, dokumen aktif, dan detail komersial request.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-5">
              <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Status Kontrak</p>
                  <div class="mt-3">
                    <Badge variant="outline" :class="statusTone(record.contract_status_value)">{{ record.contract_status_label }}</Badge>
                  </div>
                  <p class="mt-3 text-xs text-slate-500">
                    {{
                      record.contract_status_value === 'signed'
                        ? 'Customer sudah mengunggah kontrak yang ditandatangani.'
                        : 'Kontrak masih menunggu finalisasi atau tanda tangan customer.'
                    }}
                  </p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nomor Kontrak</p>
                  <p class="mt-3 text-sm font-semibold text-slate-950">{{ record.contract_number }}</p>
                  <p class="mt-2 text-xs text-slate-500">Tanggal kontrak: {{ formatDateTime(record.contract_date) }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nilai Penugasan</p>
                  <p class="mt-3 text-sm font-semibold text-slate-950">{{ formatCurrency(record.fee_total) }}</p>
                  <p class="mt-2 text-xs text-slate-500">
                    Pelunasan penuh via payment gateway
                  </p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Masa Berlaku Offer</p>
                  <p class="mt-3 text-sm font-semibold text-slate-950">
                    {{ record.offer_validity_days ? `${record.offer_validity_days} hari` : '-' }}
                  </p>
                  <p class="mt-2 text-xs text-slate-500">Klien: {{ record.client_name || '-' }}</p>
                </div>
              </div>

              <div class="grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Dokumen Kontrak</p>
                      <p class="mt-2 text-sm text-slate-600">File kontrak aktif yang dikirim customer untuk proses penugasan ini.</p>
                    </div>
                    <Badge v-if="contractFiles.length" variant="outline" class="border-slate-200 bg-white text-slate-700">
                      {{ contractFiles.length }} file
                    </Badge>
                  </div>

                  <div v-if="contractFiles.length" class="mt-4 grid gap-4 xl:grid-cols-2">
                    <button
                      v-for="file in contractFiles"
                      :key="file.id"
                      type="button"
                      class="block overflow-hidden rounded-2xl border bg-white text-left transition hover:border-slate-300"
                      @click="openRevisionFilePreview(file, `Kontrak - ${file.type_label}`)"
                    >
                      <template v-if="isImageFile(file)">
                        <div class="relative">
                          <img
                            :src="file.url"
                            :alt="file.original_name"
                            class="h-52 w-full object-cover"
                          />
                          <div class="absolute right-3 top-3 rounded-full bg-black/70 px-3 py-1 text-xs font-medium text-white">
                            Lihat Foto
                          </div>
                        </div>
                      </template>
                      <template v-else>
                        <div class="flex h-52 items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200">
                          <div class="flex flex-col items-center gap-3 text-center text-slate-600">
                            <FileText class="h-12 w-12" />
                            <p class="text-sm font-medium">Preview Dokumen Kontrak</p>
                            <p class="max-w-xs text-xs text-slate-500">
                              Klik untuk membuka file kontrak yang diunggah customer.
                            </p>
                          </div>
                        </div>
                      </template>

                      <div class="space-y-1 p-4">
                        <p class="text-sm font-semibold text-slate-950">{{ file.original_name }}</p>
                        <p class="text-xs text-slate-500">{{ file.type_label }} - {{ file.size_label }}</p>
                        <p class="text-xs text-slate-500">{{ file.mime || '-' }} - {{ formatDateTime(file.created_at) }}</p>
                        <div class="pt-1 text-xs font-medium text-slate-700">
                          <span class="inline-flex items-center gap-1">
                            <Eye class="h-3.5 w-3.5" />
                            Buka Preview
                          </span>
                        </div>
                      </div>
                    </button>
                  </div>
                  <div v-else class="mt-4 rounded-2xl border border-dashed p-4 text-sm text-slate-500">
                    Belum ada file kontrak yang diunggah customer.
                  </div>
                </div>

                <div class="rounded-2xl border bg-white p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Ringkasan Komersial</p>
                  <div class="mt-4 space-y-4 text-sm text-slate-700">
                    <div>
                      <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Pemohon</p>
                      <p class="mt-2 text-sm text-slate-950">{{ requester.name || '-' }}</p>
                      <p class="mt-1 text-xs text-slate-500">{{ requester.email || '-' }}</p>
                    </div>
                    <div>
                      <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Klien</p>
                      <p class="mt-2 text-sm text-slate-950">{{ record.client_name || '-' }}</p>
                    </div>
                    <div>
                      <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Skema Pembayaran</p>
                      <p class="mt-2 text-sm text-slate-950">
                        Pelunasan penuh
                      </p>
                    </div>
                    <div>
                      <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Tindak Lanjut</p>
                      <p class="mt-2 text-sm text-slate-950">
                        {{
                          record.contract_status_value === 'signed'
                            ? 'Dokumen kontrak siap dipakai untuk proses berikutnya.'
                            : 'Pantau upload kontrak customer dan pastikan file aktif sudah lengkap.'
                        }}
                      </p>
                    </div>
                    <div v-if="contractFiles.length" class="flex flex-wrap gap-2 pt-2">
                      <Button variant="outline" size="sm" @click="openRevisionFilePreview(contractFiles[0], `Kontrak - ${contractFiles[0].type_label}`)">
                        <Eye class="h-4 w-4" />
                        Lihat Kontrak
                      </Button>
                      <Button variant="outline" size="sm" as-child>
                        <a :href="contractFiles[0].url" target="_blank" rel="noreferrer">Buka di Tab Baru</a>
                      </Button>
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card v-if="activeTab === 'pembayaran'">
            <CardHeader>
              <CardTitle>Pembayaran</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
              <div
                v-if="paymentVerification"
                :class="[
                  'rounded-2xl border p-4 text-sm',
                  paymentVerification.ready
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
                    : 'border-amber-200 bg-amber-50 text-amber-900',
                ]"
              >
                <p class="font-medium">
                  {{ paymentVerification.ready ? 'Siap Diverifikasi' : 'Belum Siap Diverifikasi' }}
                </p>
                <p class="mt-1">{{ paymentVerification.message || '-' }}</p>
                <div class="mt-3" v-if="paymentVerification.action_url">
                  <Button type="button" size="sm" @click="verifyPayment">
                    Verifikasi Pembayaran
                  </Button>

                </div>
              </div>

              <div v-for="payment in payments" :key="payment.id" class="rounded-2xl border p-4">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="font-medium text-slate-950">{{ formatCurrency(payment.amount) }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                      {{ payment.method_label }}
                      <span v-if="payment.gateway">- {{ payment.gateway }}</span>
                    </p>
                    <p class="mt-1 text-xs text-slate-500">{{ payment.external_payment_id || '-' }}</p>
                  </div>
                  <Badge variant="outline" class="bg-slate-100 text-slate-800 border-slate-200">
                    {{ paymentStatusLabel(payment.status) }}
                  </Badge>
                </div>
                <p class="mt-3 text-xs text-slate-500">{{ formatDateTime(payment.paid_at) }}</p>
              </div>
              <div v-if="!payments.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
                Belum ada data pembayaran.
              </div>
            </CardContent>
          </Card>

          <Card v-if="activeTab === 'negosiasi'">
            <CardHeader>
              <CardTitle>Negosiasi</CardTitle>
              <CardDescription>Kelola penawaran awal, respons keberatan user, dan pantau histori negosiasi.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
              <div :class="['rounded-2xl border p-4', negotiationStatusSummary.tone]">
                <p class="text-sm font-semibold">{{ negotiationStatusSummary.title }}</p>
                <p class="mt-2 text-sm">{{ negotiationStatusSummary.description }}</p>
                <div v-if="latestNegotiationEntry" class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                  <span>Event terakhir: {{ latestNegotiationEntry.action_label }}</span>
                  <span>Tanggal: {{ formatDateTime(latestNegotiationEntry.created_at) }}</span>
                  <span v-if="latestNegotiationEntry.round">Putaran: {{ latestNegotiationEntry.round }}</span>
                </div>
              </div>

              <div v-if="offerAction" class="rounded-2xl border bg-white p-5 space-y-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Aksi Admin</p>
                    <h3 class="mt-2 text-lg font-semibold text-slate-950">{{ offerAction.label }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ offerAction.description }}</p>
                  </div>
                  <Badge variant="outline" class="bg-slate-50 text-slate-700 border-slate-200">
                    {{ record.status_label }}
                  </Badge>
                </div>

                <div v-if="approveLatestNegotiationAction" class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Keberatan User Terbaru</p>
                  <div class="mt-3 grid gap-2 text-sm text-slate-700">
                    <p>Harapan fee: {{ formatCurrency(approveLatestNegotiationAction.expected_fee) }}</p>
                    <p>Putaran: {{ approveLatestNegotiationAction.round || '-' }}</p>
                    <p>Catatan: {{ approveLatestNegotiationAction.reason || '-' }}</p>
                  </div>
                  <div class="mt-4 flex flex-wrap gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      @click="approveLatestNegotiation"
                    >
                      {{ approveLatestNegotiationAction.label }}
                    </Button>
                    <p class="self-center text-xs text-slate-500">
                      Atau kirim counter offer baru melalui form di bawah.
                    </p>
                  </div>
                </div>

                <form class="space-y-5" @submit.prevent="submitOffer">
                  <div class="grid gap-5 xl:grid-cols-2">
                    <div class="space-y-2">
                      <Label for="offer_fee_total">Total Fee (Rp)</Label>
                      <Input id="offer_fee_total" v-model="offerForm.fee_total" type="number" min="1" placeholder="15000000" />
                      <p v-if="offerForm.errors.fee_total" class="text-xs text-red-500">{{ offerForm.errors.fee_total }}</p>
                    </div>

                    <div class="space-y-2">
                      <Label for="offer_validity_days">Masa Berlaku Penawaran</Label>
                      <Input id="offer_validity_days" v-model="offerForm.offer_validity_days" type="number" min="1" placeholder="14" />
                      <p v-if="offerForm.errors.offer_validity_days" class="text-xs text-red-500">{{ offerForm.errors.offer_validity_days }}</p>
                    </div>
                  </div>

                  <div class="rounded-2xl border bg-slate-50 p-4 text-sm text-slate-700">
                    Sistem pembayaran memakai pelunasan penuh melalui payment gateway. Penawaran yang dikirim ke user tidak lagi memakai skema DP.
                  </div>

                  <div class="grid gap-5 xl:grid-cols-2">
                    <div class="space-y-2">
                      <Label for="offer_contract_sequence">No. Penawaran</Label>
                      <Input id="offer_contract_sequence" v-model="offerForm.contract_sequence" type="number" min="1" placeholder="1" />
                      <p v-if="offerForm.errors.contract_sequence" class="text-xs text-red-500">{{ offerForm.errors.contract_sequence }}</p>
                    </div>

                    <div class="space-y-2">
                      <Label>Preview Nomor Penawaran</Label>
                      <div class="rounded-xl border bg-slate-50 px-4 py-3 text-sm font-medium text-slate-900">
                        {{ offerContractNumberPreview }}
                      </div>
                    </div>
                  </div>

                  <div class="flex justify-end">
                    <Button type="submit" :disabled="offerForm.processing">
                      {{ offerAction.label }}
                    </Button>
                  </div>
                </form>
              </div>

              <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Event</p>
                  <p class="mt-2 text-2xl font-semibold text-slate-950">{{ negotiationSummary.total }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Counter Request</p>
                  <p class="mt-2 text-2xl font-semibold text-slate-950">{{ negotiationSummary.counter_requests }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Offer Admin</p>
                  <p class="mt-2 text-2xl font-semibold text-slate-950">{{ negotiationSummary.offers_sent }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Disetujui</p>
                  <p class="mt-2 text-2xl font-semibold text-slate-950">{{ negotiationSummary.accepted }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Dibatalkan</p>
                  <p class="mt-2 text-2xl font-semibold text-slate-950">{{ negotiationSummary.cancelled }}</p>
                </div>
              </div>

              <div class="grid gap-4 xl:grid-cols-[0.8fr_1.2fr]">
                <div class="space-y-2">
                  <Label for="negotiation_action_filter">Filter Aksi</Label>
                  <Select v-model="negotiationFilters.action">
                    <SelectTrigger id="negotiation_action_filter">
                      <SelectValue placeholder="Semua aksi" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">Semua aksi</SelectItem>
                      <SelectItem
                        v-for="option in negotiationActionOptions"
                        :key="option.value"
                        :value="option.value"
                      >
                        {{ option.label }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div class="space-y-2">
                  <Label for="negotiation_search">Cari Riwayat</Label>
                  <Input
                    id="negotiation_search"
                    v-model="negotiationFilters.q"
                    type="text"
                    placeholder="Cari nama aktor, alasan, atau putaran"
                  />
                </div>
              </div>

              <div v-for="entry in filteredNegotiations" :key="entry.id" class="rounded-2xl border p-4">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <div class="flex flex-wrap items-center gap-2">
                      <p class="font-medium text-slate-950">{{ entry.action_label }}</p>
                      <Badge variant="outline" :class="negotiationToneClass(entry.action_tone)">
                        {{ entry.action_label }}
                      </Badge>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                      {{ entry.actor_name }}
                      <span v-if="entry.round">- Putaran {{ entry.round }}</span>
                    </p>
                  </div>
                  <span class="text-xs text-slate-500">{{ formatDateTime(entry.created_at) }}</span>
                </div>
                <div class="mt-3 grid gap-2 text-sm text-slate-700">
                  <p>Offered: {{ formatCurrency(entry.offered_fee) }}</p>
                  <p>Expected: {{ formatCurrency(entry.expected_fee) }}</p>
                  <p>Selected: {{ formatCurrency(entry.selected_fee) }}</p>
                  <p v-if="entry.reason">Catatan: {{ entry.reason }}</p>
                </div>
              </div>
              <div v-if="!filteredNegotiations.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
                {{
                  negotiations.length
                    ? 'Tidak ada histori negosiasi yang cocok dengan filter saat ini.'
                    : 'Belum ada histori negosiasi.'
                }}
              </div>
            </CardContent>
          </Card>

          <Card v-if="activeTab === 'ringkasan'">
            <CardHeader>
              <CardTitle>Catatan</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4 text-sm text-slate-700">
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Catatan User</p>
                <p class="mt-2 whitespace-pre-line">{{ record.user_request_note || '-' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Catatan Internal</p>
                <p class="mt-2 whitespace-pre-line">{{ record.notes || '-' }}</p>
              </div>
            </CardContent>
          </Card>
        </div>
      </section>
    </div>

    <Dialog :open="revisionDialogOpen" @update:open="(open) => { if (!open) closeRevisionDialog(); else revisionDialogOpen = open; }">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Permintaan Revisi Dokumen</DialogTitle>
          <DialogDescription>
            Jelaskan dengan singkat apa yang perlu customer perbaiki atau unggah ulang pada item ini.
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-4">
          <div v-if="selectedRevisionTarget" class="rounded-2xl border bg-slate-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Target Revisi</p>
            <p class="mt-2 text-sm font-medium text-slate-950">{{ selectedRevisionTarget.label }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ selectedRevisionTarget.description }}</p>
          </div>

          <div class="space-y-2">
            <Label for="revision_dialog_note">Catatan Revisi</Label>
            <Textarea
              id="revision_dialog_note"
              v-model="revisionForm.items[0].issue_note"
              rows="5"
              placeholder="Contoh: file buram, halaman kurang lengkap, foto tidak menunjukkan fasad depan, atau dokumen perlu diunggah ulang."
            />
            <p v-if="revisionForm.errors['items.0.issue_note']" class="text-xs text-rose-600">
              {{ revisionForm.errors['items.0.issue_note'] }}
            </p>
            <p v-if="revisionForm.errors['items.0.target_key']" class="text-xs text-rose-600">
              {{ revisionForm.errors['items.0.target_key'] }}
            </p>
          </div>
        </div>

        <DialogFooter class="gap-2 sm:justify-end">
          <Button type="button" variant="outline" @click="closeRevisionDialog">
            <X class="h-4 w-4" />
            Batal
          </Button>
          <Button type="button" :disabled="revisionForm.processing" @click="submitRevisionItem">
            Simpan Revisi
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog :open="revisionReviewDialogOpen" @update:open="(open) => { if (!open) closeRejectRevisionDialog(); else revisionReviewDialogOpen = open; }">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Minta Revisi Lagi</DialogTitle>
          <DialogDescription>
            Jelaskan apa yang masih perlu diperbaiki customer pada file revisi yang baru diunggah.
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-4">
          <div v-if="selectedReviewItem" class="rounded-2xl border bg-slate-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Item Revisi</p>
            <p class="mt-2 text-sm font-medium text-slate-950">{{ selectedReviewItem.target_label }}</p>
            <p v-if="selectedReviewItem.replacement_file" class="mt-1 text-xs text-slate-500">
              File terakhir: {{ selectedReviewItem.replacement_file.original_name }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="revision_review_note">Catatan Admin</Label>
            <Textarea
              id="revision_review_note"
              v-model="revisionReviewForm.review_note"
              rows="5"
              placeholder="Contoh: file masih buram, sudut foto belum sesuai, atau halaman dokumen belum lengkap."
            />
            <p v-if="revisionReviewForm.errors.review_note" class="text-xs text-rose-600">
              {{ revisionReviewForm.errors.review_note }}
            </p>
          </div>
        </div>

        <DialogFooter class="gap-2 sm:justify-end">
          <Button type="button" variant="outline" @click="closeRejectRevisionDialog">
            <X class="h-4 w-4" />
            Batal
          </Button>
          <Button type="button" :disabled="revisionReviewForm.processing" @click="submitRejectedRevision">
            Simpan Catatan Revisi
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog :open="revisionFilePreviewOpen" @update:open="(open) => { if (!open) closeRevisionFilePreview(); }">
      <DialogContent class="max-h-[92vh] overflow-hidden p-0 sm:max-w-5xl">
        <DialogHeader class="border-b px-6 py-4">
          <DialogTitle class="truncate text-left">{{ selectedPreviewFile?.original_name || selectedPreviewLabel || 'Preview File' }}</DialogTitle>
          <DialogDescription class="flex flex-wrap items-center gap-2 text-left">
            <span>{{ selectedPreviewLabel || '-' }}</span>
            <template v-if="selectedPreviewFile?.size">
              <span>-</span>
              <span>{{ formatNumber(selectedPreviewFile.size / 1024, 2) }} KB</span>
            </template>
            <template v-if="selectedPreviewFile?.created_at">
              <span>-</span>
              <span>{{ formatDateTime(selectedPreviewFile.created_at) }}</span>
            </template>
          </DialogDescription>
        </DialogHeader>

        <div class="flex max-h-[calc(92vh-88px)] flex-col bg-muted/10">
          <div class="flex-1 overflow-auto p-4">
            <div class="flex min-h-[60vh] items-center justify-center overflow-hidden rounded-xl border bg-background">
              <template v-if="selectedPreviewFile?.url && isImageFile(selectedPreviewFile)">
                <div class="relative flex h-[68vh] w-full items-center justify-center overflow-hidden bg-slate-100">
                  <img
                    :src="selectedPreviewFile.url"
                    :alt="selectedPreviewFile.original_name"
                    class="max-h-[68vh] w-full object-contain"
                  />
                </div>
              </template>

              <div v-else-if="selectedPreviewFile?.url" class="h-[68vh] w-full overflow-auto">
                <ReviewerFilePreview
                  :url="selectedPreviewFile.url"
                  :name="selectedPreviewFile.original_name"
                />
              </div>

              <div v-else class="flex flex-col items-center gap-3 px-6 text-center text-muted-foreground">
                <FileText class="h-10 w-10" />
                <p class="text-sm">Preview file tidak tersedia untuk item ini.</p>
              </div>
            </div>
          </div>

          <DialogFooter class="flex items-center justify-end gap-2 border-t bg-background px-6 py-4">
            <Button v-if="selectedPreviewFile?.url" variant="ghost" as-child>
              <a :href="selectedPreviewFile.url" download>
                <Download class="mr-2 h-4 w-4" />
                Download
              </a>
            </Button>
            <Button v-if="selectedPreviewFile?.url" variant="outline" as-child>
              <a :href="selectedPreviewFile.url" target="_blank" rel="noopener noreferrer">
                <ExternalLink class="mr-2 h-4 w-4" />
                Buka di Tab Baru
              </a>
            </Button>
          </DialogFooter>
        </div>
      </DialogContent>
    </Dialog>

    <AlertDialog :open="confirmDialogOpen" @update:open="(open) => { if (!open) closeConfirmDialog(); else confirmDialogOpen = open; }">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{{ confirmDialogState.title }}</AlertDialogTitle>
          <AlertDialogDescription>
            {{ confirmDialogState.description }}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <Button type="button" variant="outline" @click="closeConfirmDialog">
            {{ confirmDialogState.cancelLabel }}
          </Button>
          <Button
            type="button"
            :variant="confirmDialogState.confirmVariant"
            @click="submitConfirmDialog"
          >
            {{ confirmDialogState.confirmLabel }}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </AdminLayout>
</template>
