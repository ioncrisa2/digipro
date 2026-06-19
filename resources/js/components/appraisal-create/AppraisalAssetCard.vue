<script setup>
import { computed } from 'vue';
import {
  ArrowDown,
  ArrowUp,
  ChevronDown,
  Copy,
  Info,
  MapPin,
  Trash2,
} from 'lucide-vue-next';

import BaseFileUpload from '@/components/base/BaseFileUpload.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

const props = defineProps({
  asset: { type: Object, required: true },
  index: { type: Number, required: true },
  total: { type: Number, required: true },
  isOpen: { type: Boolean, required: true },
  completionPercent: { type: Number, default: 0 },
  missingItems: { type: Array, default: () => [] },
  assetTypeOptions: { type: Array, required: true },
  usageOptions: { type: Array, required: true },
  titleDocumentOptions: { type: Array, required: true },
  landShapeOptions: { type: Array, required: true },
  landPositionOptions: { type: Array, required: true },
  landConditionOptions: { type: Array, required: true },
  topographyOptions: { type: Array, required: true },
  provinces: { type: Array, required: true },
  getAssetTypeLabel: { type: Function, required: true },
  isLandOnly: { type: Function, required: true },
  onToggle: { type: Function, required: true },
  onMoveUp: { type: Function, required: true },
  onMoveDown: { type: Function, required: true },
  onDuplicate: { type: Function, required: true },
  onRemove: { type: Function, required: true },
  onProvinceChange: { type: Function, required: true },
  onRegencyChange: { type: Function, required: true },
  onDistrictChange: { type: Function, required: true },
  onVillageChange: { type: Function, required: true },
});

const status = computed(() => {
  if (props.completionPercent >= 100) {
    return {
      label: 'Siap dikirim',
      class: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    };
  }

  if (props.completionPercent > 0) {
    return {
      label: 'Belum lengkap',
      class: 'border-amber-200 bg-amber-50 text-amber-800',
    };
  }

  return {
    label: 'Draft',
    class: 'border-slate-200 bg-slate-50 text-slate-600',
  };
});

const locationLabel = computed(() => {
  const parts = [
    props.asset.villageName,
    props.asset.districtName,
    props.asset.regencyName,
  ].filter(Boolean);

  if (parts.length) return parts.slice(0, 2).join(', ');
  return props.asset.address || 'Lokasi belum diisi';
});

const progressBarClass = computed(() => {
  if (props.completionPercent >= 100) return 'bg-emerald-500';
  if (props.completionPercent >= 60) return 'bg-amber-500';
  return 'bg-slate-400';
});

const topMissingItems = computed(() => props.missingItems.slice(0, 4));
</script>

