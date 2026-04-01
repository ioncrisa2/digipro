<script setup>
import { Link } from '@inertiajs/vue3';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Calculator, Save, ArrowLeft, SlidersHorizontal } from 'lucide-vue-next';

defineProps({
  asset: {
    type: Object,
    required: true,
  },
  contextMeta: {
    type: Object,
    default: () => ({}),
  },
  rangeSummary: {
    type: Object,
    default: () => ({}),
  },
  feedback: {
    type: String,
    default: '',
  },
  feedbackClasses: {
    type: String,
    default: '',
  },
  busyPreview: {
    type: Boolean,
    default: false,
  },
  busySave: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['preview', 'save']);
</script>

<template>
  <Card class="border-border/60 shadow-sm">
    <CardHeader class="pb-5">
      <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
        <div class="space-y-1.5">
          <div class="flex items-center gap-2">
            <SlidersHorizontal class="h-3.5 w-3.5 text-muted-foreground" />
            <CardDescription class="text-[10px] font-semibold uppercase tracking-widest">Adjust Harga Tanah</CardDescription>
          </div>
          <CardTitle class="text-2xl font-semibold leading-snug tracking-tight">
            {{ asset.address }}
          </CardTitle>
          <p class="text-sm text-muted-foreground">
            Request&nbsp;<span class="font-medium text-foreground">{{ contextMeta?.request_number || asset.request_number || '-' }}</span>
            &nbsp;·&nbsp;
            Guideline&nbsp;<span class="font-medium text-foreground">{{ contextMeta?.guideline || '-' }} {{ contextMeta?.guideline_year || '' }}</span>
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <Button variant="outline" size="sm" :disabled="busyPreview" class="gap-1.5 text-sm" @click="$emit('preview')">
            <Calculator class="h-3.5 w-3.5" />
            Preview
          </Button>
          <Button size="sm" :disabled="busySave" class="gap-1.5 text-sm" @click="$emit('save')">
            <Save class="h-3.5 w-3.5" />
            Simpan Adjustment
          </Button>
          <Button variant="ghost" size="sm" as-child class="gap-1.5 text-sm text-muted-foreground hover:text-foreground">
            <Link :href="route('reviewer.assets.show', asset.id)">
              <ArrowLeft class="h-3.5 w-3.5" />
              Kembali
            </Link>
          </Button>
        </div>
      </div>
    </CardHeader>

    <CardContent class="space-y-4 pt-0">
      <Alert v-if="feedback" :class="feedbackClasses" class="py-3">
        <Calculator class="h-4 w-4" />
        <AlertTitle class="text-sm font-medium">Adjustment Feedback</AlertTitle>
        <AlertDescription class="text-sm">{{ feedback }}</AlertDescription>
      </Alert>

      <div class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-lg border border-border/50 bg-muted/20 px-4 py-3 transition-colors hover:bg-muted/40">
          <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Low</p>
          <p class="mt-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Per Meter</p>
          <p class="mt-1.5 text-xl font-semibold tabular-nums">{{ rangeSummary.unit_low_text || '-' }}</p>
          <p class="mt-3 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Value</p>
          <p class="mt-1.5 text-xl font-semibold tabular-nums">{{ rangeSummary.value_low_text || '-' }}</p>
        </div>
        <div class="rounded-lg border border-border/50 bg-muted/30 px-4 py-3 transition-colors hover:bg-muted/50">
          <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">High</p>
          <p class="mt-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Per Meter</p>
          <p class="mt-1.5 text-xl font-semibold tabular-nums">{{ rangeSummary.unit_high_text || '-' }}</p>
          <p class="mt-3 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Value</p>
          <p class="mt-1.5 text-xl font-semibold tabular-nums">{{ rangeSummary.value_high_text || '-' }}</p>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
