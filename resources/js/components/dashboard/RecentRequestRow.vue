<script setup>
import { Link } from "@inertiajs/vue3";
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
  href: {
    type: String,
    required: true,
  },
});
</script>

<template>
  <Link
    :href="href"
    class="group block px-6 py-5 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-900/15 focus-visible:ring-offset-2 focus-visible:ring-offset-white sm:px-8"
  >
    <div class="flex items-start justify-between gap-4">
      <div class="flex-1 min-w-0">
        <div class="mb-2 flex items-center gap-3">
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

        <p class="mb-3 flex items-start gap-2 text-sm text-slate-700">
          <MapPin class="w-4 h-4 text-slate-400 mt-0.5 shrink-0" />
          <span class="line-clamp-1">{{ request.property }}</span>
        </p>

        <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500">
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
        class="w-5 h-5 shrink-0 text-slate-400 transition-transform duration-150 group-hover:translate-x-1 group-hover:text-slate-600 motion-reduce:transform-none motion-reduce:transition-none"
      />
    </div>
  </Link>
</template>
