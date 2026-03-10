<script setup>
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { statusToneClass } from '@/utils/reviewer';

const props = defineProps({
  status: {
    type: Object,
    required: true,
  },
});

const badgeClass = computed(() => statusToneClass(props.status?.value));
</script>

<template>
  <Badge variant="outline" :class="badgeClass">
    {{ status?.label ?? '-' }}
  </Badge>
</template>
