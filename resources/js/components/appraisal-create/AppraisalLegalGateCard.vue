<script setup>
import { Card, CardContent } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import { AlertTriangle, FileText, ShieldCheck, Target } from 'lucide-vue-next'

defineProps({
  sertifikatOnHandConfirmed: {
    type: Boolean,
    required: true,
  },
  certificateNotEncumberedConfirmed: {
    type: Boolean,
    required: true,
  },
  representativeLetterNotice: {
    type: Object,
    default: () => ({
      title: 'Surat Representatif DigiPro by KJPP HJAR',
      description: '',
    }),
  },
  valuationObjective: {
    type: Object,
    default: () => ({
      label: 'Kajian Nilai Pasar dalam Bentuk Range',
      description: '',
    }),
  },
})

const emit = defineEmits([
  'update:sertifikatOnHandConfirmed',
  'update:certificateNotEncumberedConfirmed',
])
</script>

<template>
  <div class="space-y-3">

    <!-- Header notice -->
    <div class="flex items-center gap-2.5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5">
      <AlertTriangle class="h-4 w-4 shrink-0 text-amber-600" />
      <p class="text-sm font-medium text-amber-900">
        Pernyataan legal diperlukan sebelum permohonan dapat dikirim.
      </p>
    </div>

    <Card class="overflow-hidden border border-slate-200 shadow-none">

      <!-- Tujuan Penilaian -->
      <div class="border-b border-slate-100 px-5 py-4">
        <div class="flex items-start gap-3">
          <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-slate-100">
            <Target class="h-4 w-4 text-slate-600" />
          </div>
          <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">Tujuan Penilaian</p>
            <p class="mt-1 text-sm font-medium text-slate-800">
              {{ valuationObjective?.label || 'Kajian Nilai Pasar dalam Bentuk Range' }}
            </p>
            <p v-if="valuationObjective?.description" class="mt-0.5 text-sm text-slate-500">
              {{ valuationObjective.description }}
            </p>
          </div>
        </div>
      </div>

      <!-- Pernyataan Legal -->
      <div class="divide-y divide-slate-100">
        <label
          class="flex cursor-pointer items-start gap-4 px-5 py-4 transition-colors hover:bg-slate-50"
          :class="sertifikatOnHandConfirmed ? 'bg-emerald-50/60' : ''"
        >
          <Checkbox
            :model-value="sertifikatOnHandConfirmed"
            class="mt-0.5 shrink-0"
            @update:model-value="emit('update:sertifikatOnHandConfirmed', Boolean($event))"
          />
          <div class="space-y-0.5">
            <p class="text-sm font-medium leading-snug text-slate-800">
              Sertifikat fisik tersedia dan on hand
            </p>
            <p class="text-xs text-slate-500 leading-relaxed">
              Dokumen kepemilikan utama saat ini dalam penguasaan pemohon dan siap diserahkan jika diperlukan.
            </p>
          </div>
          <ShieldCheck
            v-if="sertifikatOnHandConfirmed"
            class="ml-auto mt-0.5 h-4 w-4 shrink-0 text-emerald-500"
          />
        </label>

        <label
          class="flex cursor-pointer items-start gap-4 px-5 py-4 transition-colors hover:bg-slate-50"
          :class="certificateNotEncumberedConfirmed ? 'bg-emerald-50/60' : ''"
        >
          <Checkbox
            :model-value="certificateNotEncumberedConfirmed"
            class="mt-0.5 shrink-0"
            @update:model-value="emit('update:certificateNotEncumberedConfirmed', Boolean($event))"
          />
          <div class="space-y-0.5">
            <p class="text-sm font-medium leading-snug text-slate-800">
              Sertifikat tidak sedang dijaminkan
            </p>
            <p class="text-xs text-slate-500 leading-relaxed">
              Sertifikat bebas dari tanggungan atau jaminan pihak ketiga. Proses tidak dapat dilanjutkan jika sertifikat sedang dijaminkan.
            </p>
          </div>
          <ShieldCheck
            v-if="certificateNotEncumberedConfirmed"
            class="ml-auto mt-0.5 h-4 w-4 shrink-0 text-emerald-500"
          />
        </label>
      </div>

      <!-- Surat Representatif Notice -->
      <div class="border-t border-slate-100 bg-sky-50/60 px-5 py-4">
        <div class="flex items-start gap-3">
          <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-sky-100">
            <FileText class="h-4 w-4 text-sky-600" />
          </div>
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <p class="text-sm font-semibold text-sky-900">
                {{ representativeLetterNotice?.title || 'Surat Representatif DigiPro by KJPP HJAR' }}
              </p>
              <Badge variant="outline" class="border-sky-200 bg-sky-100 px-1.5 py-0 text-[10px] font-medium text-sky-700">
                Disiapkan sistem
              </Badge>
            </div>
            <p class="mt-1 text-xs leading-relaxed text-sky-800/80">
              {{
                representativeLetterNotice?.description ||
                'Setelah kontrak ditandatangani, DigiPro by KJPP HJAR akan menyiapkan surat representatif berdasarkan data permohonan.'
              }}
            </p>
          </div>
        </div>
      </div>

    </Card>
  </div>
</template>
