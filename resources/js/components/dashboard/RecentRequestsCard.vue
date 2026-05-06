<script setup>
import { Link } from "@inertiajs/vue3";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { ArrowRight, FileText, Clock, CheckCircle2, AlertCircle } from "lucide-vue-next";
import RecentRequestsEmptyState from "@/components/dashboard/RecentRequestsEmptyState.vue";
import RecentRequestRow from "@/components/dashboard/RecentRequestRow.vue";

const props = defineProps({
  recentRequests: {
    type: Array,
    default: () => [],
  },
  requestListHref: { type: String, required: true },
  newRequestHref: { type: String, required: true },
});

const statusConfig = {
  success: {
    badgeClass: "bg-emerald-50 text-emerald-700 border-emerald-200",
    icon: CheckCircle2,
    iconClass: "text-emerald-700",
  },
  warning: {
    badgeClass: "bg-amber-50 text-amber-700 border-amber-200",
    icon: Clock,
    iconClass: "text-amber-700",
  },
  info: {
    badgeClass: "bg-sky-50 text-sky-700 border-sky-200",
    icon: Clock,
    iconClass: "text-sky-700",
  },
  danger: {
    badgeClass: "bg-rose-50 text-rose-700 border-rose-200",
    icon: AlertCircle,
    iconClass: "text-rose-700",
  },
  secondary: {
    badgeClass: "bg-slate-50 text-slate-600 border-slate-200",
    icon: FileText,
    iconClass: "text-slate-600",
  },
};

const getStatusConfig = (color) => statusConfig[color] || statusConfig.secondary;
</script>

<template>
  <Card class="rounded-[2rem] border-slate-200 shadow-none">
    <CardHeader class="border-b border-slate-200 px-6 py-5 sm:px-8">
      <div class="flex items-center justify-between">
        <div>
          <CardTitle class="text-lg font-semibold text-slate-900">
            Permohonan Terbaru
          </CardTitle>
          <p class="mt-1 text-sm text-slate-500">
            5 permohonan penilaian terakhir
          </p>
        </div>
        <Link
          :href="requestListHref"
          class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-slate-600 transition-colors hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-900/15 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
        >
          Lihat Semua
          <ArrowRight class="w-4 h-4" />
        </Link>
      </div>
    </CardHeader>
    <CardContent class="p-0">
      <RecentRequestsEmptyState
        v-if="props.recentRequests.length === 0"
        :new-request-href="newRequestHref"
      />

      <div v-else class="divide-y divide-slate-200">
        <RecentRequestRow
          v-for="req in props.recentRequests"
          :key="req.id"
          :request="req"
          :status-config="getStatusConfig(req.status_color)"
          :href="req.detail_url || `/permohonan-penilaian/${req.id}`"
        />
      </div>
    </CardContent>
  </Card>
</template>
