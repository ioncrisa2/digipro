<script setup>
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

defineOptions({
  name: 'ComparableSnapshotTree',
});

const props = defineProps({
  value: {
    type: null,
    required: true,
  },
  label: {
    type: String,
    default: '',
  },
  depth: {
    type: Number,
    default: 0,
  },
});

const isPlainObject = computed(() => {
  return props.value !== null && typeof props.value === 'object' && !Array.isArray(props.value);
});

const isArray = computed(() => Array.isArray(props.value));

const entries = computed(() => {
  if (!isPlainObject.value) {
    return [];
  }

  return Object.entries(props.value).sort(([left], [right]) => left.localeCompare(right));
});

const isPrimitive = (value) => value === null || ['string', 'number', 'boolean'].includes(typeof value);

const isUrl = (value) => typeof value === 'string' && /^https?:\/\//i.test(value);

const formatLabel = (value) => {
  return String(value || '')
    .replace(/[_-]+/g, ' ')
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/\s+/g, ' ')
    .trim()
    .replace(/\b\w/g, (char) => char.toUpperCase());
};

const formatPrimitive = (value) => {
  if (value === null || value === undefined || value === '') {
    return '-';
  }

  if (typeof value === 'boolean') {
    return value ? 'Ya' : 'Tidak';
  }

  return String(value);
};
</script>

<template>
  <div class="space-y-3">
    <div v-if="label" class="flex items-center gap-2">
      <Badge variant="outline" class="text-[11px] font-medium">
        {{ formatLabel(label) }}
      </Badge>
    </div>

    <div v-if="isPlainObject" class="rounded-xl border bg-muted/10">
      <div
        v-for="[entryKey, entryValue] in entries"
        :key="`${depth}-${entryKey}`"
        class="border-b last:border-b-0"
      >
        <div v-if="isPrimitive(entryValue)" class="grid gap-2 px-4 py-3 md:grid-cols-[220px_1fr]">
          <div class="text-sm font-medium text-muted-foreground">
            {{ formatLabel(entryKey) }}
          </div>
          <div class="text-sm text-foreground break-all">
            <a
              v-if="isUrl(entryValue)"
              :href="entryValue"
              target="_blank"
              rel="noopener noreferrer"
              class="text-primary underline underline-offset-2"
            >
              {{ formatPrimitive(entryValue) }}
            </a>
            <span v-else>{{ formatPrimitive(entryValue) }}</span>
          </div>
        </div>

        <div v-else class="px-4 py-3">
          <div class="mb-3 text-sm font-medium text-muted-foreground">
            {{ formatLabel(entryKey) }}
          </div>
          <ComparableSnapshotTree :value="entryValue" :depth="depth + 1" />
        </div>
      </div>
    </div>

    <div v-else-if="isArray" class="space-y-3">
      <div v-if="value.length === 0" class="rounded-xl border border-dashed px-4 py-3 text-sm text-muted-foreground">
        Tidak ada data.
      </div>
      <div v-for="(item, index) in value" :key="`${depth}-${index}`" class="rounded-xl border bg-background p-3">
        <template v-if="isPrimitive(item)">
          <div class="text-sm text-foreground break-all">
            {{ formatPrimitive(item) }}
          </div>
        </template>
        <template v-else>
          <ComparableSnapshotTree :label="`Item ${index + 1}`" :value="item" :depth="depth + 1" />
        </template>
      </div>
    </div>

    <div v-else class="rounded-xl border bg-muted/10 px-4 py-3 text-sm text-foreground break-all">
      <a
        v-if="isUrl(value)"
        :href="value"
        target="_blank"
        rel="noopener noreferrer"
        class="text-primary underline underline-offset-2"
      >
        {{ formatPrimitive(value) }}
      </a>
      <span v-else>{{ formatPrimitive(value) }}</span>
    </div>
  </div>
</template>
