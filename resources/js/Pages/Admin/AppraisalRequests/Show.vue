<script setup>
import { computed, reactive, toRefs, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import BaseFileUpload from '@/components/base/BaseFileUpload.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { formatArea, formatCurrency, formatDateTime, formatNumber } from '@/utils/reviewer';

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
  requestFileTypeOptions: {
    type: Array,
    default: () => [],
  },
  assets: {
    type: Array,
    default: () => [],
  },
  assetCreateUrl: {
    type: String,
    default: null,
  },
  assetDocumentTypeOptions: {
    type: Array,
    default: () => [],
  },
  assetPhotoTypeOptions: {
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
  legacyPanelUrl: {
    type: String,
    default: '/legacy-admin',
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
});

const {
  record,
  requester,
  availableActions,
  requestFiles,
  requestFileTypeOptions,
  assets,
  assetCreateUrl,
  assetDocumentTypeOptions,
  assetPhotoTypeOptions,
  payments,
  negotiations,
  negotiationActionOptions,
  negotiationSummary,
  legacyPanelUrl,
  offerAction,
  approveLatestNegotiationAction,
  paymentVerification,
} = toRefs(props);

const offerForm = useForm({
  fee_total: offerAction.value?.defaults?.fee_total ?? '',
  fee_has_dp: Boolean(offerAction.value?.defaults?.fee_has_dp ?? false),
  fee_dp_percent: offerAction.value?.defaults?.fee_dp_percent ?? '',
  contract_sequence: offerAction.value?.defaults?.contract_sequence ?? '',
  offer_validity_days: offerAction.value?.defaults?.offer_validity_days ?? '',
});

const requestFileForm = useForm({
  type: 'other_request_document',
  file: null,
});

const negotiationFilters = reactive({
  action: 'all',
  q: '',
});

const assetDocumentForms = reactive({});
const assetPhotoForms = reactive({});

const ensureAssetUploadForms = (assetList = []) => {
  assetList.forEach((asset) => {
    if (!assetDocumentForms[asset.id]) {
      assetDocumentForms[asset.id] = useForm({
        type: 'doc_pbb',
        file: null,
      });
    }

    if (!assetPhotoForms[asset.id]) {
      assetPhotoForms[asset.id] = useForm({
        type: 'photo_front',
        file: null,
      });
    }
  });

  Object.keys(assetDocumentForms).forEach((assetId) => {
    if (!assetList.some((asset) => String(asset.id) === String(assetId))) {
      delete assetDocumentForms[assetId];
    }
  });

  Object.keys(assetPhotoForms).forEach((assetId) => {
    if (!assetList.some((asset) => String(asset.id) === String(assetId))) {
      delete assetPhotoForms[assetId];
    }
  });
};

watch(assets, (value) => {
  ensureAssetUploadForms(value ?? []);
}, { immediate: true, deep: true });

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

const runAction = (action) => {
  if (!window.confirm(action.message)) {
    return;
  }

  router.post(action.url, {}, {
    preserveScroll: true,
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

  if (!window.confirm(approveLatestNegotiationAction.value.message)) {
    return;
  }

  router.post(approveLatestNegotiationAction.value.url, {}, {
    preserveScroll: true,
  });
};

const verifyPayment = () => {
  if (!paymentVerification.value?.action_url) {
    return;
  }

  if (!window.confirm('Pembayaran sudah valid. Lanjutkan request ini ke proses valuasi?')) {
    return;
  }

  router.post(paymentVerification.value.action_url, {}, {
    preserveScroll: true,
  });
};

const setRequestFile = (file) => {
  requestFileForm.file = Array.isArray(file) ? (file[0] ?? null) : file;
};

const submitRequestFile = () => {
  if (!requestFileForm.file) {
    return;
  }

  requestFileForm.post(route('admin.appraisal-requests.files.store', record.value.id), {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      requestFileForm.reset('file');
      requestFileForm.type = 'other_request_document';
    },
  });
};

const deleteRequestFile = (file) => {
  if (!file?.can_delete) {
    return;
  }

  if (!window.confirm(`Hapus file "${file.original_name}" dari request ini?`)) {
    return;
  }

  router.delete(route('admin.appraisal-requests.files.destroy', [record.value.id, file.id]), {
    preserveScroll: true,
  });
};

const deleteAsset = (asset) => {
  if (!asset?.destroy_url) {
    return;
  }

  if (!window.confirm(`Hapus aset #${asset.order} dari request ini?`)) {
    return;
  }

  router.delete(asset.destroy_url, {
    preserveScroll: true,
  });
};

const setAssetFile = (form, file) => {
  if (!form) {
    return;
  }

  form.file = Array.isArray(file) ? (file[0] ?? null) : file;
};

const assetDocumentFormFor = (assetId) => assetDocumentForms[assetId] ?? null;
const assetPhotoFormFor = (assetId) => assetPhotoForms[assetId] ?? null;

const submitAssetDocument = (asset) => {
  const form = assetDocumentFormFor(asset.id);

  if (!form?.file) {
    return;
  }

  form.post(route('admin.appraisal-requests.assets.files.store', [record.value.id, asset.id]), {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      form.reset('file');
      form.type = 'doc_pbb';
    },
  });
};

const submitAssetPhoto = (asset) => {
  const form = assetPhotoFormFor(asset.id);

  if (!form?.file) {
    return;
  }

  form.post(route('admin.appraisal-requests.assets.files.store', [record.value.id, asset.id]), {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      form.reset('file');
      form.type = 'photo_front';
    },
  });
};

