<script setup>
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { TriangleAlert } from 'lucide-vue-next';

defineProps({
  warnings: {
    type: Array,
    default: () => [],
  },
});
</script>

<template>
  <Card v-if="warnings.length" class="border-amber-200/80 bg-amber-50/40 shadow-sm">
    <CardHeader class="pb-3">
      <div class="flex items-start gap-3">
        <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-amber-100/80">
          <TriangleAlert class="h-4 w-4 text-amber-600" />
        </div>
        <div>
          <CardTitle class="text-base">Peringatan Pembanding</CardTitle>
          <CardDescription class="mt-0.5 text-sm">Periksa data yang belum layak estimasi sebelum final save.</CardDescription>
        </div>
      </div>
    </CardHeader>
    <CardContent>
      <div class="grid gap-3 lg:grid-cols-2">
        <div
          v-for="column in warnings"
          :key="column.id"
          class="rounded-lg border border-amber-200/60 bg-white/70 p-3.5"
        >
          <p class="text-sm font-medium text-foreground">
            {{ column.title }}
            <span class="ml-1.5 text-xs font-normal text-muted-foreground">· Ext {{ column.external_id }}</span>
          </p>
          <ul class="mt-2 space-y-1 pl-4 text-sm text-muted-foreground" style="list-style: disc;">
            <li v-for="warning in column.warnings" :key="warning">{{ warning }}</li>
          </ul>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
