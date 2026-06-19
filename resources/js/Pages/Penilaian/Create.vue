<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { useDialogStore } from '@/stores/dialogStore';
import {
  AlertTriangle,
  CheckCircle2,
  Circle,
  ClipboardCheck,
  FileCheck2,
  Plus,
  Send,
  ShieldCheck,
} from 'lucide-vue-next';

import DashboardLayout from '@/layouts/UserDashboardLayout.vue';
import AppraisalConsentCard from '@/components/appraisal-create/AppraisalConsentCard.vue';
import AppraisalAssetCard from '@/components/appraisal-create/AppraisalAssetCard.vue';
import SubmittingOverlay from '@/components/appraisal-create/SubmittingOverlay.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';

const page = usePage();
const props = defineProps({
  provinces: { type: Array, default: () => [] },
  regencies: { type: Array, default: () => [] },
  districts: { type: Array, default: () => [] },
  villages: { type: Array, default: () => [] },
  assetTypeOptions: { type: Array, default: () => [] },
  usageOptions: { type: Array, default: () => [] },
  titleDocumentOptions: { type: Array, default: () => [] },
  landShapeOptions: { type: Array, default: () => [] },
  landPositionOptions: { type: Array, default: () => [] },
  landConditionOptions: { type: Array, default: () => [] },
  topographyOptions: { type: Array, default: () => [] },
  needsConsent: { type: Boolean, default: false },
  consentData: { type: Object, default: null },
  valuationObjective: { type: Object, default: null },
  representativeLetterNotice: { type: Object, default: null },
  uploadLimits: {
    type: Object,
    default: () => ({
      maxFileUploads: null,
      uploadMaxFilesize: null,
      postMaxSize: null,
    }),
  },
});

const { notify } = useNotification();
const dialog = useDialogStore();
const authUser = computed(() => page.props.auth?.user || {});

const consentSubmitting = ref(false);
const sertifikatOnHandConfirmed = ref(false);
const certificateNotEncumberedConfirmed = ref(false);
const assets = ref([]);
const submitting = ref(false);
const openAssetIndex = ref(null);

const ASSET_TYPE_OPTIONS = computed(() => props.assetTypeOptions ?? []);

const ASSET_TYPE_LABELS = computed(() => ASSET_TYPE_OPTIONS.value.reduce((acc, item) => {
  acc[item.value] = item.label;
  return acc;
}, {}));

function declineConsent() {
  consentSubmitting.value = true;
  router.post(route('appraisal.consent.decline'), {}, {
    onFinish: () => {
      consentSubmitting.value = false;
    },
  });
}

function acceptConsent() {
  consentSubmitting.value = true;
  router.post(route('appraisal.consent.accept'), { accepted: true }, {
    onFinish: () => {
      consentSubmitting.value = false;
    },
  });
}

function createEmptyAsset() {
  return {
    _uid: `asset_${Date.now()}_${Math.random()}`,
    type: 'rumah_tinggal',
    landArea: '',
    buildingArea: '',
    floors: '',
    buildYear: '',
    renovationYear: '',
    peruntukan: '',
    titleDocument: '',
    landShape: '',
    landPosition: '',
    landCondition: '',
    topography: '',
    frontageWidth: '',
    accessRoadWidth: '',
    province: null,
    regency: null,
    district: null,
    village: null,
    address: '',
    coordinatesLat: '',
    coordinatesLng: '',
    mapsLink: '',
    _regencies: [],
    _districts: [],
    _villages: [],
    provinceName: '',
    regencyName: '',
    districtName: '',
    villageName: '',
    docPbb: null,
    docImb: null,
    docCerts: [],
    photosAccessRoad: [],
    photosFront: [],
    photosInterior: [],
  };
}

function addAsset() {
  assets.value.push(createEmptyAsset());
  openAssetIndex.value = assets.value.length - 1;
}

function duplicateAsset(index) {
  const source = assets.value[index];
  if (!source) return;

  const clone = {
    ...source,
    _uid: `asset_${Date.now()}_${Math.random()}`,
    docPbb: null,
    docImb: null,
    docCerts: [],
    photosAccessRoad: [],
    photosFront: [],
    photosInterior: [],
  };

  assets.value.splice(index + 1, 0, clone);
  openAssetIndex.value = index + 1;
  notify('success', 'Aset diduplikasi. Upload dokumen dan foto ulang untuk unit baru.');
}

