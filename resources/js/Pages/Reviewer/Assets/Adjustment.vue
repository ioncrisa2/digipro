<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import ReviewerLayout from '@/layouts/ReviewerLayout.vue';
import { cloneDeep } from '@/utils/reviewer';
import AdjustmentHeaderCard from '@/components/reviewer/AdjustmentHeaderCard.vue';
import AdjustmentWarningsPanel from '@/components/reviewer/AdjustmentWarningsPanel.vue';
import AdjustmentEditableMatrixTable from '@/components/reviewer/AdjustmentEditableMatrixTable.vue';

const props = defineProps({
  asset: Object,
  workbench: Object,
});

const state = ref(cloneDeep(props.workbench));
const draftInputs = ref(cloneDeep(props.workbench.adjustment_inputs) || {});
const draftGeneralInputs = ref(cloneDeep(props.workbench.general_inputs) || {});
const customFactors = ref(cloneDeep(props.workbench.custom_adjustment_factors) || []);
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

const syncWorkbenchState = (payload) => {
  state.value = cloneDeep(payload);
  draftInputs.value = cloneDeep(payload.adjustment_inputs || {});
  draftGeneralInputs.value = cloneDeep(payload.general_inputs || {});
  customFactors.value = cloneDeep(payload.custom_adjustment_factors || []);
};

const setFeedback = (message, tone = 'default') => {
  feedback.value = message;
  feedbackTone.value = tone;
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

    syncWorkbenchState(response.data.state);
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

    syncWorkbenchState(response.data.result?.state || state.value);
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

const addCustomFactor = async (labelInput) => {
  const label = String(labelInput || '').trim();
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

  await previewAdjustment();
};

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
      <AdjustmentHeaderCard
        :asset="asset"
        :context-meta="state.context_meta || {}"
        :range-summary="rangeSummary"
        :feedback="feedback"
        :feedback-classes="feedbackClasses"
        :busy-preview="busyPreview"
        :busy-save="busySave"
        @preview="previewAdjustment"
        @save="saveAdjustment"
      />

      <AdjustmentWarningsPanel :warnings="comparableWarnings" />

      <AdjustmentEditableMatrixTable
        :matrix-sections="matrixSections"
        :matrix-columns="matrixColumns"
        :adjustment-computed="state.adjustment_computed || {}"
        :comparables="state.comparables || []"
        :draft-inputs="draftInputs"
        :draft-general-inputs="draftGeneralInputs"
        :custom-factors="customFactors"
        :has-wide-comparables="hasWideComparables"
        @preview="previewAdjustment"
        @add-custom-factor="addCustomFactor"
      />
    </div>
  </ReviewerLayout>
</template>
