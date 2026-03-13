<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import { Head, Link } from '@inertiajs/vue3';
import ReviewerLayout from '@/layouts/ReviewerLayout.vue';
import { cloneDeep, formatCurrency, formatPercent } from '@/utils/reviewer';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Calculator, Save, Plus, TriangleAlert, ArrowLeft, SlidersHorizontal } from 'lucide-vue-next';

const props = defineProps({
  asset: Object,
  workbench: Object,
});

const state = ref(cloneDeep(props.workbench));
const draftInputs = ref(cloneDeep(props.workbench.adjustment_inputs) || {});
const draftGeneralInputs = ref(cloneDeep(props.workbench.general_inputs) || {});
const customFactors = ref(cloneDeep(props.workbench.custom_adjustment_factors) || []);
const newCustomFactorLabel = ref('');
const busyPreview = ref(false);
const busySave = ref(false);
const feedback = ref('');
const feedbackTone = ref('default');

const matrixSections = computed(() => state.value?.matrix_sections || []);
const matrixColumns = computed(() => state.value?.matrix_columns || []);
const rangeSummary = computed(() => state.value?.range_summary || {});
const hasWideComparables = computed(() => matrixColumns.value.length > 3);
const comparableWarnings = computed(() => {
  const computedState = state.value?.adjustment_computed || {};
  return matrixColumns.value
    .map((column) => ({
      ...column,
      warnings: computedState[column.id]?.warnings || [],
    }))
    .filter((column) => column.warnings.length > 0);
});

const syncState = (payload) => {
  state.value = cloneDeep(payload);
  draftInputs.value = cloneDeep(payload.adjustment_inputs || {});
  draftGeneralInputs.value = cloneDeep(payload.general_inputs || {});
  customFactors.value = cloneDeep(payload.custom_adjustment_factors || []);
};

const setFeedback = (message, tone = 'default') => {
  feedback.value = message;
  feedbackTone.value = tone;
};

const rowComputed = (comparableId, rowKey) => {
  return state.value?.adjustment_computed?.[comparableId]?.factors?.[rowKey] || null;
};

const totalComputed = (comparableId) => {
  return state.value?.adjustment_computed?.[comparableId] || null;
};

const comparableState = (comparableId) => {
  return state.value?.comparables?.find((item) => String(item.id) === String(comparableId)) || null;
};

const maintenanceAllowsAdjustment = (comparableId) => {
  const finalText = comparableState(comparableId)?.maintenance_final_text || '';

  return finalText !== '' && !String(finalText).toUpperCase().startsWith('N/A');
};

const previewAdjustment = async () => {
  busyPreview.value = true;
  setFeedback('');
  try {
    const response = await axios.post(props.asset.adjustment_preview_url, {
      adjustment_inputs: draftInputs.value,
      general_inputs: draftGeneralInputs.value,
      custom_adjustment_factors: customFactors.value,
    });
    syncState(response.data.state);
    setFeedback(response.data.message, 'success');
  } catch (error) {
    setFeedback(error.response?.data?.message || 'Preview gagal diperbarui.', 'error');
  } finally {
    busyPreview.value = false;
  }
};

const saveAdjustment = async () => {
  busySave.value = true;
  setFeedback('');
  try {
    const response = await axios.post(props.asset.adjustment_save_url, {
      adjustment_inputs: draftInputs.value,
      general_inputs: draftGeneralInputs.value,
      custom_adjustment_factors: customFactors.value,
    });
    syncState(response.data.result?.state || state.value);
    setFeedback(response.data.result?.notification_body || response.data.message, 'success');
  } catch (error) {
    setFeedback(error.response?.data?.message || 'Simpan adjustment gagal.', 'error');
  } finally {
    busySave.value = false;
  }
};

const slugify = (value) => value
  .toLowerCase()
  .trim()
  .replace(/[^a-z0-9]+/g, '_')
  .replace(/^_+|_+$/g, '');

const addCustomFactor = async () => {
  const label = newCustomFactorLabel.value.trim();
  if (!label) return;

  const existingKeys = new Set(customFactors.value.map((item) => item.key));
  const base = `adj_custom_${slugify(label) || 'faktor'}`;
  let key = base;
  let counter = 2;
  while (existingKeys.has(key)) {
    key = `${base}_${counter}`;
    counter += 1;
  }

  customFactors.value = [...customFactors.value, { key, label }];
  for (const column of matrixColumns.value) {
    draftInputs.value[column.id] = draftInputs.value[column.id] || {};
    draftInputs.value[column.id][key] = 0;
  }

  newCustomFactorLabel.value = '';
  await previewAdjustment();
};

const isAdjustmentSection = (section) => section.title?.toLowerCase().includes('adjustment - element of comparison');

const feedbackClasses = computed(() => {
  if (feedbackTone.value === 'error') return 'border-red-200 bg-red-50 text-red-900';
  if (feedbackTone.value === 'success') return 'border-emerald-200 bg-emerald-50 text-emerald-900';
  return 'border-slate-200 bg-slate-50 text-slate-900';
});
</script>

