<script setup>
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Filter, RotateCcw, Search } from 'lucide-vue-next';

const props = defineProps({
  searchValue: { type: String, default: '' },
  searchPlaceholder: { type: String, default: 'Cari data' },
  filterTitle: { type: String, default: 'Filter data' },
  filterDescription: { type: String, default: '' },
  activeFilterCount: { type: Number, default: 0 },
  hasFilter: { type: Boolean, default: true },
});

const emit = defineEmits(['search', 'apply-filters', 'reset-filters']);

const search = ref(props.searchValue ?? '');
const open = ref(false);

watch(() => props.searchValue, (value) => {
  search.value = value ?? '';
});

const filterBadgeLabel = computed(() => {
  if (!props.activeFilterCount) {
    return null;
  }

  return String(props.activeFilterCount);
});

const submitSearch = () => {
  emit('search', search.value);
};

const applyFilters = () => {
  emit('apply-filters');
  open.value = false;
};

const resetFilters = () => {
  emit('reset-filters');
  open.value = false;
};
</script>

<template>
  <div class="flex w-full flex-col gap-3 md:flex-row md:items-center md:justify-end">
    <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
      <Popover v-if="hasFilter" v-model:open="open">
        <PopoverTrigger as-child>
          <Button type="button" variant="outline" class="shrink-0">
            <Filter class="h-4 w-4" />
            <span>Filter</span>
            <Badge v-if="filterBadgeLabel" variant="secondary" class="ml-1 min-w-5 justify-center px-1.5">
              {{ filterBadgeLabel }}
            </Badge>
          </Button>
        </PopoverTrigger>

        <PopoverContent align="end" class="w-[min(92vw,42rem)] max-h-[80vh] overflow-y-auto p-4">
          <div class="space-y-4">
            <div>
              <p class="text-sm font-semibold text-slate-950">{{ filterTitle }}</p>
              <p v-if="filterDescription" class="mt-1 text-xs leading-5 text-slate-500">{{ filterDescription }}</p>
            </div>

            <div class="space-y-4">
              <slot />
            </div>

            <div class="flex justify-end gap-2 border-t pt-4">
              <Button type="button" variant="outline" size="sm" @click="resetFilters">
                <RotateCcw class="h-4 w-4" />
                Reset
              </Button>
              <Button type="button" size="sm" @click="applyFilters">Terapkan</Button>
            </div>
          </div>
        </PopoverContent>
      </Popover>

      <form class="relative w-full md:w-[22rem]" @submit.prevent="submitSearch">
        <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <Input
          :model-value="search"
          :placeholder="searchPlaceholder"
          class="pl-9"
          @input="search = $event.target.value"
        />
      </form>
    </div>
  </div>
</template>
