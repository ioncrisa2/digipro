<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useDialogStore } from '@/stores/dialogStore';

import BaseFileUpload from "@/components/base/BaseFileUpload.vue";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from "@/components/ui/accordion";

import {
    Plus,
    Trash2,
    ChevronUp,
    ChevronDown,
    MapPin,
    AlertCircle,
} from "lucide-vue-next";

const page = usePage();
const dialog = useDialogStore();

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => []
    },
    provinces: {
        type: Array,
        default: () => []
    },
    locationRouteName: {
        type: String,
        default: 'appraisal.create'
    }
});

const emit = defineEmits(['update:modelValue']);

// Local assets state
const assets = ref([...props.modelValue]);

// Watch for external changes
watch(() => props.modelValue, (newVal) => {
    assets.value = [...newVal];
}, { deep: true });

// Emit changes to parent
watch(assets, (newVal) => {
    emit('update:modelValue', newVal);
}, { deep: true });

// ============================================
// ASSET CRUD
// ============================================
function createEmptyAsset() {
    return {
        _uid: `asset_${Date.now()}_${Math.random()}`,
        // Physical info
        type: 'house',
        landArea: '',
        buildingArea: '',
        floors: '',
        renovationYear: '',

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

// ============================================
// LOCATION CASCADE
// ============================================
function onProvinceChange(assetIndex, provinceId) {
    const asset = assets.value[assetIndex];
    asset.province = provinceId;
    asset.regency = null;
    asset.district = null;
    asset.village = null;

    const selectedProvince = props.provinces.find(p => p.id === provinceId);
    asset.provinceName = selectedProvince?.name || '';

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
    const labels = {
        house: 'Rumah Tinggal',
        land: 'Tanah Kosong',
        shophouse: 'Ruko / Rukan',
        warehouse: 'Gudang / Pabrik',
    };
    return labels[type] || type;
}

function isLandOnly(asset) {
    return asset.type === 'land';
}

const assetsCount = computed(() => assets.value.length);
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold">
                    Daftar Aset
                    <span class="text-muted-foreground font-normal">({{ assetsCount }} Unit)</span>
                </h2>
                <p class="text-sm text-muted-foreground mt-1">
                    Tambahkan satu atau lebih aset yang akan dinilai
                </p>
            </div>
            <Button @click="addAsset" size="sm">
                <Plus class="w-4 h-4 mr-2" />
                Tambah Aset
            </Button>
        </div>

        <!-- Empty State -->
        <div v-if="assetsCount === 0" class="text-center py-12 border-2 border-dashed rounded-lg">
            <AlertCircle class="w-12 h-12 mx-auto text-muted-foreground mb-4" />
            <p class="text-muted-foreground mb-4">Belum ada aset yang ditambahkan</p>
            <Button @click="addAsset">
                <Plus class="w-4 h-4 mr-2" />
                Tambah Aset Pertama
            </Button>
        </div>

        <!-- Asset Items -->
        <Accordion
            v-if="assetsCount > 0"
            type="single"
            collapsible
            class="space-y-4"
            default-value="asset_0"
        >
            <AccordionItem
                v-for="(asset, index) in assets"
                :key="asset._uid"
                :value="`asset_${index}`"
                class="border rounded-lg"
            >
                <!-- Header -->
                <AccordionTrigger class="px-6 py-4 hover:no-underline">
                    <div class="flex items-center justify-between w-full pr-4">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-lg border bg-muted flex items-center justify-center font-bold">
                                {{ index + 1 }}
                            </div>
                            <div class="text-left">
                                <h3 class="font-semibold text-base">
                                    {{ getAssetTypeLabel(asset.type) }}
                                </h3>
                                <p v-if="asset.regencyName" class="text-sm text-muted-foreground flex items-center gap-1">
                                    <MapPin class="w-3 h-3" />
                                    {{ asset.regencyName }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                v-if="index > 0"
                                variant="ghost"
                                size="icon"
                                @click.stop="moveAssetUp(index)"
                                title="Pindah ke atas"
                            >
                                <ChevronUp class="w-4 h-4" />
                            </Button>
                            <Button
                                v-if="index < assetsCount - 1"
                                variant="ghost"
                                size="icon"
                                @click.stop="moveAssetDown(index)"
                                title="Pindah ke bawah"
                            >
                                <ChevronDown class="w-4 h-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                @click.stop="removeAsset(index)"
                                title="Hapus aset"
                            >
                                <Trash2 class="w-4 h-4 text-destructive" />
                            </Button>
                        </div>
                    </div>
                </AccordionTrigger>

                <!-- Form Content -->
                <AccordionContent class="px-6 pb-6">
                    <div class="space-y-8 pt-4">
                        <!-- Physical Info Section -->
                        <section class="space-y-4">
                            <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider border-b pb-2">
                                Informasi Fisik
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="grid gap-2">
                                    <Label>Tipe Properti <span class="text-destructive">*</span></Label>
                                    <Select v-model="asset.type">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih tipe" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="house">Rumah Tinggal</SelectItem>
                                            <SelectItem value="land">Tanah Kosong</SelectItem>
                                            <SelectItem value="shophouse">Ruko / Rukan</SelectItem>
                                            <SelectItem value="warehouse">Gudang / Pabrik</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="grid gap-2">
                                    <Label>Luas Tanah (m²) <span class="text-destructive">*</span></Label>
                                    <Input type="number" v-model="asset.landArea" placeholder="0" />
                                </div>
                            </div>

                            <div v-if="!isLandOnly(asset)" class="rounded-lg border bg-muted/20 p-4 space-y-4">
                                <div class="grid gap-2">
                                    <Label>Luas Bangunan (m²) <span class="text-destructive">*</span></Label>
                                    <Input type="number" v-model="asset.buildingArea" placeholder="0" />
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="grid gap-2">
                                        <Label>Jml. Lantai <span class="text-destructive">*</span></Label>
                                        <Input type="number" v-model="asset.floors" placeholder="Cth: 2" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label>Thn. Renovasi</Label>
                                        <Input type="number" v-model="asset.renovationYear" placeholder="Cth: 2020" />
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Location Section -->
                        <section class="rounded-xl border bg-muted/20 p-5 space-y-4">
                            <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                Lokasi Aset
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="grid gap-2">
                                    <Label>Provinsi <span class="text-destructive">*</span></Label>
                                    <Select
                                        :model-value="asset.province"
                                        @update:model-value="onProvinceChange(index, $event)"
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih Provinsi" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="p in provinces" :key="p.id" :value="p.id">
                                                {{ p.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="grid gap-2">
                                    <Label>Kab/Kota <span class="text-destructive">*</span></Label>
                                    <Select
                                        :disabled="!asset.province"
                                        :model-value="asset.regency"
                                        @update:model-value="onRegencyChange(index, $event)"
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih Kab/Kota" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="r in asset._regencies"
                                                :key="r.id"
                                                :value="r.id"
                                            >
                                                {{ r.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="grid gap-2">
                                    <Label>Kecamatan</Label>
                                    <Select
                                        :disabled="!asset.regency"
                                        :model-value="asset.district"
                                        @update:model-value="onDistrictChange(index, $event)"
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih Kecamatan" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="d in asset._districts"
                                                :key="d.id"
                                                :value="d.id"
                                            >
                                                {{ d.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="grid gap-2">
                                    <Label>Kelurahan</Label>
                                    <Select
                                        :disabled="!asset.district"
                                        :model-value="asset.village"
                                        @update:model-value="onVillageChange(index, $event)"
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih Kelurahan" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="v in asset._villages"
                                                :key="v.id"
                                                :value="v.id"
                                            >
                                                {{ v.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label>Alamat Lengkap <span class="text-destructive">*</span></Label>
                                <Textarea
                                    class="min-h-[88px]"
                                    v-model="asset.address"
                                    :rows="2"
                                    placeholder="Nama Jalan, No, RT/RW..."
                                />
                            </div>

                            <div class="space-y-3">
                                <div class="text-xs text-muted-foreground">
                                    Lokasi presisi wajib diisi. Anda bisa isi <b>koordinat</b> atau <b>link Google Maps</b>.
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="grid gap-2">
                                        <Label>Latitude</Label>
                                        <Input v-model="asset.coordinatesLat" placeholder="Contoh: -2.9760" />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>Longitude</Label>
                                        <Input v-model="asset.coordinatesLng" placeholder="Contoh: 104.7754" />
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <Label>Link Google Maps</Label>
                                    <Input v-model="asset.mapsLink" placeholder="https://maps.google.com/?q=..." />
                                </div>
                            </div>
                        </section>

                        <!-- Documents Section -->
                        <section class="space-y-4">
                            <div class="text-sm font-semibold border-b pb-2">
                                Dokumen Legalitas &amp; Foto
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="pt-1">
                                    <BaseFileUpload
                                        label="FC PBB Tahun Terakhir *"
                                        v-model="asset.docPbb"
                                        accept=".pdf"
                                        :max-file-size-mb="10"
                                        helper-text="Wajib. Pastikan PBB adalah tahun terakhir yang tersedia."
                                    />
                                </div>

                                <div class="pt-1" v-if="!isLandOnly(asset)">
                                    <BaseFileUpload
                                        label="FC IMB / PBG (Opsional)"
                                        v-model="asset.docImb"
                                        accept=".pdf"
                                        :max-file-size-mb="10"
                                        helper-text="Opsional untuk aset yang memiliki bangunan."
                                    />
                                </div>
                            </div>

                            <div class="pt-1">
                                <BaseFileUpload
                                    label="FC Sertifikat Tanah (SHM/HGB) *"
                                    v-model="asset.docCerts"
                                    accept=".pdf"
                                    multiple
                                    :max-files="10"
                                    :max-file-size-mb="10"
                                    :max-total-size-mb="60"
                                    helper-text="Wajib. Upload sertifikat lengkap (bisa multi halaman / multi file)."
                                />
                            </div>

                            <!-- Photo Sections -->
                            <div class="space-y-6">
                                <div class="pt-1">
                                    <BaseFileUpload
                                        label="1) Nampak Akses Jalan Property *"
                                        multiple
                                        v-model="asset.photosAccessRoad"
                                        accept=".jpg,.jpeg,.png"
                                        :max-files="5"
                                        :max-file-size-mb="8"
                                        :max-total-size-mb="40"
                                        helper-text="Ambil foto dari seberang jalan. Pastikan seluruh fasad bangunan dan akses jalan di depannya terlihat jelas dalam satu bingkai."
                                    />
                                </div>

                                <div class="pt-1">
                                    <BaseFileUpload
                                        label="2) Nampak Depan Aset *"
                                        multiple
                                        v-model="asset.photosFront"
                                        accept=".jpg,.jpeg,.png"
                                        :max-files="5"
                                        :max-file-size-mb="8"
                                        :max-total-size-mb="40"
                                        helper-text="Ambil foto yang menampilkan seluruh bagian depan properti dalam satu frame. Pastikan pencahayaan cukup."
                                    />
                                </div>

                                <div class="pt-1">
                                    <BaseFileUpload
                                        label="3) Nampak Dalam Aset *"
                                        multiple
                                        v-model="asset.photosInterior"
                                        accept=".jpg,.jpeg,.png"
                                        :max-files="8"
                                        :max-file-size-mb="8"
                                        :max-total-size-mb="60"
                                        helper-text="Foto sudut ruangan. Ambil dari pojok ruangan agar area interior terlihat luas dan jelas. Pastikan ruangan terang."
                                    />
                                </div>
                            </div>
                        </section>
                    </div>
                </AccordionContent>
            </AccordionItem>
        </Accordion>
    </div>
</template>
