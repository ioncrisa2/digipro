<script setup>
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

defineProps({
  links: {
    type: Array,
    default: () => [],
  },
});
</script>

<template>
  <div v-if="links.length > 3" class="flex flex-wrap items-center gap-2">
    <Button
      v-for="(link, index) in links"
      :key="`${index}-${link.label}`"
      :as="link.url ? Link : 'span'"
      :href="link.url || undefined"
      size="sm"
      :variant="link.active ? 'default' : 'outline'"
      :class="!link.url ? 'pointer-events-none opacity-50' : ''"
      v-html="link.label"
    />
  </div>
</template>