<template>
  <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
    <div class="border-b border-slate-200 bg-white px-4 py-3">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <button
          type="button"
          class="flex min-w-0 flex-1 items-start gap-3 text-left"
          @click="onToggle(index)"
        >
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-950 text-sm font-semibold text-white">
            {{ index + 1 }}
          </span>
          <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-2">
              <span class="text-base font-semibold text-slate-950">
                Unit {{ index + 1 }} - {{ getAssetTypeLabel(asset.type) }}
              </span>
              <Badge variant="outline" :class="status.class" class="rounded-full">
                {{ status.label }}
              </Badge>
            </span>
            <span class="mt-1 flex items-center gap-1 text-sm text-slate-500">
              <MapPin class="h-3.5 w-3.5" />
              {{ locationLabel }}
            </span>
          </span>
        </button>

        <div class="flex shrink-0 items-center gap-2">
          <div class="hidden w-32 md:block">
            <div class="mb-1 flex items-center justify-between text-[11px] text-slate-500">
              <span>{{ completionPercent }}%</span>
              <span>lengkap</span>
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
              <div class="h-full rounded-full transition-all" :class="progressBarClass" :style="{ width: `${completionPercent}%` }" />
            </div>
          </div>

          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            :disabled="index === 0"
            aria-label="Naikkan urutan aset"
            title="Naikkan urutan"
            @click.stop="onMoveUp(index)"
          >
            <ArrowUp class="h-4 w-4" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            :disabled="index === total - 1"
            aria-label="Turunkan urutan aset"
            title="Turunkan urutan"
            @click.stop="onMoveDown(index)"
          >
            <ArrowDown class="h-4 w-4" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            aria-label="Duplikasi aset"
            title="Duplikasi aset"
            @click.stop="onDuplicate(index)"
          >
            <Copy class="h-4 w-4" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            aria-label="Hapus aset"
            title="Hapus aset"
            @click.stop="onRemove(index)"
          >
            <Trash2 class="h-4 w-4 text-rose-600" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            aria-label="Buka atau tutup detail aset"
            title="Buka/tutup detail"
            @click.stop="onToggle(index)"
          >
            <ChevronDown class="h-4 w-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" />
          </Button>
        </div>
      </div>

      <div v-if="missingItems.length" class="mt-3 flex flex-wrap gap-2">
        <span class="text-xs font-medium text-slate-500">Kurang:</span>
        <Badge
          v-for="item in topMissingItems"
          :key="item"
          variant="outline"
          class="rounded-full border-slate-200 bg-slate-50 text-slate-600"
        >
          {{ item }}
        </Badge>
        <Badge v-if="missingItems.length > topMissingItems.length" variant="outline" class="rounded-full">
          +{{ missingItems.length - topMissingItems.length }}
        </Badge>
      </div>
    </div>

    <div v-show="isOpen" class="space-y-5 px-4 py-5">
      <section class="space-y-3">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-2">
          <h3 class="text-sm font-semibold text-slate-950">Informasi Objek</h3>
          <span class="text-xs text-slate-500">Data dasar aset</span>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div class="grid gap-2">
            <Label>Tipe Properti <span class="text-rose-600">*</span></Label>
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
            <Label>Luas Tanah (m2) <span class="text-rose-600">*</span></Label>
            <Input v-model="asset.landArea" type="number" min="0" placeholder="0" />
          </div>

          <template v-if="!isLandOnly(asset)">
            <div class="grid gap-2">
              <Label>Luas Bangunan (m2) <span class="text-rose-600">*</span></Label>
              <Input v-model="asset.buildingArea" type="number" min="0" placeholder="0" />
            </div>

            <div class="grid gap-2">
              <Label>Jumlah Lantai <span class="text-rose-600">*</span></Label>
              <Input v-model="asset.floors" type="number" min="0" placeholder="Contoh: 2" />
            </div>

            <div class="grid gap-2">
              <Label>Tahun Bangun <span class="text-rose-600">*</span></Label>
              <Input v-model="asset.buildYear" type="number" min="1900" placeholder="Contoh: 2018" />
            </div>

            <div class="grid gap-2">
              <Label>Tahun Renovasi</Label>
              <Input v-model="asset.renovationYear" type="number" min="1900" placeholder="Opsional" />
            </div>
          </template>
        </div>
      </section>

      <section class="space-y-3">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-2">
          <h3 class="text-sm font-semibold text-slate-950">Lokasi Objek</h3>
          <span class="text-xs text-slate-500">Alamat dan titik lokasi</span>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div class="grid gap-2">
            <Label>Provinsi <span class="text-rose-600">*</span></Label>
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
            <Label>Kab/Kota <span class="text-rose-600">*</span></Label>
            <Select
              :disabled="!asset.province"
              :model-value="asset.regency"
              @update:model-value="onRegencyChange(index, $event)"
            >
              <SelectTrigger class="w-full">
                <SelectValue placeholder="Pilih Kab/Kota" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="r in asset._regencies" :key="r.id" :value="r.id">
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
                <SelectItem v-for="d in asset._districts" :key="d.id" :value="d.id">
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
                <SelectItem v-for="v in asset._villages" :key="v.id" :value="v.id">
                  {{ v.name }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="grid gap-2 md:col-span-2">
            <Label>Alamat Lengkap <span class="text-rose-600">*</span></Label>
            <Textarea
              v-model="asset.address"
              class="min-h-[82px]"
              :rows="2"
              placeholder="Nama jalan, nomor, patokan, RT/RW..."
            />
          </div>

          <div class="grid gap-2">
            <Label>Latitude</Label>
            <Input v-model="asset.coordinatesLat" placeholder="Contoh: -6.2000" />
          </div>

          <div class="grid gap-2">
            <Label>Longitude</Label>
            <Input v-model="asset.coordinatesLng" placeholder="Contoh: 106.8167" />
          </div>

          <div class="grid gap-2 md:col-span-2">
            <Label>Link Google Maps</Label>
            <Input v-model="asset.mapsLink" placeholder="https://maps.google.com/?q=..." />
            <p class="text-xs text-slate-500">
              Isi koordinat atau link Google Maps agar reviewer bisa memverifikasi titik objek.
            </p>
          </div>
        </div>
      </section>

      <section class="space-y-3">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-2">
          <h3 class="text-sm font-semibold text-slate-950">Legalitas & Dokumen</h3>
          <span class="text-xs text-slate-500">PDF, JPG, PNG</span>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div class="grid gap-2">
            <Label>Jenis Dokumen Tanah <span class="text-rose-600">*</span></Label>
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

          <BaseFileUpload
            v-model="asset.docCerts"
            label="FC Sertifikat Tanah"
            accept=".pdf,.jpg,.jpeg,.png"
            multiple
            compact
            :max-files="10"
            :max-file-size-mb="10"
            :max-total-size-mb="60"
            helper-text="Upload sertifikat lengkap."
          />

          <BaseFileUpload
            v-model="asset.docPbb"
            label="FC PBB Tahun Terakhir"
            accept=".pdf,.jpg,.jpeg,.png"
            compact
            :max-file-size-mb="10"
            helper-text="PBB tahun terakhir."
          />

          <BaseFileUpload
            v-if="!isLandOnly(asset)"
            v-model="asset.docImb"
            label="FC IMB / PBG"
            accept=".pdf,.jpg,.jpeg,.png"
            compact
            :max-file-size-mb="10"
            helper-text="Wajib untuk aset bangunan."
          />
        </div>
      </section>

      <section class="space-y-3">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-2">
          <h3 class="text-sm font-semibold text-slate-950">Foto Objek</h3>
          <span class="text-xs text-slate-500">JPG, PNG, WEBP</span>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
          <BaseFileUpload
            v-model="asset.photosAccessRoad"
            label="Akses Jalan Property"
            multiple
            compact
            accept=".jpg,.jpeg,.png,.webp"
            :max-files="5"
            :max-file-size-mb="15"
            :max-total-size-mb="75"
            helper-text="Minimal 1 foto."
          />

          <BaseFileUpload
            v-model="asset.photosFront"
            label="Tampak Depan Aset"
            multiple
            compact
            accept=".jpg,.jpeg,.png,.webp"
            :max-files="5"
            :max-file-size-mb="15"
            :max-total-size-mb="75"
            helper-text="Minimal 1 foto."
          />

          <BaseFileUpload
            v-model="asset.photosInterior"
            label="Tampak Dalam Aset"
            multiple
            compact
            accept=".jpg,.jpeg,.png,.webp"
            :max-files="20"
            :max-file-size-mb="15"
            :max-total-size-mb="120"
            helper-text="Minimal 1 foto."
          />
        </div>
      </section>

      <section class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
        <div class="flex items-start gap-3">
          <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-slate-500 shadow-sm">
            <Info class="h-4 w-4" />
          </div>
          <div>
            <h3 class="text-sm font-semibold text-slate-950">Data Penilaian Internal</h3>
            <p class="mt-1 text-sm leading-6 text-slate-500">
              Peruntukan, bentuk tanah, posisi tanah, kondisi tanah, topografi, lebar muka, dan lebar akses jalan akan dilengkapi oleh reviewer setelah dokumen diperiksa.
            </p>
          </div>
        </div>
      </section>
    </div>
  </article>
</template>
