<script setup>
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
import BaseFileUpload from "@/components/base/BaseFileUpload.vue";
import { Trash2, ChevronDown, ArrowUp, ArrowDown, MapPin } from "lucide-vue-next";

defineProps({
  asset: {
    type: Object,
    required: true,
  },
  index: {
    type: Number,
    required: true,
  },
  total: {
    type: Number,
    required: true,
  },
  isOpen: {
    type: Boolean,
    required: true,
  },
  assetTypeOptions: {
    type: Array,
    required: true,
  },
  usageOptions: {
    type: Array,
    required: true,
  },
  titleDocumentOptions: {
    type: Array,
    required: true,
  },
  landShapeOptions: {
    type: Array,
    required: true,
  },
  landPositionOptions: {
    type: Array,
    required: true,
  },
  landConditionOptions: {
    type: Array,
    required: true,
  },
  topographyOptions: {
    type: Array,
    required: true,
  },
  provinces: {
    type: Array,
    required: true,
  },
  getAssetTypeLabel: {
    type: Function,
    required: true,
  },
  isLandOnly: {
    type: Function,
    required: true,
  },
  onToggle: {
    type: Function,
    required: true,
  },
  onMoveUp: {
    type: Function,
    required: true,
  },
  onMoveDown: {
    type: Function,
    required: true,
  },
  onRemove: {
    type: Function,
    required: true,
  },
  onProvinceChange: {
    type: Function,
    required: true,
  },
  onRegencyChange: {
    type: Function,
    required: true,
  },
  onDistrictChange: {
    type: Function,
    required: true,
  },
  onVillageChange: {
    type: Function,
    required: true,
  },
});
</script>

