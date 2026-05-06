<script setup>
import { computed } from "vue";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { TrendingUp } from "lucide-vue-next";

const props = defineProps({
  stats: {
    type: Object,
    required: true,
  },
});

const totalRequests = computed(() => props.stats?.total_requests ?? 0);
const completed = computed(() => props.stats?.completed ?? 0);
const inProgress = computed(() => props.stats?.in_progress ?? 0);
const completionRate = computed(() => {
  if (!totalRequests.value) return 0;
  return Math.round((completed.value / totalRequests.value) * 100);
});
const completionWidth = computed(() => `${completionRate.value}%`);
</script>

<template>
  <Card class="shadow-sm">
    <CardHeader class="border-b bg-slate-50/50">
      <CardTitle class="text-lg font-semibold text-slate-900 flex items-center gap-2">
        <TrendingUp class="w-5 h-5" />
        Progress Overview
      </CardTitle>
    </CardHeader>
    <CardContent class="p-6">
      <div class="space-y-4">
        <div>
          <div class="flex justify-between text-sm mb-2">
            <span class="text-slate-600 font-medium">Tingkat Penyelesaian</span>
            <span class="text-slate-900 font-semibold">{{ completionRate }}%</span>
          </div>
          <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
            <div
              class="bg-emerald-500 h-2.5 rounded-full"
              :style="{ width: completionWidth }"
            ></div>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3 pt-2">
          <div class="p-3 bg-amber-50 rounded-lg border border-amber-100">
            <p class="text-xs text-amber-600 font-medium mb-1">Proses</p>
            <p class="text-lg font-bold text-amber-900">{{ inProgress }}</p>
          </div>
          <div class="p-3 bg-emerald-50 rounded-lg border border-emerald-100">
            <p class="text-xs text-emerald-600 font-medium mb-1">Selesai</p>
            <p class="text-lg font-bold text-emerald-900">{{ completed }}</p>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
