<script setup>
import { FileText, Calendar, MapPin, Clock, ArrowRight } from "lucide-vue-next";

defineProps({
  request: {
    type: Object,
    required: true,
  },
  statusConfig: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(["view"]);
</script>

<template>
  <div
    @click="emit('view', request.id)"
    class="p-4 hover:bg-slate-50 transition-colors cursor-pointer group"
  >
    <div class="flex items-start justify-between gap-4">
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-3 mb-2">
          <span class="inline-flex items-center gap-1.5 font-mono text-sm font-semibold text-slate-900">
            <FileText class="w-4 h-4 text-slate-400" />
            {{ request.code }}
          </span>
          <component
            :is="statusConfig.icon"
            class="w-4 h-4"
            :class="statusConfig.iconClass"
          />
          <span
            class="inline-flex items-center px-2.5 py-0.5 rounded-full border text-xs font-medium"
            :class="statusConfig.badgeClass"
          >
            {{ request.status }}
          </span>
        </div>

        <p class="text-sm text-slate-700 mb-2 flex items-start gap-2">
          <MapPin class="w-4 h-4 text-slate-400 mt-0.5 shrink-0" />
          <span class="line-clamp-1">{{ request.property }}</span>
        </p>

        <div class="flex items-center gap-4 text-xs text-slate-500">
          <span class="flex items-center gap-1">
            <Calendar class="w-3.5 h-3.5" />
            {{ request.created_at }}
          </span>
          <span class="flex items-center gap-1">
            <Clock class="w-3.5 h-3.5" />
            {{ request.created_diff }}
          </span>
          <span v-if="request.asset_count > 1" class="flex items-center gap-1">
            <FileText class="w-3.5 h-3.5" />
            {{ request.asset_count }} Aset
          </span>
        </div>
      </div>

      <ArrowRight
        class="w-5 h-5 text-slate-400 group-hover:text-slate-600 group-hover:translate-x-1 transition-all shrink-0"
      />
    </div>
  </div>
</template>
