<script setup>
import { computed } from 'vue'

const props = defineProps({
  align: { type: String, default: 'left' },
  wrapperClass: { type: String, default: '' },
  logoClass: { type: String, default: '' },
  alt: { type: String, default: 'DigiPro by KJPP HJAR' },
})

const alignmentClass = computed(() => {
  if (props.align === 'center') return 'items-center text-center'
  if (props.align === 'right') return 'items-end text-right'

  return 'items-start text-left'
})
</script>

<template>
  <div :class="['inline-flex w-fit flex-col leading-none', alignmentClass]">
    <div
      :class="[
        'overflow-hidden rounded-[1rem]',
        wrapperClass,
      ]"
    >
      <img
        src="/images/brand/digipro-by-kjpp-hjar-logo.png"
        :alt="alt"
        :class="[
          'block h-auto w-[220px] max-w-full object-contain',
          logoClass,
        ]"
      />
    </div>
  </div>
</template>
