<script setup>
import { computed, nextTick, ref } from 'vue';
import { formatCurrency, formatNumber, formatPercent } from '@/utils/reviewer';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const props = defineProps({
  input: {
    type: Object,
    required: true,
  },
  templates: {
    type: Array,
    default: () => [],
  },
  worksheetContext: {
    type: Object,
    default: () => ({}),
  },
  worksheetState: {
    type: Object,
    default: () => ({}),
  },
  depreciationState: {
    type: Object,
    default: () => ({}),
  },
  referenceState: {
    type: Object,
    default: () => ({}),
  },
  summaryState: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(['preview']);

const activeCellEditor = ref(null);
const activeCellDraft = ref('');
const activeCellInput = ref(null);
let queuedPreviewTimeout = null;

const selectedTemplateLabel = computed(() =>
  props.templates.find((template) => template.value === props.input?.template_key)?.label || '-'
);

const queuePreview = () => {
  if (queuedPreviewTimeout) {
    clearTimeout(queuedPreviewTimeout);
  }

  queuedPreviewTimeout = setTimeout(() => {
    emit('preview');
  }, 180);
};

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

const parseEditableNumber = (value, fallback = 0) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
};

const beginCellEditor = (config) => {
  if (!config?.id) return;

  if (activeCellEditor.value?.id && activeCellEditor.value.id !== config.id) {
    commitCellEditor({ preview: false });
  }

  activeCellEditor.value = config;
  activeCellDraft.value = config.formatIn ? config.formatIn(config.getValue()) : config.getValue();
  focusActiveCellInput();
};

const isCellEditing = (cellId) => activeCellEditor.value?.id === cellId;

const commitCellEditor = ({ preview = true } = {}) => {
  const editor = activeCellEditor.value;
  if (!editor) return;

  const nextValue = editor.parse ? editor.parse(activeCellDraft.value) : activeCellDraft.value;
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

const openRootNumberCell = (field, options = {}) => {
  beginCellEditor({
    id: `root:${field}`,
    getValue: () => props.input?.[field] ?? '',
    setValue: (value) => {
      props.input[field] = value;
    },
    parse: (value) => parseEditableNumber(value, options.fallback ?? 0),
    formatIn: (value) => value ?? '',
  });
};

const openTemplateCell = () => {
  beginCellEditor({
    id: 'root:template_key',
    getValue: () => String(props.input?.template_key || ''),
    setValue: (value) => {
      props.input.template_key = value;
    },
    preview: true,
  });
};

const applySelectCellValue = (value) => {
  activeCellDraft.value = value;
  commitCellEditor();
};

const worksheetCellClass = (editable = true) => [
  'inline-flex min-h-9 w-full items-center rounded-md px-2.5 py-2 text-sm transition',
  editable
    ? 'cursor-pointer border border-dashed border-border/60 bg-background text-right text-foreground hover:border-slate-400 hover:bg-slate-50'
    : 'justify-end text-right font-semibold text-foreground',
];

const worksheetTextCellClass = (editable = true) => [
  'inline-flex min-h-9 w-full items-center rounded-md px-2.5 py-2 text-sm transition',
  editable
    ? 'cursor-pointer border border-dashed border-border/60 bg-background text-left text-foreground hover:border-slate-400 hover:bg-slate-50'
    : 'justify-end text-right font-semibold text-foreground',
];

const formatFactor = (value, digits = 4) => {
  const number = Number(value);
  if (Number.isNaN(number)) return '-';
  return number.toFixed(digits);
};

const formatPercentRatio = (value, digits = 2) => {
  const number = Number(value);
  if (!Number.isFinite(number)) return '-';
  return formatPercent(number * 100, digits);
};

const formatCellPlainPercent = (value, digits = 2) => {
  const number = Number(value);
  if (!Number.isFinite(number)) return '-';
  return `${formatNumber(number, digits)}%`;
};

const formatCellArea = (value) => {
  const number = Number(value);
  if (!Number.isFinite(number)) return '-';
  return `${formatNumber(number, 2)} m2`;
};
</script>

<template>
  <div class="grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
    <div class="rounded-lg border border-border/50 bg-muted/10">
      <div class="border-b border-border/40 px-4 py-3">
        <p class="text-sm font-semibold text-foreground">Form Adjustment BTB</p>
        <p class="mt-1 text-xs text-muted-foreground">
          Struktur adjustment mengikuti worksheet workbook 2025. Semua input utama diedit langsung dari cell tabel seperti worksheet.
        </p>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <tbody>
            <tr class="border-b border-border/30 bg-background/70">
              <td class="px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-muted-foreground" colspan="3">Objek yang Dinilai</td>
            </tr>
            <tr class="border-b border-border/30">
              <td class="px-4 py-3 font-medium text-foreground/80">Template</td>
              <td class="px-4 py-3 text-muted-foreground">Template workbook</td>
              <td class="px-4 py-3">
                <div v-if="isCellEditing('root:template_key')" class="space-y-2">
                  <Select :model-value="String(activeCellDraft || '')" @update:model-value="applySelectCellValue">
                    <SelectTrigger class="h-9 w-full">
                      <SelectValue placeholder="Pilih template worksheet" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="template in templates" :key="template.value" :value="template.value">
                        {{ template.label }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <div class="flex justify-end">
                    <Button variant="ghost" size="sm" type="button" @click="cancelCellEditor">Batal</Button>
                  </div>
                </div>
                <button v-else type="button" :class="worksheetTextCellClass()" @click="openTemplateCell">
                  {{ selectedTemplateLabel }}
                </button>
              </td>
            </tr>
            <tr class="border-b border-border/30">
              <td class="px-4 py-3 font-medium text-foreground/80">Jumlah Lantai</td>
              <td class="px-4 py-3 text-muted-foreground">Input objek</td>
              <td class="px-4 py-3">
                <Input v-if="isCellEditing('root:floor_count')" :ref="setActiveCellInputRef" v-model="activeCellDraft" type="number" min="1" step="1" class="h-9 text-right text-sm tabular-nums" @blur="commitCellEditor" @keydown="handleCellEditorKeydown" />
                <button v-else type="button" :class="worksheetCellClass()" @click="openRootNumberCell('floor_count', { fallback: 1 })">
                  {{ formatNumber(input.floor_count || 0, 0) }}
                </button>
              </td>
            </tr>
            <tr class="border-b border-border/30">
              <td class="px-4 py-3 font-medium text-foreground/80">Luas Bangunan</td>
              <td class="px-4 py-3 text-muted-foreground">Input objek</td>
              <td class="px-4 py-3">
                <Input v-if="isCellEditing('root:building_area')" :ref="setActiveCellInputRef" v-model="activeCellDraft" type="number" min="0" step="0.01" class="h-9 text-right text-sm tabular-nums" @blur="commitCellEditor" @keydown="handleCellEditorKeydown" />
                <button v-else type="button" :class="worksheetCellClass()" @click="openRootNumberCell('building_area')">
                  {{ formatCellArea(input.building_area || 0) }}
                </button>
              </td>
            </tr>
            <tr class="border-b border-border/30">
              <td class="px-4 py-3 font-medium text-foreground/80">Luas Tanah</td>
              <td class="px-4 py-3 text-muted-foreground">Input objek</td>
              <td class="px-4 py-3">
                <Input v-if="isCellEditing('root:land_area')" :ref="setActiveCellInputRef" v-model="activeCellDraft" type="number" min="0" step="0.01" class="h-9 text-right text-sm tabular-nums" @blur="commitCellEditor" @keydown="handleCellEditorKeydown" />
                <button v-else type="button" :class="worksheetCellClass()" @click="openRootNumberCell('land_area')">
                  {{ formatCellArea(input.land_area || 0) }}
                </button>
              </td>
            </tr>
            <tr class="border-b border-border/30">
              <td class="px-4 py-3 font-medium text-foreground/80">Market Value Aset</td>
              <td class="px-4 py-3 text-muted-foreground">Input objek</td>
              <td class="px-4 py-3">
                <Input v-if="isCellEditing('root:market_value')" :ref="setActiveCellInputRef" v-model="activeCellDraft" type="number" min="0" step="1" class="h-9 text-right text-sm tabular-nums" @blur="commitCellEditor" @keydown="handleCellEditorKeydown" />
                <button v-else type="button" :class="worksheetCellClass()" @click="openRootNumberCell('market_value')">
                  {{ formatCurrency(input.market_value || 0) }}
                </button>
              </td>
            </tr>
            <tr class="border-b border-border/30">
              <td class="px-4 py-3 font-medium text-foreground/80">Tahun Penilaian</td>
              <td class="px-4 py-3 text-muted-foreground">Workbook baseline</td>
              <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ worksheetContext.year || '-' }}</td>
            </tr>
            <tr class="border-b border-border/30">
              <td class="px-4 py-3 font-medium text-foreground/80">Tahun Dibangun</td>
              <td class="px-4 py-3 text-muted-foreground">Input objek</td>
              <td class="px-4 py-3">
                <Input v-if="isCellEditing('root:build_year')" :ref="setActiveCellInputRef" v-model="activeCellDraft" type="number" min="1900" :max="new Date().getFullYear()" class="h-9 text-right text-sm tabular-nums" @blur="commitCellEditor" @keydown="handleCellEditorKeydown" />
                <button v-else type="button" :class="worksheetCellClass()" @click="openRootNumberCell('build_year')">
                  {{ formatNumber(input.build_year || 0, 0) }}
                </button>
              </td>
            </tr>
            <tr class="border-b border-border/30">
              <td class="px-4 py-3 font-medium text-foreground/80">Tahun Direnovasi</td>
              <td class="px-4 py-3 text-muted-foreground">Input objek</td>
              <td class="px-4 py-3">
                <Input v-if="isCellEditing('root:renovation_year')" :ref="setActiveCellInputRef" v-model="activeCellDraft" type="number" min="1900" :max="new Date().getFullYear()" class="h-9 text-right text-sm tabular-nums" @blur="commitCellEditor" @keydown="handleCellEditorKeydown" />
                <button v-else type="button" :class="worksheetCellClass()" @click="openRootNumberCell('renovation_year')">
                  {{ formatNumber(input.renovation_year || 0, 0) }}
                </button>
              </td>
            </tr>
            <tr class="border-b border-border/30">
              <td class="px-4 py-3 font-medium text-foreground/80">Umur Ekonomis</td>
              <td class="px-4 py-3 text-muted-foreground">Referensi guideline</td>
              <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ referenceState.economic_life || '-' }}</td>
            </tr>
            <tr class="border-b border-border/30">
              <td class="px-4 py-3 font-medium text-foreground/80">Umur Efektif</td>
              <td class="px-4 py-3 text-muted-foreground">Derived</td>
              <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ formatFactor(depreciationState.effective_age || 0, 2) }}</td>
            </tr>

            <tr class="border-b border-border/30 bg-background/70">
              <td class="px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-muted-foreground" colspan="3">Nilai Tambah dan Depresiasi</td>
            </tr>
            <tr class="border-b border-border/30">
              <td class="px-4 py-3 font-medium text-foreground/80">Nilai Tambah (Desain / Finishing)</td>
              <td class="px-4 py-3 text-muted-foreground">Input reviewer</td>
              <td class="px-4 py-3">
                <div v-if="isCellEditing('root:design_finish_addition_percent')" class="relative">
                  <Input :ref="setActiveCellInputRef" v-model="activeCellDraft" type="number" min="0" max="100" step="0.01" class="h-9 pr-8 text-right text-sm tabular-nums" @blur="commitCellEditor" @keydown="handleCellEditorKeydown" />
                  <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-semibold text-muted-foreground">%</span>
                </div>
                <button v-else type="button" :class="worksheetCellClass()" @click="openRootNumberCell('design_finish_addition_percent')">
                  {{ formatCellPlainPercent(input.design_finish_addition_percent || 0) }}
                </button>
              </td>
            </tr>
            <tr class="border-b border-border/30">
              <td class="px-4 py-3 font-medium text-foreground/80">Kemunduran Fisik (Curable)</td>
              <td class="px-4 py-3 text-muted-foreground">Derived = umur efektif / umur ekonomis</td>
              <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ formatPercentRatio(depreciationState.curable_physical_percent || 0) }}</td>
            </tr>
            <tr class="border-b border-border/30">
              <td class="px-4 py-3 font-medium text-foreground/80">Kondisi Terlihat (Perawatan)</td>
              <td class="px-4 py-3 text-muted-foreground">Input reviewer</td>
              <td class="px-4 py-3">
                <div v-if="isCellEditing('root:maintenance_adjustment_percent')" class="relative">
                  <Input :ref="setActiveCellInputRef" v-model="activeCellDraft" type="number" min="0" max="100" step="0.01" class="h-9 pr-8 text-right text-sm tabular-nums" @blur="commitCellEditor" @keydown="handleCellEditorKeydown" />
                  <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-semibold text-muted-foreground">%</span>
                </div>
                <button v-else type="button" :class="worksheetCellClass()" @click="openRootNumberCell('maintenance_adjustment_percent')">
                  {{ formatCellPlainPercent(input.maintenance_adjustment_percent || 0) }}
                </button>
              </td>
            </tr>
            <tr class="border-b border-border/30">
              <td class="px-4 py-3 font-medium text-foreground/80">Kemunduran Incurable</td>
              <td class="px-4 py-3 text-muted-foreground">Input reviewer</td>
              <td class="px-4 py-3">
                <div v-if="isCellEditing('root:incurable_depreciation_percent')" class="relative">
                  <Input :ref="setActiveCellInputRef" v-model="activeCellDraft" type="number" min="0" max="100" step="0.01" class="h-9 pr-8 text-right text-sm tabular-nums" @blur="commitCellEditor" @keydown="handleCellEditorKeydown" />
                  <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-semibold text-muted-foreground">%</span>
                </div>
                <button v-else type="button" :class="worksheetCellClass()" @click="openRootNumberCell('incurable_depreciation_percent')">
                  {{ formatCellPlainPercent(input.incurable_depreciation_percent || 0) }}
                </button>
              </td>
            </tr>
            <tr class="border-b border-border/30">
              <td class="px-4 py-3 font-medium text-foreground/80">Keusangan Fungsi</td>
              <td class="px-4 py-3 text-muted-foreground">Input reviewer</td>
              <td class="px-4 py-3">
                <div v-if="isCellEditing('root:functional_obsolescence_percent')" class="relative">
                  <Input :ref="setActiveCellInputRef" v-model="activeCellDraft" type="number" min="0" max="100" step="0.01" class="h-9 pr-8 text-right text-sm tabular-nums" @blur="commitCellEditor" @keydown="handleCellEditorKeydown" />
                  <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-semibold text-muted-foreground">%</span>
                </div>
                <button v-else type="button" :class="worksheetCellClass()" @click="openRootNumberCell('functional_obsolescence_percent')">
                  {{ formatCellPlainPercent(input.functional_obsolescence_percent || 0) }}
                </button>
              </td>
            </tr>
            <tr class="border-b border-border/30">
              <td class="px-4 py-3 font-medium text-foreground/80">Keusangan Ekonomis</td>
              <td class="px-4 py-3 text-muted-foreground">Input reviewer</td>
              <td class="px-4 py-3">
                <div v-if="isCellEditing('root:economic_obsolescence_percent')" class="relative">
                  <Input :ref="setActiveCellInputRef" v-model="activeCellDraft" type="number" min="0" max="100" step="0.01" class="h-9 pr-8 text-right text-sm tabular-nums" @blur="commitCellEditor" @keydown="handleCellEditorKeydown" />
                  <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-semibold text-muted-foreground">%</span>
                </div>
                <button v-else type="button" :class="worksheetCellClass()" @click="openRootNumberCell('economic_obsolescence_percent')">
                  {{ formatCellPlainPercent(input.economic_obsolescence_percent || 0) }}
                </button>
              </td>
            </tr>
            <tr class="border-b border-border/30 bg-amber-50/70">
              <td class="px-4 py-3 font-semibold text-foreground">Total Penyusutan</td>
              <td class="px-4 py-3 text-muted-foreground">Derived workbook formula</td>
              <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ formatPercentRatio(depreciationState.total_depreciation_percent || 0) }}</td>
            </tr>
            <tr>
              <td class="px-4 py-3 font-semibold text-foreground">Kondisi Bangunan</td>
              <td class="px-4 py-3 text-muted-foreground">Derived workbook condition band</td>
              <td class="px-4 py-3 text-right font-semibold">{{ depreciationState.condition_label || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="space-y-4">
      <div class="rounded-lg border border-border/50 bg-muted/10 p-4">
        <p class="text-sm font-semibold text-foreground">Reference Adjustment</p>
        <div class="mt-3 space-y-3 text-sm">
          <div class="flex items-center justify-between gap-3">
            <span class="text-foreground/70">Indeks Kemahalan Konstruksi</span>
            <span class="font-medium tabular-nums">{{ formatFactor(referenceState.ikk_value || 1) }}</span>
          </div>
          <div class="flex items-center justify-between gap-3">
            <span class="text-foreground/70">Indeks lantai</span>
            <span class="font-medium tabular-nums">{{ formatFactor(referenceState.floor_index_value || 1) }}</span>
          </div>
          <div class="flex items-center justify-between gap-3">
            <span class="text-foreground/70">PPN guideline</span>
            <span class="font-medium tabular-nums">{{ formatPercent(referenceState.ppn_percent || 0, 2) }}</span>
          </div>
          <div class="flex items-center justify-between gap-3">
            <span class="text-foreground/70">Sisa nilai bangunan</span>
            <span class="font-medium tabular-nums">{{ formatPercentRatio(depreciationState.remaining_value_factor || 0) }}</span>
          </div>
        </div>
      </div>

      <div class="rounded-lg border border-border/50 p-4">
        <p class="text-sm font-semibold text-foreground">Ringkasan Worksheet</p>
        <div class="mt-3 space-y-2">
          <div class="flex items-center justify-between gap-3 text-sm">
            <span class="text-foreground/70">Biaya langsung setelah Indeks Kemahalan Konstruksi & indeks lantai</span>
            <span class="font-medium tabular-nums">{{ formatCurrency(worksheetState.hard_cost_total_ikk_floor_index || 0) }}</span>
          </div>
          <div class="flex items-center justify-between gap-3 text-sm">
            <span class="text-foreground/70">Nilai tambah desain / finishing</span>
            <span class="font-medium tabular-nums">{{ formatCurrency(worksheetState.design_finish_addition_amount || 0) }}</span>
          </div>
          <div class="flex items-center justify-between gap-3 text-sm">
            <span class="text-foreground/70">Biaya tak langsung</span>
            <span class="font-medium tabular-nums">{{ formatCurrency(worksheetState.soft_cost_total || 0) }}</span>
          </div>
          <div class="flex items-center justify-between gap-3 border-t border-border/40 pt-2 text-sm">
            <span class="text-foreground/70">DEPRESIASI (Rp/m2)</span>
            <span class="font-medium tabular-nums">{{ formatCurrency(depreciationState.depreciation_amount_per_sqm || 0) }}</span>
          </div>
          <div class="flex items-center justify-between gap-3 text-sm">
            <span class="text-foreground/70">BRB terdepresiasi (Rp/m2)</span>
            <span class="font-medium tabular-nums">{{ formatCurrency(depreciationState.depreciated_brb_per_sqm || 0) }}</span>
          </div>
          <div class="flex items-center justify-between gap-3 text-sm font-semibold">
            <span>BRB terdepresiasi total</span>
            <span class="tabular-nums">{{ formatCurrency(depreciationState.depreciated_brb_total || 0) }}</span>
          </div>
          <div class="flex items-center justify-between gap-3 text-sm font-semibold">
            <span>Residual land value / sqm</span>
            <span class="tabular-nums">{{ formatCurrency(summaryState.residual_land_value_per_sqm || 0) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
