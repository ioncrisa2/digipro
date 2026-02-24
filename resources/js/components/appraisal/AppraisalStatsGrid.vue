<script setup>
import { computed } from "vue";
import { FileText, Clock, CheckCircle2 } from "lucide-vue-next";
import { Card, CardContent } from "@/components/ui/card";

const props = defineProps({
  statsCards: {
    type: Array,
    required: true,
  },
});

const metaMap = {
  total: {
    icon: FileText,
    bgColor: "bg-slate-100",
    iconColor: "text-slate-600",
  },
  pending: {
    icon: Clock,
    bgColor: "bg-amber-100",
    iconColor: "text-amber-600",
  },
  in_progress: {
    icon: Clock,
    bgColor: "bg-blue-100",
    iconColor: "text-blue-600",
  },
  completed: {
    icon: CheckCircle2,
    bgColor: "bg-emerald-100",
    iconColor: "text-emerald-600",
  },
};

const normalizedCards = computed(() =>
  (props.statsCards || []).map((card) => ({
    ...card,
    ...(metaMap[card.key] || {}),
  }))
);
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <Card
      v-for="stat in normalizedCards"
      :key="stat.label"
      class="hover:shadow-md transition-shadow"
    >
      <CardContent class="p-5">
        <div class="flex items-center justify-between">
          <div class="flex-1">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
              {{ stat.label }}
            </p>
            <p class="text-3xl font-bold text-slate-900">{{ stat.value }}</p>
          </div>
          <div :class="[stat.bgColor, 'p-3 rounded-xl']">
            <component :is="stat.icon" :class="[stat.iconColor, 'w-6 h-6']" />
          </div>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
