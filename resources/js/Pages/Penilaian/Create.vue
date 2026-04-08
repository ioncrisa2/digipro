<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { useDialogStore } from '@/stores/dialogStore';
import { AlertTriangle } from 'lucide-vue-next';

import DashboardLayout from "@/layouts/UserDashboardLayout.vue";
import NotificationCenter from "@/components/ui/notification/NotificationCenter.vue";
import AppraisalConsentCard from "@/components/appraisal-create/AppraisalConsentCard.vue";
import AppraisalCreateHeader from "@/components/appraisal-create/AppraisalCreateHeader.vue";
import AppraisalAssetListHeader from "@/components/appraisal-create/AppraisalAssetListHeader.vue";
import AppraisalAssetEmptyState from "@/components/appraisal-create/AppraisalAssetEmptyState.vue";
import AppraisalAssetCard from "@/components/appraisal-create/AppraisalAssetCard.vue";
import AppraisalLegalGateCard from "@/components/appraisal-create/AppraisalLegalGateCard.vue";
import AppraisalSubmitBar from "@/components/appraisal-create/AppraisalSubmitBar.vue";
import SubmittingOverlay from "@/components/appraisal-create/SubmittingOverlay.vue";

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
const needsHardCopy = ref(true);
const physicalCopiesCount = ref(1);

const assets = ref([]);
const submitting = ref(false);
const openAssetIndex = ref(null);

const ASSET_TYPE_OPTIONS = computed(() => props.assetTypeOptions ?? []);

const ASSET_TYPE_LABELS = computed(() => {
    return ASSET_TYPE_OPTIONS.value.reduce((acc, item) => {
        acc[item.value] = item.label;
        return acc;
    }, {});
});

function declineConsent() {
    consentSubmitting.value = true;
    router.post(route('appraisal.consent.decline'), {}, {
        onFinish: () => { consentSubmitting.value = false; }
    });
}

function acceptConsent() {
    consentSubmitting.value = true;
    router.post(route('appraisal.consent.accept'), { accepted: true }, {
        onFinish: () => { consentSubmitting.value = false; }
    });
}

// ============================================
// ASSET REPEATER FUNCTIONS
// ============================================
function createEmptyAsset() {
    return {
        // Internal ID for Vue key
        _uid: `asset_${Date.now()}_${Math.random()}`,

        // Physical info
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

        // Location
        province: null,
        regency: null,
        district: null,
        village: null,
        address: '',
        coordinatesLat: '',
        coordinatesLng: '',
        mapsLink: '',

        // Location cascading data
        _regencies: [],
        _districts: [],
        _villages: [],

        // Labels for display
        provinceName: '',
        regencyName: '',
        districtName: '',
        villageName: '',

        // Documents
        docPbb: null,
        docImb: null,
        docCerts: [],

        // Photos
        photosAccessRoad: [],
        photosFront: [],
        photosInterior: [],
    };
}

function addAsset() {
    assets.value.push(createEmptyAsset());
    openAssetIndex.value = assets.value.length - 1;
}

async function removeAsset(index) {
    const asset = assets.value[index];
    const assetType = getAssetTypeLabel(asset.type);

    const confirmed = await dialog.confirmDestruct({
        title: 'Hapus Aset?',
        description: `Apakah Anda yakin ingin menghapus "${assetType}" dari daftar? Tindakan ini tidak dapat dibatalkan.`,
        confirmText: 'Ya, Hapus',
        cancelText: 'Batal',
    });

    if (confirmed) {
        assets.value.splice(index, 1);
        if (openAssetIndex.value === index) {
            openAssetIndex.value = null;
        } else if (openAssetIndex.value !== null && openAssetIndex.value > index) {
            openAssetIndex.value -= 1;
        }
        notify('success', 'Aset berhasil dihapus');
    }
}

function moveAssetUp(index) {
    if (index === 0) return;
    const temp = assets.value[index];
    assets.value[index] = assets.value[index - 1];
    assets.value[index - 1] = temp;
}

function moveAssetDown(index) {
    if (index === assets.value.length - 1) return;
    const temp = assets.value[index];
    assets.value[index] = assets.value[index + 1];
    assets.value[index + 1] = temp;
}

