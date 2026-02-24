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
  purpose: { type: String, default: "" },
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
        Isi data aset, lalu simpan ke portofolio.
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
            <Label>Tipe Properti *</Label>
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
            <Label>Luas Tanah (m²) *</Label>
            <Input class="h-10" type="number" v-model="asset.landArea" placeholder="0" />
          </div>
        </div>

        <div v-if="!isLandOnly" class="rounded-lg border bg-muted/20 p-4 space-y-4">
          <div class="grid gap-2">
            <Label>Luas Bangunan (m²) *</Label>
            <Input class="h-10" type="number" v-model="asset.buildingArea" placeholder="0" />
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="grid gap-2">
              <Label>Jml. Lantai *</Label>
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
            <Label>Provinsi *</Label>
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
            <Label>Kab/Kota *</Label>
            <Select :disabled="!asset.province" :model-value="asset.regency" @update:model-value="onRegencyChange">
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
            <Select :disabled="!asset.regency" :model-value="asset.district" @update:model-value="onDistrictChange">
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
          <Label>Alamat Lengkap *</Label>
          <Textarea class="min-h-[88px]" v-model="asset.address" :rows="2" placeholder="Nama Jalan, No, RT/RW..." />
        </div>

        <div class="grid gap-2">
          <Label>Titik Koordinat / Link Google Maps</Label>
          <Input class="h-10" v-model="asset.coordinates" placeholder="Opsional" />
        </div>
      </section>

      <!-- Dokumen -->
      <section class="space-y-4">
        <div class="text-sm font-semibold border-b pb-2">
          Dokumen Legalitas &amp; Foto
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="pt-1">
            <BaseFileUpload label="FC PBB Tahun Terakhir *" v-model="asset.docPbb" accept=".pdf" />
          </div>
        </div>

        <div class="pt-1">
          <BaseFileUpload v-if="!isLandOnly" label="FC IMB / PBG *" v-model="asset.docImb" accept=".pdf" />
        </div>

        <div class="pt-1">
          <BaseFileUpload
            v-if="purpose === 'lelang'"
            label="Laporan Lama (Opsional)"
            v-model="asset.docOldReport"
            accept=".pdf"
          />
        </div>

        <div class="pt-1">
          <BaseFileUpload label="FC Sertifikat Tanah (SHM/HGB) *" v-model="asset.docCerts" accept=".pdf" multiple />
        </div>

        <div class="pt-1">
          <BaseFileUpload
            label="Foto Properti (Min 8 Foto) *"
            multiple
            v-model="asset.photos"
            accept=".jpg,.jpeg,.png"
          />
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