async function removeAsset(index) {
  const asset = assets.value[index];
  const assetType = getAssetTypeLabel(asset.type);

  const confirmed = await dialog.confirmDestruct({
    title: 'Hapus Aset?',
    description: `Hapus ${assetType} dari permohonan ini? Data dan file yang sudah dipilih akan dilepas dari draft.`,
    confirmText: 'Hapus Aset',
    cancelText: 'Batal',
  });

  if (!confirmed) return;

  assets.value.splice(index, 1);
  if (openAssetIndex.value === index) {
    openAssetIndex.value = assets.value.length ? Math.max(0, index - 1) : null;
  } else if (openAssetIndex.value !== null && openAssetIndex.value > index) {
    openAssetIndex.value -= 1;
  }

  notify('success', 'Aset berhasil dihapus.');
}

function moveAssetUp(index) {
  if (index === 0) return;
  const temp = assets.value[index];
  assets.value[index] = assets.value[index - 1];
  assets.value[index - 1] = temp;
  openAssetIndex.value = index - 1;
}

function moveAssetDown(index) {
  if (index === assets.value.length - 1) return;
  const temp = assets.value[index];
  assets.value[index] = assets.value[index + 1];
  assets.value[index + 1] = temp;
  openAssetIndex.value = index + 1;
}

function toggleAsset(index) {
  openAssetIndex.value = openAssetIndex.value === index ? null : index;
}

function onProvinceChange(assetIndex, provinceId) {
  const asset = assets.value[assetIndex];
  asset.province = provinceId;
  asset.regency = null;
  asset.district = null;
  asset.village = null;

  const selectedProvince = props.provinces.find((p) => p.id === provinceId);
  asset.provinceName = selectedProvince?.name || '';
  asset.regencyName = '';
  asset.districtName = '';
  asset.villageName = '';

  if (provinceId) {
    router.reload({
      data: { province_id: provinceId },
      only: ['regencies'],
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        asset._regencies = page.props.regencies || [];
        asset._districts = [];
        asset._villages = [];
      },
    });
    return;
  }

  asset._regencies = [];
  asset._districts = [];
  asset._villages = [];
}

function onRegencyChange(assetIndex, regencyId) {
  const asset = assets.value[assetIndex];
  asset.regency = regencyId;
  asset.district = null;
  asset.village = null;

  const selectedRegency = asset._regencies.find((r) => r.id === regencyId);
  asset.regencyName = selectedRegency?.name || '';
  asset.districtName = '';
  asset.villageName = '';

  if (regencyId) {
    router.reload({
      data: { regency_id: regencyId },
      only: ['districts'],
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        asset._districts = page.props.districts || [];
        asset._villages = [];
      },
    });
    return;
  }

  asset._districts = [];
  asset._villages = [];
}

function onDistrictChange(assetIndex, districtId) {
  const asset = assets.value[assetIndex];
  asset.district = districtId;
  asset.village = null;

  const selectedDistrict = asset._districts.find((d) => d.id === districtId);
  asset.districtName = selectedDistrict?.name || '';
  asset.villageName = '';

  if (districtId) {
    router.reload({
      data: { district_id: districtId },
      only: ['villages'],
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        asset._villages = page.props.villages || [];
      },
    });
    return;
  }

  asset._villages = [];
}

function onVillageChange(assetIndex, villageId) {
  const asset = assets.value[assetIndex];
  asset.village = villageId;

  const selectedVillage = asset._villages.find((v) => v.id === villageId);
  asset.villageName = selectedVillage?.name || '';
}

function getAssetTypeLabel(type) {
  return ASSET_TYPE_LABELS.value[type] || type || 'Aset';
}

function isLandOnly(asset) {
  return asset.type === 'tanah' || asset.type === 'land';
}

const isFilled = (value) => {
  if (value === null || value === undefined) return false;
  if (typeof value === 'string') return value.trim().length > 0;
  if (typeof value === 'number') return Number.isFinite(value);
  if (Array.isArray(value)) return value.length > 0;
  return true;
};

const hasLocationPrecision = (asset) => {
  return (isFilled(asset.coordinatesLat) && isFilled(asset.coordinatesLng)) || isFilled(asset.mapsLink);
};

