<script setup>
import { reactive, ref } from 'vue';
import axios from 'axios';
import { Head, Link } from '@inertiajs/vue3';
import ReviewerLayout from '@/layouts/ReviewerLayout.vue';
import { formatArea, formatCurrency, formatPercent } from '@/utils/reviewer';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
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
import { Save, ArrowLeftRight, Image as ImageIcon } from 'lucide-vue-next';
import ComparableSnapshotTree from '@/components/reviewer/ComparableSnapshotTree.vue';

const props = defineProps({
  comparable: Object,
});

const comparableState = ref(props.comparable);
const form = reactive({
  is_selected: props.comparable.is_selected ? '1' : '0',
  manual_rank: props.comparable.manual_rank,
});
const feedback = ref('');
const busy = ref(false);
const feedbackTone = ref('default');

const setFeedback = (message, tone = 'default') => {
  feedback.value = message;
  feedbackTone.value = tone;
};

const saveComparable = async () => {
  busy.value = true;
  setFeedback('');
  try {
    const response = await axios.post(comparableState.value.update_url, {
      ...form,
      is_selected: form.is_selected === '1',
    });
    comparableState.value = response.data.comparable;
    form.is_selected = response.data.comparable.is_selected ? '1' : '0';
    form.manual_rank = response.data.comparable.manual_rank;
    setFeedback(response.data.message, 'success');
  } catch (error) {
    setFeedback(error.response?.data?.message || 'Update pembanding gagal.', 'error');
  } finally {
    busy.value = false;
  }
};
</script>

<template>
  <Head :title="`Comparable ${comparable.external_id}`" />

  <ReviewerLayout :title="`Comparable ${comparable.external_id}`">
    <div class="grid gap-6 xl:grid-cols-[0.85fr_1.15fr]">
      <Card>
        <CardHeader>
          <div class="flex items-start justify-between gap-4">
            <div>
              <CardDescription>Data Pembanding</CardDescription>
              <CardTitle class="mt-2 text-3xl">Ext ID {{ comparableState.external_id }}</CardTitle>
              <p class="mt-2 text-sm text-muted-foreground">{{ comparableState.asset_address }} • {{ comparableState.request_number }}</p>
            </div>
            <div class="flex h-24 w-32 items-center justify-center overflow-hidden rounded-2xl border bg-muted/30">
              <img v-if="comparableState.image_url" :src="comparableState.image_url" alt="Comparable" class="h-full w-full object-cover" />
              <ImageIcon v-else class="h-6 w-6 text-muted-foreground" />
            </div>
          </div>
        </CardHeader>
        <CardContent class="space-y-6">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border bg-muted/30 p-4">
              <p class="text-xs uppercase tracking-wide text-muted-foreground">LT / LB</p>
              <p class="mt-2 font-medium">{{ formatArea(comparableState.raw_land_area) }} / {{ formatArea(comparableState.raw_building_area) }}</p>
            </div>
            <div class="rounded-xl border bg-muted/30 p-4">
              <p class="text-xs uppercase tracking-wide text-muted-foreground">Harga</p>
              <p class="mt-2 font-medium">{{ formatCurrency(comparableState.raw_price) }}</p>
            </div>
            <div class="rounded-xl border bg-muted/30 p-4">
              <p class="text-xs uppercase tracking-wide text-muted-foreground">Adjust Harga Tanah</p>
              <p class="mt-2 font-medium">{{ formatPercent(comparableState.total_adjustment_percent ?? 0) }}</p>
            </div>
            <div class="rounded-xl border bg-muted/30 p-4">
              <p class="text-xs uppercase tracking-wide text-muted-foreground">Nilai /m2</p>
              <p class="mt-2 font-medium">{{ formatCurrency(comparableState.adjusted_unit_value) }}</p>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2">
              <label class="text-sm font-medium">Dipakai reviewer</label>
                <Select v-model="form.is_selected">
                  <SelectTrigger>
                    <SelectValue placeholder="Pilih status" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="1">Ya</SelectItem>
                    <SelectItem value="0">Tidak</SelectItem>
                  </SelectContent>
                </Select>
            </div>
            <div class="grid gap-2">
              <label class="text-sm font-medium">Manual rank</label>
              <Input v-model="form.manual_rank" type="number" min="1" max="999" />
            </div>
          </div>

          <div class="flex flex-wrap gap-3">
            <Button :disabled="busy" @click="saveComparable">
              <Save class="mr-2 h-4 w-4" />
              Simpan
            </Button>
            <Button variant="outline" as-child>
              <Link :href="comparableState.adjustment_url">Buka Adjust Harga Tanah</Link>
            </Button>
            <Button v-if="comparableState.asset" variant="ghost" as-child>
              <Link :href="comparableState.asset.detail_url">
                <ArrowLeftRight class="mr-2 h-4 w-4" />
                Kembali ke aset
              </Link>
            </Button>
          </div>

          <Alert v-if="feedback" :class="feedbackTone === 'error' ? 'border-red-200 bg-red-50 text-red-900' : feedbackTone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-200 bg-slate-50 text-slate-900'">
            <Save class="h-4 w-4" />
            <AlertTitle>Comparable Update</AlertTitle>
            <AlertDescription>{{ feedback }}</AlertDescription>
          </Alert>
        </CardContent>
      </Card>

      <div class="space-y-6">
        <Card>
          <CardHeader class="pb-4">
            <CardTitle>Adjustment Tersimpan</CardTitle>
            <CardDescription>Faktor adjustment yang sudah tersimpan untuk comparable ini.</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Faktor</TableHead>
                    <TableHead>Persen</TableHead>
                    <TableHead>Nominal</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="item in comparableState.land_adjustments" :key="item.id">
                    <TableCell>{{ item.factor_name }}</TableCell>
                    <TableCell>{{ formatPercent(item.adjustment_percent ?? 0) }}</TableCell>
                    <TableCell>{{ formatCurrency(item.adjustment_amount) }}</TableCell>
                  </TableRow>
                  <TableRow v-if="!comparableState.land_adjustments?.length">
                    <TableCell :colspan="3" class="text-center text-muted-foreground">Belum ada adjustment tersimpan.</TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-4">
            <div class="flex items-center justify-between gap-3">
              <div>
                <CardTitle>Snapshot Pembanding</CardTitle>
                <CardDescription>Payload pembanding ditampilkan dalam format baca layar untuk audit reviewer.</CardDescription>
              </div>
              <Badge variant="outline">Readable View</Badge>
            </div>
          </CardHeader>
          <CardContent>
            <ComparableSnapshotTree :value="comparableState.snapshot_json || {}" />
          </CardContent>
        </Card>
      </div>
    </div>
  </ReviewerLayout>
</template>
