<script setup>
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const props = defineProps({
  items: { type: Array, default: () => [] },
  meta: { type: Object, default: null },
  emptyText: { type: String, default: 'Tidak ada data.' },
  gridClass: { type: String, default: 'grid gap-4 lg:grid-cols-2 xl:grid-cols-3' },
});

const paginationLinks = computed(() => props.meta?.links ?? []);

const visit = (url) => {
  if (!url) return;

  router.visit(url, {
    preserveScroll: true,
    preserveState: true,
  });
};
</script>

<template>
  <div class="space-y-4">
    <div :class="cn(gridClass)">
      <template v-if="items.length">
        <slot
          v-for="(item, index) in items"
          :key="item.id ?? index"
          name="item"
          :item="item"
          :index="index"
        />
      </template>
      <div
        v-else
        class="rounded-3xl border border-dashed border-slate-200 bg-white/70 p-6 text-sm text-slate-500"
      >
        {{ emptyText }}
      </div>
    </div>

    <div v-if="paginationLinks.length" class="flex flex-wrap gap-2">
      <Button
        v-for="link in paginationLinks"
        :key="`${link.label}-${link.url}`"
        type="button"
        size="sm"
        :variant="link.active ? 'default' : 'outline'"
        :disabled="!link.url"
        @click="visit(link.url)"
      >
        <span v-html="link.label" />
      </Button>
    </div>
  </div>
</template>
