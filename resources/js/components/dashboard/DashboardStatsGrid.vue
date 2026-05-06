<script setup>
import { computed } from "vue";
import {
  FileText,
  Clock,
  CheckCircle2,
  AlertCircle,
  Calendar,
} from "lucide-vue-next";
import DashboardStatCard from "@/components/dashboard/DashboardStatCard.vue";

const props = defineProps({
  stats: {
    type: Object,
    required: true,
  },
  compact: {
    type: Boolean,
    default: false,
  },
});

const cards = computed(() => {
  const stats = props.stats ?? {};
  return [
    {
      label: "Total Permohonan",
      value: stats.total_requests ?? 0,
      accentClass: "bg-slate-900",
      tileClass: "bg-slate-100",
      icon: FileText,
      iconClass: "text-slate-600",
      subtext: "Hingga hari ini",
      subIcon: Calendar,
    },
    {
      label: "Sedang Proses",
      value: stats.in_progress ?? 0,
      accentClass: "bg-amber-500",
      tileClass: "bg-amber-100",
      icon: Clock,
      iconClass: "text-amber-600",
      subtext: "Dalam pengerjaan",
      subIcon: Clock,
    },
    {
      label: "Selesai",
      value: stats.completed ?? 0,
      accentClass: "bg-emerald-500",
      tileClass: "bg-emerald-100",
      icon: CheckCircle2,
      iconClass: "text-emerald-600",
      subtext: "Laporan terbit",
      subIcon: CheckCircle2,
    },
    {
      label: "Perlu Revisi",
      value: stats.need_revision ?? 0,
      accentClass: "bg-rose-500",
      tileClass: "bg-rose-100",
      icon: AlertCircle,
      iconClass: "text-rose-600",
      subtext: "Butuh klarifikasi",
      subIcon: AlertCircle,
    },
  ];
});
</script>

<template>
  <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white">
    <div
      class="grid divide-slate-200 sm:grid-cols-2 sm:divide-x"
      :class="props.compact ? 'xl:grid-cols-2 xl:divide-y-0' : 'xl:grid-cols-4'"
    >
    <DashboardStatCard
      v-for="card in cards"
      :key="card.label"
      v-bind="card"
    />
    </div>
  </section>
</template>
