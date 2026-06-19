<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
  ArrowRight,
  CheckCircle2,
  ClipboardList,
  Clock3,
  FileCheck2,
  FileText,
  Ruler,
} from 'lucide-vue-next';
import ReviewerLayout from '@/layouts/ReviewerLayout.vue';
import StatusBadge from '@/components/reviewer/StatusBadge.vue';
import { formatCurrency, formatDateTime, formatPercent } from '@/utils/reviewer';

const props = defineProps({
  stats: { type: Object, default: () => ({}) },
  featuredReview: { type: Object, default: null },
  focusSummary: { type: Object, default: () => ({}) },
  reviewWorkQueues: { type: Array, default: () => [] },
  queuePreview: { type: Array, default: () => [] },
  assetPreview: { type: Array, default: () => [] },
  activityPreview: { type: Array, default: () => [] },
  signingWorkspace: { type: Object, default: null },
});

const reviewQueueHref = computed(() => {
  try {
    return route('reviewer.reviews.index');
  } catch (_error) {
    return '/reviewer/reviews';
  }
});

const assetsHref = computed(() => {
  try {
    return route('reviewer.assets.index');
  } catch (_error) {
    return '/reviewer/assets';
  }
});

const comparableHref = computed(() => {
  try {
    return route('reviewer.comparables.index', { is_selected: 1 });
  } catch (_error) {
    return '/reviewer/comparables?is_selected=1';
  }
});

const headlineStats = computed(() => [
  {
    key: 'total_queue',
    label: 'Queue Aktif',
    value: props.stats.total_queue ?? 0,
    helper: 'Permohonan di reviewer',
    icon: ClipboardList,
    class: 'text-slate-700',
  },
  {
    key: 'ready_review',
    label: 'Siap Review',
    value: props.stats.ready_review ?? 0,
    helper: 'Bisa langsung dibuka',
    icon: FileCheck2,
    class: 'text-sky-700',
  },
  {
    key: 'in_progress',
    label: 'Sedang Review',
    value: props.stats.in_progress ?? 0,
    helper: 'Valuasi berjalan',
    icon: Clock3,
    class: 'text-amber-700',
  },
  {
    key: 'assets_need_adjustment',
    label: 'Perlu Penyesuaian',
    value: props.stats.assets_need_adjustment ?? 0,
    helper: 'Aset tertahan',
    icon: Ruler,
    class: 'text-rose-700',
  },
]);

const portfolioStats = computed(() => [
  {
    key: 'aset_aktif',
    label: 'Aset aktif',
    value: props.focusSummary.aset_aktif ?? 0,
  },
  {
    key: 'aset_sudah_ada_range',
    label: 'Sudah ada range',
    value: props.focusSummary.aset_sudah_ada_range ?? 0,
  },
  {
    key: 'aset_sudah_nilai_final',
    label: 'Nilai final',
    value: props.focusSummary.aset_sudah_nilai_final ?? 0,
  },
  {
    key: 'selected_comparables',
    label: 'Comparable dipakai',
    value: props.focusSummary.selected_comparables ?? 0,
  },
]);

const selectedWorkQueues = computed(() => (props.reviewWorkQueues || []).slice(0, 4));
const todayComparableCount = computed(() => props.stats.comparables_touched_today ?? 0);
</script>

