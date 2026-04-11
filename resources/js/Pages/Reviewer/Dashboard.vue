<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ReviewerLayout from '@/layouts/ReviewerLayout.vue';
import StatusBadge from '@/components/reviewer/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCurrency, formatDateTime, formatPercent } from '@/utils/reviewer';

const props = defineProps({
  stats: { type: Object, default: () => ({}) },
  featuredReview: { type: Object, default: null },
  focusSummary: { type: Object, default: () => ({}) },
  queuePreview: { type: Array, default: () => [] },
  assetPreview: { type: Array, default: () => [] },
  activityPreview: { type: Array, default: () => [] },
});

const workloadItems = [
  { key: 'total_queue', label: 'Total queue aktif' },
  { key: 'ready_review', label: 'Siap dimulai' },
  { key: 'in_progress', label: 'Sedang dikerjakan' },
  { key: 'assets_need_adjustment', label: 'Aset perlu penyesuaian' },
];

const analyticsItems = [
  { key: 'aset_aktif', label: 'Aset aktif' },
  { key: 'aset_sudah_ada_range', label: 'Sudah ada range' },
  { key: 'aset_sudah_nilai_final', label: 'Sudah nilai final' },
  { key: 'selected_comparables', label: 'Comparable dipakai' },
];
</script>

<template>
  <Head title="Reviewer - Dashboard" />

  <ReviewerLayout title="Dashboard Reviewer">
    <div class="space-y-6">
      <section>
        <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Dashboard Reviewer</h1>
        <p class="mt-2 text-sm text-slate-600">
          Buka antrean paling penting, lihat aset yang masih tertahan, dan pantau aktivitas penyesuaian tanpa berpindah-pindah modul.
        </p>
      </section>

      <section class="grid gap-6 xl:grid-cols-[1.35fr_0.9fr]">
        <div class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-white shadow-sm">
          <div class="grid gap-0 xl:grid-cols-[1.2fr_0.8fr]">
            <div class="border-b border-slate-200/80 p-6 xl:border-r xl:border-b-0 xl:p-8">
              <div class="flex flex-wrap items-center gap-2">
                <Badge variant="outline" class="rounded-full px-3 py-1 text-[11px] uppercase tracking-[0.24em] text-slate-600">
                  Fokus Reviewer
                </Badge>
                <StatusBadge v-if="featuredReview?.status" :status="featuredReview.status" />
              </div>

              <div class="mt-5 space-y-3">
                <h2 class="max-w-3xl text-3xl font-semibold leading-tight tracking-tight text-slate-950">
                  {{ featuredReview?.request_number ? `Permohonan ${featuredReview.request_number} paling relevan untuk dibuka sekarang` : 'Belum ada permohonan aktif di area reviewer' }}
                </h2>
                <p class="max-w-2xl text-sm leading-7 text-slate-600">
                  {{
                    featuredReview
                      ? 'Prioritaskan permohonan ini untuk menjaga alur review tetap bergerak. Gunakan detail request untuk membuka aset utama dan lanjut ke adjustment.'
                      : 'Queue reviewer sedang kosong. Saat permohonan baru masuk, sistem akan menempatkannya di area ini.'
                  }}
                </p>
              </div>

              <div v-if="featuredReview" class="mt-6 flex flex-wrap gap-6 text-sm text-slate-600">
                <div>
                  <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Klien</div>
                  <div class="mt-1 font-medium text-slate-950">{{ featuredReview.client_name }}</div>
                </div>
                <div>
                  <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Jumlah Aset</div>
                  <div class="mt-1 font-medium text-slate-950">{{ featuredReview.assets_count }}</div>
                </div>
                <div>
                  <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Masuk Queue</div>
                  <div class="mt-1 font-medium text-slate-950">{{ formatDateTime(featuredReview.requested_at) }}</div>
                </div>
              </div>

              <div class="mt-8 flex flex-wrap gap-3">
                <Button v-if="featuredReview?.detail_url" as-child>
                  <Link :href="featuredReview.detail_url">Buka Detail Review</Link>
                </Button>
                <Button variant="outline" as-child>
                  <Link :href="route('reviewer.reviews.index')">Lihat Semua Queue</Link>
                </Button>
                <Button variant="ghost" class="text-slate-700" as-child>
                  <Link :href="route('reviewer.assets.index')">Buka Aset</Link>
                </Button>
              </div>
            </div>

            <div class="p-6 xl:p-8">
              <div class="space-y-6">
                <div>
                  <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Beban Kerja Saat Ini</p>
                  <div class="mt-4 space-y-4">
                    <div v-for="item in workloadItems" :key="item.key" class="flex items-end justify-between gap-4 border-b border-slate-100 pb-3 last:border-b-0 last:pb-0">
                      <div class="text-sm text-slate-600">{{ item.label }}</div>
                      <div class="text-2xl font-semibold tracking-tight text-slate-950">{{ stats[item.key] ?? 0 }}</div>
                    </div>
                  </div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                  <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Aktivitas Hari Ini</p>
                  <div class="mt-3 flex items-end justify-between gap-4">
                    <div class="text-sm text-slate-600">Comparable terpilih yang disentuh hari ini</div>
                    <div class="text-3xl font-semibold tracking-tight text-slate-950">{{ stats.comparables_touched_today ?? 0 }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-white shadow-sm">
          <div class="border-b border-slate-200/80 px-6 py-5">
            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Analitik Portofolio</p>
            <p class="mt-2 text-sm text-slate-600">Ringkasan progres aset aktif yang sedang berada di workspace reviewer.</p>
          </div>
          <div class="px-6 py-5">
            <div class="space-y-4">
              <div v-for="item in analyticsItems" :key="item.key" class="flex items-end justify-between gap-4 border-b border-slate-100 pb-4 last:border-b-0 last:pb-0">
                <div>
                  <div class="text-sm text-slate-700">{{ item.label }}</div>
                </div>
                <div class="text-3xl font-semibold tracking-tight text-slate-950">{{ focusSummary[item.key] ?? 0 }}</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
        <div class="overflow-hidden rounded-[1.5rem] border border-slate-200/80 bg-white shadow-sm">
          <div class="flex items-center justify-between gap-4 border-b border-slate-200/80 px-6 py-5">
            <div>
              <h2 class="text-lg font-semibold text-slate-950">Queue Permohonan</h2>
              <p class="mt-1 text-sm text-slate-600">Snapshot permohonan terbaru yang aktif di reviewer.</p>
            </div>
            <Button variant="outline" size="sm" as-child>
              <Link :href="route('reviewer.reviews.index')">Buka Queue</Link>
            </Button>
          </div>

          <div class="divide-y divide-slate-100">
            <div v-for="item in queuePreview" :key="item.id" class="flex items-start justify-between gap-4 px-6 py-4">
              <div class="min-w-0">
                <Button variant="link" class="h-auto px-0 text-left font-medium" as-child>
                  <Link :href="item.detail_url">{{ item.request_number }}</Link>
                </Button>
                <p class="mt-1 text-sm text-slate-700">{{ item.client_name }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ item.assets_count }} aset • {{ formatDateTime(item.requested_at) }}</p>
              </div>
              <StatusBadge :status="item.status" />
            </div>

            <div v-if="!queuePreview.length" class="px-6 py-10 text-sm text-slate-500">
              Belum ada permohonan aktif di queue reviewer.
            </div>
          </div>
        </div>

        <div class="overflow-hidden rounded-[1.5rem] border border-slate-200/80 bg-white shadow-sm">
          <div class="flex items-center justify-between gap-4 border-b border-slate-200/80 px-6 py-5">
            <div>
              <h2 class="text-lg font-semibold text-slate-950">Aset Perlu Perhatian</h2>
              <p class="mt-1 text-sm text-slate-600">Aset yang paling sering butuh lanjut ke adjustment atau BTB.</p>
            </div>
            <Button variant="outline" size="sm" as-child>
              <Link :href="route('reviewer.assets.index', { needs_adjustment: 1 })">Buka Aset</Link>
            </Button>
          </div>

          <div class="divide-y divide-slate-100">
            <div v-for="asset in assetPreview" :key="asset.id" class="px-6 py-4">
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <Button variant="link" class="h-auto px-0 text-left font-medium" as-child>
                    <Link :href="asset.detail_url">{{ asset.address }}</Link>
                  </Button>
                  <p class="mt-1 text-sm text-slate-700">{{ asset.request_number }} • {{ asset.asset_type?.label || '-' }}</p>
                </div>
                <StatusBadge :status="asset.request_status" />
              </div>

              <div class="mt-3 flex flex-wrap gap-2">
                <Badge variant="outline">Dipilih: {{ asset.selected_comparables_count }}</Badge>
                <Badge variant="outline">Range: {{ formatCurrency(asset.estimated_value_low) }} - {{ formatCurrency(asset.estimated_value_high) }}</Badge>
              </div>

              <div class="mt-3 flex flex-wrap gap-3">
                <Button variant="link" class="h-auto px-0" as-child>
                  <Link :href="asset.land_adjustment_url || asset.adjustment_url">Adjust Harga Tanah</Link>
                </Button>
                <Button v-if="asset.has_btb && asset.btb_url" variant="link" class="h-auto px-0" as-child>
                  <Link :href="asset.btb_url">BTB Bangunan</Link>
                </Button>
              </div>
            </div>

            <div v-if="!assetPreview.length" class="px-6 py-10 text-sm text-slate-500">
              Belum ada aset yang perlu perhatian khusus.
            </div>
          </div>
        </div>
      </section>

      <section class="overflow-hidden rounded-[1.5rem] border border-slate-200/80 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-4 border-b border-slate-200/80 px-6 py-5">
          <div>
            <h2 class="text-lg font-semibold text-slate-950">Aktivitas Penyesuaian Hari Ini</h2>
            <p class="mt-1 text-sm text-slate-600">Comparable yang terakhir disentuh agar reviewer bisa lanjut ke item berikutnya dengan cepat.</p>
          </div>
          <Button variant="outline" size="sm" as-child>
            <Link :href="route('reviewer.comparables.index', { is_selected: 1 })">Lihat Comparable</Link>
          </Button>
        </div>

        <div class="divide-y divide-slate-100">
          <div v-for="activity in activityPreview" :key="activity.id" class="grid gap-4 px-6 py-4 md:grid-cols-[1.3fr_0.8fr_0.9fr_auto] md:items-start">
            <div>
              <Button variant="link" class="h-auto px-0 text-left font-medium" as-child>
                <Link :href="activity.detail_url">Ext ID {{ activity.external_id }}</Link>
              </Button>
              <p class="mt-1 text-sm text-slate-700">{{ activity.request_number }} • {{ activity.asset_address }}</p>
            </div>
            <div>
              <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Penyesuaian</div>
              <div class="mt-1 text-sm text-slate-950">{{ formatPercent(activity.total_adjustment_percent ?? 0) }}</div>
              <div class="mt-1 text-xs text-slate-500">{{ activity.adjustment_factors }} faktor</div>
            </div>
            <div>
              <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Nilai / m2</div>
              <div class="mt-1 text-sm text-slate-950">{{ formatCurrency(activity.adjusted_unit_value) }}</div>
              <div class="mt-1 text-xs text-slate-500">{{ formatDateTime(activity.updated_at) }}</div>
            </div>
            <div class="flex justify-start md:justify-end">
              <Button variant="link" class="h-auto px-0" as-child>
                <Link :href="activity.adjustment_url">Buka Adjustment</Link>
              </Button>
            </div>
          </div>

          <div v-if="!activityPreview.length" class="px-6 py-10 text-sm text-slate-500">
            Belum ada aktivitas penyesuaian reviewer hari ini.
          </div>
        </div>
      </section>
    </div>
  </ReviewerLayout>
</template>
