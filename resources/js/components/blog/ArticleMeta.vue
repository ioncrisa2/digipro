<script setup>
import { computed } from 'vue'
import { useArticleFormat } from '@/composables/useArticleFormat'

const props = defineProps({
  publishedAt: { type: [String, Number, Date], default: null },
  readSource: { type: String, default: '' },
  readSuffix: { type: String, default: '' },
  category: { type: String, default: '' },
  views: { type: Number, default: null },
  showViews: { type: Boolean, default: false },
  containerClass: {
    type: String,
    default: 'flex flex-wrap items-center gap-3 text-sm text-slate-500',
  },
  categoryClass: {
    type: String,
    default: 'rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700',
  },
})

const { formatDate, getReadTime } = useArticleFormat()
const readTime = computed(() => getReadTime(props.readSource, { suffix: props.readSuffix }))
</script>

<template>
  <div :class="containerClass">
    <span>{{ formatDate(publishedAt) }}</span>
    <span class="text-slate-300">/</span>
    <span>{{ readTime }}</span>
    <template v-if="showViews">
      <span class="text-slate-300">/</span>
      <span>{{ views ?? 0 }} views</span>
    </template>
    <span v-if="category" :class="categoryClass">
      {{ category }}
    </span>
  </div>
</template>
