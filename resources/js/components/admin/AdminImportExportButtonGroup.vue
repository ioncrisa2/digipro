<script setup>
import { Button } from '@/components/ui/button';

const props = defineProps({
  importLabel: {
    type: String,
    default: 'Import',
  },
  exportLabel: {
    type: String,
    default: 'Export',
  },
  exportUrl: {
    type: String,
    default: '',
  },
  showImport: {
    type: Boolean,
    default: false,
  },
  showExport: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['import']);
</script>

<template>
  <div v-if="showImport || showExport" class="inline-flex">
    <Button
      v-if="showImport"
      variant="outline"
      type="button"
      :class="showExport ? 'rounded-r-none' : ''"
      @click="emit('import')"
    >
      {{ importLabel }}
    </Button>

    <Button
      v-if="showExport"
      variant="outline"
      as-child
      :class="showImport ? 'rounded-l-none border-l-0' : ''"
    >
      <a :href="exportUrl">{{ exportLabel }}</a>
    </Button>
  </div>
</template>