const assetMissingItems = (asset) => {
  const missing = [];
  const hasBuilding = !isLandOnly(asset);

  if (!isFilled(asset.type)) missing.push('Tipe properti');
  if (!isFilled(asset.landArea)) missing.push('Luas tanah');
  if (hasBuilding && !isFilled(asset.buildingArea)) missing.push('Luas bangunan');
  if (hasBuilding && !isFilled(asset.floors)) missing.push('Jumlah lantai');
  if (hasBuilding && !isFilled(asset.buildYear)) missing.push('Tahun bangun');
  if (!isFilled(asset.province) || !isFilled(asset.regency) || !isFilled(asset.address)) missing.push('Lokasi dan alamat');
  if (!hasLocationPrecision(asset)) missing.push('Koordinat atau link Maps');
  if (!isFilled(asset.titleDocument)) missing.push('Jenis dokumen tanah');
  if (!isFilled(asset.docPbb)) missing.push('FC PBB');
  if ((asset.docCerts?.length ?? 0) === 0) missing.push('Sertifikat tanah');
  if (hasBuilding && !isFilled(asset.docImb)) missing.push('IMB/PBG');
  if ((asset.photosAccessRoad?.length ?? 0) === 0) missing.push('Foto akses jalan');
  if ((asset.photosFront?.length ?? 0) === 0) missing.push('Foto depan aset');
  if ((asset.photosInterior?.length ?? 0) === 0) missing.push('Foto dalam aset');

  return missing;
};

const assetCompletionPercent = (asset) => {
  const total = !isLandOnly(asset) ? 14 : 10;
  return Math.max(0, Math.min(100, Math.round(((total - assetMissingItems(asset).length) / total) * 100)));
};

const isAssetComplete = (asset) => assetMissingItems(asset).length === 0;

const hardCopyProfileReady = computed(() => Boolean(
  authUser.value?.phone_number &&
  authUser.value?.billing_recipient_name &&
  authUser.value?.billing_address_detail,
));

const legalReady = computed(() => sertifikatOnHandConfirmed.value && certificateNotEncumberedConfirmed.value);
const completeAssetsCount = computed(() => assets.value.filter(isAssetComplete).length);
const incompleteAssetsCount = computed(() => Math.max(0, assets.value.length - completeAssetsCount.value));

const assetSummaryLabel = computed(() => {
  if (assets.value.length === 0) return 'Belum ada aset';
  if (incompleteAssetsCount.value === 0) return `${assets.value.length} aset siap`;
  return `${incompleteAssetsCount.value} aset belum lengkap`;
});

const readinessChecklist = computed(() => [
  {
    label: 'Minimal 1 aset ditambahkan',
    done: assets.value.length > 0,
    helper: assets.value.length > 0 ? `${assets.value.length} unit dalam permohonan` : 'Tambahkan aset pertama.',
  },
  {
    label: 'Data objek lengkap',
    done: assets.value.length > 0 && assets.value.every((asset) => {
      const missing = assetMissingItems(asset);
      return !missing.some((item) => ['Tipe properti', 'Luas tanah', 'Luas bangunan', 'Jumlah lantai', 'Tahun bangun'].includes(item));
    }),
    helper: 'Tipe, luas, lantai, dan tahun bangun.',
  },
  {
    label: 'Dokumen utama diunggah',
    done: assets.value.length > 0 && assets.value.every((asset) => {
      const missing = assetMissingItems(asset);
      return !missing.some((item) => ['Jenis dokumen tanah', 'FC PBB', 'Sertifikat tanah', 'IMB/PBG'].includes(item));
    }),
    helper: 'Sertifikat, PBB, dan IMB/PBG bila ada bangunan.',
  },
  {
    label: 'Foto wajib tersedia',
    done: assets.value.length > 0 && assets.value.every((asset) => {
      const missing = assetMissingItems(asset);
      return !missing.some((item) => item.startsWith('Foto'));
    }),
    helper: 'Akses jalan, tampak depan, dan tampak dalam.',
  },
  {
    label: 'Billing profile lengkap',
    done: hardCopyProfileReady.value,
    helper: hardCopyProfileReady.value ? 'Nama, telepon, dan alamat billing tersedia.' : 'Lengkapi profil billing sebelum submit.',
  },
  {
    label: 'Pernyataan legal disetujui',
    done: legalReady.value,
    helper: 'Sertifikat on hand dan tidak dijaminkan.',
  },
]);

const canSubmit = computed(() => assets.value.length > 0 &&
  assets.value.every(isAssetComplete) &&
  hardCopyProfileReady.value &&
  legalReady.value &&
  !submitting.value);

