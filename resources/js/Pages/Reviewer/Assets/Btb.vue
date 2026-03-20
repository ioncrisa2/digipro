<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import { Head, Link } from '@inertiajs/vue3';
import ReviewerLayout from '@/layouts/ReviewerLayout.vue';
import { cloneDeep, formatCurrency } from '@/utils/reviewer';
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

const hasBtb = computed(() => Boolean(btbPayload.value?.enabled));
const btbTemplates = computed(() => btbPayload.value?.templates || []);
const btbHardCostLines = computed(() => btbState.value?.worksheet?.hard_cost_lines || []);
const btbIndirectCostLines = computed(() => btbState.value?.worksheet?.indirect_cost_lines || []);

const normalizeBtbInput = (payload) => {
  const nextInput = cloneDeep(payload?.input || {});
  const nextState = cloneDeep(payload?.state || {});

  nextInput.subject_overrides = nextInput.subject_overrides || {};

  const hardCostLines = nextState?.worksheet?.hard_cost_lines || [];
  for (const line of hardCostLines) {
    for (const item of line.items || []) {
      const itemKey = item.item_key;
      nextInput.subject_overrides[itemKey] = {
        subject_material_spec: item.subject_material_spec || '',
        subject_unit_cost: item.subject_unit_cost ?? item.model_unit_cost ?? 0,
        subject_volume_percent: item.subject_volume_percent ?? item.model_volume_percent ?? 0,
        other_adjustment_factor: item.other_adjustment_factor ?? 1,
        ...(nextInput.subject_overrides[itemKey] || {}),
      };
    }
  }

  return nextInput;
};

const syncBtbState = (payload) => {
  btbPayload.value = cloneDeep(payload || {});
  btbState.value = cloneDeep(payload?.state || null);
  btbInput.value = normalizeBtbInput(payload);
};

syncBtbState(props.btb);

const setFeedback = (message, tone = 'default') => {
  feedback.value = message;
  feedbackTone.value = tone;
};

const formatFactor = (value, digits = 4) => {
  const number = Number(value);
  if (Number.isNaN(number)) return '-';
  return number.toFixed(digits);
};

const previewBtb = async () => {
  busyPreview.value = true;
  setFeedback('');
  try {
    const response = await axios.post(props.asset.btb_preview_url, {
      btb_input: btbInput.value,
    });
    syncBtbState(response.data.btb);
    setFeedback(response.data.message, 'success');
  } catch (error) {
    setFeedback(error.response?.data?.message || 'Preview BTB gagal diperbarui.', 'error');
  } finally {
    busyPreview.value = false;
  }
};

