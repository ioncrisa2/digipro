<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import { Head, Link } from '@inertiajs/vue3';
import ReviewerLayout from '@/layouts/ReviewerLayout.vue';
import { cloneDeep, formatCurrency, formatDateTime } from '@/utils/reviewer';
import BtbAdjustmentPanel from '@/components/reviewer/BtbAdjustmentPanel.vue';
import BtbEditableHardCostTable from '@/components/reviewer/BtbEditableHardCostTable.vue';
import BtbOutcomePanels from '@/components/reviewer/BtbOutcomePanels.vue';
import BtbTraceAudit from '@/components/reviewer/BtbTraceAudit.vue';
import BtbWorksheetSummaryCards from '@/components/reviewer/BtbWorksheetSummaryCards.vue';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { ArrowLeft, Calculator, Home, Save, Scale } from 'lucide-vue-next';

const props = defineProps({
  asset: Object,
  btb: Object,
});

const assetState = ref(cloneDeep(props.asset) || {});
const btbPayload = ref(cloneDeep(props.btb) || {});
const btbInput = ref({});
const btbState = ref(cloneDeep(props.btb?.state) || null);
const busyPreview = ref(false);
const busySave = ref(false);
const feedback = ref('');
const feedbackTone = ref('default');
const previewRequestSequence = ref(0);

const hasBtb = computed(() => Boolean(btbPayload.value?.enabled));
const btbTemplates = computed(() => btbPayload.value?.templates || []);
const worksheetState = computed(() => btbState.value?.worksheet || {});
const depreciationState = computed(() => btbState.value?.depreciation || {});
const referenceState = computed(() => btbState.value?.reference || {});
const summaryState = computed(() => btbState.value?.summary || {});
const worksheetContext = computed(() => btbState.value?.context || {});
const auditState = computed(() => btbState.value?.audit || {});
const savedValuation = computed(() => btbPayload.value?.saved_valuation || null);
const btbWarnings = computed(() => btbState.value?.warnings || []);
const btbHardCostLines = computed(() => worksheetState.value?.hard_cost_lines || []);
const btbIndirectCostLines = computed(() => worksheetState.value?.indirect_cost_lines || []);
const auditReferenceChecks = computed(() => auditState.value?.reference_checks || []);
const auditHardCostGroups = computed(() => auditState.value?.trace?.hard_cost_groups || []);

const toHumanPercentInput = (value) => {
  const number = Number(value);
  if (!Number.isFinite(number)) return 0;
  return number <= 1 ? Number((number * 100).toFixed(4)) : number;
};

const normalizeBtbInput = (payload) => {
  const nextInput = cloneDeep(payload?.input || {});
  const nextState = cloneDeep(payload?.state || {});

  nextInput.subject_overrides = nextInput.subject_overrides || {};
  nextInput.design_finish_addition_percent = Number(nextInput.design_finish_addition_percent ?? 0);
  nextInput.maintenance_adjustment_percent = Number(nextInput.maintenance_adjustment_percent ?? 0);
  nextInput.incurable_depreciation_percent = Number(nextInput.incurable_depreciation_percent ?? 0);
  nextInput.functional_obsolescence_percent = Number(nextInput.functional_obsolescence_percent ?? 0);
  nextInput.economic_obsolescence_percent = Number(nextInput.economic_obsolescence_percent ?? 0);
  nextInput.renovation_year = nextInput.renovation_year ?? props.asset?.renovation_year ?? props.asset?.build_year ?? null;
  nextInput.build_year = nextInput.build_year ?? nextState?.context?.build_year ?? props.asset?.build_year ?? null;

  for (const [itemKey, override] of Object.entries(nextInput.subject_overrides)) {
    if (!override || typeof override !== 'object') continue;

    nextInput.subject_overrides[itemKey] = {
      ...override,
      subject_volume_percent: toHumanPercentInput(override.subject_volume_percent ?? 0),
    };
  }

  const hardCostLines = nextState?.worksheet?.hard_cost_lines || [];
  for (const line of hardCostLines) {
    for (const item of line.items || []) {
      const itemKey = item.item_key;
      const matchedOption = (item.material_options || []).find((option) => option.value === (item.subject_material_spec || ''));

      nextInput.subject_overrides[itemKey] = {
        ...(nextInput.subject_overrides[itemKey] || {}),
        subject_material_spec: item.subject_material_spec || '',
        subject_unit_cost: item.subject_unit_cost ?? item.model_unit_cost ?? 0,
        subject_volume_percent: toHumanPercentInput(item.subject_volume_percent ?? item.model_volume_percent ?? 0),
        other_adjustment_factor: item.other_adjustment_factor ?? 1,
        material_option_value: matchedOption?.value || '__manual__',
      };
    }
  }

  return nextInput;
};