const submitBlockerText = computed(() => {
  const blocker = readinessChecklist.value.find((item) => !item.done);
  return blocker?.helper || 'Permohonan siap dikirim.';
});

const maxFileUploads = computed(() => {
  const parsed = Number(props.uploadLimits?.maxFileUploads);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
});

const countFilesInAsset = (asset) => [
  asset.docPbb ? 1 : 0,
  asset.docImb ? 1 : 0,
  asset.docCerts?.length ?? 0,
  asset.photosAccessRoad?.length ?? 0,
  asset.photosFront?.length ?? 0,
  asset.photosInterior?.length ?? 0,
].reduce((sum, val) => sum + val, 0);

const totalSelectedFiles = computed(() => assets.value.reduce((sum, asset) => sum + countFilesInAsset(asset), 0));

const normalizeFirstErrorMessage = (value) => {
  if (!value) return '';
  if (typeof value === 'string') return value;
  if (Array.isArray(value)) return normalizeFirstErrorMessage(value[0]);
  if (typeof value === 'object') return normalizeFirstErrorMessage(Object.values(value)[0]);
  return '';
};

const normalizeKeyLabel = (key) => {
  if (typeof key !== 'string' || !key) return '';

  const match = key.match(/^assets\.(\d+)\.(.+)$/);
  if (!match) return '';

  const assetNumber = Number(match[1]) + 1;
  const fieldPath = match[2];

  if (fieldPath.startsWith('photos_access_road')) return `Aset #${assetNumber} - foto akses jalan`;
  if (fieldPath.startsWith('photos_front')) return `Aset #${assetNumber} - foto tampak depan`;
  if (fieldPath.startsWith('photos_interior')) return `Aset #${assetNumber} - foto tampak dalam`;
  if (fieldPath.startsWith('doc_certs')) return `Aset #${assetNumber} - sertifikat tanah`;
  if (fieldPath.startsWith('title_document')) return `Aset #${assetNumber} - jenis dokumen tanah`;
  if (fieldPath.startsWith('doc_pbb')) return `Aset #${assetNumber} - FC PBB`;
  if (fieldPath.startsWith('doc_imb')) return `Aset #${assetNumber} - FC IMB/PBG`;
  if (fieldPath.startsWith('coordinates')) return `Aset #${assetNumber} - lokasi presisi`;

  return `Aset #${assetNumber}`;
};

const cancelCreate = () => {
  router.visit(route('appraisal.list'));
};

const openProfilePage = () => {
  router.visit(route('profile.edit'));
};

