<script setup>
import { nextTick, ref } from 'vue';
import { formatCurrency, formatNumber, formatPercent } from '@/utils/reviewer';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Check, ChevronsUpDown, Search } from 'lucide-vue-next';

const props = defineProps({
  lines: {
    type: Array,
    default: () => [],
  },
  subjectOverrides: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(['preview']);

const materialPickerOpen = ref({});
const materialSearch = ref({});
const activeCellEditor = ref(null);
const activeCellDraft = ref('');
const activeCellInput = ref(null);
let queuedPreviewTimeout = null;

const toHumanPercentInput = (value) => {
  const number = Number(value);
  if (!Number.isFinite(number)) return 0;
  return number <= 1 ? Number((number * 100).toFixed(4)) : number;
};

const toFiniteNumber = (value, fallback = null) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
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

const ensureSubjectOverride = (item) => {
  const itemKey = item?.item_key;
  if (!itemKey) return null;

  if (!props.subjectOverrides[itemKey]) {
    const matchedOption = (item?.material_options || []).find((option) => option.value === (item?.subject_material_spec || ''));
    props.subjectOverrides[itemKey] = {
      subject_material_spec: item?.subject_material_spec || item?.model_material_spec || '',
      subject_unit_cost: item?.subject_unit_cost ?? item?.model_unit_cost ?? 0,
      subject_volume_percent: toHumanPercentInput(item?.subject_volume_percent ?? item?.model_volume_percent ?? 0),
      other_adjustment_factor: item?.other_adjustment_factor ?? 1,
      material_option_value: matchedOption?.value || '__manual__',
    };
  }

  return props.subjectOverrides[itemKey];
};

const displayDirectCostResult = (item) => {
  const override = props.subjectOverrides?.[item?.item_key] || {};

  const volumePercent = toFiniteNumber(
    override.subject_volume_percent,
    toHumanPercentInput(item?.subject_volume_percent ?? item?.model_volume_percent ?? 0),
  );

  const unitCost = toFiniteNumber(
    override.subject_unit_cost,
    item?.subject_unit_cost ?? item?.model_unit_cost ?? 0,
  );

  const otherFactor = toFiniteNumber(
    override.other_adjustment_factor,
    item?.other_adjustment_factor ?? 1,
  );

  return Math.round(unitCost * (volumePercent / 100) * otherFactor);
};

const materialSelectValue = (item) => {
  const override = ensureSubjectOverride(item);
  if (override?.material_option_value) {
    return override.material_option_value;
  }

  const currentSpec = String(override?.subject_material_spec || '').trim();
  const options = item?.material_options || [];
  const matched = options.find((option) => String(option.value || '').trim() === currentSpec);

  return matched ? matched.value : '__manual__';
};

const materialSearchValue = (item) => materialSearch.value[item?.item_key] || '';

const setMaterialPickerOpen = (item, open) => {
  if (!item?.item_key) return;
  materialPickerOpen.value[item.item_key] = Boolean(open);

  if (!open) {
    materialSearch.value[item.item_key] = '';
  }
};

const filteredMaterialOptions = (item) => {
  const query = materialSearchValue(item).trim().toLowerCase();
  const options = item?.material_options || [];

  if (!query) {
    return options;
  }

  return options.filter((option) =>
    String(option.label || option.value || '')
      .toLowerCase()
      .includes(query),
  );
};

const selectedMaterialLabel = (item) => {
  const selectedValue = materialSelectValue(item);
  if (selectedValue === '__manual__') {
    return props.subjectOverrides?.[item?.item_key]?.subject_material_spec || 'Manual / Material lain';
  }

  const selectedOption = (item?.material_options || []).find((option) => option.value === selectedValue);
  return selectedOption?.label || item?.subject_material_spec || 'Pilih material objek';
};

const queuePreview = () => {
  if (queuedPreviewTimeout) {
    clearTimeout(queuedPreviewTimeout);
  }

  queuedPreviewTimeout = setTimeout(() => {
    emit('preview');
  }, 180);
};

const applyMaterialSelection = (item, value) => {
  const override = ensureSubjectOverride(item);
  if (!override) return;

  if (value === '__manual__') {
    override.material_option_value = '__manual__';
    if (!override.subject_material_spec) {
      override.subject_material_spec = item?.subject_material_spec || item?.model_material_spec || '';
    }

    setMaterialPickerOpen(item, false);
    return;
  }

  const selectedOption = (item?.material_options || []).find((option) => option.value === value);
  if (!selectedOption) return;

  override.material_option_value = selectedOption.value;
  override.subject_material_spec = selectedOption.label;
  override.subject_unit_cost = selectedOption.unit_cost;
  setMaterialPickerOpen(item, false);
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

const openSubjectNumberCell = (item, field, options = {}) => {
  const override = ensureSubjectOverride(item);
  if (!override) return;

  beginCellEditor({
    id: `subject:${item.item_key}:${field}`,
    getValue: () => override[field] ?? '',
    setValue: (value) => {
      override[field] = value;
    },
    parse: (value) => parseEditableNumber(value, options.fallback ?? 0),
    formatIn: (value) => value ?? '',
  });
};

const worksheetCellClass = () => 'inline-flex min-h-9 w-full items-center rounded-md border border-dashed border-border/60 bg-background px-2.5 py-2 text-right text-sm text-foreground transition hover:border-slate-400 hover:bg-slate-50';
</script>

<template>
  <div class="overflow-x-auto rounded-lg border border-border/50">
    <table class="w-full text-sm">
      <thead class="bg-muted/20">
        <tr class="border-b border-border/40">
          <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
            Elemen</th>
          <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
            Material Model</th>
          <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
            Volume Model (%)</th>
          <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
            Biaya Model</th>
          <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
            Material Objek</th>
          <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
            Volume Objek (%)</th>
          <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
            Biaya Objek</th>
          <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
            Adj. Lain</th>
          <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
            Hasil</th>
        </tr>
      </thead>
      <tbody>
        <template v-for="line in lines" :key="line.line_code">
          <tr class="border-t border-border/40 bg-muted/10">
            <td colspan="9" class="px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
              {{ line.label }}
              <span class="ml-2 text-xs normal-case text-foreground/70">Subtotal {{
                formatCurrency(line.subtotal || 0) }}</span>
            </td>
          </tr>
          <tr v-for="item in line.items" :key="item.item_key" class="border-t border-border/30">
            <td class="px-3 py-3 text-sm font-medium text-foreground/80">{{ item.element_name }}</td>
            <td class="px-3 py-3 text-sm text-foreground/70">{{ item.model_material_spec }}</td>
            <td class="px-3 py-3 text-sm tabular-nums text-foreground/70">{{ formatPercentRatio(item.model_volume_percent || 0) }}</td>
            <td class="px-3 py-3 text-sm tabular-nums text-foreground/70">{{ formatCurrency(item.model_unit_cost) }}</td>
            <td class="px-3 py-3">
              <div class="space-y-2">
                <Popover :open="materialPickerOpen[item.item_key] || false"
                  @update:open="(open) => setMaterialPickerOpen(item, open)">
                  <PopoverTrigger as-child>
                    <button type="button"
                      class="inline-flex min-h-9 w-full min-w-44 items-center justify-between rounded-md border border-dashed border-border/60 bg-background px-2.5 py-2 text-left text-sm text-foreground transition hover:border-slate-400 hover:bg-slate-50">
                      <span class="truncate">{{ selectedMaterialLabel(item) }}</span>
                      <ChevronsUpDown class="ml-2 h-3.5 w-3.5 shrink-0 opacity-60" />
                    </button>
                  </PopoverTrigger>

                  <PopoverContent class="w-[22rem] p-3" align="start">
                    <div class="space-y-3">
                      <div class="relative">
                        <Search
                          class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <Input :model-value="materialSearchValue(item)" type="text"
                          placeholder="Cari material workbook" class="pl-9"
                          @input="materialSearch[item.item_key] = $event.target.value" />
                      </div>

                      <div
                        class="max-h-64 space-y-1 overflow-y-auto rounded-md border border-border/40 bg-background/70 p-1">
                        <button v-for="option in filteredMaterialOptions(item)" :key="`${item.item_key}-${option.value}`"
                          type="button"
                          class="flex w-full items-start justify-between rounded-md px-2 py-2 text-left text-sm hover:bg-muted/70"
                          @click="applyMaterialSelection(item, option.value); queuePreview();">
                          <div class="min-w-0">
                            <p class="truncate font-medium text-foreground">{{ option.label }}</p>
                            <p class="text-xs text-muted-foreground">
                              {{ formatCurrency(option.unit_cost) }}
                            </p>
                          </div>
                          <Check v-if="materialSelectValue(item) === option.value"
                            class="ml-2 h-4 w-4 shrink-0 text-emerald-600" />
                        </button>

                        <div v-if="!filteredMaterialOptions(item).length" class="px-2 py-3 text-sm text-muted-foreground">
                          Tidak ada material yang cocok dengan pencarian ini.
                        </div>
                      </div>

                      <Button variant="outline" type="button" class="w-full justify-start text-sm"
                        @click="applyMaterialSelection(item, '__manual__')">
                        Gunakan input manual
                      </Button>
                    </div>
                  </PopoverContent>
                </Popover>

                <Input v-if="materialSelectValue(item) === '__manual__'"
                  v-model="subjectOverrides[item.item_key].subject_material_spec" type="text" class="h-8 min-w-44 text-sm"
                  placeholder="Masukkan material objek" @blur="queuePreview" />
              </div>
            </td>
            <td class="px-3 py-3">
              <div v-if="isCellEditing(`subject:${item.item_key}:subject_volume_percent`)" class="relative">
                <Input :ref="setActiveCellInputRef" v-model="activeCellDraft" type="number" min="0" max="100"
                  step="0.01" class="h-9 w-28 pr-7 text-right text-sm tabular-nums" @blur="commitCellEditor"
                  @keydown="handleCellEditorKeydown" />
                <span
                  class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-xs font-semibold text-muted-foreground">%</span>
              </div>
              <button v-else type="button" :class="worksheetCellClass()"
                @click="openSubjectNumberCell(item, 'subject_volume_percent')">
                {{ formatCellPlainPercent(subjectOverrides[item.item_key].subject_volume_percent || 0) }}
              </button>
            </td>
            <td class="px-3 py-3">
              <Input v-if="isCellEditing(`subject:${item.item_key}:subject_unit_cost`)" :ref="setActiveCellInputRef"
                v-model="activeCellDraft" type="number" min="0" step="1" class="h-9 w-32 text-right text-sm tabular-nums"
                @blur="commitCellEditor" @keydown="handleCellEditorKeydown" />
              <button v-else type="button" :class="worksheetCellClass()"
                @click="openSubjectNumberCell(item, 'subject_unit_cost')">
                {{ formatCurrency(subjectOverrides[item.item_key].subject_unit_cost || 0) }}
              </button>
            </td>
            <td class="px-3 py-3">
              <Input v-if="isCellEditing(`subject:${item.item_key}:other_adjustment_factor`)" :ref="setActiveCellInputRef"
                v-model="activeCellDraft" type="number" min="0" step="0.0001" class="h-9 w-24 text-right text-sm tabular-nums"
                @blur="commitCellEditor" @keydown="handleCellEditorKeydown" />
              <button v-else type="button" :class="worksheetCellClass()"
                @click="openSubjectNumberCell(item, 'other_adjustment_factor', { fallback: 1 })">
                {{ formatNumber(subjectOverrides[item.item_key].other_adjustment_factor || 1, 4) }}
              </button>
            </td>
            <td class="px-3 py-3 text-sm font-semibold tabular-nums text-foreground">
              {{ formatCurrency(displayDirectCostResult(item)) }}
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>
