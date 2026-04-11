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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
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
  marketPreview: {
    type: Object,
    default: () => ({
      version: 0,
      published_at: null,
      approved_at: null,
      appeal_count: 0,
      appeal_reason: null,
      appeal_submitted_at: null,
      summary: null,
      assets: [],
    }),
  },
  reportPreparation: {
    type: Object,
    default: () => ({
      status: null,
      draft_available: false,
      draft_generated_at: null,
      configuration_url: null,
      draft_download_url: null,
      final_upload_url: null,
      valuation_date: null,
      selected_review_signer_id: null,
      selected_public_appraiser_signer_id: null,
      signer_snapshot: null,
      signer_options: {
        reviewers: [],
        public_appraisers: [],
      },
    }),
  },
  physicalReport: {
    type: Object,
    default: () => ({
      needs_physical_report: false,
      report_format: 'digital',
      report_format_label: 'Digital',
      copies_count: 0,
      delivery_recipient_name: null,
      delivery_recipient_phone: null,
      delivery_address: null,
      courier: null,
      tracking_number: null,
      notes: null,
      printed_at: null,
      printed_by_name: null,
      shipped_at: null,
      delivered_at: null,
      state: 'digital_only',
      state_label: 'Digital Only',
      state_description: null,
      update_url: null,
      workspace: {
        show: false,
        ready: false,
        message: null,
      },
    }),
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
  marketPreview,
  reportPreparation,
  physicalReport,
  approveLatestNegotiationAction,
  paymentVerification,
  revisionWorkspace,
} = toRefs(props);

const activeTab = ref('ringkasan');

const offerForm = useForm({
  billing_dpp_amount: offerAction.value?.defaults?.billing_dpp_amount ?? '',
  contract_sequence: offerAction.value?.defaults?.contract_sequence ?? '',
  offer_validity_days: offerAction.value?.defaults?.offer_validity_days ?? '',
});
const finalReportForm = useForm({
  report_pdf: null,
});
const reportConfigForm = useForm({
  report_reviewer_signer_id: props.reportPreparation?.selected_review_signer_id ? String(props.reportPreparation.selected_review_signer_id) : '',
  report_public_appraiser_signer_id: props.reportPreparation?.selected_public_appraiser_signer_id ? String(props.reportPreparation.selected_public_appraiser_signer_id) : '',
});
const physicalReportForm = useForm({
  action: 'save_details',
  courier: props.physicalReport?.courier ?? '',
  tracking_number: props.physicalReport?.tracking_number ?? '',
  notes: props.physicalReport?.notes ?? '',
});

const negotiationFilters = reactive({
  action: 'all',
  q: '',
});
const revisionDialogOpen = ref(false);
const revisionReviewDialogOpen = ref(false);
const revisionFilePreviewOpen = ref(false);
const fieldCorrectionDialogOpen = ref(false);
const cancellationDialogOpen = ref(false);
const confirmDialogOpen = ref(false);
const selectedRevisionTarget = ref(null);
const selectedReviewItem = ref(null);
const selectedFieldCorrectionTarget = ref(null);
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
const cancelRequestForm = useForm({
  reason: '',
});
const fieldCorrectionForm = useForm({
  target_key: '',
  value: '',
  reason: '',
});
const selectedCancellationAction = ref(null);

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
    case 'preview_ready':
      return 'bg-fuchsia-100 text-fuchsia-900 border-fuchsia-200';
    case 'report_preparation':
    case 'report_ready':
      return 'bg-cyan-100 text-cyan-900 border-cyan-200';
    case 'cancelled':
      return 'bg-rose-100 text-rose-900 border-rose-200';
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

const physicalReportWorkspace = computed(() => physicalReport.value?.workspace ?? {
  show: false,
  ready: false,
  message: null,
});

const physicalReportStateTone = computed(() => {
  switch (physicalReport.value?.state) {
    case 'delivered':
      return 'border-emerald-200 bg-emerald-50 text-emerald-900';
    case 'shipped':
      return 'border-sky-200 bg-sky-50 text-sky-900';
    case 'printed':
    case 'ready_to_print':
      return 'border-amber-200 bg-amber-50 text-amber-900';
    default:
      return 'border-slate-200 bg-slate-50 text-slate-700';
  }
});

const canMarkPhysicalPrinted = computed(() => {
  return Boolean(physicalReportWorkspace.value?.ready && !physicalReport.value?.printed_at);
});

const canMarkPhysicalShipped = computed(() => {
  return Boolean(
    physicalReportWorkspace.value?.ready
    && physicalReport.value?.printed_at
    && !physicalReport.value?.shipped_at
  );
});

