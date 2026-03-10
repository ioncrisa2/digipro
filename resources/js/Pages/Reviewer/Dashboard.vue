<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ReviewerLayout from '@/layouts/ReviewerLayout.vue';
import StatusBadge from '@/components/reviewer/StatusBadge.vue';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { BarChart3, ClipboardList, FolderSearch, TrendingUp } from 'lucide-vue-next';
import { formatCurrency, formatDateTime, formatPercent } from '@/utils/reviewer';

const props = defineProps({
  stats: Object,
  queuePreview: Array,
  assetPreview: Array,
  activityPreview: Array,
});

const statCards = [
  { key: 'ready_review', label: 'Siap Review', icon: ClipboardList, tone: 'text-amber-700 bg-amber-100' },
  { key: 'in_progress', label: 'Sedang Review', icon: TrendingUp, tone: 'text-sky-700 bg-sky-100' },
  { key: 'completed', label: 'Selesai Valuasi', icon: BarChart3, tone: 'text-emerald-700 bg-emerald-100' },
  { key: 'assets_need_adjustment', label: 'Aset Butuh Adjustment', icon: FolderSearch, tone: 'text-rose-700 bg-rose-100' },
];
</script>

<template>
  <Head title="Reviewer Dashboard" />

  <ReviewerLayout title="Dashboard">
    <div class="space-y-6">
      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card v-for="card in statCards" :key="card.key">
          <CardContent class="flex items-center justify-between p-5">
            <div>
              <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">{{ card.label }}</p>
              <p class="mt-2 text-4xl font-semibold text-foreground">{{ stats?.[card.key] ?? 0 }}</p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-xl" :class="card.tone">
              <component :is="card.icon" class="h-5 w-5" />
            </div>
          </CardContent>
        </Card>
      </section>

      <section class="grid gap-6 xl:grid-cols-[1.1fr_1fr]">
        <Card>
          <CardHeader class="pb-4">
            <div class="flex items-center justify-between gap-3">
              <div>
                <CardTitle>Review Queue</CardTitle>
                <CardDescription>Snapshot permohonan terbaru yang masuk area reviewer.</CardDescription>
              </div>
              <Button variant="link" class="h-auto px-0" as-child>
                <Link :href="route('reviewer.reviews.index')">Lihat semua</Link>
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            <div class="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Request</TableHead>
                    <TableHead>Klien</TableHead>
                    <TableHead>Aset</TableHead>
                    <TableHead>Status</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="item in queuePreview" :key="item.id">
                    <TableCell>
                      <Button variant="link" class="h-auto px-0 font-medium" as-child>
                        <Link :href="item.detail_url">{{ item.request_number }}</Link>
                      </Button>
                      <p class="mt-1 text-xs text-muted-foreground">{{ formatDateTime(item.requested_at) }}</p>
                    </TableCell>
                    <TableCell>{{ item.client_name }}</TableCell>
                    <TableCell>{{ item.assets_count }}</TableCell>
                    <TableCell><StatusBadge :status="item.status" /></TableCell>
                  </TableRow>
                  <TableRow v-if="!queuePreview?.length">
                    <TableCell :colspan="4" class="text-center text-muted-foreground">Belum ada queue reviewer.</TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>

        <div class="space-y-6">
          <Card>
            <CardHeader class="pb-4">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <CardTitle>Aset Prioritas</CardTitle>
                  <CardDescription>Aset yang siap dibuka untuk adjustment.</CardDescription>
                </div>
                <Button variant="link" class="h-auto px-0" as-child>
                  <Link :href="route('reviewer.assets.index')">Lihat semua</Link>
                </Button>
              </div>
            </CardHeader>
            <CardContent class="space-y-3">
              <div v-for="asset in assetPreview" :key="asset.id" class="rounded-xl border p-4">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <Button variant="link" class="h-auto px-0 font-medium text-left" as-child>
                      <Link :href="asset.detail_url">{{ asset.address }}</Link>
                    </Button>
                    <p class="mt-1 text-xs text-muted-foreground">{{ asset.request_number }} • {{ asset.asset_type?.label }}</p>
                  </div>
                  <StatusBadge :status="asset.request_status" />
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs text-muted-foreground">
                  <Badge variant="outline">Pembanding dipilih: {{ asset.selected_comparables_count }}</Badge>
                  <Badge variant="outline">Range bawah: {{ formatCurrency(asset.estimated_value_low) }}</Badge>
                </div>
              </div>
              <div v-if="!assetPreview?.length" class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                Belum ada aset prioritas.
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader class="pb-4">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <CardTitle>Aktivitas Hari Ini</CardTitle>
                  <CardDescription>Pembanding terpilih yang terakhir disentuh reviewer.</CardDescription>
                </div>
                <Button variant="link" class="h-auto px-0" as-child>
                  <Link :href="route('reviewer.comparables.index')">Comparables</Link>
                </Button>
              </div>
            </CardHeader>
            <CardContent class="space-y-3">
              <div v-for="activity in activityPreview" :key="activity.id" class="rounded-xl border p-4">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <Button variant="link" class="h-auto px-0 font-medium text-left" as-child>
                      <Link :href="activity.detail_url">Ext ID {{ activity.external_id }}</Link>
                    </Button>
                    <p class="mt-1 text-xs text-muted-foreground">{{ activity.request_number }} • {{ activity.asset_address }}</p>
                  </div>
                  <Button variant="outline" size="sm" as-child>
                    <Link :href="activity.adjustment_url">Matrix</Link>
                  </Button>
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs text-muted-foreground">
                  <Badge variant="outline">Adj: {{ formatPercent(activity.total_adjustment_percent ?? 0) }}</Badge>
                  <Badge variant="outline">Nilai/m2: {{ formatCurrency(activity.adjusted_unit_value) }}</Badge>
                  <Badge variant="outline">{{ formatDateTime(activity.updated_at) }}</Badge>
                </div>
              </div>
              <div v-if="!activityPreview?.length" class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                Belum ada aktivitas reviewer hari ini.
              </div>
            </CardContent>
          </Card>
        </div>
      </section>
    </div>
  </ReviewerLayout>
</template>
