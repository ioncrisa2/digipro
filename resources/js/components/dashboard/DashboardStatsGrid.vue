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
});

const cards = computed(() => {
  const stats = props.stats ?? {};
  return [
    {
      label: "Total Permohonan",
      value: stats.total_requests ?? 0,
      borderClass: "border-slate-500",
      tileClass: "bg-slate-100",
      icon: FileText,
      iconClass: "text-slate-600",
      subtext: "Hingga hari ini",
      subIcon: Calendar,
    },
    {
      label: "Sedang Proses",
      value: stats.in_progress ?? 0,
      borderClass: "border-amber-500",
      tileClass: "bg-amber-100",
      icon: Clock,
      iconClass: "text-amber-600",
      subtext: "Dalam pengerjaan",
      subIcon: Clock,
    },
    {
      label: "Selesai",
      value: stats.completed ?? 0,
      borderClass: "border-emerald-500",
      tileClass: "bg-emerald-100",
      icon: CheckCircle2,
      iconClass: "text-emerald-600",
      subtext: "Laporan terbit",
      subIcon: CheckCircle2,
    },
    {
      label: "Perlu Revisi",
      value: stats.need_revision ?? 0,
      borderClass: "border-rose-500",
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
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <DashboardStatCard
      v-for="card in cards"
      :key="card.label"
      v-bind="card"
    />
  </div>
</template>