async function handleSubmit() {
  if (!canSubmit.value) {
    notify('warning', submitBlockerText.value);
    const invalidIndex = assets.value.findIndex((asset) => !isAssetComplete(asset));
    if (invalidIndex !== -1) openAssetIndex.value = invalidIndex;
    return;
  }

  if (maxFileUploads.value && totalSelectedFiles.value > maxFileUploads.value) {
    notify(
      'error',
      `Total file terpilih (${totalSelectedFiles.value}) melebihi batas server (${maxFileUploads.value} file per submit). Kurangi jumlah file atau minta admin menaikkan max_file_uploads.`,
    );
    return;
  }

  const confirmed = await dialog.confirm({
    title: 'Kirim Permohonan?',
    description: 'Setelah dikirim, data dan berkas tidak dapat diedit. Pastikan aset, dokumen, foto, dan pernyataan legal sudah benar.',
    confirmText: 'Kirim Permohonan',
    cancelText: 'Cek Ulang',
  });

  if (!confirmed) return;

  submitting.value = true;

  const formData = new FormData();

  assets.value.forEach((asset, i) => {
    const assetData = {
      type: asset.type,
      land_area: asset.landArea,
      building_area: asset.buildingArea,
      floors: asset.floors,
      build_year: asset.buildYear,
      renovation_year: asset.renovationYear,
      peruntukan: asset.peruntukan,
      title_document: asset.titleDocument,
      land_shape: asset.landShape,
      land_position: asset.landPosition,
      land_condition: asset.landCondition,
      topography: asset.topography,
      frontage_width: asset.frontageWidth,
      access_road_width: asset.accessRoadWidth,
      province_id: asset.province,
      regency_id: asset.regency,
      district_id: asset.district,
      village_id: asset.village,
      address: asset.address,
      coordinates_lat: asset.coordinatesLat,
      coordinates_lng: asset.coordinatesLng,
      maps_link: asset.mapsLink,
    };

    formData.append(`assets[${i}][data]`, JSON.stringify(assetData));

    if (asset.docPbb) formData.append(`assets[${i}][doc_pbb]`, asset.docPbb);
    if (asset.docImb) formData.append(`assets[${i}][doc_imb]`, asset.docImb);
    asset.docCerts?.forEach((file, idx) => formData.append(`assets[${i}][doc_certs][${idx}]`, file));
    asset.photosAccessRoad?.forEach((file, idx) => formData.append(`assets[${i}][photos_access_road][${idx}]`, file));
    asset.photosFront?.forEach((file, idx) => formData.append(`assets[${i}][photos_front][${idx}]`, file));
    asset.photosInterior?.forEach((file, idx) => formData.append(`assets[${i}][photos_interior][${idx}]`, file));
  });

  formData.append('sertifikat_on_hand_confirmed', sertifikatOnHandConfirmed.value ? '1' : '0');
  formData.append('certificate_not_encumbered_confirmed', certificateNotEncumberedConfirmed.value ? '1' : '0');

  router.post(route('appraisal.store'), formData, {
    forceFormData: true,
    onFinish: () => {
      submitting.value = false;
    },
    onError: (errors) => {
      const entries = Object.entries(errors || {});
      const [firstKey, firstValue] = entries.find(([key]) => key === 'assets') || entries[0] || [];
      const firstMessage = normalizeFirstErrorMessage(firstValue);
      const fallbackLabel =
        firstKey === 'sertifikat_on_hand_confirmed'
          ? 'Pernyataan sertifikat on hand'
          : firstKey === 'certificate_not_encumbered_confirmed'
            ? 'Pernyataan sertifikat tidak dijaminkan'
            : normalizeKeyLabel(firstKey);

      const message = firstMessage
        ? (fallbackLabel ? `${fallbackLabel}: ${firstMessage}` : firstMessage)
        : (fallbackLabel ? `${fallbackLabel} tidak valid. Periksa kembali data yang diunggah.` : 'Validasi gagal. Periksa kembali isian Anda.');

      notify('error', message);
    },
  });
}
</script>