function toggleAsset(index) {
    openAssetIndex.value = openAssetIndex.value === index ? null : index;
}

// ============================================
// LOCATION CASCADE HANDLERS
// ============================================
function onProvinceChange(assetIndex, provinceId) {
    const asset = assets.value[assetIndex];
    asset.province = provinceId;
    asset.regency = null;
    asset.district = null;
    asset.village = null;

    const selectedProvince = props.provinces.find(p => p.id === provinceId);
    asset.provinceName = selectedProvince?.name || '';

    // Fetch regencies
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
            }
        });
    } else {
        asset._regencies = [];
        asset._districts = [];
        asset._villages = [];
    }
}

function onRegencyChange(assetIndex, regencyId) {
    const asset = assets.value[assetIndex];
    asset.regency = regencyId;
    asset.district = null;
    asset.village = null;

    const selectedRegency = asset._regencies.find(r => r.id === regencyId);
    asset.regencyName = selectedRegency?.name || '';

    // Fetch districts
    if (regencyId) {
        router.reload({
            data: { regency_id: regencyId },
            only: ['districts'],
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                asset._districts = page.props.districts || [];
                asset._villages = [];
            }
        });
    } else {
        asset._districts = [];
        asset._villages = [];
    }
}

function onDistrictChange(assetIndex, districtId) {
    const asset = assets.value[assetIndex];
    asset.district = districtId;
    asset.village = null;

    const selectedDistrict = asset._districts.find(d => d.id === districtId);
    asset.districtName = selectedDistrict?.name || '';

    // Fetch villages
    if (districtId) {
        router.reload({
            data: { district_id: districtId },
            only: ['villages'],
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                asset._villages = page.props.villages || [];
            }
        });
    } else {
        asset._villages = [];
    }
}

function onVillageChange(assetIndex, villageId) {
    const asset = assets.value[assetIndex];
    asset.village = villageId;

    const selectedVillage = asset._villages.find(v => v.id === villageId);
    asset.villageName = selectedVillage?.name || '';
}

// ============================================
// HELPERS
// ============================================
function getAssetTypeLabel(type) {
    return ASSET_TYPE_LABELS.value[type] || type;
}

function isLandOnly(asset) {
    return asset.type === 'tanah' || asset.type === 'land';
}

const hardCopyProfileReady = computed(() => {
  return Boolean(
    authUser.value?.phone_number &&
    authUser.value?.billing_recipient_name &&
    authUser.value?.billing_address_detail
  )
})

const hardCopySummary = computed(() => {
  const user = authUser.value || {}

  return {
    recipient: user.billing_recipient_name || '-',
    phone: user.phone_number || user.whatsapp_number || '-',
    address: user.billing_address_detail || '-',
  }
})

const openProfilePage = () => {
  router.visit(route('profile.edit'))
}

// ============================================
// VALIDATION & SUBMIT
// ============================================
const isFilled = (value) => {
  if (value === null || value === undefined) return false
  if (typeof value === 'string') return value.trim().length > 0
  if (typeof value === 'number') return Number.isFinite(value)
  if (Array.isArray(value)) return value.length > 0
  return true
}

const isAssetComplete = (asset) => {
  const hasCoords = isFilled(asset.coordinatesLat) && isFilled(asset.coordinatesLng)
  const hasMaps = isFilled(asset.mapsLink)
  const hasImb = isLandOnly(asset) ? true : isFilled(asset.docImb)

  const hasLocation =
    isFilled(asset.province) &&
    isFilled(asset.regency) &&
    isFilled(asset.address)

  const hasDocs =
    isFilled(asset.titleDocument) &&
    isFilled(asset.docPbb) &&
    (asset.docCerts?.length ?? 0) > 0 &&
    hasImb

  const hasPhotos =
    (asset.photosAccessRoad?.length ?? 0) > 0 &&
    (asset.photosFront?.length ?? 0) > 0 &&
    (asset.photosInterior?.length ?? 0) > 0

  if (isLandOnly(asset)) {
    return isFilled(asset.landArea) &&
      hasLocation &&
      (hasCoords || hasMaps) &&
      hasDocs &&
      hasPhotos
  }

  return isFilled(asset.landArea) &&
    isFilled(asset.buildingArea) &&
    isFilled(asset.floors) &&
    isFilled(asset.buildYear) &&
    hasLocation &&
    (hasCoords || hasMaps) &&
    hasDocs &&
    hasPhotos
}

