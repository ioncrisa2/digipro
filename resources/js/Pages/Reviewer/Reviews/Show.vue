<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import ReviewerLayout from '@/layouts/ReviewerLayout.vue';
import StatusBadge from '@/components/reviewer/StatusBadge.vue';
import { formatCurrency, formatDateTime } from '@/utils/reviewer';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Play, CheckCheck, ClipboardList } from 'lucide-vue-next';

const props = defineProps({
  review: Object,
});

const reviewState = ref(props.review);
const busyAction = ref(false);
const feedback = ref('');
const feedbackTone = ref('default');

const primaryAssetUrl = computed(() => reviewState.value?.primary_asset_url || null);
const canOpenPrimaryAsset = computed(() => Boolean(primaryAssetUrl.value));
const canStartReview = computed(() => (
  canOpenPrimaryAsset.value
  && ['contract_signed', 'valuation_in_progress'].includes(reviewState.value?.status?.value)
));
const canFinishReview = computed(() => reviewState.value?.status?.value === 'valuation_in_progress');

const setFeedback = (message, tone = 'default') => {
  feedback.value = message;
  feedbackTone.value = tone;
};

const goToPrimaryAsset = () => {
  if (!primaryAssetUrl.value) {
    setFeedback('Belum ada aset yang bisa dibuka untuk review ini.', 'error');
    return;
  }

  router.visit(primaryAssetUrl.value);
};

const startReview = async () => {
  if (!canOpenPrimaryAsset.value) {
    setFeedback('Belum ada aset yang bisa dibuka untuk review ini.', 'error');
    return;
  }

  if (reviewState.value?.status?.value === 'valuation_in_progress') {
    goToPrimaryAsset();
    return;
  }

  busyAction.value = true;
  setFeedback('');

  try {
    const response = await axios.post(route('reviewer.api.reviews.start', reviewState.value.id));
    reviewState.value = {
      ...reviewState.value,
      status: response.data.review.status,
    };
    setFeedback(response.data.message, 'success');
    goToPrimaryAsset();
  } catch (error) {
    setFeedback(error.response?.data?.message || 'Aksi gagal diproses.', 'error');
  } finally {
    busyAction.value = false;
  }
};

const finishReview = async () => {
  busyAction.value = true;
  setFeedback('');

  try {
    const response = await axios.post(route('reviewer.api.reviews.finish', reviewState.value.id));
    reviewState.value = {
      ...reviewState.value,
      status: response.data.review.status,
    };
    setFeedback(response.data.message, 'success');
  } catch (error) {
    setFeedback(error.response?.data?.message || 'Aksi gagal diproses.', 'error');
  } finally {
    busyAction.value = false;
  }
};

const feedbackClasses = computed(() => {
  if (feedbackTone.value === 'error') return 'border-red-200 bg-red-50 text-red-900';
  if (feedbackTone.value === 'success') return 'border-emerald-200 bg-emerald-50 text-emerald-900';
  return 'border-slate-200 bg-slate-50 text-slate-900';
});
</script>

<template>
  <Head :title="`Review ${review.request_number}`" />

  <ReviewerLayout :title="`Review ${review.request_number}`">
    <div class="max-w-5xl space-y-6">
      <Card>
        <CardHeader>
          <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <CardDescription>Permohonan</CardDescription>
              <CardTitle class="mt-2 text-3xl">{{ reviewState.request_number }}</CardTitle>
              <p class="mt-2 text-sm text-muted-foreground">{{ formatDateTime(reviewState.requested_at) }}</p>
            </div>
            <StatusBadge :status="reviewState.status" />
          </div>
        </CardHeader>

        <CardContent class="space-y-6">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border bg-muted/30 p-4">
              <p class="text-xs uppercase tracking-wide text-muted-foreground">Klien</p>
              <p class="mt-2 font-medium text-foreground">{{ reviewState.client_name }}</p>
              <p class="text-sm text-muted-foreground">{{ reviewState.client_email }}</p>
              <p class="mt-2 text-sm text-muted-foreground">{{ reviewState.client_address || '-' }}</p>
            </div>

            <div class="rounded-xl border bg-muted/30 p-4">
              <p class="text-xs uppercase tracking-wide text-muted-foreground">Administrasi</p>
              <p class="mt-2 text-sm">No. kontrak: {{ reviewState.contract_number || '-' }}</p>
              <p class="mt-1 text-sm">Fee: {{ formatCurrency(reviewState.fee_total) }}</p>
              <p class="mt-1 text-sm">Aset: {{ reviewState.assets_count }}</p>
              <p class="mt-1 text-sm">Pembayaran: {{ reviewState.latest_payment_status }}</p>
              <p class="mt-3 text-xs uppercase tracking-wide text-muted-foreground">Aset utama review</p>
              <p class="mt-1 text-sm text-foreground">{{ reviewState.primary_asset_address || '-' }}</p>
            </div>
          </div>

          <div class="flex flex-wrap gap-3">
            <Button
              class="bg-amber-500 text-stone-950 hover:bg-amber-500/90"
              :disabled="busyAction || !canStartReview"
              @click="startReview"
            >
              <Play class="mr-2 h-4 w-4" />
              Mulai Review
            </Button>

            <Button
              class="bg-emerald-600 text-white hover:bg-emerald-600/90"
              :disabled="busyAction || !canFinishReview"
              @click="finishReview"
            >
              <CheckCheck class="mr-2 h-4 w-4" />
              Finalisasi Review
            </Button>
          </div>

          <Alert v-if="feedback" :class="feedbackClasses">
            <ClipboardList class="h-4 w-4" />
            <AlertTitle>Status Review</AlertTitle>
            <AlertDescription>{{ feedback }}</AlertDescription>
          </Alert>

          <div>
            <h4 class="text-lg font-semibold">Catatan Internal</h4>
            <div class="mt-2 rounded-xl border bg-muted/30 p-4 text-sm text-muted-foreground">{{ reviewState.notes || '-' }}</div>
          </div>
        </CardContent>
      </Card>
    </div>
  </ReviewerLayout>
</template>