<template>
  <DashboardLayout>
    <template #title>Buat Permohonan Baru</template>

    <div class="mx-auto flex min-h-[calc(100dvh-8rem)] max-w-[1280px] flex-col">
      <AppraisalConsentCard
        v-if="needsConsent && consentData"
        :consent-data="consentData"
        :consent-submitting="consentSubmitting"
        @accept="acceptConsent"
        @decline="declineConsent"
      />

      <div v-else class="flex flex-1 flex-col gap-5">
        <header class="max-w-3xl">
          <div class="max-w-3xl">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-950">
              Formulir Permohonan Penilaian
            </h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">
              Lengkapi data aset, dokumen, dan foto objek untuk pengajuan penilaian.
            </p>
          </div>
        </header>

        <div class="grid gap-5 xl:grid-cols-12 xl:items-start">
          <main class="space-y-5 xl:col-span-8">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h2 class="text-base font-semibold text-slate-950">Prasyarat Pengajuan</h2>
                  <p class="mt-1 text-sm text-slate-500">
                    Selesaikan syarat berikut sebelum permohonan dikirim.
                  </p>
                </div>
                <Badge
                  variant="outline"
                  class="w-fit rounded-full border-amber-200 bg-amber-50 px-3 py-1 text-amber-800"
                >
                  Data terkirim tidak dapat diedit
                </Badge>
              </div>

              <div class="mt-4 grid gap-3 md:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                  <div class="flex items-start gap-3">
                    <CheckCircle2 v-if="hardCopyProfileReady" class="mt-0.5 h-4 w-4 text-emerald-600" />
                    <AlertTriangle v-else class="mt-0.5 h-4 w-4 text-amber-600" />
                    <div class="min-w-0">
                      <p class="text-sm font-semibold text-slate-950">Billing profile</p>
                      <p class="mt-1 text-xs leading-5 text-slate-500">
                        {{ hardCopyProfileReady ? 'Lengkap' : 'Belum lengkap' }}
                      </p>
                      <Button
                        v-if="!hardCopyProfileReady"
                        type="button"
                        variant="link"
                        class="mt-1 h-auto px-0 text-xs text-slate-950"
                        @click="openProfilePage"
                      >
                        Lengkapi Profil
                      </Button>
                    </div>
                  </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                  <div class="flex items-start gap-3">
                    <CheckCircle2 v-if="legalReady" class="mt-0.5 h-4 w-4 text-emerald-600" />
                    <AlertTriangle v-else class="mt-0.5 h-4 w-4 text-amber-600" />
                    <div>
                      <p class="text-sm font-semibold text-slate-950">Pernyataan legal</p>
                      <p class="mt-1 text-xs leading-5 text-slate-500">
                        {{ legalReady ? 'Disetujui' : 'Belum disetujui' }}
                      </p>
                    </div>
                  </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                  <div class="flex items-start gap-3">
                    <FileCheck2 class="mt-0.5 h-4 w-4 text-slate-500" />
                    <div>
                      <p class="text-sm font-semibold text-slate-950">Catatan submit</p>
                      <p class="mt-1 text-xs leading-5 text-slate-500">
                        Dokumen dan foto akan diperiksa oleh reviewer.
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
              <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                  <div>
                    <h2 class="text-lg font-semibold text-slate-950">Daftar Aset</h2>
                    <p class="mt-1 text-sm text-slate-500">
                      Kelola unit aset dalam satu permohonan.
                    </p>
                  </div>
                  <Badge variant="outline" class="rounded-full px-3 py-1">
                    {{ assets.length }} Unit
                  </Badge>
                </div>

                <Button type="button" class="rounded-xl bg-slate-950 hover:bg-slate-800" @click="addAsset">
                  <Plus class="h-4 w-4" />
                  Tambah Aset
                </Button>
              </div>

              <div class="p-4">
                <div
                  v-if="assets.length === 0"
                  class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-5 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-slate-500 shadow-sm">
                      <ClipboardCheck class="h-4 w-4" />
                    </div>
                    <div>
                      <p class="text-sm font-semibold text-slate-950">Belum ada aset ditambahkan</p>
                      <p class="mt-1 text-sm text-slate-500">
                        Tambahkan aset pertama untuk mulai menyusun permohonan.
                      </p>
                    </div>
                  </div>
                  <Button type="button" class="rounded-xl bg-slate-950 hover:bg-slate-800" @click="addAsset">
                    <Plus class="h-4 w-4" />
                    Tambah Aset Pertama
                  </Button>
                </div>

                <div v-else class="space-y-3">
                  <AppraisalAssetCard
                    v-for="(asset, index) in assets"
                    :key="asset._uid"
                    :asset="asset"
                    :index="index"
                    :total="assets.length"
                    :is-open="openAssetIndex === index"
                    :asset-type-options="ASSET_TYPE_OPTIONS"
                    :usage-options="props.usageOptions"
                    :title-document-options="props.titleDocumentOptions"
                    :land-shape-options="props.landShapeOptions"
                    :land-position-options="props.landPositionOptions"
                    :land-condition-options="props.landConditionOptions"
                    :topography-options="props.topographyOptions"
                    :provinces="props.provinces"
                    :get-asset-type-label="getAssetTypeLabel"
                    :is-land-only="isLandOnly"
                    :completion-percent="assetCompletionPercent(asset)"
                    :missing-items="assetMissingItems(asset)"
                    :on-toggle="toggleAsset"
                    :on-move-up="moveAssetUp"
                    :on-move-down="moveAssetDown"
                    :on-duplicate="duplicateAsset"
                    :on-remove="removeAsset"
                    :on-province-change="onProvinceChange"
                    :on-regency-change="onRegencyChange"
                    :on-district-change="onDistrictChange"
                    :on-village-change="onVillageChange"
                  />
                </div>
              </div>
            </section>
          </main>

          <aside class="space-y-4 xl:sticky xl:top-24 xl:col-span-4">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="text-base font-semibold text-slate-950">Ringkasan Permohonan</h2>
              <div class="mt-4 divide-y divide-slate-100 text-sm">
                <div class="flex items-center justify-between gap-4 py-2">
                  <span class="text-slate-500">Tujuan Penilaian</span>
                  <span class="text-right font-medium text-slate-950">
                    {{ valuationObjective?.label || 'Kajian Nilai Pasar Range' }}
                  </span>
                </div>
                <div class="flex items-center justify-between gap-4 py-2">
                  <span class="text-slate-500">Jumlah Aset</span>
                  <span class="font-medium text-slate-950">{{ assets.length }}</span>
                </div>
                <div class="flex items-center justify-between gap-4 py-2">
                  <span class="text-slate-500">Kelengkapan</span>
                  <span class="font-medium text-slate-950">{{ completeAssetsCount }} lengkap / {{ incompleteAssetsCount }} belum</span>
                </div>
                <div class="flex items-center justify-between gap-4 py-2">
                  <span class="text-slate-500">Billing profile</span>
                  <Badge :class="hardCopyProfileReady ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-800'" variant="outline">
                    {{ hardCopyProfileReady ? 'Lengkap' : 'Belum lengkap' }}
                  </Badge>
                </div>
                <div class="flex items-center justify-between gap-4 py-2">
                  <span class="text-slate-500">Pernyataan legal</span>
                  <Badge :class="legalReady ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-800'" variant="outline">
                    {{ legalReady ? 'Disetujui' : 'Belum' }}
                  </Badge>
                </div>
              </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="text-base font-semibold text-slate-950">Checklist Siap Kirim</h2>
              <div class="mt-4 space-y-3">
                <div
                  v-for="item in readinessChecklist"
                  :key="item.label"
                  class="flex items-start gap-3"
                >
                  <CheckCircle2 v-if="item.done" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                  <Circle v-else class="mt-0.5 h-4 w-4 shrink-0 text-slate-300" />
                  <div>
                    <p class="text-sm font-medium text-slate-950">{{ item.label }}</p>
                    <p class="mt-0.5 text-xs leading-5 text-slate-500">{{ item.helper }}</p>
                  </div>
                </div>
              </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <h2 class="text-base font-semibold text-slate-950">Pernyataan Legal</h2>
                  <p class="mt-1 text-sm leading-6 text-slate-500">
                    Wajib disetujui sebelum submit.
                  </p>
                </div>
                <Badge variant="outline" class="rounded-full border-sky-200 bg-sky-50 text-sky-700">
                  Disiapkan sistem
                </Badge>
              </div>

              <div class="mt-4 space-y-3">
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 transition hover:bg-slate-50">
                  <Checkbox
                    :model-value="sertifikatOnHandConfirmed"
                    class="mt-0.5"
                    @update:model-value="sertifikatOnHandConfirmed = Boolean($event)"
                  />
                  <span class="text-sm leading-6 text-slate-700">
                    Sertifikat fisik tersedia dan on hand.
                  </span>
                </label>

                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 transition hover:bg-slate-50">
                  <Checkbox
                    :model-value="certificateNotEncumberedConfirmed"
                    class="mt-0.5"
                    @update:model-value="certificateNotEncumberedConfirmed = Boolean($event)"
                  />
                  <span class="text-sm leading-6 text-slate-700">
                    Sertifikat tidak sedang dijaminkan atau menjadi tanggungan pihak ketiga.
                  </span>
                </label>

                <div class="rounded-xl border border-sky-100 bg-sky-50/70 p-3">
                  <div class="flex items-start gap-3">
                    <ShieldCheck class="mt-0.5 h-4 w-4 text-sky-700" />
                    <p class="text-xs leading-5 text-sky-900">
                      {{ representativeLetterNotice?.description || 'Surat representatif akan disiapkan sistem setelah kontrak ditandatangani.' }}
                    </p>
                  </div>
                </div>
              </div>
            </section>

          </aside>
        </div>

        <section class="sticky bottom-0 z-20 -mx-4 mt-auto border-t border-slate-200 bg-white/95 px-4 py-3 shadow-[0_-8px_24px_rgba(15,23,42,0.08)] backdrop-blur lg:-mx-6 lg:px-6">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0 text-sm">
              <p class="font-semibold text-slate-950">Status pengajuan</p>
              <p class="text-xs text-slate-500">
                {{ canSubmit ? 'Permohonan siap dikirim.' : submitBlockerText }}
              </p>
            </div>
            <div class="flex shrink-0 items-center justify-end gap-2">
              <Button type="button" variant="outline" class="rounded-xl" @click="cancelCreate">Batal</Button>
              <Button
                type="button"
                class="rounded-xl bg-slate-950 hover:bg-slate-800"
                :disabled="!canSubmit"
                @click="handleSubmit"
              >
                Kirim Permohonan
                <Send class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </section>
      </div>
    </div>

    <SubmittingOverlay :show="submitting" />
  </DashboardLayout>
</template>
