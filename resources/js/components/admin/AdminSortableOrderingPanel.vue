<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, GripVertical, Save, RotateCcw } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

const props = defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  saveUrl: { type: String, required: true },
  items: { type: Array, default: () => [] },
  emptyText: { type: String, default: 'Belum ada item untuk diurutkan.' },
});

const localItems = ref([]);
const draggedId = ref(null);
const isSaving = ref(false);

const cloneItems = (items) => items.map((item) => ({ ...item }));

watch(
  () => props.items,
  (items) => {
    localItems.value = cloneItems(items ?? []);
  },
  { immediate: true, deep: true },
);

const originalOrder = computed(() => (props.items ?? []).map((item) => item.id));
const currentOrder = computed(() => localItems.value.map((item) => item.id));
const hasChanges = computed(() => JSON.stringify(originalOrder.value) !== JSON.stringify(currentOrder.value));

const moveItem = (fromIndex, toIndex) => {
  if (toIndex < 0 || toIndex >= localItems.value.length || fromIndex === toIndex) {
    return;
  }

  const next = [...localItems.value];
  const [item] = next.splice(fromIndex, 1);
  next.splice(toIndex, 0, item);
  localItems.value = next;
};

const onDragStart = (itemId) => {
  draggedId.value = itemId;
};

const onDrop = (targetId) => {
  if (draggedId.value === null || draggedId.value === targetId) {
    draggedId.value = null;
    return;
  }

  const fromIndex = localItems.value.findIndex((item) => item.id === draggedId.value);
  const toIndex = localItems.value.findIndex((item) => item.id === targetId);

  if (fromIndex !== -1 && toIndex !== -1) {
    moveItem(fromIndex, toIndex);
  }

  draggedId.value = null;
};

const resetOrder = () => {
  localItems.value = cloneItems(props.items ?? []);
};

const saveOrder = () => {
  if (!hasChanges.value || isSaving.value) {
    return;
  }

  isSaving.value = true;

  router.put(props.saveUrl, {
    ids: currentOrder.value,
  }, {
    preserveScroll: true,
    onFinish: () => {
      isSaving.value = false;
    },
  });
};
</script>

<template>
  <Card>
    <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
      <div>
        <CardTitle>{{ title }}</CardTitle>
        <CardDescription>{{ description }}</CardDescription>
      </div>
      <div class="flex flex-wrap gap-2">
        <Button type="button" variant="outline" :disabled="!hasChanges || isSaving" @click="resetOrder">
          <RotateCcw class="h-4 w-4" />
          Reset
        </Button>
        <Button type="button" :disabled="!hasChanges || isSaving" @click="saveOrder">
          <Save class="h-4 w-4" />
          Simpan Urutan
        </Button>
      </div>
    </CardHeader>
    <CardContent>
      <div v-if="localItems.length" class="space-y-3">
        <div
          v-for="(item, index) in localItems"
          :key="item.id"
          class="flex items-start gap-3 rounded-2xl border bg-slate-50 p-4"
          draggable="true"
          @dragstart="onDragStart(item.id)"
          @dragover.prevent
          @drop="onDrop(item.id)"
        >
          <button type="button" class="mt-0.5 rounded-lg border bg-white p-2 text-slate-500" aria-label="Drag handle">
            <GripVertical class="h-4 w-4" />
          </button>

          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <Badge variant="outline">{{ index + 1 }}</Badge>
              <p class="font-medium text-slate-950">{{ item.title }}</p>
              <Badge
                v-if="item.is_active !== undefined"
                variant="outline"
                :class="item.is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-600'"
              >
                {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
              </Badge>
            </div>
            <p v-if="item.subtitle" class="mt-1 text-sm text-slate-600">{{ item.subtitle }}</p>
          </div>

          <div class="flex flex-col gap-2">
            <Button type="button" size="icon" variant="outline" :disabled="index === 0" @click="moveItem(index, index - 1)">
              <ArrowUp class="h-4 w-4" />
            </Button>
            <Button type="button" size="icon" variant="outline" :disabled="index === localItems.length - 1" @click="moveItem(index, index + 1)">
              <ArrowDown class="h-4 w-4" />
            </Button>
          </div>
        </div>
      </div>
      <div v-else class="rounded-2xl border border-dashed bg-slate-50 p-6 text-sm text-slate-500">
        {{ emptyText }}
      </div>
    </CardContent>
  </Card>
</template>
