<script setup>
import { computed } from "vue";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  ArrowRight,
  Clock3,
  FileSearch,
  MapPin,
  Sparkles,
} from "lucide-vue-next";

const props = defineProps({
  featuredRequest: {
    type: Object,
    default: null,
  },
  onNewRequest: {
    type: Function,
    required: true,
  },
  onOpenDetail: {
    type: Function,
    required: true,
  },
  onOpenTracking: {
    type: Function,
    required: true,
  },
  onRunPrimaryAction: {
    type: Function,
    required: true,
  },
});

const milestoneSegments = computed(() => props.featuredRequest?.progress_summary?.milestones ?? []);
const primaryAction = computed(() => props.featuredRequest?.progress_summary?.primary_action ?? null);
const hasRequest = computed(() => Boolean(props.featuredRequest?.id));

const segmentClass = (milestone) => {
  const state = String(milestone?.state ?? "upcoming");
  if (state === "completed") return "bg-emerald-500";
  if (state === "current") return "bg-sky-600";
  return "bg-slate-200";
};

const badgeVariant = computed(() => {
  const tone = props.featuredRequest?.status_color;
  if (tone === "success") return "default";
  if (tone === "danger") return "destructive";
  if (tone === "warning" || tone === "info") return "secondary";
  return "outline";
});
</script>

<template>
  <section class="rounded-[2rem] border border-slate-200 bg-white p-6 sm:p-8">
    <div class="grid gap-8 xl:grid-cols-[minmax(0,1.3fr)_minmax(280px,0.7fr)] xl:items-start">
      <div class="space-y-6">
        <div class="space-y-4">
          <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">
            <Sparkles class="h-3.5 w-3.5" />
            Permohonan Prioritas
          </div>

          <div v-if="hasRequest" class="space-y-3">
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-3xl font-semibold tracking-tight text-slate-950">Permohonan yang perlu Anda pantau sekarang</h2>
              <Badge :variant="badgeVariant">{{ featuredRequest.status }}</Badge>
            </div>
            <p class="max-w-2xl text-sm leading-6 text-slate-600">
              Status aktif dan tindakan berikutnya ditampilkan di sini agar Anda bisa langsung melanjutkan proses tanpa membuka detail request lebih dulu.
            </p>
          </div>

          <div v-else class="space-y-3">
            <h2 class="text-3xl font-semibold tracking-tight text-slate-950">Mulai permohonan pertama Anda</h2>
            <p class="max-w-2xl text-sm leading-6 text-slate-600">
              Dashboard akan menampilkan progres, tindakan berikutnya, dan dokumen penting setelah Anda memiliki permohonan penilaian aktif.
            </p>
          </div>
        </div>

        <div v-if="hasRequest" class="space-y-5">
          <div class="flex flex-wrap items-center gap-4 text-sm text-slate-500">
            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 font-mono font-semibold text-slate-900">
              {{ featuredRequest.code }}
            </span>
            <span class="inline-flex items-center gap-1">
              <MapPin class="h-4 w-4" />
              {{ featuredRequest.property }}
            </span>
            <span class="inline-flex items-center gap-1">
              <Clock3 class="h-4 w-4" />
              {{ featuredRequest.updated_diff || featuredRequest.updated_at }}
            </span>
          </div>

          <div class="space-y-4 rounded-[1.5rem] bg-slate-50 p-4 sm:p-5">
            <div class="flex gap-2">
              <div
                v-for="milestone in milestoneSegments"
                :key="milestone.key"
                class="h-2 flex-1 rounded-full"
                :class="segmentClass(milestone)"
              />
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <Badge variant="outline">
                Tahap {{ featuredRequest.progress_summary?.current_step }} dari {{ featuredRequest.progress_summary?.total_steps }}
              </Badge>
              <span class="text-sm font-medium text-slate-900">{{ featuredRequest.progress_summary?.current_label }}</span>
            </div>
            <p class="max-w-2xl text-sm leading-6 text-slate-600">
              {{ featuredRequest.progress_summary?.helper_text }}
            </p>
          </div>

          <div class="flex flex-wrap gap-2">
            <Button v-if="primaryAction" @click="onRunPrimaryAction(featuredRequest)">
              <ArrowRight class="mr-2 h-4 w-4" />
              {{ primaryAction.label }}
            </Button>
            <Button variant="outline" @click="onOpenTracking(featuredRequest)">
              <FileSearch class="mr-2 h-4 w-4" />
              Lihat Tracking
            </Button>
            <Button variant="ghost" @click="onOpenDetail(featuredRequest)">
              Detail Permohonan
            </Button>
          </div>
        </div>

        <div v-else class="flex flex-wrap gap-2">
          <Button @click="onNewRequest">
            <ArrowRight class="mr-2 h-4 w-4" />
            Buat Permohonan Baru
          </Button>
        </div>
      </div>

      <div class="border-t border-slate-200 pt-6 xl:border-l xl:border-t-0 xl:pl-8 xl:pt-0">
        <div v-if="hasRequest" class="space-y-5">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Status Saat Ini</p>
            <p class="mt-2 text-lg font-semibold text-slate-950">{{ featuredRequest.progress_summary?.status_label }}</p>
            <p v-if="featuredRequest.progress_summary?.substatus?.label" class="mt-2 text-sm text-slate-600">
              {{ featuredRequest.progress_summary?.substatus?.label }}
            </p>
          </div>

          <dl class="divide-y divide-slate-200 rounded-[1.5rem] border border-slate-200 bg-slate-50/70">
            <div class="px-4 py-4">
              <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Alamat Ringkas</dt>
              <dd class="mt-2 text-sm leading-6 text-slate-700">{{ featuredRequest.property }}</dd>
            </div>
            <div class="px-4 py-4">
              <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Jumlah Aset</dt>
              <dd class="mt-2 text-2xl font-semibold text-slate-950">{{ featuredRequest.asset_count || 0 }}</dd>
            </div>
            <div class="px-4 py-4">
              <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Pembaruan Terakhir</dt>
              <dd class="mt-2 text-sm text-slate-700">{{ featuredRequest.updated_diff || featuredRequest.updated_at }}</dd>
            </div>
          </dl>
        </div>

        <div v-else class="space-y-4">
          <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Belum Ada Aktivitas</p>
          <div class="rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50 p-4 text-sm leading-6 text-slate-600">
            Setelah permohonan pertama dibuat, area ini akan menunjukkan milestone aktif, tindakan berikutnya, dan akses cepat ke tracking.
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
