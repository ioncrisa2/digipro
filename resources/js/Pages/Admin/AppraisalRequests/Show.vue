<script setup>
import { computed, reactive, ref, toRefs } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Pencil } from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
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
} = toRefs(props);

const activeTab = ref('ringkasan');

const offerForm = useForm({
  fee_total: offerAction.value?.defaults?.fee_total ?? '',
  fee_has_dp: Boolean(offerAction.value?.defaults?.fee_has_dp ?? false),
  fee_dp_percent: offerAction.value?.defaults?.fee_dp_percent ?? '',
  contract_sequence: offerAction.value?.defaults?.contract_sequence ?? '',
  offer_validity_days: offerAction.value?.defaults?.offer_validity_days ?? '',
});

const negotiationFilters = reactive({
  action: 'all',
  q: '',
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

const contractFiles = computed(() => (requestFiles.value ?? []).filter((file) => file.type === 'contract_signed_pdf'));
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
            <Link :href="route('admin.appraisal-requests.edit', record.id)"><Pencil class="h-4 w-4" />Edit Dasar</Link>
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

          <Card v-if="activeTab === 'dokumen'">
            <CardHeader>
              <CardTitle>Dokumen Request</CardTitle>
              <CardDescription>Dokumen yang diajukan customer untuk diverifikasi admin.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                Admin hanya memverifikasi dokumen yang diunggah customer. Jika ada dokumen yang salah atau tidak cocok,
                gunakan aksi status untuk meminta customer melakukan revisi.
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
                  </div>
                </div>
              </div>
              <div v-if="!requestFiles.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
                Belum ada dokumen request.
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

                <div v-if="activeTab === 'dokumen'" class="mt-5 grid gap-4 xl:grid-cols-2">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Dokumen Aset</p>
                    <div class="mt-3 space-y-3">
                      <div v-for="file in asset.documents" :key="file.id" class="rounded-2xl border bg-slate-50 p-4">
                        <p class="font-medium text-slate-950">{{ file.original_name }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ file.type_label }} - {{ file.size_label }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                          <Button variant="outline" size="sm" as-child>
                            <a :href="file.url" target="_blank" rel="noreferrer">Buka Dokumen</a>
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

          <Card v-if="activeTab === 'negosiasi' && offerAction">
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

          <Card v-if="activeTab === 'kontrak'">
            <CardHeader>
              <CardTitle>Kontrak</CardTitle>
              <CardDescription>Ringkasan kontrak dan file tanda tangan yang sudah dikirim oleh customer.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-5">
              <div class="grid gap-4 md:grid-cols-2">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nomor Kontrak</p>
                  <p class="mt-2 text-sm text-slate-900">{{ record.contract_number }}</p>
                </div>
                <div>
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Status Kontrak</p>
                  <div class="mt-2">
                    <Badge variant="outline" :class="statusTone(record.contract_status_value)">{{ record.contract_status_label }}</Badge>
                  </div>
                </div>
                <div>
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Tanggal Kontrak</p>
                  <p class="mt-2 text-sm text-slate-900">{{ formatDateTime(record.contract_date) }}</p>
                </div>
                <div>
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Fee</p>
                  <p class="mt-2 text-sm text-slate-900">{{ formatCurrency(record.fee_total) }}</p>
                </div>
              </div>

              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">File Kontrak</p>
                <div v-if="contractFiles.length" class="mt-4 space-y-3">
                  <div v-for="file in contractFiles" :key="file.id" class="rounded-2xl border bg-white p-4">
                    <p class="font-medium text-slate-950">{{ file.original_name }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ file.type_label }} - {{ file.size_label }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ file.mime || '-' }} - {{ formatDateTime(file.created_at) }}</p>
                    <div class="mt-3">
                      <Button variant="outline" size="sm" as-child>
                        <a :href="file.url" target="_blank" rel="noreferrer">Buka File Kontrak</a>
                      </Button>
                    </div>
                  </div>
                </div>
                <div v-else class="mt-4 rounded-2xl border border-dashed p-4 text-sm text-slate-500">
                  Belum ada file kontrak yang diunggah customer.
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
  </AdminLayout>
</template>