<template>
  <div class="border rounded-lg">
    <div class="px-6 py-4 flex items-center justify-between gap-4">
      <button
        type="button"
        class="flex items-center gap-4 text-left flex-1"
        @click="onToggle(index)"
      >
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
      </button>

      <div class="shrink-0">
        <div class="flex items-center gap-1 rounded-md border bg-muted/30 px-1">
          <span class="hidden lg:inline text-[11px] text-muted-foreground px-1">Urutan</span>
          <Button
            variant="ghost"
            size="icon"
            :disabled="index === 0"
            aria-label="Naikkan urutan aset"
            title="Naikkan urutan aset"
            @click.stop="onMoveUp(index)"
          >
            <ArrowUp class="w-4 h-4" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            :disabled="index === total - 1"
            aria-label="Turunkan urutan aset"
            title="Turunkan urutan aset"
            @click.stop="onMoveDown(index)"
          >
            <ArrowDown class="w-4 h-4" />
          </Button>

          <span class="h-5 w-px bg-border mx-1" />

          <Button
            variant="ghost"
            size="icon"
            aria-label="Hapus aset"
            title="Hapus aset"
            @click.stop="onRemove(index)"
          >
            <Trash2 class="w-4 h-4 text-destructive" />
          </Button>

          <span class="h-5 w-px bg-border mx-1" />

          <span class="hidden lg:inline text-[11px] text-muted-foreground px-1">Detail</span>
          <Button
            variant="ghost"
            size="icon"
            aria-label="Buka/tutup detail aset"
            title="Buka/tutup detail aset"
            @click.stop="onToggle(index)"
          >
            <ChevronDown
              class="w-4 h-4 transition-transform"
              :class="isOpen ? 'rotate-180' : ''"
            />
          </Button>
        </div>
      </div>
    </div>

    <div v-show="isOpen" class="px-6 pb-6">
      <div class="space-y-8 pt-4">
        <section class="space-y-4">
          <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider border-b pb-2">
            Informasi Objek
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="grid gap-2">
              <Label>Tipe Properti <span class="text-destructive">*</span></Label>
              <Select v-model="asset.type">
                <SelectTrigger class="w-full">
                  <SelectValue placeholder="Pilih tipe" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="option in assetTypeOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="grid gap-2">
              <Label>Luas Tanah (m2) <span class="text-destructive">*</span></Label>
              <Input type="number" v-model="asset.landArea" placeholder="0" />
            </div>
          </div>

          <div v-if="!isLandOnly(asset)" class="rounded-lg border bg-muted/20 p-4 space-y-4">
            <div class="grid gap-2">
              <Label>Luas Bangunan (m2) <span class="text-destructive">*</span></Label>
              <Input type="number" v-model="asset.buildingArea" placeholder="0" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="grid gap-2">
                <Label>Jml. Lantai <span class="text-destructive">*</span></Label>
                <Input type="number" v-model="asset.floors" placeholder="Cth: 2" />
              </div>

              <div class="grid gap-2">
                <Label>Tahun Bangun <span class="text-destructive">*</span></Label>
                <Input type="number" v-model="asset.buildYear" placeholder="Cth: 2018" />
              </div>

              <div class="grid gap-2">
                <Label>Thn. Renovasi</Label>
                <Input type="number" v-model="asset.renovationYear" placeholder="Cth: 2020" />
              </div>
            </div>
          </div>
        </section>

        <section class="rounded-xl border bg-muted/20 p-5 space-y-4">
          <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
            Lokasi Objek
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="grid gap-2">
              <Label>Provinsi <span class="text-destructive">*</span></Label>
              <Select
                :model-value="asset.province"
                @update:model-value="onProvinceChange(index, $event)"
              >
                <SelectTrigger class="w-full">
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
                <SelectTrigger class="w-full">
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
                <SelectTrigger class="w-full">
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
                <SelectTrigger class="w-full">
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

        <section class="rounded-xl border bg-muted/10 p-5 space-y-4">
          <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
            Legalitas & Dokumen
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="grid gap-2">
              <Label>Jenis Dokumen Tanah <span class="text-destructive">*</span></Label>
              <Select v-model="asset.titleDocument">
                <SelectTrigger class="w-full">
                  <SelectValue placeholder="Pilih dokumen tanah" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="option in titleDocumentOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="pt-1">
              <BaseFileUpload
                label="FC Sertifikat Tanah *"
                v-model="asset.docCerts"
                accept=".pdf,.jpg,.jpeg,.png"
                multiple
                :max-files="10"
                :max-file-size-mb="10"
                :max-total-size-mb="60"
                helper-text="Pilih jenis dokumen tanah terlebih dahulu, lalu upload scan sertifikat lengkap."
              />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="pt-1">
              <BaseFileUpload
                label="FC PBB Tahun Terakhir *"
                v-model="asset.docPbb"
                accept=".pdf,.jpg,.jpeg,.png"
                :max-file-size-mb="10"
                helper-text="Wajib. Pastikan PBB adalah tahun terakhir yang tersedia."
              />
            </div>

            <div class="pt-1" v-if="!isLandOnly(asset)">
              <BaseFileUpload
                label="FC IMB / PBG *"
                v-model="asset.docImb"
                accept=".pdf,.jpg,.jpeg,.png"
                :max-file-size-mb="10"
                helper-text="Wajib untuk aset yang memiliki bangunan."
              />
            </div>
          </div>
        </section>

        <section class="rounded-xl border bg-muted/20 p-5 space-y-4">
          <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
            Foto Objek
          </div>

          <div class="space-y-6">
            <div class="pt-1">
              <BaseFileUpload
                label="1) Nampak Akses Jalan Property *"
                multiple
                v-model="asset.photosAccessRoad"
                accept=".jpg,.jpeg,.png"
                :max-files="5"
                :max-file-size-mb="15"
                :max-total-size-mb="75"
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
                :max-file-size-mb="15"
                :max-total-size-mb="75"
                helper-text="Ambil foto yang menampilkan seluruh bagian depan properti dalam satu frame. Pastikan pencahayaan cukup."
              />
            </div>

            <div class="pt-1">
              <BaseFileUpload
                label="3) Nampak Dalam Aset *"
                multiple
                v-model="asset.photosInterior"
                accept=".jpg,.jpeg,.png"
                :max-files="20"
                :max-file-size-mb="15"
                :max-total-size-mb="120"
                helper-text="Foto sudut ruangan. Ambil dari pojok ruangan agar area interior terlihat luas dan jelas. Pastikan ruangan terang."
              />
            </div>
          </div>
        </section>

        <section class="rounded-xl border border-dashed bg-muted/10 p-5 space-y-3">
          <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
            Data Penilaian Internal
          </div>
          <p class="text-sm text-muted-foreground">
            Peruntukan, bentuk tanah, posisi tanah, kondisi tanah, topografi, lebar muka, dan lebar akses
            jalan akan dilengkapi oleh reviewer setelah dokumen dan foto objek diperiksa.
          </p>
        </section>
      </div>
    </div>
  </div>
</template>