<template>
  <Head :title="`Adjustment ${asset.request_number || asset.id}`" />

  <ReviewerLayout :title="`Adjustment ${asset.request_number || asset.id}`">
    <div class="space-y-5">

      <!-- ── Header Card ─────────────────────────────────────────────── -->
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
                Request&nbsp;<span class="font-medium text-foreground">{{ state.context_meta?.request_number || asset.request_number || '-' }}</span>
                &nbsp;·&nbsp;
                Guideline&nbsp;<span class="font-medium text-foreground">{{ state.context_meta?.guideline || '-' }} {{ state.context_meta?.guideline_year || '' }}</span>
              </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <Button variant="outline" size="sm" :disabled="busyPreview" @click="previewAdjustment" class="gap-1.5 text-sm">
                <Calculator class="h-3.5 w-3.5" />
                Preview
              </Button>
              <Button size="sm" :disabled="busySave" @click="saveAdjustment" class="gap-1.5 text-sm">
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
          <!-- Feedback alert -->
          <Alert v-if="feedback" :class="feedbackClasses" class="py-3">
            <Calculator class="h-4 w-4" />
            <AlertTitle class="text-sm font-medium">Adjustment Feedback</AlertTitle>
            <AlertDescription class="text-sm">{{ feedback }}</AlertDescription>
          </Alert>

          <!-- Range summary strip -->
          <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-border/50 bg-muted/20 px-4 py-3 transition-colors hover:bg-muted/40">
              <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Unit Low</p>
              <p class="mt-1.5 text-xl font-semibold tabular-nums">{{ rangeSummary.unit_low_text || '-' }}</p>
            </div>
            <div class="rounded-lg border border-border/50 bg-muted/20 px-4 py-3 transition-colors hover:bg-muted/40">
              <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Unit High</p>
              <p class="mt-1.5 text-xl font-semibold tabular-nums">{{ rangeSummary.unit_high_text || '-' }}</p>
            </div>
            <div class="rounded-lg border border-border/50 bg-muted/30 px-4 py-3 transition-colors hover:bg-muted/50">
              <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Value Mid</p>
              <p class="mt-1.5 text-xl font-semibold tabular-nums">{{ rangeSummary.value_mid_text || '-' }}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- ── Comparable Warnings ──────────────────────────────────────── -->
      <Card v-if="comparableWarnings.length" class="border-amber-200/80 bg-amber-50/40 shadow-sm">
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
              v-for="column in comparableWarnings"
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

      <!-- ── Matrix Section Cards ────────────────────────────────────── -->
      <Card
        v-for="section in matrixSections"
        :key="section.title"
        class="overflow-hidden border-border/60 shadow-sm"
      >
        <!-- Section header -->
        <CardHeader class="border-b border-border/40 bg-muted/10 pb-4">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <CardTitle class="text-base font-semibold">{{ section.title }}</CardTitle>
              <CardDescription class="mt-0.5 text-sm">
                {{ isAdjustmentSection(section)
                  ? 'Input adjustment dikirim ke server saat blur atau preview.'
                  : 'Data normalization hasil engine reviewer.' }}
              </CardDescription>
            </div>
            <div v-if="isAdjustmentSection(section)" class="flex w-full flex-col gap-2 lg:w-auto lg:flex-row">
              <Input
                v-model="newCustomFactorLabel"
                type="text"
                placeholder="Tambah faktor custom…"
                class="h-8 text-sm lg:min-w-56"
              />
              <Button variant="outline" size="sm" class="gap-1.5 whitespace-nowrap text-sm" @click="addCustomFactor">
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

                  <!-- Sticky: Parameter -->
                  <th class="matrix-sticky-1 min-w-40 border-r border-border/30 bg-muted/20 px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                    Parameter
                  </th>

                  <th
                    v-if="!isAdjustmentSection(section)"
                    class="matrix-sticky-2 min-w-44 border-r border-border/50 bg-muted/20 px-4 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground"
                  >
                    Objek Penilaian
                  </th>

                  <!-- Scrollable: Comparable columns -->
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

                  <!-- Group divider row -->
                  <tr v-if="row.type === 'group'" class="border-b border-border/30">
                    <td
                      :colspan="matrixColumns.length + (isAdjustmentSection(section) ? 1 : 2)"
                      class="bg-muted/30 px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground"
                    >
                      {{ row.label }}
                    </td>
                  </tr>

                  <!-- Data row -->
                  <tr
                    v-else
                    class="border-b border-border/20 transition-colors hover:bg-muted/10"
                    :class="row.type === 'total' ? 'bg-muted/20 hover:bg-muted/30' : ''"
                  >

                    <!-- Sticky col 1: Parameter label -->
                    <td
                      class="matrix-sticky-1 w-40 min-w-40 max-w-40 border-r border-border/30 px-3 py-3 text-sm font-medium leading-snug"
                      :class="row.type === 'total' ? 'bg-muted/30 text-foreground' : 'bg-background text-foreground/80'"
                    >
                      {{ row.label }}
                    </td>

                    <!-- Sticky col 2: Subject value -->
                    <td
                      v-if="!isAdjustmentSection(section)"
                      class="matrix-sticky-2 border-r border-border/50 px-4 py-3 text-sm"
                      :class="row.type === 'total'
                        ? 'bg-muted/30 font-semibold text-foreground'
                        : 'bg-background text-foreground/70'"
                    >
                      {{ row.subject || '-' }}
                    </td>

                    <!-- Scrollable: Comparable cells -->
                    <td
                      v-for="(cell, index) in row.comparables"
                      :key="`${row.key}-${matrixColumns[index]?.id}`"
                      class="px-4 py-3 align-top"
                      :class="row.type === 'total' ? 'bg-muted/20' : ''"
                    >

                      <!-- Adjustment input cell -->
                      <template v-if="isAdjustmentSection(section) && row.key && row.type !== 'total'">
                        <div class="flex items-start gap-4">
                          <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Adj. %</p>
                            <div class="mt-1 flex items-center gap-2">
                              <Input
                                v-model.number="draftInputs[matrixColumns[index].id][row.key]"
                                type="number"
                                step="0.01"
                                min="-100"
                                max="100"
                                class="h-7 w-24 text-sm tabular-nums"
                                @blur="previewAdjustment"
                              />
                              <span class="text-xs text-muted-foreground">%</span>
                            </div>
                          </div>
                          <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Nominal</p>
                            <p class="mt-1 text-sm tabular-nums text-foreground/70">
                              {{ rowComputed(matrixColumns[index].id, row.key)?.amount_text || formatCurrency(0) }}
                            </p>
                          </div>
                        </div>
                      </template>

                      <!-- Total adjustment row -->
                      <template v-else-if="isAdjustmentSection(section) && row.key === 'adj_total'">
                        <p class="font-semibold tabular-nums text-primary">
                          {{ totalComputed(matrixColumns[index].id)?.total_amount_text || '-' }}
                        </p>
                        <p class="mt-0.5 text-xs tabular-nums text-muted-foreground">
                          {{ totalComputed(matrixColumns[index].id)?.total_percent_text || formatPercent(0) }}
                        </p>
                      </template>

                      <!-- Estimated unit row -->
                      <template v-else-if="isAdjustmentSection(section) && row.key === 'adj_estimated_unit'">
                        <p class="font-semibold tabular-nums text-emerald-700">
                          {{ totalComputed(matrixColumns[index].id)?.estimated_unit_text || '-' }}
                        </p>
                      </template>

                      <template v-else-if="!isAdjustmentSection(section) && row.key === 'assumed_discount'">
                        <div class="space-y-2">
                          <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Assumed Discount</p>
                          <div class="flex items-center gap-2">
                            <Input
                              v-model.number="draftGeneralInputs[matrixColumns[index].id].assumed_discount"
                              type="number"
                              step="1"
                              min="0"
                              max="100"
                              class="h-7 w-20 text-sm tabular-nums"
                              @blur="previewAdjustment"
                            />
                            <span class="text-xs text-muted-foreground">%</span>
                          </div>
                        </div>
                      </template>

                      <template v-else-if="!isAdjustmentSection(section) && row.key === 'material_quality_adj'">
                        <div class="space-y-2">
                          <div class="flex items-center gap-2">
                            <Input
                              v-model.number="draftGeneralInputs[matrixColumns[index].id].material_quality_adj"
                              type="number"
                              step="0.01"
                              min="0.01"
                              max="10"
                              class="h-7 w-24 text-sm tabular-nums"
                              @blur="previewAdjustment"
                            />
                          </div>
                        </div>
                      </template>

                      <template v-else-if="!isAdjustmentSection(section) && row.key === 'maintenance_ref'">
                        <div class="space-y-3">
                          <div v-if="maintenanceAllowsAdjustment(matrixColumns[index].id)" class="flex items-start gap-4">
                            <div>
                              <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">
                                Adj. Tambahan
                              </p>
                              <div class="mt-1 flex items-center gap-2">
                                <Input
                                  v-model.number="draftGeneralInputs[matrixColumns[index].id].maintenance_adj_delta"
                                  type="number"
                                  step="0.01"
                                  min="-100"
                                  max="100"
                                  class="h-7 w-24 text-sm tabular-nums"
                                  @blur="previewAdjustment"
                                />
                                <span class="text-xs text-muted-foreground">%</span>
                              </div>
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

                      <!-- Plain data cell -->
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

    </div>
  </ReviewerLayout>
</template>

<style scoped>
/*
  Sticky column helpers.

  Col 1 "Parameter"      → left: 0
  Col 2 "Objek Penilaian" → left: 160px  (matches min-w-40 = 10rem = 160px)

  A subtle drop-shadow on col 2 signals that content is scrolling behind it.
*/
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
    left: 160px; /* 10rem — keep in sync with min-w-40 on col 1 */
    z-index: 10;
    box-shadow: 2px 0 8px -2px rgba(0, 0, 0, 0.07);
  }

  thead .matrix-sticky-1,
  thead .matrix-sticky-2 {
    z-index: 20;
  }
}
</style>
