<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { Head, Link } from '@inertiajs/vue3';
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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Play, CheckCheck, FolderOpen, ClipboardList } from 'lucide-vue-next';

const props = defineProps({
  review: Object,
});

const reviewState = ref(props.review);
const busyAction = ref(false);
const feedback = ref('');
const feedbackTone = ref('default');

const setFeedback = (message, tone = 'default') => {
  feedback.value = message;
  feedbackTone.value = tone;
};

const runStatusAction = async (endpointName) => {
  busyAction.value = true;
  setFeedback('');
  try {
    const response = await axios.post(route(endpointName, reviewState.value.id));
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

const feedbackClasses = () => {
  if (feedbackTone.value === 'error') return 'border-red-200 bg-red-50 text-red-900';
  if (feedbackTone.value === 'success') return 'border-emerald-200 bg-emerald-50 text-emerald-900';
  return 'border-slate-200 bg-slate-50 text-slate-900';
};
</script>

<template>
  <Head :title="`Review ${review.request_number}`" />

  <ReviewerLayout :title="`Review ${review.request_number}`">
    <div class="space-y-6">
      <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
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
              </div>
            </div>

            <div class="flex flex-wrap gap-3">
              <Button
                class="bg-amber-500 text-stone-950 hover:bg-amber-500/90"
                :disabled="busyAction || reviewState.status?.value !== 'contract_signed'"
                @click="runStatusAction('reviewer.api.reviews.start')"
              >
                <Play class="mr-2 h-4 w-4" />
                Mulai Review
              </Button>
              <Button
                class="bg-emerald-600 text-white hover:bg-emerald-600/90"
                :disabled="busyAction || reviewState.status?.value !== 'valuation_in_progress'"
                @click="runStatusAction('reviewer.api.reviews.finish')"
              >
                <CheckCheck class="mr-2 h-4 w-4" />
                Finalisasi Review
              </Button>
            </div>

            <Alert v-if="feedback" :class="feedbackClasses()">
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

        <Card>
          <CardHeader class="pb-4">
            <CardTitle>Dokumen & File Aset</CardTitle>
            <CardDescription>Kumpulan file dari seluruh aset dalam request ini.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3">
            <div v-for="file in reviewState.files" :key="file.id" class="rounded-xl border p-4">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="font-medium text-foreground">{{ file.original_name }}</p>
                  <p class="mt-1 text-xs text-muted-foreground">{{ file.asset_address }} • {{ file.type }}</p>
                </div>
                <FolderOpen class="h-4 w-4 text-muted-foreground" />
              </div>
              <Button v-if="file.url" variant="link" class="mt-2 h-auto px-0" as-child>
                <a :href="file.url" target="_blank" rel="noopener noreferrer">Buka file</a>
              </Button>
            </div>
            <div v-if="!reviewState.files?.length" class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
              Belum ada file terdeteksi.
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader class="pb-4">
          <div class="flex items-center justify-between gap-3">
              <div>
                <CardTitle>Daftar Aset</CardTitle>
              <CardDescription>Masuk ke detail aset atau penyesuaian harga tanah langsung dari sini.</CardDescription>
              </div>
            <Badge variant="outline">{{ reviewState.assets?.length || 0 }} aset</Badge>
          </div>
        </CardHeader>
        <CardContent>
          <div class="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Alamat</TableHead>
                  <TableHead>Jenis</TableHead>
                  <TableHead>LT / LB</TableHead>
                  <TableHead>Aksi</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="asset in reviewState.assets" :key="asset.id">
                  <TableCell>{{ asset.address }}</TableCell>
                  <TableCell>{{ asset.asset_type?.label }}</TableCell>
                  <TableCell>{{ asset.land_area || '-' }} / {{ asset.building_area || '-' }}</TableCell>
                  <TableCell>
                    <div class="flex flex-wrap gap-2">
                      <Button variant="link" class="h-auto px-0" as-child>
                        <Link :href="asset.detail_url">Detail aset</Link>
                        </Button>
                        <Button variant="link" class="h-auto px-0" as-child>
                          <Link :href="asset.adjustment_url">Adjust Harga Tanah</Link>
                        </Button>
                    </div>
                  </TableCell>
                </TableRow>
                <TableRow v-if="!reviewState.assets?.length">
                  <TableCell :colspan="4" class="text-center text-muted-foreground">Belum ada aset pada request ini.</TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>
  </ReviewerLayout>
</template>

