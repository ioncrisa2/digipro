<script setup>
import { nextTick, onBeforeUnmount, ref } from 'vue';
import { formatCurrency, formatNumber, formatPercent } from '@/utils/reviewer';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Plus } from 'lucide-vue-next';

const props = defineProps({
  matrixSections: {
    type: Array,
    default: () => [],
  },
  matrixColumns: {
    type: Array,
    default: () => [],
  },
  adjustmentComputed: {
    type: Object,
    default: () => ({}),
  },
  comparables: {
    type: Array,
    default: () => [],
  },
  draftInputs: {
    type: Object,
    required: true,
  },
  draftGeneralInputs: {
    type: Object,
    required: true,
  },
  customFactors: {
    type: Array,
    default: () => [],
  },
  hasWideComparables: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['preview', 'add-custom-factor']);

const localCustomFactorLabel = ref('');
const activeCellEditor = ref(null);
const activeCellDraft = ref('');
const activeCellInput = ref(null);
let queuedPreviewTimeout = null;

const isAdjustmentSection = (section) => section.title?.toLowerCase().includes('adjustment - element of comparison');

const rowComputed = (comparableId, rowKey) => props.adjustmentComputed?.[comparableId]?.factors?.[rowKey] || null;

const totalComputed = (comparableId) => props.adjustmentComputed?.[comparableId] || null;

const comparableState = (comparableId) => (
  props.comparables?.find((item) => String(item.id) === String(comparableId))
  || null
);

const maintenanceAllowsAdjustment = (comparableId) => {
  const finalText = comparableState(comparableId)?.maintenance_final_text || '';
  return finalText !== '' && !String(finalText).toUpperCase().startsWith('N/A');
};

const ensureDraftInputs = (comparableId) => {
  if (!props.draftInputs[comparableId]) {
    props.draftInputs[comparableId] = {};
  }

  return props.draftInputs[comparableId];
};

const ensureDraftGeneralInputs = (comparableId) => {
  if (!props.draftGeneralInputs[comparableId]) {
    props.draftGeneralInputs[comparableId] = {};
  }

  return props.draftGeneralInputs[comparableId];
};

const queuePreview = () => {
  if (queuedPreviewTimeout) {
    clearTimeout(queuedPreviewTimeout);
  }

  queuedPreviewTimeout = setTimeout(() => {
    emit('preview');
  }, 180);
};

onBeforeUnmount(() => {
  if (queuedPreviewTimeout) {
    clearTimeout(queuedPreviewTimeout);
  }
});

const focusActiveCellInput = () => {
  nextTick(() => {
    const target = activeCellInput.value?.$el?.querySelector?.('input')
      || activeCellInput.value?.$el?.querySelector?.('textarea')
      || activeCellInput.value;

    target?.focus?.();
    target?.select?.();
  });
};

const setActiveCellInputRef = (element) => {
  activeCellInput.value = element;
};

const closeCellEditor = () => {
  activeCellEditor.value = null;
  activeCellDraft.value = '';
  activeCellInput.value = null;
};

const toNumber = (value, fallback = 0) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
};

const beginCellEditor = (config) => {
  if (!config?.id) return;

  if (activeCellEditor.value?.id && activeCellEditor.value.id !== config.id) {
    const previousPreview = activeCellEditor.value.preview;
    commitCellEditor({ preview: previousPreview !== false });
  }

  activeCellEditor.value = config;
  activeCellDraft.value = config.formatIn ? config.formatIn(config.getValue()) : config.getValue();
  focusActiveCellInput();
};

const isCellEditing = (cellId) => activeCellEditor.value?.id === cellId;

const commitCellEditor = ({ preview = true } = {}) => {
  const editor = activeCellEditor.value;
  if (!editor) return;

  const nextValue = editor.parse
    ? editor.parse(activeCellDraft.value)
    : activeCellDraft.value;

  editor.setValue(nextValue);
  const shouldPreview = editor.preview !== false && preview;
  closeCellEditor();

  if (shouldPreview) {
    queuePreview();
  }
};

const cancelCellEditor = () => {
  closeCellEditor();
};

const handleCellEditorKeydown = (event) => {
  if (event.key === 'Enter') {
    event.preventDefault();
    commitCellEditor();
    return;
  }

  if (event.key === 'Escape') {
    event.preventDefault();
    cancelCellEditor();
  }
};