const saveBtb = async () => {
  busySave.value = true;
  setFeedback('');
  try {
    const response = await axios.post(props.asset.btb_save_url, {
      btb_input: btbInput.value,
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
              <CardTitle class="text-2xl font-semibold leading-snug tracking-tight">
                {{ asset.address }}
              </CardTitle>
              <p class="text-sm text-muted-foreground">
                Request&nbsp;<span class="font-medium text-foreground">{{ asset.request_number || '-' }}</span>
                &nbsp;·&nbsp;
                Peruntukan&nbsp;<span class="font-medium text-foreground">{{ asset.peruntukan || '-' }}</span>
              </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <Button variant="outline" size="sm" :disabled="busyPreview" @click="previewBtb" class="gap-1.5 text-sm">
                <Calculator class="h-3.5 w-3.5" />
                Preview
              </Button>
              <Button size="sm" :disabled="busySave" @click="saveBtb" class="gap-1.5 text-sm">
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

          <Alert v-if="!hasBtb" class="border-amber-200 bg-amber-50 text-amber-900">
            <Calculator class="h-4 w-4" />
            <AlertTitle>Worksheet BTB belum tersedia</AlertTitle>
            <AlertDescription>{{ btbPayload.reason || 'Aset ini tidak memerlukan worksheet BTB.' }}</AlertDescription>
          </Alert>

          <template v-else>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
              <label class="space-y-1.5">
                <span class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Template</span>
                <select
                  v-model="btbInput.template_key"
                  class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
                  @change="previewBtb"
                >
                  <option v-for="template in btbTemplates" :key="template.value" :value="template.value">
                    {{ template.label }}
                  </option>
                </select>
              </label>

              <label class="space-y-1.5">
                <span class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Jumlah Lantai</span>
                <Input v-model.number="btbInput.floor_count" type="number" min="1" class="h-9 text-sm" @blur="previewBtb" />
              </label>

              <label class="space-y-1.5">
                <span class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Luas Bangunan</span>
                <Input v-model.number="btbInput.building_area" type="number" min="0" step="0.01" class="h-9 text-sm" @blur="previewBtb" />
              </label>

              <label class="space-y-1.5">
                <span class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Luas Tanah</span>
                <Input v-model.number="btbInput.land_area" type="number" min="0" step="0.01" class="h-9 text-sm" @blur="previewBtb" />
              </label>

              <label class="space-y-1.5">
                <span class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Effective Age</span>
                <Input v-model.number="btbInput.effective_age" type="number" min="0" step="0.01" class="h-9 text-sm" @blur="previewBtb" />
              </label>

              <label class="space-y-1.5">
                <span class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Adj. Kualitas Material</span>
                <Input v-model.number="btbInput.material_quality_adjustment" type="number" min="0.01" max="10" step="0.01" class="h-9 text-sm" @blur="previewBtb" />
              </label>

              <label class="space-y-1.5">
                <span class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Adj. Maintenance</span>
                <Input v-model.number="btbInput.maintenance_adjustment_factor" type="number" min="-1" max="1" step="0.01" class="h-9 text-sm" @blur="previewBtb" />
              </label>

              <label class="space-y-1.5">
                <span class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Market Value Aset</span>
                <Input v-model.number="btbInput.market_value" type="number" min="0" step="1" class="h-9 text-sm" @blur="previewBtb" />
              </label>
            </div>

            <div class="rounded-lg border border-border/50 bg-muted/10 px-4 py-3 text-sm text-muted-foreground">
              Hasil akhir aset mengikuti aturan: <span class="font-medium text-foreground">Range Tanah + Nilai Bangunan BTB</span>.
              Nilai saat ini: <span class="font-semibold text-foreground">{{ formatCurrency(assetState.market_value_final) }}</span>
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
              <div class="rounded-lg border border-border/50 bg-muted/20 px-4 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Hard Cost</p>
                <p class="mt-1.5 text-lg font-semibold tabular-nums">{{ formatCurrency(btbState?.worksheet?.hard_cost_total || 0) }}</p>
              </div>
              <div class="rounded-lg border border-border/50 bg-muted/20 px-4 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Total RCN</p>
                <p class="mt-1.5 text-lg font-semibold tabular-nums">{{ formatCurrency(btbState?.worksheet?.total_rcn || 0) }}</p>
              </div>
              <div class="rounded-lg border border-border/50 bg-muted/20 px-4 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Nilai Bangunan</p>
                <p class="mt-1.5 text-lg font-semibold tabular-nums">{{ formatCurrency(btbState?.depreciation?.depreciated_brb_total || 0) }}</p>
              </div>
              <div class="rounded-lg border border-border/50 bg-muted/20 px-4 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Residual Land Value</p>
                <p class="mt-1.5 text-lg font-semibold tabular-nums">{{ formatCurrency(btbState?.summary?.residual_land_value || 0) }}</p>
              </div>
            </div>

            <div class="rounded-lg border border-border/50 bg-muted/10 p-4">
              <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div>
                  <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">IKK</p>
                  <p class="mt-1 text-sm font-medium">{{ formatFactor(btbState?.reference?.ikk_value || 1) }}</p>
                </div>
                <div>
                  <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Indeks Lantai</p>
                  <p class="mt-1 text-sm font-medium">{{ formatFactor(btbState?.reference?.floor_index_value || 1) }}</p>
                </div>
                <div>
                  <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Umur Ekonomis</p>
                  <p class="mt-1 text-sm font-medium">{{ btbState?.reference?.economic_life || '-' }}</p>
                </div>
                <div>
                  <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Final Factor</p>
                  <p class="mt-1 text-sm font-medium">{{ formatFactor(btbState?.depreciation?.final_adjustment_factor || 0) }}</p>
                </div>
              </div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-border/50">
              <table class="w-full text-sm">
                <thead class="bg-muted/20">
                  <tr class="border-b border-border/40">
                    <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Elemen</th>
                    <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Material Model</th>
                    <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Biaya Model</th>
                    <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Volume Model</th>
                    <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Material Objek</th>
                    <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Biaya Objek</th>
                    <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Volume Objek</th>
                    <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Adj. Lain</th>
                    <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Hasil</th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="line in btbHardCostLines" :key="line.line_code">
                    <tr class="border-t border-border/40 bg-muted/10">
                      <td colspan="9" class="px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                        {{ line.label }}
                        <span class="ml-2 text-xs normal-case text-foreground/70">Subtotal {{ formatCurrency(line.subtotal || 0) }}</span>
                      </td>
                    </tr>
                    <tr v-for="item in line.items" :key="item.item_key" class="border-t border-border/30">
                      <td class="px-3 py-3 text-sm font-medium text-foreground/80">{{ item.element_name }}</td>
                      <td class="px-3 py-3 text-sm text-foreground/70">{{ item.model_material_spec }}</td>
                      <td class="px-3 py-3 text-sm tabular-nums text-foreground/70">{{ formatCurrency(item.model_unit_cost) }}</td>
                      <td class="px-3 py-3 text-sm tabular-nums text-foreground/70">{{ formatFactor(item.model_volume_percent || 0) }}</td>
                      <td class="px-3 py-3">
                        <Input v-model="btbInput.subject_overrides[item.item_key].subject_material_spec" type="text" class="h-8 min-w-44 text-sm" @blur="previewBtb" />
                      </td>
                      <td class="px-3 py-3">
                        <Input v-model.number="btbInput.subject_overrides[item.item_key].subject_unit_cost" type="number" min="0" step="1" class="h-8 w-28 text-sm tabular-nums" @blur="previewBtb" />
                      </td>
                      <td class="px-3 py-3">
                        <Input v-model.number="btbInput.subject_overrides[item.item_key].subject_volume_percent" type="number" min="0" step="0.0001" class="h-8 w-24 text-sm tabular-nums" @blur="previewBtb" />
                      </td>
                      <td class="px-3 py-3">
                        <Input v-model.number="btbInput.subject_overrides[item.item_key].other_adjustment_factor" type="number" min="0" step="0.0001" class="h-8 w-24 text-sm tabular-nums" @blur="previewBtb" />
                      </td>
                      <td class="px-3 py-3 text-sm font-semibold tabular-nums text-foreground">{{ formatCurrency(item.direct_cost_result || 0) }}</td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
              <div class="rounded-lg border border-border/50 p-4">
                <p class="text-sm font-semibold text-foreground">Biaya Tak Langsung</p>
                <div class="mt-3 space-y-2">
                  <div v-for="line in btbIndirectCostLines" :key="line.line_code" class="flex items-center justify-between gap-3 text-sm">
                    <span class="text-foreground/70">{{ line.label }}</span>
                    <span class="font-medium tabular-nums">{{ formatCurrency(line.value || 0) }}</span>
                  </div>
                  <div class="flex items-center justify-between gap-3 border-t border-border/40 pt-2 text-sm font-semibold">
                    <span>Total Soft Cost</span>
                    <span class="tabular-nums">{{ formatCurrency(btbState?.worksheet?.soft_cost_total || 0) }}</span>
                  </div>
                </div>
              </div>

              <div class="rounded-lg border border-border/50 p-4">
                <p class="text-sm font-semibold text-foreground">Depresiasi Bangunan</p>
                <div class="mt-3 space-y-2 text-sm">
                  <div class="flex items-center justify-between gap-3">
                    <span class="text-foreground/70">Depresiasi / sqm</span>
                    <span class="font-medium tabular-nums">{{ formatCurrency(btbState?.depreciation?.depreciation_amount_per_sqm || 0) }}</span>
                  </div>
                  <div class="flex items-center justify-between gap-3">
                    <span class="text-foreground/70">BRB Terdepresiasi / sqm</span>
                    <span class="font-medium tabular-nums">{{ formatCurrency(btbState?.depreciation?.depreciated_brb_per_sqm || 0) }}</span>
                  </div>
                  <div class="flex items-center justify-between gap-3 border-t border-border/40 pt-2 font-semibold">
                    <span>BRB Terdepresiasi Total</span>
                    <span class="tabular-nums">{{ formatCurrency(btbState?.depreciation?.depreciated_brb_total || 0) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </CardContent>
      </Card>
    </div>
  </ReviewerLayout>
</template>
