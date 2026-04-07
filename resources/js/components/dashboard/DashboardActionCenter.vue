<script setup>
import { computed } from "vue";
import { Button } from "@/components/ui/button";
import { ArrowRight, Clock3, FileCheck2, FileWarning, HandCoins, ReceiptText } from "lucide-vue-next";

const props = defineProps({
  actionCenter: {
    type: Array,
    default: () => [],
  },
  onOpenAction: {
    type: Function,
    required: true,
  },
});

const actionableItems = computed(() => (props.actionCenter || []).filter((item) => Number(item.count || 0) > 0));

const iconFor = (key) => {
  switch (key) {
    case "need_revision":
      return FileWarning;
    case "offer_sent":
      return HandCoins;
    case "waiting_signature":
      return FileCheck2;
    case "contract_signed":
      return ReceiptText;
    default:
      return Clock3;
  }
};

const iconClass = (tone) => {
  switch (tone) {
    case "danger":
      return "text-rose-700";
    case "warning":
      return "text-amber-800";
    case "info":
      return "text-sky-700";
    default:
      return "text-slate-600";
  }
};

const rowClass = (tone) => {
  switch (tone) {
    case "danger":
      return "bg-rose-50";
    case "warning":
      return "bg-amber-50";
    case "info":
      return "bg-sky-50";
    default:
      return "bg-slate-50";
  }
};
</script>

<template>
  <section class="rounded-[2rem] border border-slate-200 bg-white p-6">
    <div class="space-y-2">
      <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Butuh Tindakan</p>
      <h2 class="text-lg font-semibold text-slate-950">Hal yang menunggu respon Anda</h2>
      <p class="text-sm leading-6 text-slate-500">Prioritaskan item ini agar permohonan aktif Anda bisa terus bergerak.</p>
    </div>

    <div v-if="!actionableItems.length" class="mt-6 rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50 p-4 text-sm leading-6 text-slate-500">
      Tidak ada tindakan mendesak saat ini. Anda bisa memantau progress dari request yang sedang berjalan.
    </div>

    <div v-else class="mt-6 space-y-3">
      <article
        v-for="item in actionableItems"
        :key="item.key"
        class="rounded-[1.5rem] border border-slate-200 p-4"
        :class="rowClass(item.tone)"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-start gap-3">
            <div class="rounded-xl border border-white/80 bg-white/90 p-2">
              <component :is="iconFor(item.key)" class="h-4 w-4" :class="iconClass(item.tone)" />
            </div>
            <div>
              <p class="text-sm font-medium text-slate-950">{{ item.label }}</p>
              <p class="mt-1 text-xs leading-5 text-slate-600">{{ item.count }} permohonan membutuhkan perhatian.</p>
            </div>
          </div>
          <div class="rounded-full bg-white px-3 py-1 text-sm font-semibold text-slate-950">{{ item.count }}</div>
        </div>

        <Button class="mt-4 w-full justify-between bg-white/80" variant="outline" @click="onOpenAction(item)">
          Lihat Daftar
          <ArrowRight class="h-4 w-4" />
        </Button>
      </article>
    </div>
  </section>
</template>