const displayPlainPercent = (value, digits = 2) => {
  const number = Number(value);
  if (!Number.isFinite(number)) return '-';
  return `${formatNumber(number, digits)}%`;
};

const openAdjustmentPercentCell = (comparableId, rowKey) => {
  const draft = ensureDraftInputs(comparableId);

  beginCellEditor({
    id: `adjustment:${comparableId}:${rowKey}`,
    getValue: () => draft[rowKey] ?? 0,
    setValue: (value) => {
      draft[rowKey] = value;
    },
    parse: (value) => toNumber(value, 0),
    formatIn: (value) => value ?? 0,
  });
};

const openGeneralNumberCell = (comparableId, field, options = {}) => {
  const draft = ensureDraftGeneralInputs(comparableId);

  beginCellEditor({
    id: `general:${comparableId}:${field}`,
    getValue: () => draft[field] ?? (options.fallback ?? 0),
    setValue: (value) => {
      draft[field] = value;
    },
    parse: (value) => toNumber(value, options.fallback ?? 0),
    formatIn: (value) => value ?? (options.fallback ?? 0),
  });
};

const submitCustomFactor = () => {
  const label = localCustomFactorLabel.value.trim();
  if (!label) return;

  emit('add-custom-factor', label);
  localCustomFactorLabel.value = '';
};

const worksheetCellClass = (align = 'right') => {
  const alignment = align === 'left' ? 'justify-start text-left' : 'justify-end text-right';
  return `inline-flex min-h-8 w-full items-center rounded-md border border-dashed border-border/60 bg-background px-2.5 py-1.5 text-sm text-foreground transition hover:border-slate-400 hover:bg-slate-50 ${alignment}`;
};
</script>