const canSubmit = computed(() => {
  return assets.value.length > 0 &&
    assets.value.every(isAssetComplete) &&
    (!needsHardCopy.value || Number(physicalCopiesCount.value || 0) >= 1) &&
    (!needsHardCopy.value || hardCopyProfileReady.value) &&
    !submitting.value
})

const maxFileUploads = computed(() => {
  const parsed = Number(props.uploadLimits?.maxFileUploads)
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null
})

const countFilesInAsset = (asset) => {
  return [
    asset.docPbb ? 1 : 0,
    asset.docImb ? 1 : 0,
    asset.docCerts?.length ?? 0,
    asset.photosAccessRoad?.length ?? 0,
    asset.photosFront?.length ?? 0,
    asset.photosInterior?.length ?? 0,
  ].reduce((sum, val) => sum + val, 0)
}

const totalSelectedFiles = computed(() => {
  return assets.value.reduce((sum, asset) => sum + countFilesInAsset(asset), 0)
})

const normalizeFirstErrorMessage = (value) => {
  if (!value) return ''
  if (typeof value === 'string') return value

  if (Array.isArray(value)) {
    return normalizeFirstErrorMessage(value[0])
  }

  if (typeof value === 'object') {
    const firstValue = Object.values(value)[0]
    return normalizeFirstErrorMessage(firstValue)
  }

  return ''
}

const normalizeKeyLabel = (key) => {
  if (typeof key !== 'string' || !key) return ''

  const match = key.match(/^assets\.(\d+)\.(.+)$/)
  if (!match) return ''

  const assetNumber = Number(match[1]) + 1
  const fieldPath = match[2]

  if (fieldPath.startsWith('photos_access_road')) return `Aset #${assetNumber} - foto akses jalan`
  if (fieldPath.startsWith('photos_front')) return `Aset #${assetNumber} - foto tampak depan`
  if (fieldPath.startsWith('photos_interior')) return `Aset #${assetNumber} - foto tampak dalam`
  if (fieldPath.startsWith('doc_certs')) return `Aset #${assetNumber} - sertifikat tanah`
  if (fieldPath.startsWith('title_document')) return `Aset #${assetNumber} - jenis dokumen tanah`
  if (fieldPath.startsWith('doc_pbb')) return `Aset #${assetNumber} - FC PBB`
  if (fieldPath.startsWith('doc_imb')) return `Aset #${assetNumber} - FC IMB/PBG`
  if (fieldPath.startsWith('coordinates')) return `Aset #${assetNumber} - lokasi presisi`

  return `Aset #${assetNumber}`
}

const cancelCreate = () => {
    router.visit(route('appraisal.list'));
};