const deleteAssetFile = (asset, file) => {
  if (!file?.destroy_url) {
    return;
  }

  if (!window.confirm(`Hapus file "${file.original_name}" dari aset #${asset.order}?`)) {
    return;
  }

  router.delete(file.destroy_url, {
    preserveScroll: true,
  });
};

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
          <Button
            v-for="action in availableActions"
            :key="action.key"
            :variant="action.variant"
            @click="runAction(action)"
          >
            {{ action.label }}
          </Button>
          <Button variant="outline" as-child>
            <Link :href="route('admin.appraisal-requests.edit', record.id)">Edit Dasar</Link>
          </Button>
          <Button variant="outline" as-child>
            <Link :href="route('admin.appraisal-requests.index')">Kembali ke daftar</Link>
          </Button>
          <Button v-if="record.legacy_url" as-child>
            <a :href="record.legacy_url">Buka di Legacy Admin</a>
          </Button>
          <Button v-else variant="outline" as-child>
            <a :href="legacyPanelUrl">Buka Legacy Admin</a>
          </Button>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <div class="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Ringkasan Permohonan</CardTitle>
              <CardDescription>Data inti request yang sebelumnya tersebar di form dan infolist Filament.</CardDescription>
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
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Skema DP</p>
                <p class="mt-2 text-sm text-slate-900">
                  {{ record.fee_has_dp ? `Ya, ${record.fee_dp_percent ?? 0}%` : 'Tidak ada DP' }}
                </p>
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

          <Card>
            <CardHeader>
              <CardTitle>Dokumen Request</CardTitle>
              <CardDescription>File level request seperti kontrak bertanda tangan dan lampiran global.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="rounded-2xl border bg-slate-50 p-4">
                <div class="grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
                  <div class="space-y-2">
                    <Label for="request_file_type">Tipe File</Label>
                    <Select v-model="requestFileForm.type">
                      <SelectTrigger id="request_file_type">
                        <SelectValue placeholder="Pilih tipe file" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem
                          v-for="option in requestFileTypeOptions"
                          :key="option.value"
                          :value="option.value"
                        >
                          {{ option.label }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                    <p v-if="requestFileForm.errors.type" class="text-xs text-red-500">{{ requestFileForm.errors.type }}</p>
                  </div>

                  <div class="space-y-2">
                    <Label>Upload File</Label>
                    <BaseFileUpload
                      label=""
                      :multiple="false"
                      accept=".pdf,.jpg,.jpeg,.png"
                      :model-value="requestFileForm.file"
                      @update:modelValue="setRequestFile"
                    />
                    <p v-if="requestFileForm.errors.file" class="text-xs text-red-500">{{ requestFileForm.errors.file }}</p>
                  </div>
                </div>

                <div class="mt-4 flex justify-end">
                  <Button type="button" :disabled="requestFileForm.processing || !requestFileForm.file" @click="submitRequestFile">
                    Upload File Request
                  </Button>
                </div>
              </div>

              <div v-for="file in requestFiles" :key="file.id" class="rounded-2xl border p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                  <div>
                    <p class="font-medium text-slate-950">{{ file.original_name }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ file.type_label }} - {{ file.size_label }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ file.mime || '-' }} - {{ formatDateTime(file.created_at) }}</p>
                  </div>
                  <div class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" as-child>
                      <a :href="file.url" target="_blank" rel="noreferrer">Buka File</a>
                    </Button>
                    <Button v-if="file.can_delete" type="button" variant="outline" size="sm" @click="deleteRequestFile(file)">
                      Hapus
                    </Button>
                  </div>
                </div>
              </div>
              <div v-if="!requestFiles.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
                Belum ada dokumen request.
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                  <CardTitle>Aset Terkait</CardTitle>
                  <CardDescription>Ringkasan aset, metadata properti, dokumen, dan foto yang sebelumnya tersebar di relation manager Filament.</CardDescription>
                </div>
                <Button v-if="assetCreateUrl" variant="outline" as-child>
                  <Link :href="assetCreateUrl">Tambah Aset</Link>
                </Button>
              </div>
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
                    <div class="flex flex-wrap justify-end gap-2">
                      <Button v-if="asset.edit_url" variant="outline" size="sm" as-child>
                        <Link :href="asset.edit_url">Edit Aset</Link>
                      </Button>
                      <Button
                        v-if="asset.destroy_url"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="deleteAsset(asset)"
                      >
                        Hapus
                      </Button>
                    </div>
                  </div>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
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

                <div class="mt-5 grid gap-4 xl:grid-cols-2">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Dokumen Aset</p>
                    <div class="mt-3 space-y-3">
                      <div class="rounded-2xl border bg-slate-50 p-4">
                        <div class="grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
                          <div class="space-y-2">
                            <Label :for="`asset_document_type_${asset.id}`">Tipe Dokumen</Label>
                            <Select
                              :model-value="assetDocumentFormFor(asset.id)?.type"
                              @update:model-value="assetDocumentFormFor(asset.id).type = $event"
                            >
                              <SelectTrigger :id="`asset_document_type_${asset.id}`">
                                <SelectValue placeholder="Pilih tipe dokumen" />
                              </SelectTrigger>
                              <SelectContent>
                                <SelectItem
                                  v-for="option in assetDocumentTypeOptions"
                                  :key="option.value"
                                  :value="option.value"
                                >
                                  {{ option.label }}
                                </SelectItem>
                              </SelectContent>
                            </Select>
                            <p v-if="assetDocumentFormFor(asset.id)?.errors?.type" class="text-xs text-red-500">
                              {{ assetDocumentFormFor(asset.id).errors.type }}
                            </p>
                          </div>

                          <div class="space-y-2">
                            <Label>Upload Dokumen</Label>
                            <BaseFileUpload
                              label=""
                              :multiple="false"
                              accept=".pdf,.jpg,.jpeg,.png"
                              :model-value="assetDocumentFormFor(asset.id)?.file"
                              @update:modelValue="setAssetFile(assetDocumentFormFor(asset.id), $event)"
                            />
                            <p v-if="assetDocumentFormFor(asset.id)?.errors?.file" class="text-xs text-red-500">
                              {{ assetDocumentFormFor(asset.id).errors.file }}
                            </p>
                          </div>
                        </div>

                        <div class="mt-4 flex justify-end">
                          <Button
                            type="button"
                            :disabled="assetDocumentFormFor(asset.id)?.processing || !assetDocumentFormFor(asset.id)?.file"
                            @click="submitAssetDocument(asset)"
                          >
                            Upload Dokumen Aset
                          </Button>
                        </div>
                      </div>

                      <div v-for="file in asset.documents" :key="file.id" class="rounded-2xl border bg-slate-50 p-4">
                        <p class="font-medium text-slate-950">{{ file.original_name }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ file.type_label }} - {{ file.size_label }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                          <Button variant="outline" size="sm" as-child>
                            <a :href="file.url" target="_blank" rel="noreferrer">Buka Dokumen</a>
                          </Button>
                          <Button v-if="file.can_delete" type="button" variant="outline" size="sm" @click="deleteAssetFile(asset, file)">
                            Hapus
                          </Button>
                        </div>
                      </div>
                      <div v-if="!asset.documents.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
                        Tidak ada dokumen aset.
                      </div>
                    </div>
                  </div>

                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Foto Aset</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                      <div class="rounded-2xl border bg-slate-50 p-4 sm:col-span-2">
                        <div class="grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
                          <div class="space-y-2">
                            <Label :for="`asset_photo_type_${asset.id}`">Tipe Foto</Label>
                            <Select
                              :model-value="assetPhotoFormFor(asset.id)?.type"
                              @update:model-value="assetPhotoFormFor(asset.id).type = $event"
                            >
                              <SelectTrigger :id="`asset_photo_type_${asset.id}`">
                                <SelectValue placeholder="Pilih tipe foto" />
                              </SelectTrigger>
                              <SelectContent>
                                <SelectItem
                                  v-for="option in assetPhotoTypeOptions"
                                  :key="option.value"
                                  :value="option.value"
                                >
                                  {{ option.label }}
                                </SelectItem>
                              </SelectContent>
                            </Select>
                            <p v-if="assetPhotoFormFor(asset.id)?.errors?.type" class="text-xs text-red-500">
                              {{ assetPhotoFormFor(asset.id).errors.type }}
                            </p>
                          </div>

                          <div class="space-y-2">
                            <Label>Upload Foto</Label>
                            <BaseFileUpload
                              label=""
                              :multiple="false"
                              accept=".jpg,.jpeg,.png"
                              :model-value="assetPhotoFormFor(asset.id)?.file"
                              @update:modelValue="setAssetFile(assetPhotoFormFor(asset.id), $event)"
                            />
                            <p v-if="assetPhotoFormFor(asset.id)?.errors?.file" class="text-xs text-red-500">
                              {{ assetPhotoFormFor(asset.id).errors.file }}
                            </p>
                          </div>
                        </div>

                        <div class="mt-4 flex justify-end">
                          <Button
                            type="button"
                            :disabled="assetPhotoFormFor(asset.id)?.processing || !assetPhotoFormFor(asset.id)?.file"
                            @click="submitAssetPhoto(asset)"
                          >
                            Upload Foto Aset
                          </Button>
                        </div>
                      </div>

                      <a
                        v-for="photo in asset.photos"
                        :key="photo.id"
                        :href="photo.url"
                        target="_blank"
                        rel="noreferrer"
                        class="overflow-hidden rounded-2xl border bg-slate-50"
                      >
                        <img :src="photo.url" :alt="photo.original_name" class="h-40 w-full object-cover" />
                        <div class="p-3">
                          <p class="text-sm font-medium text-slate-950">{{ photo.type_label }}</p>
                          <p class="mt-1 text-xs text-slate-500">{{ photo.original_name }}</p>
                          <div class="mt-3">
                            <Button v-if="photo.can_delete" type="button" variant="outline" size="sm" @click.prevent="deleteAssetFile(asset, photo)">
                              Hapus
                            </Button>
                          </div>
                        </div>
                      </a>
                      <div v-if="!asset.photos.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500 sm:col-span-2">
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
          <Card>
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

          <Card v-if="offerAction">
            <CardHeader>
              <CardTitle>{{ offerAction.label }}</CardTitle>
              <CardDescription>{{ offerAction.description }}</CardDescription>
            </CardHeader>
            <CardContent class="space-y-5">
              <div v-if="approveLatestNegotiationAction" class="rounded-2xl border bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Negosiasi User Terbaru</p>
                <div class="mt-3 space-y-2 text-sm text-slate-700">
                  <p>Harapan fee: {{ formatCurrency(approveLatestNegotiationAction.expected_fee) }}</p>
                  <p>Putaran: {{ approveLatestNegotiationAction.round || '-' }}</p>
                  <p>Catatan: {{ approveLatestNegotiationAction.reason || '-' }}</p>
                </div>
                <div class="mt-4">
                  <Button
                    type="button"
                    variant="outline"
                    @click="approveLatestNegotiation"
                  >
                    {{ approveLatestNegotiationAction.label }}
                  </Button>
                </div>
              </div>

              <form class="space-y-5" @submit.prevent="submitOffer">
                <div class="space-y-2">
                  <Label for="offer_fee_total">Total Fee (Rp)</Label>
                  <Input id="offer_fee_total" v-model="offerForm.fee_total" type="number" min="1" placeholder="15000000" />
                  <p v-if="offerForm.errors.fee_total" class="text-xs text-red-500">{{ offerForm.errors.fee_total }}</p>
                </div>

                <div class="space-y-3">
                  <Label>Skema DP</Label>
                  <label class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm text-slate-700">
                    <Checkbox v-model="offerForm.fee_has_dp" />
                    <span>Gunakan DP</span>
                  </label>
                </div>

                <div class="space-y-2" v-if="offerForm.fee_has_dp">
                  <Label for="offer_fee_dp_percent">Persentase DP (%)</Label>
                  <Input id="offer_fee_dp_percent" v-model="offerForm.fee_dp_percent" type="number" min="0" max="100" step="0.01" placeholder="50" />
                  <p v-if="offerForm.errors.fee_dp_percent" class="text-xs text-red-500">{{ offerForm.errors.fee_dp_percent }}</p>
                </div>

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

                <div class="space-y-2">
                  <Label for="offer_validity_days">Masa Berlaku Penawaran</Label>
                  <Input id="offer_validity_days" v-model="offerForm.offer_validity_days" type="number" min="1" placeholder="14" />
                  <p v-if="offerForm.errors.offer_validity_days" class="text-xs text-red-500">{{ offerForm.errors.offer_validity_days }}</p>
                </div>

                <div class="flex justify-end">
                  <Button type="submit" :disabled="offerForm.processing">
                    {{ offerAction.label }}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>

          <Card>
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

          <Card>
            <CardHeader>
              <CardTitle>Riwayat Negosiasi</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
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

          <Card>
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
  </AdminLayout>
</template>