const canMarkPhysicalDelivered = computed(() => {
  return Boolean(
    physicalReportWorkspace.value?.ready
    && physicalReport.value?.shipped_at
    && !physicalReport.value?.delivered_at
  );
});

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

  if (action.requires_reason) {
    selectedCancellationAction.value = action;
    cancelRequestForm.reset();
    cancelRequestForm.clearErrors();
    cancellationDialogOpen.value = true;
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

const closeCancellationDialog = () => {
  cancellationDialogOpen.value = false;
  selectedCancellationAction.value = null;
  cancelRequestForm.reset();
  cancelRequestForm.clearErrors();
};

const submitCancellation = () => {
  if (!selectedCancellationAction.value?.url) {
    return;
  }

  cancelRequestForm.post(selectedCancellationAction.value.url, {
    preserveScroll: true,
    onSuccess: () => {
      closeCancellationDialog();
    },
  });
};

const submitPhysicalReport = (action) => {
  if (!physicalReport.value?.update_url || physicalReportForm.processing) {
    return;
  }

  physicalReportForm.transform((data) => ({
    ...data,
    action,
  })).post(physicalReport.value.update_url, {
    preserveScroll: true,
    onFinish: () => {
      physicalReportForm.transform((data) => data);
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

const offerDppValue = computed(() => Number(offerForm.billing_dpp_amount || 0));
const offerVatValue = computed(() => Math.round(offerDppValue.value * 0.11));
const offerGrossValue = computed(() => offerDppValue.value + offerVatValue.value);
const offerPphValue = computed(() => Math.round(offerDppValue.value * 0.02));
const offerNetValue = computed(() => Math.max(0, offerGrossValue.value - offerPphValue.value));

const submitOffer = () => {
  if (!offerAction.value?.url) {
    return;
  }

  offerForm.post(offerAction.value.url, {
    preserveScroll: true,
  });
};

const onFinalReportSelected = (event) => {
  finalReportForm.report_pdf = event?.target?.files?.[0] ?? null;
};

const submitFinalReport = () => {
  if (!reportPreparation.value?.final_upload_url || !finalReportForm.report_pdf) {
    return;
  }

  finalReportForm.post(reportPreparation.value.final_upload_url, {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      finalReportForm.reset();
    },
  });
};

const submitReportConfiguration = () => {
  if (!reportPreparation.value?.configuration_url) {
    return;
  }

  reportConfigForm.post(reportPreparation.value.configuration_url, {
    preserveScroll: true,
  });
};

const downloadReportDraft = () => {
  if (!reportPreparation.value?.draft_download_url) {
    return;
  }

  window.location.assign(reportPreparation.value.draft_download_url);
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
const assetFieldRevisionOptions = computed(() => revisionTargetOptions.value.filter((option) => option.item_type === 'asset_field'));
const assetFieldOptions = (assetId) => assetFieldRevisionOptions.value.filter((option) => option.appraisal_asset_id === assetId);
const showContractTab = computed(() => {
  return record.value?.contract_status_value === 'signed'
    || contractFiles.value.length > 0
    || Boolean(record.value?.contract_number && record.value.contract_number !== '-');
});
const showReportTab = computed(() => {
  return [
    'preview_ready',
    'report_preparation',
    'report_ready',
    'completed',
  ].includes(record.value?.status_value) || Boolean(reportPreparation.value?.draft_available);
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
const canApplyFieldCorrection = computed(() => ['submitted', 'docs_incomplete', 'waiting_offer', 'offer_sent'].includes(record.value?.status_value));

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

const fieldInputComponent = (target) => target?.field?.input_type ?? 'text';
const fieldSelectOptions = (target) => target?.field?.options ?? [];
const fieldDisplayValue = (snapshot) => snapshot?.display ?? 'Belum diisi';
const fieldFormInitialValue = (target) => {
  const value = target?.field?.value;
  return value === null || value === undefined ? '' : String(value);
};

const openFieldCorrectionDialog = (target) => {
  if (!target || !revisionWorkspace.value?.field_correction_url || !canApplyFieldCorrection.value) {
    return;
  }

  selectedFieldCorrectionTarget.value = target;
  fieldCorrectionForm.clearErrors();
  fieldCorrectionForm.target_key = target.key;
  fieldCorrectionForm.value = fieldFormInitialValue(target);
  fieldCorrectionForm.reason = '';
  fieldCorrectionDialogOpen.value = true;
};

const closeFieldCorrectionDialog = () => {
  fieldCorrectionDialogOpen.value = false;
  selectedFieldCorrectionTarget.value = null;
  fieldCorrectionForm.reset();
  fieldCorrectionForm.clearErrors();
};

const submitFieldCorrection = () => {
  if (!revisionWorkspace.value?.field_correction_url || !selectedFieldCorrectionTarget.value) {
    return;
  }

  fieldCorrectionForm.post(revisionWorkspace.value.field_correction_url, {
    preserveScroll: true,
    onSuccess: () => {
      closeFieldCorrectionDialog();
    },
  });
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
          <Button v-if="showReportTab" type="button" :variant="activeTab === 'laporan' ? 'default' : 'ghost'" @click="activeTab = 'laporan'">Laporan</Button>
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
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Tujuan Penilaian</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.valuation_objective_label }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Jenis Laporan</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.report_type_label }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Format Pengiriman</p>
                <p class="mt-2 text-sm text-slate-900">
                  {{ physicalReport.report_format_label || (record.report_format === 'both' ? 'Digital + Hard Copy' : 'Digital') }}
                </p>
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
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Jumlah Hard Copy</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.physical_copies_count ? `${record.physical_copies_count} copy` : '-' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Klien</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.client_name }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Sertifikat On Hand</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.sertifikat_on_hand_confirmed ? 'Ya' : 'Tidak' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nomor Kontrak</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.contract_number }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Tidak Dijaminkan</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.certificate_not_encumbered_confirmed ? 'Ya' : 'Tidak' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Tanggal Kontrak</p>
                <p class="mt-2 text-sm text-slate-900">{{ formatDateTime(record.contract_date) }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Pernyataan Dibuat</p>
                <p class="mt-2 text-sm text-slate-900">{{ formatDateTime(record.certificate_statements_accepted_at) }}</p>
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
                <p class="mt-2 text-sm text-slate-900">{{ formatCurrency(record.ringkasan_tagihan?.nilai_jasa_dpp ?? record.billing_dpp_amount ?? 0) }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">PPN 11%</p>
                <p class="mt-2 text-sm text-slate-900">{{ formatCurrency(record.ringkasan_tagihan?.nilai_ppn ?? 0) }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Tagihan</p>
                <p class="mt-2 text-sm text-slate-900">{{ formatCurrency(record.ringkasan_tagihan?.total_tagihan ?? record.fee_total) }}</p>
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

          <Card
            v-if="activeTab === 'ringkasan' && record.status_value === 'cancelled'"
            class="border-rose-200 bg-rose-50/80"
          >
            <CardHeader>
              <CardTitle class="text-rose-950">Request Dibatalkan Sistem</CardTitle>
              <CardDescription class="text-rose-800">
                Pembatalan ini bersifat final untuk request saat ini. Alasan tersimpan dan juga terlihat di sisi customer.
              </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-3">
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-rose-700">Dicatat Oleh</p>
                <p class="mt-2 text-sm text-rose-950">{{ record.cancelled_by_name || 'Admin' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-rose-700">Waktu Pembatalan</p>
                <p class="mt-2 text-sm text-rose-950">{{ formatDateTime(record.cancelled_at) }}</p>
              </div>
              <div class="md:col-span-3">
                <p class="text-xs font-semibold uppercase tracking-widest text-rose-700">Alasan Pembatalan</p>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-rose-950">{{ record.cancellation_reason || '-' }}</p>
              </div>
            </CardContent>
          </Card>

          <Card v-if="activeTab === 'ringkasan' && marketPreview.version">
            <CardHeader>
              <CardTitle>Preview Kajian Pasar</CardTitle>
              <CardDescription>Snapshot preview yang sedang atau terakhir ditinjau customer sebelum laporan final diupload.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="grid gap-3 md:grid-cols-2">
                <div class="rounded-2xl border p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Estimasi Bawah</p>
                  <p class="mt-2 text-sm font-semibold text-slate-950">{{ formatCurrency(marketPreview.summary?.estimated_value_low) }}</p>
                </div>
                <div class="rounded-2xl border p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Estimasi Atas</p>
                  <p class="mt-2 text-sm font-semibold text-slate-950">{{ formatCurrency(marketPreview.summary?.estimated_value_high) }}</p>
                </div>
              </div>

              <div class="grid gap-3 md:grid-cols-2">
                <div class="rounded-2xl border bg-slate-50 p-4 text-sm">
                  <p><span class="font-medium">Versi preview:</span> {{ marketPreview.version }}</p>
                  <p class="mt-2"><span class="font-medium">Dipublikasikan:</span> {{ formatDateTime(marketPreview.published_at) }}</p>
                  <p class="mt-2"><span class="font-medium">Disetujui customer:</span> {{ formatDateTime(marketPreview.approved_at) }}</p>
                </div>
                <div
                  :class="[
                    'rounded-2xl border p-4 text-sm',
                    marketPreview.appeal_count > 0
                      ? 'border-amber-200 bg-amber-50 text-amber-900'
                      : 'border-emerald-200 bg-emerald-50 text-emerald-900',
                  ]"
                >
                  <p class="font-medium">Status banding</p>
                  <p class="mt-2">
                    {{ marketPreview.appeal_count > 0 ? 'Customer sudah menggunakan 1x kesempatan banding.' : 'Belum ada banding customer.' }}
                  </p>
                  <p v-if="marketPreview.appeal_reason" class="mt-2">{{ marketPreview.appeal_reason }}</p>
                  <p v-if="marketPreview.appeal_submitted_at" class="mt-2 text-xs">
                    Diajukan pada {{ formatDateTime(marketPreview.appeal_submitted_at) }}
                  </p>
                </div>
              </div>

              <div v-if="marketPreview.assets?.length" class="rounded-2xl border">
                <div class="border-b px-4 py-3">
                  <p class="text-sm font-semibold text-slate-950">Breakdown per aset</p>
                </div>
                <div class="divide-y">
                  <div
                    v-for="asset in marketPreview.assets"
                    :key="asset.asset_id"
                    class="grid gap-3 px-4 py-3 lg:grid-cols-[1.6fr_1fr_1fr]"
                  >
                    <div>
                      <p class="font-medium text-slate-950">{{ asset.asset_type_label }}</p>
                      <p class="text-xs text-slate-500">{{ asset.address }}</p>
                    </div>
                    <div class="text-sm text-slate-700">{{ formatCurrency(asset.estimated_value_low) }}</div>
                    <div class="text-sm text-slate-700">{{ formatCurrency(asset.estimated_value_high) }}</div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card v-if="activeTab === 'dokumen'">
            <CardHeader>
              <CardTitle>Permintaan Revisi Data & Dokumen</CardTitle>
              <CardDescription>Gunakan koreksi admin untuk kesalahan yang jelas, atau kirim permintaan revisi ketika customer perlu memperbaiki data, dokumen, atau foto sendiri.</CardDescription>
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
                    <p class="text-xs text-slate-500">Catatan revisi terdahulu tetap disimpan agar histori data dan dokumen bisa diaudit.</p>
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

                      <div v-if="item.field" class="mt-3 grid gap-3 xl:grid-cols-2">
                        <div class="rounded-2xl border bg-white p-3">
                          <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nilai Sebelumnya</p>
                          <p class="mt-3 text-sm font-medium text-slate-950">{{ fieldDisplayValue(item.field.original_value) }}</p>
                        </div>

                        <div class="rounded-2xl border bg-white p-3">
                          <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nilai Revisi</p>
                          <p class="mt-3 text-sm font-medium text-slate-950">
                            {{ item.field.replacement_value ? fieldDisplayValue(item.field.replacement_value) : 'Customer belum mengirim nilai revisi.' }}
                          </p>
                        </div>
                      </div>

                      <div v-else class="mt-3 grid gap-3 xl:grid-cols-2">
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
                  Belum ada batch revisi data atau dokumen untuk request ini.
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
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Data Input Customer</p>
                    <div class="mt-3 space-y-3">
                      <div
                        v-for="option in assetFieldOptions(asset.id)"
                        :key="option.key"
                        class="rounded-2xl border bg-slate-50 p-4"
                      >
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                          <div class="space-y-2">
                            <div>
                              <p class="font-medium text-slate-950">{{ option.field.label }}</p>
                              <p class="mt-1 text-xs text-slate-500">{{ option.description }}</p>
                            </div>
                            <div class="rounded-2xl border bg-white px-3 py-2 text-sm text-slate-700">
                              Nilai saat ini: <span class="font-medium text-slate-950">{{ option.field.display }}</span>
                            </div>
                            <div
                              v-if="targetRevisionStatus(option.key)"
                              class="rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                            >
                              <p class="font-medium">Permintaan revisi aktif</p>
                              <p class="mt-1">{{ targetRevisionStatus(option.key).issue_note }}</p>
                            </div>
                          </div>

                          <div class="flex flex-wrap gap-2">
                            <Button
                              variant="outline"
                              size="sm"
                              :disabled="Boolean(targetRevisionStatus(option.key)) || !canApplyFieldCorrection"
                              @click="openFieldCorrectionDialog(option)"
                            >
                              Perbaiki Admin
                            </Button>
                            <Button
                              variant="outline"
                              size="sm"
                              :disabled="Boolean(targetRevisionStatus(option.key)) || !revisionState.can_create"
                              @click="openRevisionDialog(option)"
                            >
                              Minta Revisi
                            </Button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

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
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nomor Telepon</p>
                <p class="mt-1">{{ requester.phone_number || '-' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">WhatsApp</p>
                <p class="mt-1">{{ requester.whatsapp_number || '-' }}</p>
              </div>
            </CardContent>
          </Card>

          <Card v-if="activeTab === 'ringkasan' && physicalReportWorkspace.show">
            <CardHeader>
              <CardTitle>Pengiriman Hard Copy</CardTitle>
              <CardDescription>
                Workspace manual untuk mencatat cetak, resi, dan status akhir pengiriman laporan fisik.
              </CardDescription>
            </CardHeader>
            <CardContent class="space-y-5">
              <div
                class="rounded-2xl border px-4 py-4"
                :class="physicalReportStateTone"
              >
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-widest">Status Pengiriman</p>
                    <p class="text-base font-semibold">{{ physicalReport.state_label }}</p>
                    <p class="text-sm">
                      {{ physicalReport.state_description || physicalReportWorkspace.message || 'Pengiriman hard copy sedang dipantau secara manual oleh admin.' }}
                    </p>
                  </div>
                  <Badge variant="outline" class="bg-white/70">
                    {{ physicalReport.copies_count || 0 }} copy
                  </Badge>
                </div>
              </div>

              <div class="grid gap-3 md:grid-cols-2">
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Penerima</p>
                  <p class="mt-2 text-sm font-medium text-slate-950">{{ physicalReport.delivery_recipient_name || '-' }}</p>
                  <p class="mt-1 text-sm text-slate-600">{{ physicalReport.delivery_recipient_phone || '-' }}</p>
                </div>
                <div class="rounded-2xl border bg-slate-50 p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Alamat Pengiriman</p>
                  <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ physicalReport.delivery_address || '-' }}</p>
                </div>
              </div>

              <div
                v-if="physicalReportWorkspace.message"
                class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-600"
              >
                {{ physicalReportWorkspace.message }}
              </div>

              <div v-else class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                  <div class="space-y-2">
                    <Label for="physical_report_courier">Kurir</Label>
                    <Input
                      id="physical_report_courier"
                      v-model="physicalReportForm.courier"
                      placeholder="Contoh: JNE, J&T, SiCepat"
                    />
                    <p v-if="physicalReportForm.errors.courier" class="text-xs text-rose-600">
                      {{ physicalReportForm.errors.courier }}
                    </p>
                  </div>

                  <div class="space-y-2">
                    <Label for="physical_report_tracking_number">Nomor Resi</Label>
                    <Input
                      id="physical_report_tracking_number"
                      v-model="physicalReportForm.tracking_number"
                      placeholder="Masukkan nomor resi pengiriman"
                    />
                    <p v-if="physicalReportForm.errors.tracking_number" class="text-xs text-rose-600">
                      {{ physicalReportForm.errors.tracking_number }}
                    </p>
                  </div>
                </div>

                <div class="space-y-2">
                  <Label for="physical_report_notes">Catatan Pengiriman</Label>
                  <Textarea
                    id="physical_report_notes"
                    v-model="physicalReportForm.notes"
                    rows="4"
                    placeholder="Contoh: dikirim melalui pickup sore, paket diasuransikan, atau ada instruksi khusus penerima."
                  />
                  <p v-if="physicalReportForm.errors.notes" class="text-xs text-rose-600">
                    {{ physicalReportForm.errors.notes }}
                  </p>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                  <div class="rounded-2xl border bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Dicetak</p>
                    <p class="mt-2 text-sm font-medium text-slate-950">{{ formatDateTime(physicalReport.printed_at) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ physicalReport.printed_by_name || 'Belum dicatat' }}</p>
                  </div>
                  <div class="rounded-2xl border bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Dikirim</p>
                    <p class="mt-2 text-sm font-medium text-slate-950">{{ formatDateTime(physicalReport.shipped_at) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ physicalReport.courier || 'Kurir belum diisi' }}</p>
                  </div>
                  <div class="rounded-2xl border bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Diterima</p>
                    <p class="mt-2 text-sm font-medium text-slate-950">{{ formatDateTime(physicalReport.delivered_at) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ physicalReport.tracking_number || 'Resi belum diisi' }}</p>
                  </div>
                </div>

                <div class="flex flex-wrap gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    :disabled="physicalReportForm.processing"
                    @click="submitPhysicalReport('save_details')"
                  >
                    Simpan Detail
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    :disabled="!canMarkPhysicalPrinted || physicalReportForm.processing"
                    @click="submitPhysicalReport('mark_printed')"
                  >
                    Tandai Dicetak
                  </Button>
                  <Button
                    type="button"
                    :disabled="!canMarkPhysicalShipped || physicalReportForm.processing"
                    @click="submitPhysicalReport('mark_shipped')"
                  >
                    Tandai Dikirim
                  </Button>
                  <Button
                    type="button"
                    variant="secondary"
                    :disabled="!canMarkPhysicalDelivered || physicalReportForm.processing"
                    @click="submitPhysicalReport('mark_delivered')"
                  >
                    Tandai Diterima
                  </Button>
                </div>
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
                  <p class="mt-3 text-sm font-semibold text-slate-950">{{ formatCurrency(record.ringkasan_tagihan?.total_tagihan ?? record.fee_total) }}</p>
                  <p class="mt-2 text-xs text-slate-500">
                    Nilai Jasa: {{ formatCurrency(record.ringkasan_tagihan?.nilai_jasa_dpp ?? record.billing_dpp_amount ?? 0) }} · PPN 11%: {{ formatCurrency(record.ringkasan_tagihan?.nilai_ppn ?? 0) }}
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

          <Card v-if="activeTab === 'laporan' && (record.status_value === 'report_preparation' || reportPreparation.draft_available)">
            <CardHeader>
              <CardTitle>Persiapan Laporan Final</CardTitle>
              <CardDescription>Atur signer report, download draft DigiPro by KJPP HJAR, lalu upload PDF final yang sudah diproses di luar sistem.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4 text-sm text-cyan-900">
                Draft report hanya untuk kebutuhan internal admin. Customer baru mendapat akses ketika PDF final sudah diupload dan request berubah ke status selesai.
              </div>

              <div class="grid gap-4 xl:grid-cols-3">
                <div class="rounded-2xl border p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Konfigurasi Report</p>
                  <p class="mt-2 text-sm text-slate-700">
                    Pilih reviewer dan penilai publik yang akan tampil pada blok otorisasi draft report.
                  </p>
                  <p class="mt-3 text-xs text-slate-500">
                    Tanggal penilaian: {{ formatDateTime(reportPreparation.valuation_date) }}
                  </p>
                  <div class="mt-4 space-y-3">
                    <div class="space-y-2">
                      <Label for="report_reviewer_signer_id">Reviewer</Label>
                      <Select v-model="reportConfigForm.report_reviewer_signer_id">
                        <SelectTrigger id="report_reviewer_signer_id">
                          <SelectValue placeholder="Pilih reviewer" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem
                            v-for="option in reportPreparation.signer_options?.reviewers || []"
                            :key="option.value"
                            :value="String(option.value)"
                          >
                            {{ option.label }}
                          </SelectItem>
                        </SelectContent>
                      </Select>
                      <p v-if="reportConfigForm.errors.report_reviewer_signer_id" class="text-xs text-rose-600">
                        {{ reportConfigForm.errors.report_reviewer_signer_id }}
                      </p>
                    </div>

                    <div class="space-y-2">
                      <Label for="report_public_appraiser_signer_id">Penilai Publik</Label>
                      <Select v-model="reportConfigForm.report_public_appraiser_signer_id">
                        <SelectTrigger id="report_public_appraiser_signer_id">
                          <SelectValue placeholder="Pilih penilai publik" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem
                            v-for="option in reportPreparation.signer_options?.public_appraisers || []"
                            :key="option.value"
                            :value="String(option.value)"
                          >
                            {{ option.label }}
                          </SelectItem>
                        </SelectContent>
                      </Select>
                      <p v-if="reportConfigForm.errors.report_public_appraiser_signer_id" class="text-xs text-rose-600">
                        {{ reportConfigForm.errors.report_public_appraiser_signer_id }}
                      </p>
                    </div>

                    <Button
                      :disabled="reportConfigForm.processing || !reportPreparation.configuration_url"
                      @click="submitReportConfiguration"
                    >
                      Simpan Konfigurasi Report
                    </Button>
                  </div>
                </div>

                <div class="rounded-2xl border p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Draft Laporan</p>
                  <p class="mt-2 text-sm text-slate-700">
                    {{ reportPreparation.draft_available ? 'Draft tersedia untuk diunduh.' : 'Draft belum tersedia.' }}
                  </p>
                  <p class="mt-2 text-xs text-slate-500">
                    Dibuat: {{ formatDateTime(reportPreparation.draft_generated_at) }}
                  </p>
                  <div v-if="reportPreparation.signer_snapshot" class="mt-3 rounded-xl bg-slate-50 p-3 text-xs text-slate-600">
                    <p>Reviewer: {{ reportPreparation.signer_snapshot?.reviewer?.name || '-' }}</p>
                    <p class="mt-1">Penilai Publik: {{ reportPreparation.signer_snapshot?.public_appraiser?.name || '-' }}</p>
                  </div>
                  <div v-if="reportPreparation.draft_download_url" class="mt-4">
                    <Button type="button" @click="downloadReportDraft">
                      <Download class="mr-2 h-4 w-4" />
                      Download Draft Report
                    </Button>
                  </div>
                </div>

                <div class="rounded-2xl border p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Upload PDF Final</p>
                  <p class="mt-2 text-sm text-slate-700">
                    Upload file final yang sudah diberi QR/barcode P2PK/ELSA dan tanda tangan reviewer serta penilai publik.
                  </p>
                  <div class="mt-4 space-y-3">
                    <Input type="file" accept="application/pdf" @change="onFinalReportSelected" />
                    <p v-if="!reportPreparation.signer_snapshot" class="text-xs text-amber-700">
                      Simpan konfigurasi signer report terlebih dahulu sebelum upload final.
                    </p>
                    <p v-if="finalReportForm.errors.report_pdf" class="text-sm text-rose-600">
                      {{ finalReportForm.errors.report_pdf }}
                    </p>
                    <Button
                      :disabled="finalReportForm.processing || !finalReportForm.report_pdf || !reportPreparation.final_upload_url || !reportPreparation.signer_snapshot"
                      @click="submitFinalReport"
                    >
                      Upload Laporan Final
                    </Button>
                  </div>
                </div>
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
                      <Label for="offer_fee_total">Nilai Jasa (DPP)</Label>
                      <Input id="offer_fee_total" v-model="offerForm.billing_dpp_amount" type="number" min="1" placeholder="15000000" />
                      <p v-if="offerForm.errors.billing_dpp_amount" class="text-xs text-red-500">{{ offerForm.errors.billing_dpp_amount }}</p>
                    </div>

                    <div class="space-y-2">
                      <Label for="offer_validity_days">Masa Berlaku Penawaran</Label>
                      <Input id="offer_validity_days" v-model="offerForm.offer_validity_days" type="number" min="1" placeholder="14" />
                      <p v-if="offerForm.errors.offer_validity_days" class="text-xs text-red-500">{{ offerForm.errors.offer_validity_days }}</p>
                    </div>
                  </div>

                  <div class="rounded-2xl border bg-slate-50 p-4 text-sm text-slate-700">
                    Sistem pembayaran memakai pelunasan penuh melalui payment gateway. Admin hanya menginput Nilai Jasa (DPP), lalu sistem menghitung PPN 11%, PPh 23 Dipotong, dan Total Transfer Customer.
                  </div>

                  <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border bg-white p-4">
                      <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">PPN 11%</p>
                      <p class="mt-2 text-sm font-semibold text-slate-950">{{ formatCurrency(offerVatValue) }}</p>
                    </div>
                    <div class="rounded-2xl border bg-white p-4">
                      <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Tagihan</p>
                      <p class="mt-2 text-sm font-semibold text-slate-950">{{ formatCurrency(offerGrossValue) }}</p>
                    </div>
                    <div class="rounded-2xl border bg-white p-4">
                      <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">PPh 23 Dipotong</p>
                      <p class="mt-2 text-sm font-semibold text-slate-950">{{ formatCurrency(offerPphValue) }}</p>
                    </div>
                    <div class="rounded-2xl border bg-white p-4">
                      <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Transfer Customer</p>
                      <p class="mt-2 text-sm font-semibold text-slate-950">{{ formatCurrency(offerNetValue) }}</p>
                    </div>
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
          <DialogTitle>Permintaan Revisi Customer</DialogTitle>
          <DialogDescription>
            Jelaskan dengan singkat apa yang perlu customer perbaiki pada data, dokumen, atau foto pada item ini.
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

    <Dialog :open="fieldCorrectionDialogOpen" @update:open="(open) => { if (!open) closeFieldCorrectionDialog(); else fieldCorrectionDialogOpen = open; }">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Perbaiki Data oleh Admin</DialogTitle>
          <DialogDescription>
            Gunakan aksi ini jika nilai yang benar sudah bisa ditentukan admin tanpa perlu mengembalikan item ke customer.
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-4">
          <div v-if="selectedFieldCorrectionTarget" class="rounded-2xl border bg-slate-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Field Target</p>
            <p class="mt-2 text-sm font-medium text-slate-950">{{ selectedFieldCorrectionTarget.field.label }}</p>
            <p class="mt-1 text-xs text-slate-500">Nilai saat ini: {{ selectedFieldCorrectionTarget.field.display }}</p>
          </div>

          <div class="space-y-2">
            <Label for="field_correction_value">Nilai Baru</Label>
            <Select
              v-if="fieldInputComponent(selectedFieldCorrectionTarget) === 'select'"
              v-model="fieldCorrectionForm.value"
            >
              <SelectTrigger id="field_correction_value">
                <SelectValue placeholder="Pilih nilai" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="option in fieldSelectOptions(selectedFieldCorrectionTarget)"
                  :key="option.value"
                  :value="String(option.value)"
                >
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
            <Textarea
              v-else-if="fieldInputComponent(selectedFieldCorrectionTarget) === 'textarea'"
              id="field_correction_value"
              v-model="fieldCorrectionForm.value"
              rows="4"
              :placeholder="selectedFieldCorrectionTarget?.field?.placeholder || 'Isi nilai baru'"
            />
            <Input
              v-else
              id="field_correction_value"
              v-model="fieldCorrectionForm.value"
              :type="['number', 'integer'].includes(fieldInputComponent(selectedFieldCorrectionTarget)) ? 'number' : 'text'"
              :step="fieldInputComponent(selectedFieldCorrectionTarget) === 'number' ? '0.0000001' : '1'"
              :placeholder="selectedFieldCorrectionTarget?.field?.placeholder || 'Isi nilai baru'"
            />
            <p v-if="fieldCorrectionForm.errors.value" class="text-xs text-rose-600">{{ fieldCorrectionForm.errors.value }}</p>
          </div>

          <div class="space-y-2">
            <Label for="field_correction_reason">Alasan Koreksi</Label>
            <Textarea
              id="field_correction_reason"
              v-model="fieldCorrectionForm.reason"
              rows="4"
              placeholder="Contoh: typo alamat, titik koordinat tidak valid, atau data luas salah input."
            />
            <p v-if="fieldCorrectionForm.errors.reason" class="text-xs text-rose-600">{{ fieldCorrectionForm.errors.reason }}</p>
            <p v-if="fieldCorrectionForm.errors.target_key" class="text-xs text-rose-600">{{ fieldCorrectionForm.errors.target_key }}</p>
          </div>
        </div>

        <DialogFooter class="gap-2 sm:justify-end">
          <Button type="button" variant="outline" @click="closeFieldCorrectionDialog">
            <X class="h-4 w-4" />
            Batal
          </Button>
          <Button type="button" :disabled="fieldCorrectionForm.processing" @click="submitFieldCorrection">
            Simpan Koreksi
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

    <Dialog :open="cancellationDialogOpen" @update:open="(open) => { if (!open) closeCancellationDialog(); else cancellationDialogOpen = open; }">
      <DialogContent class="sm:max-w-xl">
        <DialogHeader>
          <DialogTitle>Batalkan Request Penilaian</DialogTitle>
          <DialogDescription>
            Gunakan aksi ini hanya jika request memang tidak dapat dilanjutkan, misalnya dokumen legal bermasalah, verifikasi peta bidang gagal, atau ada indikasi data material yang tidak valid.
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-4">
          <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            Status request akan berpindah ke <span class="font-semibold">Dibatalkan</span>, kontrak ikut ditutup, dan alasan pembatalan akan terlihat oleh customer.
          </div>

          <div class="space-y-2">
            <Label for="cancellation_reason">Alasan Pembatalan</Label>
            <Textarea
              id="cancellation_reason"
              v-model="cancelRequestForm.reason"
              rows="6"
              placeholder="Contoh: Sertifikat terindikasi memiliki hak tanggungan aktif sehingga proses penilaian tidak dapat dilanjutkan."
            />
            <p class="text-xs text-slate-500">
              Tulis alasan operasional yang spesifik agar customer memahami apa yang menjadi dasar pembatalan.
            </p>
            <p v-if="cancelRequestForm.errors.reason" class="text-xs text-rose-600">{{ cancelRequestForm.errors.reason }}</p>
          </div>
        </div>

        <DialogFooter class="gap-2 sm:justify-end">
          <Button type="button" variant="outline" @click="closeCancellationDialog">
            Kembali
          </Button>
          <Button
            type="button"
            variant="destructive"
            :disabled="cancelRequestForm.processing"
            @click="submitCancellation"
          >
            Batalkan Request
          </Button>
        </DialogFooter>
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