const mergeWorksheetOverridesIntoDraft = (state) => {
  const hardCostLines = state?.worksheet?.hard_cost_lines || [];

  btbInput.value ??= {};
  btbInput.value.subject_overrides ??= {};

  for (const line of hardCostLines) {
    for (const item of line.items || []) {
      const itemKey = item?.item_key;
      if (!itemKey) continue;

      const matchedOption = (item.material_options || []).find(
        (option) => option.value === (item.subject_material_spec || '')
      );

      const target =
        btbInput.value.subject_overrides[itemKey] ??
        (btbInput.value.subject_overrides[itemKey] = {});

      if (target.subject_material_spec == null || target.subject_material_spec === '') {
        target.subject_material_spec = item.subject_material_spec || '';
      }

      if (target.subject_unit_cost == null || target.subject_unit_cost === '') {
        target.subject_unit_cost = item.subject_unit_cost ?? item.model_unit_cost ?? 0;
      }

      if (target.subject_volume_percent == null || target.subject_volume_percent === '') {
        target.subject_volume_percent = toHumanPercentInput(
          item.subject_volume_percent ?? item.model_volume_percent ?? 0
        );
      }

      if (target.other_adjustment_factor == null || target.other_adjustment_factor === '') {
        target.other_adjustment_factor = item.other_adjustment_factor ?? 1;
      }

      if (!target.material_option_value) {
        target.material_option_value = matchedOption?.value || '__manual__';
      }
    }
  }
};

const syncBtbState = (payload, { syncInput = true } = {}) => {
  btbPayload.value = cloneDeep(payload || {});
  btbState.value = cloneDeep(payload?.state || null);

  if (syncInput) {
    btbInput.value = normalizeBtbInput(payload);
    return;
  }

  mergeWorksheetOverridesIntoDraft(btbState.value);
};

syncBtbState(props.btb);

const setFeedback = (message, tone = 'default') => {
  feedback.value = message;
  feedbackTone.value = tone;
};

const toFiniteNumber = (value, fallback = null) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
};

const buildBtbRequestPayload = () => {
  const payload = cloneDeep(btbInput.value || {});
  payload.subject_overrides = payload.subject_overrides || {};

  Object.values(payload.subject_overrides).forEach((override) => {
    if (!override || typeof override !== 'object') return;

    const volumePercent = toFiniteNumber(override.subject_volume_percent, null);
    const unitCost = toFiniteNumber(override.subject_unit_cost, null);
    const otherFactor = toFiniteNumber(override.other_adjustment_factor, null);

    override.subject_volume_percent =
      volumePercent === null ? null : Number((volumePercent / 100).toFixed(6));

    override.subject_unit_cost = unitCost;
    override.other_adjustment_factor = otherFactor;
  });

  return payload;
};

const previewBtb = async () => {
  const requestSequence = ++previewRequestSequence.value;
  busyPreview.value = true;
  setFeedback('');

  try {
    const response = await axios.post(props.asset.btb_preview_url, {
      btb_input: buildBtbRequestPayload(),
    });

    if (requestSequence !== previewRequestSequence.value) {
      return;
    }

    syncBtbState(response.data.btb, { syncInput: false });
    setFeedback(response.data.message, 'success');
  } catch (error) {
    setFeedback(error.response?.data?.message || 'Preview BTB gagal diperbarui.', 'error');
  } finally {
    if (requestSequence === previewRequestSequence.value) {
      busyPreview.value = false;
    }
  }
};

const saveBtb = async () => {
  busySave.value = true;
  setFeedback('');

  try {
    const response = await axios.post(props.asset.btb_save_url, {
      btb_input: buildBtbRequestPayload(),
    });

    syncBtbState(response.data.result?.btb || btbPayload.value);
    assetState.value = {
      ...assetState.value,
      market_value_final: response.data.result?.asset_values?.market_value_final ?? assetState.value.market_value_final,
    };
    setFeedback(response.data.message, 'success');
  } catch (error) {
    setFeedback(error.response?.data?.message || 'Simpan worksheet BTB gagal.', 'error');
  } finally {
    busySave.value = false;
  }
};

const feedbackClasses = computed(() => {
  if (feedbackTone.value === 'error') return 'border-red-200 bg-red-50 text-red-900';
  if (feedbackTone.value === 'success') return 'border-emerald-200 bg-emerald-50 text-emerald-900';
  return 'border-slate-200 bg-slate-50 text-slate-900';
});
</script>