<template>
  <Head title="Reviewer - Dashboard" />

  <ReviewerLayout title="Dashboard Reviewer">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5">
      <header class="rounded-[18px] border border-slate-200 bg-white px-5 py-5 shadow-xs lg:px-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div class="min-w-0">
            <p class="text-sm font-medium text-slate-500">Reviewer Workspace</p>
            <h1 class="mt-1 text-3xl font-semibold text-balance text-slate-950">Dashboard Reviewer</h1>
            <p class="mt-1 max-w-3xl text-sm text-pretty text-slate-600">
              Pantau queue review, aset yang perlu penyesuaian, dan aktivitas comparable hari ini.
            </p>
          </div>

          <Link
            :href="reviewQueueHref"
            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition-colors hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/20 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
          >
            Buka Queue
            <ArrowRight class="size-4" />
          </Link>
        </div>
      </header>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="item in headlineStats"
          :key="item.key"
          class="min-h-[96px] rounded-[16px] border border-slate-200 bg-white px-4 py-4 shadow-xs"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="text-xs font-semibold text-slate-500">{{ item.label }}</p>
              <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-950">{{ item.value }}</p>
            </div>
            <component :is="item.icon" class="size-4 shrink-0" :class="item.class" />
          </div>
          <p class="mt-2 text-xs text-slate-500">{{ item.helper }}</p>
        </article>
      </section>

      <section class="grid gap-5 xl:grid-cols-12">
        <section class="rounded-[18px] border border-slate-200 bg-white shadow-xs xl:col-span-8">
          <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between lg:px-6">
            <div>
              <h2 class="text-lg font-semibold text-slate-950">Queue Permohonan</h2>
              <p class="mt-1 text-sm text-slate-500">Permohonan aktif terbaru di workspace reviewer.</p>
            </div>
            <Link
              :href="reviewQueueHref"
              class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/15 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
            >
              Lihat Semua
              <ArrowRight class="size-4" />
            </Link>
          </div>

          <div v-if="!queuePreview.length" class="flex min-h-[220px] items-center justify-center px-5 py-8">
            <div class="max-w-md text-center">
              <div class="mx-auto flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50">
                <FileText class="size-5 text-slate-500" />
              </div>
              <h3 class="mt-4 text-base font-semibold text-slate-950">Queue reviewer kosong</h3>
              <p class="mt-2 text-sm leading-6 text-pretty text-slate-500">
                Permohonan siap review, sedang review, dan siap preview akan muncul di sini.
              </p>
            </div>
          </div>

          <div v-else class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
              <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                <tr>
                  <th scope="col" class="px-5 py-3 lg:px-6">Request</th>
                  <th scope="col" class="px-5 py-3">Klien</th>
                  <th scope="col" class="px-5 py-3">Status</th>
                  <th scope="col" class="px-5 py-3">Aset</th>
                  <th scope="col" class="px-5 py-3 text-right lg:px-6">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200">
                <tr
                  v-for="item in queuePreview"
                  :key="item.id"
                  class="transition-colors hover:bg-slate-50"
                >
                  <td class="px-5 py-4 lg:px-6">
                    <p class="font-mono text-xs font-semibold text-slate-950">{{ item.request_number }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ formatDateTime(item.requested_at) }}</p>
                  </td>
                  <td class="min-w-[180px] px-5 py-4 font-medium text-slate-800">{{ item.client_name }}</td>
                  <td class="whitespace-nowrap px-5 py-4">
                    <StatusBadge :status="item.status" />
                  </td>
                  <td class="whitespace-nowrap px-5 py-4 tabular-nums text-slate-600">{{ item.assets_count }}</td>
                  <td class="whitespace-nowrap px-5 py-4 text-right lg:px-6">
                    <Link
                      :href="item.detail_url"
                      class="inline-flex min-h-9 items-center justify-center rounded-lg px-3 py-1.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/15 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
                    >
                      Buka
                    </Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <aside class="space-y-5 xl:col-span-4">
          <section class="rounded-[18px] border border-slate-200 bg-white shadow-xs">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="text-lg font-semibold text-slate-950">Prioritas Review</h2>
              <p class="mt-1 text-sm text-slate-500">Request paling relevan untuk dibuka sekarang.</p>
            </div>
            <div class="p-5">
              <div v-if="featuredReview" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p class="font-mono text-xs font-semibold text-slate-950">{{ featuredReview.request_number }}</p>
                    <p class="mt-1 line-clamp-1 text-sm font-medium text-slate-800">{{ featuredReview.client_name }}</p>
                  </div>
                  <StatusBadge :status="featuredReview.status" />
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 text-xs text-slate-500">
                  <div>
                    <p>Aset</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-slate-950">{{ featuredReview.assets_count }}</p>
                  </div>
                  <div>
                    <p>Masuk queue</p>
                    <p class="mt-1 font-medium text-slate-800">{{ formatDateTime(featuredReview.requested_at) }}</p>
                  </div>
                </div>
                <Link
                  :href="featuredReview.detail_url"
                  class="mt-4 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-lg bg-slate-950 px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/20 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
                >
                  Buka Detail Review
                  <ArrowRight class="size-4" />
                </Link>
              </div>

              <p v-else class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500">
                Belum ada permohonan aktif di area reviewer.
              </p>
            </div>
          </section>

          <section class="rounded-[18px] border border-slate-200 bg-white shadow-xs">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="text-lg font-semibold text-slate-950">Portofolio Aset</h2>
            </div>
            <div class="grid divide-y divide-slate-200">
              <div
                v-for="item in portfolioStats"
                :key="item.key"
                class="flex items-center justify-between gap-4 px-5 py-3"
              >
                <p class="text-sm text-slate-700">{{ item.label }}</p>
                <p class="text-2xl font-semibold tabular-nums text-slate-950">{{ item.value }}</p>
              </div>
            </div>
          </section>
        </aside>
      </section>

      <section class="grid gap-5 xl:grid-cols-12">
        <section class="rounded-[18px] border border-slate-200 bg-white shadow-xs xl:col-span-7">
          <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between lg:px-6">
            <div>
              <h2 class="text-lg font-semibold text-slate-950">Aset Perlu Perhatian</h2>
              <p class="mt-1 text-sm text-slate-500">Aset yang perlu lanjut ke adjustment atau BTB.</p>
            </div>
            <Link
              :href="assetsHref"
              class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/15 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
            >
              Buka Aset
              <ArrowRight class="size-4" />
            </Link>
          </div>

          <div v-if="!assetPreview.length" class="px-5 py-8 text-sm text-slate-500 lg:px-6">
            Belum ada aset yang perlu perhatian khusus.
          </div>

          <div v-else class="divide-y divide-slate-200">
            <article
              v-for="asset in assetPreview"
              :key="asset.id"
              class="px-5 py-4 lg:px-6"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <Link
                    :href="asset.detail_url"
                    class="line-clamp-1 text-sm font-semibold text-slate-950 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/15"
                  >
                    {{ asset.address }}
                  </Link>
                  <p class="mt-1 text-xs text-slate-500">
                    {{ asset.request_number }} - {{ asset.asset_type?.label || '-' }}
                  </p>
                </div>
                <StatusBadge :status="asset.request_status" />
              </div>

              <div class="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                  <p class="text-slate-500">Comparable</p>
                  <p class="mt-1 font-semibold tabular-nums text-slate-950">{{ asset.selected_comparables_count }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                  <p class="text-slate-500">Range</p>
                  <p class="mt-1 font-semibold text-slate-950">
                    {{ formatCurrency(asset.estimated_value_low) }} - {{ formatCurrency(asset.estimated_value_high) }}
                  </p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                  <p class="text-slate-500">Nilai final</p>
                  <p class="mt-1 font-semibold text-slate-950">{{ formatCurrency(asset.market_value_final) }}</p>
                </div>
              </div>

              <div class="mt-3 flex flex-wrap gap-3">
                <Link
                  :href="asset.land_adjustment_url || asset.adjustment_url"
                  class="text-sm font-semibold text-slate-700 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/15"
                >
                  Adjust Harga Tanah
                </Link>
                <Link
                  v-if="asset.has_btb && asset.btb_url"
                  :href="asset.btb_url"
                  class="text-sm font-semibold text-slate-700 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/15"
                >
                  BTB Bangunan
                </Link>
              </div>
            </article>
          </div>
        </section>

        <aside class="space-y-5 xl:col-span-5">
          <section class="rounded-[18px] border border-slate-200 bg-white shadow-xs">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="text-lg font-semibold text-slate-950">Antrean Kerja</h2>
              <p class="mt-1 text-sm text-slate-500">Masuk ke tahap review yang tepat.</p>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-2">
              <Link
                v-for="queue in selectedWorkQueues"
                :key="queue.value"
                :href="queue.url"
                class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 transition-colors hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/15 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
              >
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-xs font-semibold text-slate-500">Antrean</p>
                    <p class="mt-1 text-sm font-semibold text-slate-950">{{ queue.label }}</p>
                  </div>
                  <p class="text-2xl font-semibold tabular-nums text-slate-950">{{ queue.count ?? 0 }}</p>
                </div>
                <p class="mt-2 line-clamp-2 text-xs leading-5 text-slate-500">{{ queue.description }}</p>
              </Link>
            </div>
          </section>

          <section
            v-if="signingWorkspace"
            class="rounded-[18px] border border-slate-200 bg-white shadow-xs"
          >
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="text-lg font-semibold text-slate-950">Tanda Tangan Kontrak</h2>
              <p class="mt-1 text-sm text-slate-500">Queue token KEYLA penilai publik.</p>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-2">
              <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4">
                <p class="text-xs font-semibold text-slate-500">Siap Sign</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-950">{{ signingWorkspace.ready_count ?? 0 }}</p>
              </div>
              <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4">
                <p class="text-xs font-semibold text-slate-500">Perlu Diulang</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-950">{{ signingWorkspace.failed_count ?? 0 }}</p>
              </div>
              <Link
                :href="signingWorkspace.queue_url"
                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 sm:col-span-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/15 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
              >
                Buka Queue Sign
                <ArrowRight class="size-4" />
              </Link>
            </div>
          </section>
        </aside>
      </section>

      <section class="rounded-[18px] border border-slate-200 bg-white shadow-xs">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between lg:px-6">
          <div>
            <h2 class="text-lg font-semibold text-slate-950">Aktivitas Penyesuaian Hari Ini</h2>
            <p class="mt-1 text-sm text-slate-500">Comparable terakhir disentuh oleh reviewer hari ini.</p>
          </div>
          <Link
            :href="comparableHref"
            class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/15 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
          >
            Lihat Comparable
            <ArrowRight class="size-4" />
          </Link>
        </div>

        <div v-if="!activityPreview.length" class="px-5 py-8 text-sm text-slate-500 lg:px-6">
          Belum ada aktivitas penyesuaian reviewer hari ini.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
              <tr>
                <th scope="col" class="px-5 py-3 lg:px-6">Comparable</th>
                <th scope="col" class="px-5 py-3">Request / Aset</th>
                <th scope="col" class="px-5 py-3">Penyesuaian</th>
                <th scope="col" class="px-5 py-3">Nilai / m2</th>
                <th scope="col" class="px-5 py-3 text-right lg:px-6">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr
                v-for="activity in activityPreview"
                :key="activity.id"
                class="transition-colors hover:bg-slate-50"
              >
                <td class="px-5 py-4 lg:px-6">
                  <p class="font-mono text-xs font-semibold text-slate-950">Ext ID {{ activity.external_id }}</p>
                  <p class="mt-1 text-xs text-slate-500">{{ formatDateTime(activity.updated_at) }}</p>
                </td>
                <td class="min-w-[240px] px-5 py-4">
                  <p class="font-medium text-slate-900">{{ activity.request_number }}</p>
                  <p class="mt-1 line-clamp-1 text-xs text-slate-500">{{ activity.asset_address }}</p>
                </td>
                <td class="whitespace-nowrap px-5 py-4 text-slate-700">
                  {{ formatPercent(activity.total_adjustment_percent ?? 0) }}
                  <span class="block text-xs text-slate-500">{{ activity.adjustment_factors }} faktor</span>
                </td>
                <td class="whitespace-nowrap px-5 py-4 font-semibold tabular-nums text-slate-900">
                  {{ formatCurrency(activity.adjusted_unit_value) }}
                </td>
                <td class="whitespace-nowrap px-5 py-4 text-right lg:px-6">
                  <Link
                    :href="activity.adjustment_url"
                    class="inline-flex min-h-9 items-center justify-center rounded-lg px-3 py-1.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/15 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
                  >
                    Buka Adjustment
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </ReviewerLayout>
</template>
