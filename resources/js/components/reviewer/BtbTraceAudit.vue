<script setup>
import { formatCurrency } from '@/utils/reviewer';

defineProps({
  checks: {
    type: Array,
    default: () => [],
  },
  groups: {
    type: Array,
    default: () => [],
  },
});
</script>

<template>
  <div class="rounded-lg border border-border/50 p-4">
    <p class="text-sm font-semibold text-foreground">Trace Audit</p>
    <div class="mt-3 space-y-3">
      <div class="grid gap-2 md:grid-cols-2">
        <div v-for="check in checks" :key="check.label" class="rounded-md border border-border/40 bg-muted/10 px-3 py-2 text-sm">
          <div class="flex items-center justify-between gap-3">
            <span class="text-foreground/70">{{ check.label }}</span>
            <span class="font-medium" :class="check.status === 'missing' ? 'text-red-700' : 'text-emerald-700'">
              {{ check.status === 'missing' ? 'Missing' : 'Ready' }}
            </span>
          </div>
          <p class="mt-1 font-semibold tabular-nums text-foreground">{{ check.value ?? '-' }}</p>
        </div>
      </div>

      <div class="overflow-x-auto rounded-md border border-border/40">
        <table class="w-full text-sm">
          <thead class="bg-muted/20">
            <tr>
              <th class="px-3 py-2 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Group</th>
              <th class="px-3 py-2 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="group in groups" :key="group.line_code" class="border-t border-border/30">
              <td class="px-3 py-2 font-medium text-foreground/80">{{ group.label }}</td>
              <td class="px-3 py-2 font-semibold tabular-nums">{{ formatCurrency(group.subtotal || 0) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