<template>
  <Card
    v-for="section in matrixSections"
    :key="section.title"
    class="overflow-hidden border-border/60 shadow-sm"
  >
    <CardHeader class="border-b border-border/40 bg-muted/10 pb-4">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <CardTitle class="text-base font-semibold">{{ section.title }}</CardTitle>
          <CardDescription class="mt-0.5 text-sm">
            {{ isAdjustmentSection(section)
              ? 'Klik cell untuk edit. Perubahan dipreview otomatis saat editor ditutup.'
              : 'Klik cell yang dapat disesuaikan untuk membuka editor inline.' }}
          </CardDescription>
        </div>

        <div v-if="isAdjustmentSection(section)" class="flex w-full flex-col gap-2 lg:w-auto lg:flex-row">
          <Input
            v-model="localCustomFactorLabel"
            type="text"
            placeholder="Tambah faktor custom…"
            class="h-8 text-sm lg:min-w-56"
            @keydown.enter.prevent="submitCustomFactor"
          />
          <Button variant="outline" size="sm" class="gap-1.5 whitespace-nowrap text-sm" @click="submitCustomFactor">
            <Plus class="h-3.5 w-3.5" />
            Tambah Faktor
          </Button>
        </div>
      </div>
    </CardHeader>

    <CardContent class="p-0">
      <div
        v-if="hasWideComparables"
        class="border-b border-border/40 bg-muted/10 px-4 py-2 text-xs text-muted-foreground"
      >
        Geser horizontal untuk melihat semua pembanding. Kolom
        <span class="font-medium text-foreground">Parameter</span>
        <template v-if="!isAdjustmentSection(section)">
          dan <span class="font-medium text-foreground">Objek Penilaian</span>
        </template>
        tetap terlihat.
      </div>

      <div class="overflow-x-auto overscroll-x-contain">
        <table class="w-full caption-bottom text-sm">
          <thead>
            <tr class="border-b border-border/40 bg-muted/20">
              <th class="matrix-sticky-1 min-w-40 border-r border-border/30 bg-muted/20 px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                Parameter
              </th>

              <th
                v-if="!isAdjustmentSection(section)"
                class="matrix-sticky-2 min-w-44 border-r border-border/50 bg-muted/20 px-4 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground"
              >
                Objek Penilaian
              </th>

              <th
                v-for="column in matrixColumns"
                :key="column.id"
                class="min-w-64 px-4 py-3 text-left align-top"
              >
                <div class="space-y-0.5">
                  <p class="text-xs font-semibold text-foreground">{{ column.title }}</p>
                  <p class="text-[11px] text-muted-foreground">
                    Ext {{ column.external_id }} · Rank {{ column.rank || '-' }}
                  </p>
                  <p class="text-[11px] text-muted-foreground">{{ column.distance_to_subject || '-' }}</p>
                </div>
              </th>
            </tr>
          </thead>

          <tbody>
            <template v-for="row in section.rows" :key="`${section.title}-${row.label}-${row.key}`">
              <tr v-if="row.type === 'group'" class="border-b border-border/30">
                <td
                  :colspan="matrixColumns.length + (isAdjustmentSection(section) ? 1 : 2)"
                  class="bg-muted/30 px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground"
                >
                  {{ row.label }}
                </td>
              </tr>

              <tr
                v-else
                class="border-b border-border/20 transition-colors hover:bg-muted/10"
                :class="row.type === 'total' ? 'bg-muted/20 hover:bg-muted/30' : ''"
              >
                <td
                  class="matrix-sticky-1 w-40 min-w-40 max-w-40 border-r border-border/30 px-3 py-3 text-sm font-medium leading-snug"
                  :class="row.type === 'total' ? 'bg-muted/30 text-foreground' : 'bg-background text-foreground/80'"
                >
                  {{ row.label }}
                </td>

                <td
                  v-if="!isAdjustmentSection(section)"
                  class="matrix-sticky-2 border-r border-border/50 px-4 py-3 text-sm"
                  :class="row.type === 'total'
                    ? 'bg-muted/30 font-semibold text-foreground'
                    : 'bg-background text-foreground/70'"
                >
                  {{ row.subject || '-' }}
                </td>

                <td
                  v-for="(cell, index) in row.comparables"
                  :key="`${row.key}-${matrixColumns[index]?.id}`"
                  class="px-4 py-3 align-top"
                  :class="row.type === 'total' ? 'bg-muted/20' : ''"
                >
                  <template v-if="isAdjustmentSection(section) && row.key && row.type !== 'total'">
                    <div class="flex items-start gap-4">
                      <div class="min-w-28">
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Adj. %</p>

                        <div v-if="isCellEditing(`adjustment:${matrixColumns[index].id}:${row.key}`)" class="relative mt-1">
                          <Input
                            :ref="setActiveCellInputRef"
                            v-model="activeCellDraft"
                            type="number"
                            step="0.01"
                            min="-100"
                            max="100"
                            class="h-8 w-24 pr-6 text-right text-sm tabular-nums"
                            @blur="commitCellEditor"
                            @keydown="handleCellEditorKeydown"
                          />
                          <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-xs font-semibold text-muted-foreground">
                            %
                          </span>
                        </div>

                        <button
                          v-else
                          type="button"
                          :class="worksheetCellClass()"
                          class="mt-1 w-24"
                          @click="openAdjustmentPercentCell(matrixColumns[index].id, row.key)"
                        >
                          {{ displayPlainPercent(draftInputs[matrixColumns[index].id]?.[row.key] ?? 0) }}
                        </button>
                      </div>

                      <div>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Nominal</p>
                        <p class="mt-1 text-sm tabular-nums text-foreground/70">
                          {{ rowComputed(matrixColumns[index].id, row.key)?.amount_text || formatCurrency(0) }}
                        </p>
                      </div>
                    </div>
                  </template>

                  <template v-else-if="isAdjustmentSection(section) && row.key === 'adj_total'">
                    <p class="font-semibold tabular-nums text-primary">
                      {{ totalComputed(matrixColumns[index].id)?.total_amount_text || '-' }}
                    </p>
                    <p class="mt-0.5 text-xs tabular-nums text-muted-foreground">
                      {{ totalComputed(matrixColumns[index].id)?.total_percent_text || formatPercent(0) }}
                    </p>
                  </template>

                  <template v-else-if="isAdjustmentSection(section) && row.key === 'adj_estimated_unit'">
                    <p class="font-semibold tabular-nums text-emerald-700">
                      {{ totalComputed(matrixColumns[index].id)?.estimated_unit_text || '-' }}
                    </p>
                  </template>

                  <template v-else-if="!isAdjustmentSection(section) && row.key === 'assumed_discount'">
                    <div class="space-y-2">
                      <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Assumed Discount</p>

                      <div v-if="isCellEditing(`general:${matrixColumns[index].id}:assumed_discount`)" class="relative">
                        <Input
                          :ref="setActiveCellInputRef"
                          v-model="activeCellDraft"
                          type="number"
                          step="1"
                          min="0"
                          max="100"
                          class="h-8 w-20 pr-6 text-right text-sm tabular-nums"
                          @blur="commitCellEditor"
                          @keydown="handleCellEditorKeydown"
                        />
                        <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-xs font-semibold text-muted-foreground">
                          %
                        </span>
                      </div>

                      <button
                        v-else
                        type="button"
                        :class="worksheetCellClass()"
                        class="w-20"
                        @click="openGeneralNumberCell(matrixColumns[index].id, 'assumed_discount')"
                      >
                        {{ displayPlainPercent(draftGeneralInputs[matrixColumns[index].id]?.assumed_discount ?? 0, 0) }}
                      </button>
                    </div>
                  </template>

                  <template v-else-if="!isAdjustmentSection(section) && row.key === 'material_quality_adj'">
                    <div class="space-y-2">
                      <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Faktor</p>

                      <div v-if="isCellEditing(`general:${matrixColumns[index].id}:material_quality_adj`)" class="relative">
                        <Input
                          :ref="setActiveCellInputRef"
                          v-model="activeCellDraft"
                          type="number"
                          step="0.01"
                          min="0.01"
                          max="10"
                          class="h-8 w-24 text-right text-sm tabular-nums"
                          @blur="commitCellEditor"
                          @keydown="handleCellEditorKeydown"
                        />
                      </div>

                      <button
                        v-else
                        type="button"
                        :class="worksheetCellClass()"
                        class="w-24"
                        @click="openGeneralNumberCell(matrixColumns[index].id, 'material_quality_adj', { fallback: 1 })"
                      >
                        {{ formatNumber(draftGeneralInputs[matrixColumns[index].id]?.material_quality_adj ?? 1, 2) }}
                      </button>
                    </div>
                  </template>

                  <template v-else-if="!isAdjustmentSection(section) && row.key === 'maintenance_ref'">
                    <div class="space-y-3">
                      <div v-if="maintenanceAllowsAdjustment(matrixColumns[index].id)" class="flex items-start gap-4">
                        <div class="min-w-28">
                          <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">
                            Adj. Tambahan
                          </p>

                          <div v-if="isCellEditing(`general:${matrixColumns[index].id}:maintenance_adj_delta`)" class="relative mt-1">
                            <Input
                              :ref="setActiveCellInputRef"
                              v-model="activeCellDraft"
                              type="number"
                              step="0.01"
                              min="-100"
                              max="100"
                              class="h-8 w-24 pr-6 text-right text-sm tabular-nums"
                              @blur="commitCellEditor"
                              @keydown="handleCellEditorKeydown"
                            />
                            <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-xs font-semibold text-muted-foreground">
                              %
                            </span>
                          </div>

                          <button
                            v-else
                            type="button"
                            :class="worksheetCellClass()"
                            class="mt-1 w-24"
                            @click="openGeneralNumberCell(matrixColumns[index].id, 'maintenance_adj_delta')"
                          >
                            {{ displayPlainPercent(draftGeneralInputs[matrixColumns[index].id]?.maintenance_adj_delta ?? 0) }}
                          </button>
                        </div>

                        <div>
                          <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">
                            Final
                          </p>
                          <p class="mt-1 text-sm tabular-nums text-foreground/80">
                            {{ comparableState(matrixColumns[index].id)?.maintenance_final_text || '-' }}
                          </p>
                        </div>
                      </div>

                      <p v-else class="text-sm text-foreground/80">
                        {{ cell || '-' }}
                      </p>
                    </div>
                  </template>

                  <template v-else>
                    <p class="text-sm text-foreground/80">{{ cell || '-' }}</p>
                  </template>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </CardContent>
  </Card>
</template>

<style scoped>
.matrix-sticky-1 {
  position: static;
}

.matrix-sticky-2 {
  position: static;
}

@media (min-width: 1024px) {
  .matrix-sticky-1 {
    position: sticky;
    left: 0;
    z-index: 10;
  }

  .matrix-sticky-2 {
    position: sticky;
    left: 160px;
    z-index: 10;
    box-shadow: 2px 0 8px -2px rgba(0, 0, 0, 0.07);
  }

  thead .matrix-sticky-1,
  thead .matrix-sticky-2 {
    z-index: 20;
  }
}
</style>
