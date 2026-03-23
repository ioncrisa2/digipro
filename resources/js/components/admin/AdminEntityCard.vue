<script setup>
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  description: { type: String, default: '' },
  contentClass: { type: String, default: 'space-y-4' },
});
</script>

<template>
  <Card class="overflow-hidden border-slate-200/90 shadow-sm">
    <slot name="media" />

    <CardHeader class="space-y-3 pb-4">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <CardTitle class="line-clamp-2 text-lg text-slate-950">
            <slot name="title">
              {{ title }}
            </slot>
          </CardTitle>
          <CardDescription v-if="subtitle || $slots.subtitle" class="mt-1">
            <slot name="subtitle">
              {{ subtitle }}
            </slot>
          </CardDescription>
        </div>

        <div v-if="$slots.badges" class="flex flex-wrap items-center justify-end gap-2">
          <slot name="badges" />
        </div>
      </div>
    </CardHeader>

    <CardContent :class="contentClass">
      <p v-if="description || $slots.description" class="text-sm leading-6 text-slate-600">
        <slot name="description">
          {{ description }}
        </slot>
      </p>

      <div v-if="$slots.meta" class="space-y-1 text-xs text-slate-500">
        <slot name="meta" />
      </div>

      <div v-if="$slots.extra">
        <slot name="extra" />
      </div>

      <div v-if="$slots.footer" class="flex flex-wrap gap-2 pt-1">
        <slot name="footer" />
      </div>
    </CardContent>
  </Card>
</template>
