<script setup>
import { computed } from 'vue'

const props = defineProps({
  coverPath: { type: String, default: '' },
  alt: { type: String, default: 'Cover' },
  fallbackText: { type: String, default: 'DigiPro' },
  wrapperClass: {
    type: String,
    default: 'aspect-[16/10] bg-slate-100 overflow-hidden',
  },
  imageClass: {
    type: String,
    default: 'h-full w-full object-cover',
  },
  fallbackClass: {
    type: String,
    default: 'h-full w-full flex items-center justify-center text-slate-300 text-sm',
  },
})

const imageSrc = computed(() => {
  if (!props.coverPath) return ''

  if (
    props.coverPath.startsWith('http://') ||
    props.coverPath.startsWith('https://') ||
    props.coverPath.startsWith('/')
  ) {
    return props.coverPath
  }

  return `/storage/${props.coverPath}`
})
</script>

<template>
  <div :class="wrapperClass">
    <img v-if="imageSrc" :src="imageSrc" :alt="alt" :class="imageClass" />
    <div v-else :class="fallbackClass">
      {{ fallbackText }}
    </div>
  </div>
</template>
