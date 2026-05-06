<script setup>
defineProps({
  label: {
    type: String,
    required: true,
  },
  value: {
    type: [String, Number],
    default: 0,
  },
  accentClass: {
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
  <div class="flex h-full items-start justify-between gap-4 px-5 py-5 sm:px-6">
    <div class="space-y-3">
      <div class="flex items-center gap-2">
        <span class="h-2.5 w-2.5 rounded-full" :class="accentClass" />
        <p class="text-xs font-semibold uppercase text-slate-500">
          {{ label }}
        </p>
      </div>
      <h3 class="text-3xl font-semibold tabular-nums text-slate-950">
        {{ value }}
      </h3>
      <p class="flex items-center gap-1 text-xs text-slate-500">
        <component :is="subIcon" class="h-3.5 w-3.5" />
        {{ subtext }}
      </p>
    </div>
    <div :class="`rounded-2xl p-3 ${tileClass}`">
      <component :is="icon" class="h-5 w-5" :class="iconClass" />
    </div>
  </div>
</template>
