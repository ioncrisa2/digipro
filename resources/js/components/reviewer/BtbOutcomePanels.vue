<script setup>
import { formatCurrency, formatPercent } from '@/utils/reviewer';

const props = defineProps({
  indirectCostLines: {
    type: Array,
    default: () => [],
  },
  worksheetState: {
    type: Object,
    default: () => ({}),
  },
  depreciationState: {
    type: Object,
    default: () => ({}),
  },
  summaryState: {
    type: Object,
    default: () => ({}),
  },
});

const formatPercentRatio = (value, digits = 2) => {
  const number = Number(value);
  if (!Number.isFinite(number)) return '-';
  return formatPercent(number * 100, digits);
};
</script>

<template>
  <div class="grid gap-4 xl:grid-cols-2">
    <div class="rounded-lg border border-border/50 p-4">
      <p class="text-sm font-semibold text-foreground">Biaya Tak Langsung</p>
      <div class="mt-3 space-y-2">
        <div v-for="line in indirectCostLines" :key="line.line_code" class="flex items-center justify-between gap-3 text-sm">
          <span class="text-foreground/70">{{ line.label }} ({{ formatPercentRatio(line.percentage || line.factor || 0) }})</span>
          <span class="font-medium tabular-nums">{{ formatCurrency(line.value || 0) }}</span>
        </div>
        <div class="flex items-center justify-between gap-3 border-t border-border/40 pt-2 text-sm">
          <span class="text-foreground/70">Nilai tambah desain / finishing</span>
          <span class="font-medium tabular-nums">{{ formatCurrency(worksheetState.design_finish_addition_amount || 0) }}</span>
        </div>
        <div class="flex items-center justify-between gap-3 border-t border-border/40 pt-2 text-sm font-semibold">
          <span>Total biaya tak langsung</span>
          <span class="tabular-nums">{{ formatCurrency(worksheetState.soft_cost_total || 0) }}</span>
        </div>
        <div class="flex items-center justify-between gap-3 text-sm font-semibold">
          <span>Total BRB / sqm</span>
          <span class="tabular-nums">{{ formatCurrency(worksheetState.total_brb_per_sqm || worksheetState.total_rcn || 0) }}</span>
        </div>
        <div class="flex items-center justify-between gap-3 text-sm font-semibold">
          <span>Total BRB (Rp)</span>
          <span class="tabular-nums">{{ formatCurrency(worksheetState.total_brb || 0) }}</span>
        </div>
      </div>
    </div>

    <div class="rounded-lg border border-border/50 p-4">
      <p class="text-sm font-semibold text-foreground">Depresiasi Bangunan</p>
      <div class="mt-3 space-y-2 text-sm">
        <div class="flex items-center justify-between gap-3">
          <span class="text-foreground/70">Curable fisik</span>
          <span class="font-medium">{{ formatPercentRatio(depreciationState.curable_physical_percent || 0) }}</span>
        </div>
        <div class="flex items-center justify-between gap-3">
          <span class="text-foreground/70">Perawatan</span>
          <span class="font-medium">{{ formatPercentRatio(depreciationState.maintenance_adjustment_percent || 0) }}</span>
        </div>
        <div class="flex items-center justify-between gap-3">
          <span class="text-foreground/70">Incurable</span>
          <span class="font-medium">{{ formatPercentRatio(depreciationState.incurable_depreciation_percent || 0) }}</span>
        </div>
        <div class="flex items-center justify-between gap-3">
          <span class="text-foreground/70">Keusangan fungsi</span>
          <span class="font-medium">{{ formatPercentRatio(depreciationState.functional_obsolescence_percent || 0) }}</span>
        </div>
        <div class="flex items-center justify-between gap-3">
          <span class="text-foreground/70">Keusangan ekonomis</span>
          <span class="font-medium">{{ formatPercentRatio(depreciationState.economic_obsolescence_percent || 0) }}</span>
        </div>
        <div class="flex items-center justify-between gap-3 border-t border-border/40 pt-2">
          <span class="text-foreground/70">Depresiasi / sqm</span>
          <span class="font-medium tabular-nums">{{ formatCurrency(depreciationState.depreciation_amount_per_sqm || 0) }}</span>
        </div>
        <div class="flex items-center justify-between gap-3">
          <span class="text-foreground/70">BRB terdepresiasi / sqm</span>
          <span class="font-medium tabular-nums">{{ formatCurrency(depreciationState.depreciated_brb_per_sqm || 0) }}</span>
        </div>
        <div class="flex items-center justify-between gap-3 font-semibold">
          <span>BRB terdepresiasi total</span>
          <span class="tabular-nums">{{ formatCurrency(depreciationState.depreciated_brb_total || 0) }}</span>
        </div>
        <div class="flex items-center justify-between gap-3 font-semibold">
          <span>Residual land value / sqm</span>
          <span class="tabular-nums">{{ formatCurrency(summaryState.residual_land_value_per_sqm || 0) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
