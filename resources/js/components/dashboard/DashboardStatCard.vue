<script setup>
import { Card, CardContent } from "@/components/ui/card";

defineProps({
  label: {
    type: String,
    required: true,
  },
  value: {
    type: [String, Number],
    default: 0,
  },
  borderClass: {
    type: String,
    required: true,
  },
  tileClass: {
    type: String,
    required: true,
  },
  icon: {
    type: [Object, Function],
    required: true,
  },
  iconClass: {
    type: String,
    default: "",
  },
  subtext: {
    type: String,
    required: true,
  },
  subIcon: {
    type: [Object, Function],
    required: true,
  },
});
</script>

<template>
  <Card :class="`border-l-4 ${borderClass} shadow-sm hover:shadow-md transition-shadow`">
    <CardContent class="p-6">
      <div class="flex items-start justify-between">
        <div class="flex-1">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
            {{ label }}
          </p>
          <h3 class="text-3xl font-bold text-slate-900 mb-1">
            {{ value }}
          </h3>
          <p class="text-xs text-slate-400 flex items-center gap-1">
            <component :is="subIcon" class="w-3 h-3" />
            {{ subtext }}
          </p>
        </div>
        <div :class="`p-3 rounded-lg ${tileClass}`">
          <component :is="icon" class="w-6 h-6" :class="iconClass" />
        </div>
      </div>
    </CardContent>
  </Card>
</template>