<template>
  <Head :title="`BTB ${asset.request_number || asset.id}`" />

  <ReviewerLayout :title="`BTB ${asset.request_number || asset.id}`">
    <div class="space-y-5">
      <Card class="border-border/60 shadow-sm">
        <CardHeader class="pb-5">
          <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div class="space-y-1.5">
              <div class="flex items-center gap-2">
                <Home class="h-3.5 w-3.5 text-muted-foreground" />
                <CardDescription class="text-[10px] font-semibold uppercase tracking-widest">BTB Bangunan</CardDescription>
              </div>
              <CardTitle class="text-2xl font-semibold leading-snug tracking-tight">{{ asset.address }}</CardTitle>
              <p class="text-sm text-muted-foreground">
                Request <span class="font-medium text-foreground">{{ asset.request_number || '-' }}</span>
                <span class="px-2">|</span>
                Peruntukan <span class="font-medium text-foreground">{{ asset.peruntukan || '-' }}</span>
              </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <Button variant="outline" size="sm" :disabled="busyPreview" class="gap-1.5 text-sm" @click="previewBtb">
                <Calculator class="h-3.5 w-3.5" />
                Preview
              </Button>
              <Button size="sm" :disabled="busySave" class="gap-1.5 text-sm" @click="saveBtb">
                <Save class="h-3.5 w-3.5" />
                Simpan Worksheet
              </Button>
              <Button variant="outline" size="sm" as-child class="gap-1.5 text-sm">
                <Link :href="asset.land_adjustment_url">
                  <Scale class="h-3.5 w-3.5" />
                  Adjust Harga Tanah
                </Link>
              </Button>
              <Button variant="ghost" size="sm" as-child class="gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                <Link :href="asset.detail_url">
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
            <AlertTitle class="text-sm font-medium">BTB Feedback</AlertTitle>
            <AlertDescription class="text-sm">{{ feedback }}</AlertDescription>
          </Alert>

          <Alert v-if="btbWarnings.length" class="border-amber-200 bg-amber-50 text-amber-900">
            <Calculator class="h-4 w-4" />
            <AlertTitle>Perlu Review Input / Referensi</AlertTitle>
            <AlertDescription>
              <ul class="space-y-1 text-sm">
                <li v-for="warning in btbWarnings" :key="warning">{{ warning }}</li>
              </ul>
            </AlertDescription>
          </Alert>

          <Alert v-if="!hasBtb" class="border-amber-200 bg-amber-50 text-amber-900">
            <Calculator class="h-4 w-4" />
            <AlertTitle>Worksheet BTB belum tersedia</AlertTitle>
            <AlertDescription>{{ btbPayload.reason || 'Aset ini tidak memerlukan worksheet BTB.' }}</AlertDescription>
          </Alert>

          <template v-else>
            <div class="rounded-lg border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-slate-700">
              Klik nilai yang bergaris putus pada worksheet untuk mengedit. Perubahan akan dipreview otomatis setelah Anda selesai mengubah satu cell.
            </div>

            <div class="rounded-lg border border-border/50 bg-muted/10 px-4 py-3 text-sm text-muted-foreground">
              Nilai akhir aset mengikuti aturan <span class="font-medium text-foreground">Range Tanah + Nilai Bangunan BTB</span>.
              Nilai saat ini: <span class="font-semibold text-foreground">{{ formatCurrency(assetState.market_value_final) }}</span>
            </div>

            <div v-if="savedValuation" class="rounded-lg border border-border/50 bg-muted/10 px-4 py-3">
              <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                  <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Riwayat Worksheet Tersimpan</p>
                  <p class="mt-1 text-sm font-medium text-foreground">
                    Template {{ savedValuation.worksheet_template || '-' }} tersimpan terakhir {{ formatDateTime(savedValuation.updated_at) }}
                  </p>
                </div>
                <div class="text-sm text-muted-foreground">
                  Cost items: <span class="font-medium text-foreground">{{ savedValuation.cost_items_count || 0 }}</span>
                </div>
              </div>
            </div>

            <BtbWorksheetSummaryCards
              :worksheet-state="worksheetState"
              :depreciation-state="depreciationState"
              :summary-state="summaryState"
            />

            <BtbAdjustmentPanel
              :input="btbInput"
              :templates="btbTemplates"
              :worksheet-context="worksheetContext"
              :worksheet-state="worksheetState"
              :depreciation-state="depreciationState"
              :reference-state="referenceState"
              :summary-state="summaryState"
              @preview="previewBtb"
            />

            <BtbEditableHardCostTable
              :lines="btbHardCostLines"
              :subject-overrides="btbInput.subject_overrides"
              @preview="previewBtb"
            />

            <BtbTraceAudit :checks="auditReferenceChecks" :groups="auditHardCostGroups" />

            <BtbOutcomePanels
              :indirect-cost-lines="btbIndirectCostLines"
              :worksheet-state="worksheetState"
              :depreciation-state="depreciationState"
              :summary-state="summaryState"
            />
          </template>
        </CardContent>
      </Card>
    </div>
  </ReviewerLayout>
</template>
