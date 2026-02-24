<script setup>
import BaseFileUpload from "@/components/base/BaseFileUpload.vue";
import { useAssetForm } from "@/composables/useAssetForm";

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

const props = defineProps({
    locationRouteName: { type: String, default: "appraisal.create" },
    provinces: { type: Array, default: () => [] },
    regencies: { type: Array, default: () => [] },
    districts: { type: Array, default: () => [] },
    villages: { type: Array, default: () => [] },
    initialAsset: { type: Object, default: null },
});

const emit = defineEmits(["close", "save"]);

const {
    asset,
    isLandOnly,
    isEditing,
    onProvinceChange,
    onRegencyChange,
    onDistrictChange,
    saveAsset,
} = useAssetForm({ props, emit });
</script>

<template>
    <div class="rounded-xl border bg-background overflow-hidden">
        <!-- Header -->
        <div class="border-b bg-background p-5">
            <div class="text-base sm:text-lg font-semibold">
                {{ isEditing ? "Edit Detail Aset" : "Input Detail Aset" }}
            </div>
            <p class="text-xs text-muted-foreground mt-1">
                Isi data aset, lengkapi dokumen & foto, lalu simpan ke portofolio.
            </p>
        </div>

        <!-- Body -->
        <div class="p-6 space-y-10">
            <!-- Informasi Fisik -->
            <section class="space-y-4">
                <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider border-b pb-2">
                    Informasi Fisik
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label>Tipe Properti <span class="text-destructive">*</span></Label>
                        <Select v-model="asset.type">
                            <SelectTrigger class="w-full h-10">
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
                        <Input class="h-10" type="number" v-model="asset.landArea" placeholder="0" />
                    </div>
                </div>

                <div v-if="!isLandOnly" class="rounded-lg border bg-muted/20 p-4 space-y-4">
                    <div class="grid gap-2">
                        <Label>Luas Bangunan (m²) <span class="text-destructive">*</span></Label>
                        <Input class="h-10" type="number" v-model="asset.buildingArea" placeholder="0" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Jml. Lantai <span class="text-destructive">*</span></Label>
                            <Input class="h-10" type="number" v-model="asset.floors" placeholder="Cth: 2" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Thn. Renovasi</Label>
                            <Input class="h-10" type="number" v-model="asset.renovationYear" placeholder="Cth: 2020" />
                        </div>
                    </div>
                </div>
            </section>

            <!-- Lokasi -->
            <section class="rounded-xl border bg-muted/20 p-5 space-y-4">
                <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                    Lokasi Aset
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label>Provinsi <span class="text-destructive">*</span></Label>
                        <Select :model-value="asset.province" @update:model-value="onProvinceChange">
                            <SelectTrigger class="w-full h-10">
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
                            @update:model-value="onRegencyChange"
                        >
                            <SelectTrigger class="w-full h-10">
                                <SelectValue placeholder="Pilih Kab/Kota" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="r in regencies" :key="r.id" :value="r.id">
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
                            @update:model-value="onDistrictChange"
                        >
                            <SelectTrigger class="w-full h-10">
                                <SelectValue placeholder="Pilih Kecamatan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="d in districts" :key="d.id" :value="d.id">
                                    {{ d.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-2">
                        <Label>Kelurahan</Label>
                        <Select :disabled="!asset.district" v-model="asset.village">
                            <SelectTrigger class="w-full h-10">
                                <SelectValue placeholder="Pilih Kelurahan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="v in villages" :key="v.id" :value="v.id">
                                    {{ v.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label>Alamat Lengkap <span class="text-destructive">*</span></Label>
                    <Textarea class="min-h-[88px]" v-model="asset.address" :rows="2" placeholder="Nama Jalan, No, RT/RW..." />
                </div>

                <div class="space-y-3">
                    <div class="text-xs text-muted-foreground">
                        Lokasi presisi wajib diisi. Anda bisa isi <b>koordinat</b> atau <b>link Google Maps</b>.
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Latitude</Label>
                            <Input class="h-10" v-model="asset.coordinatesLat" placeholder="Contoh: -2.9760" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Longitude</Label>
                            <Input class="h-10" v-model="asset.coordinatesLng" placeholder="Contoh: 104.7754" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label>Link Google Maps</Label>
                        <Input class="h-10" v-model="asset.mapsLink" placeholder="https://maps.google.com/?q=..." />
                    </div>
                </div>
            </section>

            <!-- Dokumen -->
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

                    <div class="pt-1" v-if="!isLandOnly">
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

                <!-- Foto 3 Section -->
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

        <!-- Footer -->
        <div class="border-t bg-background p-4 flex justify-end gap-2">
            <Button variant="outline" type="button" @click="$emit('close')">Batal</Button>
            <Button type="button" @click="saveAsset">Simpan Aset</Button>
        </div>
    </div>
</template>
