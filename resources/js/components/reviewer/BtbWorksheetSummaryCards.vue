<script setup>
import { formatCurrency } from '@/utils/reviewer';

defineProps({
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
</script>

<template>
  <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
    <div class="rounded-lg border border-border/50 bg-muted/20 px-4 py-3">
      <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Hard Cost Adj.</p>
      <p class="mt-1.5 text-lg font-semibold tabular-nums">{{ formatCurrency(worksheetState.hard_cost_total_ikk_floor_index || 0) }}</p>
    </div>
    <div class="rounded-lg border border-border/50 bg-muted/20 px-4 py-3">
      <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Nilai Tambah</p>
      <p class="mt-1.5 text-lg font-semibold tabular-nums">{{ formatCurrency(worksheetState.design_finish_addition_amount || 0) }}</p>
    </div>
    <div class="rounded-lg border border-border/50 bg-muted/20 px-4 py-3">
      <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Soft Cost</p>
      <p class="mt-1.5 text-lg font-semibold tabular-nums">{{ formatCurrency(worksheetState.soft_cost_total || 0) }}</p>
    </div>
    <div class="rounded-lg border border-border/50 bg-muted/20 px-4 py-3">
      <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">BRB / sqm</p>
      <p class="mt-1.5 text-lg font-semibold tabular-nums">{{ formatCurrency(worksheetState.total_brb_per_sqm || worksheetState.total_rcn || 0) }}</p>
    </div>
    <div class="rounded-lg border border-border/50 bg-muted/20 px-4 py-3">
      <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Nilai Bangunan</p>
      <p class="mt-1.5 text-lg font-semibold tabular-nums">{{ formatCurrency(depreciationState.depreciated_brb_total || 0) }}</p>
    </div>
    <div class="rounded-lg border border-border/50 bg-muted/20 px-4 py-3">
      <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Residual Land</p>
      <p class="mt-1.5 text-lg font-semibold tabular-nums">{{ formatCurrency(summaryState.residual_land_value || 0) }}</p>
    </div>
  </div>
</template>