async function handleSubmit() {
    if (!canSubmit.value) {
        notify('warning', 'Mohon tambahkan minimal satu aset');
        return;
    }

     const invalidIndex = assets.value.findIndex(a => !isAssetComplete(a))
      if (invalidIndex !== -1) {
        notify('warning', `Lengkapi semua data di Aset #${invalidIndex + 1}`)
        return
      }

    if (maxFileUploads.value && totalSelectedFiles.value > maxFileUploads.value) {
      notify(
        'error',
        `Total file terpilih (${totalSelectedFiles.value}) melebihi batas server (${maxFileUploads.value} file per submit). Kurangi jumlah file atau minta admin menaikkan max_file_uploads.`
      )
      return
    }

    if (needsHardCopy.value && !hardCopyProfileReady.value) {
      notify('warning', 'Lengkapi profil billing dan nomor telepon terlebih dahulu sebelum meminta pengiriman hard copy.')
      return
    }

    if (needsHardCopy.value && Number(physicalCopiesCount.value || 0) < 1) {
      notify('warning', 'Jumlah hard copy minimal 1 jika Anda memilih pengiriman laporan fisik.')
      return
    }

    const confirmed = await dialog.confirm({
        title: 'Konfirmasi Kirim Permohonan',
        description: 'Setelah dikirim, data dan berkas tidak dapat diedit. Pastikan seluruh data aset, dokumen, dan foto yang diunggah sudah benar.',
        confirmText: 'Ya, Kirim Sekarang',
        cancelText: 'Cek Ulang',
    });

    if (!confirmed) return;

    submitting.value = true;

    const formData = new FormData();

    // Append each asset with its files
    assets.value.forEach((asset, i) => {
        // Prepare asset data (without files and internal properties)
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

        // Append files
       if (asset.docPbb) {
          formData.append(`assets[${i}][doc_pbb]`, asset.docPbb);
        }
        if (asset.docImb) {
          formData.append(`assets[${i}][doc_imb]`, asset.docImb);
        }
        if (asset.docCerts?.length) {
          asset.docCerts.forEach((file, idx) => {
            formData.append(`assets[${i}][doc_certs][${idx}]`, file);
          });
        }

        if (asset.photosAccessRoad?.length) {
          asset.photosAccessRoad.forEach((file, idx) => {
            formData.append(`assets[${i}][photos_access_road][${idx}]`, file);
          });
        }
        if (asset.photosFront?.length) {
          asset.photosFront.forEach((file, idx) => {
            formData.append(`assets[${i}][photos_front][${idx}]`, file);
          });
        }
        if (asset.photosInterior?.length) {
          asset.photosInterior.forEach((file, idx) => {
            formData.append(`assets[${i}][photos_interior][${idx}]`, file);
          });
        }
    });

    formData.append('sertifikat_on_hand_confirmed', sertifikatOnHandConfirmed.value ? '1' : '0');
    formData.append('certificate_not_encumbered_confirmed', certificateNotEncumberedConfirmed.value ? '1' : '0');
    formData.append('report_format', 'both');
    formData.append('physical_copies_count', String(Math.max(1, Number(physicalCopiesCount.value || 1))));

    router.post(route('appraisal.store'), formData, {
        forceFormData: true,
        onFinish: () => {
            submitting.value = false;
        },
        onError: (errors) => {
          const entries = Object.entries(errors || {})
          const [firstKey, firstValue] =
            entries.find(([key]) => key === 'assets') ||
            entries[0] ||
            []
          const firstMessage = normalizeFirstErrorMessage(firstValue)
          const fallbackLabel =
            firstKey === 'sertifikat_on_hand_confirmed'
              ? 'Pernyataan sertifikat on hand'
              : firstKey === 'certificate_not_encumbered_confirmed'
                ? 'Pernyataan sertifikat tidak dijaminkan'
                : normalizeKeyLabel(firstKey)

          const message = firstMessage
            ? (fallbackLabel ? `${fallbackLabel}: ${firstMessage}` : firstMessage)
            :
            (fallbackLabel ? `${fallbackLabel} tidak valid. Periksa kembali data yang diunggah.` : 'Validasi gagal. Periksa kembali isian Anda.')

          notify('error', message)
          console.error('Validation errors:', errors)
        }
    });
}

</script>

<template>
    <DashboardLayout>
        <template #title>Buat Permohonan Baru</template>

        <div class="max-w-6xl mx-auto">

            <AppraisalConsentCard
                v-if="needsConsent && consentData"
                :consent-data="consentData"
                :consent-submitting="consentSubmitting"
                @accept="acceptConsent"
                @decline="declineConsent"
            />

            <div v-if="!needsConsent">
                <AppraisalCreateHeader />

                <div class="space-y-6 mb-8">
                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <div class="border-b border-slate-100 px-5 py-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Delivery Preference</p>
                            <h2 class="mt-2 text-lg font-semibold text-slate-950">Pengiriman Hard Copy Wajib</h2>
                            <p class="mt-1 text-sm text-slate-600">
                                Setiap permohonan akan diproses dengan laporan digital dan hard copy. Admin akan memakai data billing di profil Anda sebagai alamat pengiriman fisik.
                            </p>
                        </div>

                        <div class="grid gap-4 px-5 py-5 lg:grid-cols-[minmax(0,1.1fr)_minmax(280px,0.9fr)]">
                            <div class="space-y-4">
                                <div class="rounded-2xl border border-slate-200 bg-slate-950 px-4 py-4 text-white">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">Format Tetap</p>
                                    <p class="mt-2 text-base font-semibold">Digital + Hard Copy</p>
                                    <p class="mt-1 text-sm text-slate-300">
                                        Laporan digital tetap tersedia untuk unduh, sementara hard copy dikirim ke alamat billing yang sudah Anda atur.
                                    </p>
                                </div>

                                <div class="grid gap-3 rounded-2xl border border-amber-200 bg-amber-50/70 p-4 sm:grid-cols-[minmax(0,160px)_1fr]">
                                    <div class="space-y-2">
                                        <label for="physical_copies_count" class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-800">
                                            Jumlah Hard Copy
                                        </label>
                                        <input
                                            id="physical_copies_count"
                                            v-model="physicalCopiesCount"
                                            type="number"
                                            min="1"
                                            max="20"
                                            class="h-10 w-full rounded-xl border border-amber-200 bg-white px-3 text-sm text-slate-900 outline-none ring-0 transition focus:border-amber-400"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <p class="text-sm font-medium text-amber-900">Alamat kirim diambil dari profil billing Anda.</p>
                                        <p class="text-sm text-amber-800">
                                            Pastikan nama penerima, nomor telepon, dan alamat billing sudah benar karena akan dipakai untuk pengiriman laporan fisik.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                <div class="space-y-2">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Snapshot Tujuan Kirim</p>
                                    <p class="text-sm font-medium text-slate-950">{{ hardCopySummary.recipient }}</p>
                                    <p class="text-sm text-slate-600">{{ hardCopySummary.phone }}</p>
                                    <p class="text-sm text-slate-600">{{ hardCopySummary.address }}</p>
                                </div>

                                <div
                                    v-if="!hardCopyProfileReady"
                                    class="mt-4 space-y-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3"
                                >
                                    <p class="text-sm font-medium text-rose-900">
                                        Profil billing belum lengkap untuk pengiriman hard copy.
                                    </p>
                                    <p class="text-sm text-rose-800">
                                        Lengkapi nama penerima laporan, nomor telepon, dan alamat detail billing terlebih dahulu.
                                    </p>
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-xl border border-rose-300 bg-white px-3 py-2 text-sm font-medium text-rose-900 transition hover:bg-rose-100"
                                        @click="openProfilePage"
                                    >
                                        Lengkapi Profil
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                        <div class="flex items-start gap-3">
                            <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                            <div class="space-y-1">
                                <p class="text-sm font-semibold text-amber-900">Perhatian sebelum kirim</p>
                                <p class="text-sm text-amber-800">
                                    Data dan berkas yang sudah dikirim tidak dapat diedit. Pastikan semua informasi aset
                                    dan dokumen sudah benar sebelum mengirim permohonan.
                                </p>
                            </div>
                        </div>
                    </div>

                    <AppraisalAssetListHeader :count="assets.length" :on-add="addAsset" />

                    <AppraisalAssetEmptyState
                        v-if="assets.length === 0"
                        :on-add="addAsset"
                    />

                    <div v-else class="space-y-4">
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
                            :on-toggle="toggleAsset"
                            :on-move-up="moveAssetUp"
                            :on-move-down="moveAssetDown"
                            :on-remove="removeAsset"
                            :on-province-change="onProvinceChange"
                            :on-regency-change="onRegencyChange"
                            :on-district-change="onDistrictChange"
                            :on-village-change="onVillageChange"
                        />
                    </div>
                </div>

                <AppraisalLegalGateCard
                    :sertifikat-on-hand-confirmed="sertifikatOnHandConfirmed"
                    :certificate-not-encumbered-confirmed="certificateNotEncumberedConfirmed"
                    :valuation-objective="props.valuationObjective"
                    :representative-letter-notice="props.representativeLetterNotice"
                    @update:sertifikat-on-hand-confirmed="sertifikatOnHandConfirmed = $event"
                    @update:certificate-not-encumbered-confirmed="certificateNotEncumberedConfirmed = $event"
                />

                <AppraisalSubmitBar
                    :can-submit="canSubmit"
                    :submitting="submitting"
                    :on-submit="handleSubmit"
                    :on-cancel="cancelCreate"
                />

            </div>
        </div>

        <SubmittingOverlay :show="submitting" />

        <NotificationCenter />
    </DashboardLayout>
</template>
