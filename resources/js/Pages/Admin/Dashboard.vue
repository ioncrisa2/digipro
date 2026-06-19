<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
  AlertCircle,
  ArrowRight,
  CheckCircle2,
  Clock3,
  CreditCard,
  FileCheck2,
  FileText,
  FileWarning,
  ShieldAlert,
  UserCog,
} from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatCurrency, formatDateTime } from '@/utils/reviewer';

const props = defineProps({
  stats: {
    type: Array,
    default: () => [],
  },
  actionItems: {
    type: Array,
    default: () => [],
  },
  paymentQueue: {
    type: Array,
    default: () => [],
  },
  isSuperAdmin: {
    type: Boolean,
    default: false,
  },
  superAdminWidgets: {
    type: Object,
    default: null,
  },
});

const statsByKey = computed(() => Object.fromEntries((props.stats || []).map((item) => [item.key, item])));

const primaryStats = computed(() => [
  statsByKey.value.submitted,
  statsByKey.value.docs_incomplete,
  statsByKey.value.waiting_offer,
  statsByKey.value.offer_sent,
  statsByKey.value.waiting_signature,
  statsByKey.value.contract_signed,
].filter(Boolean));

const dailyStats = computed(() => [
  statsByKey.value.requests_today,
  statsByKey.value.assets_today,
].filter(Boolean));

const waitingPaymentCount = computed(() => statsByKey.value.contract_signed?.value ?? props.paymentQueue.length);
const pendingActionCount = computed(() => props.actionItems.length);
const exceptionQueue = computed(() => props.superAdminWidgets?.exception_queue ?? []);
const systemHealth = computed(() => props.superAdminWidgets?.system_health ?? []);
const referenceItems = computed(() => props.superAdminWidgets?.reference_completeness?.items ?? []);
const sensitiveChanges = computed(() => props.superAdminWidgets?.sensitive_changes ?? []);
const permissionSummary = computed(() => props.superAdminWidgets?.permission_overview?.summary ?? {});

const adminRequestsHref = computed(() => {
  try {
    return route('admin.appraisal-requests.index');
  } catch (_error) {
    return '/admin/appraisal-requests';
  }
});

const adminPaymentsHref = computed(() => {
  try {
    return route('admin.finance.payments.index');
  } catch (_error) {
    return '/admin/finance/payments';
  }
});

const iconForStat = (key) => {
  switch (key) {
    case 'submitted':
      return FileText;
    case 'docs_incomplete':
      return FileWarning;
    case 'waiting_offer':
      return Clock3;
    case 'offer_sent':
      return CreditCard;
    case 'waiting_signature':
      return FileCheck2;
    case 'contract_signed':
      return CheckCircle2;
    default:
      return AlertCircle;
  }
};

const toneClass = (tone) => {
  switch (tone) {
    case 'success':
      return 'border-emerald-200 bg-emerald-50 text-emerald-800';
    case 'warning':
      return 'border-amber-200 bg-amber-50 text-amber-800';
    case 'primary':
      return 'border-indigo-200 bg-indigo-50 text-indigo-800';
    case 'info':
      return 'border-sky-200 bg-sky-50 text-sky-800';
    case 'danger':
      return 'border-rose-200 bg-rose-50 text-rose-800';
    default:
      return 'border-slate-200 bg-slate-50 text-slate-700';
  }
};

const textToneClass = (tone) => {
  switch (tone) {
    case 'success':
      return 'text-emerald-700';
    case 'warning':
      return 'text-amber-700';
    case 'primary':
      return 'text-indigo-700';
    case 'info':
      return 'text-sky-700';
    case 'danger':
      return 'text-rose-700';
    default:
      return 'text-slate-700';
  }
};

const statusTone = (value) => {
  switch (value) {
    case 'submitted':
      return 'border-sky-200 bg-sky-50 text-sky-700';
    case 'docs_incomplete':
      return 'border-rose-200 bg-rose-50 text-rose-700';
    case 'verified':
      return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    case 'waiting_offer':
    case 'waiting_signature':
    case 'contract_signed':
      return 'border-amber-200 bg-amber-50 text-amber-700';
    case 'offer_sent':
      return 'border-indigo-200 bg-indigo-50 text-indigo-700';
    default:
      return 'border-slate-200 bg-slate-50 text-slate-600';
  }
};

const shortDate = (value) => (value ? formatDateTime(value) : '-');
</script>

<template>
  <Head title="Admin Dashboard" />

  <AdminLayout title="Admin Dashboard">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5">
      <header class="rounded-[18px] border border-slate-200 bg-white px-5 py-5 shadow-xs lg:px-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div class="min-w-0">
            <p class="text-sm font-medium text-slate-500">Admin Workspace</p>
            <h1 class="mt-1 text-3xl font-semibold text-balance text-slate-950">Dashboard Admin</h1>
            <p class="mt-1 max-w-3xl text-sm text-pretty text-slate-600">
              Pantau antrean permohonan, pembayaran, dan tindak lanjut operasional harian.
            </p>
          </div>

          <Link
            :href="adminRequestsHref"
            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition-colors hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/20 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
          >
            Buka Permohonan
            <ArrowRight class="size-4" />
          </Link>
        </div>
      </header>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <article
          v-for="item in primaryStats"
          :key="item.key"
          class="min-h-[104px] rounded-[16px] border border-slate-200 bg-white px-4 py-4 shadow-xs"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="text-xs font-semibold text-slate-500">{{ item.label }}</p>
              <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-950">{{ item.value }}</p>
            </div>
            <component :is="iconForStat(item.key)" class="size-4 shrink-0" :class="textToneClass(item.tone)" />
          </div>
          <p class="mt-2 line-clamp-2 text-xs leading-5 text-slate-500">{{ item.description }}</p>
        </article>
      </section>

      <section class="grid gap-5 xl:grid-cols-12">
        <section class="rounded-[18px] border border-slate-200 bg-white shadow-xs xl:col-span-8">
          <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between lg:px-6">
            <div>
              <h2 class="text-lg font-semibold text-slate-950">Permohonan Perlu Tindakan</h2>
              <p class="mt-1 text-sm text-slate-500">{{ pendingActionCount }} request menunggu keputusan admin.</p>
            </div>
            <Link
              :href="adminRequestsHref"
              class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/15 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
            >
              Lihat Semua
              <ArrowRight class="size-4" />
            </Link>
          </div>

          <div v-if="!actionItems.length" class="flex min-h-[220px] items-center justify-center px-5 py-8">
            <div class="max-w-md text-center">
              <div class="mx-auto flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50">
                <FileText class="size-5 text-slate-500" />
              </div>
              <h3 class="mt-4 text-base font-semibold text-slate-950">Tidak ada request yang butuh tindakan</h3>
              <p class="mt-2 text-sm leading-6 text-pretty text-slate-500">
                Permohonan baru, dokumen kurang, dan request siap penawaran akan muncul di area ini.
              </p>
            </div>
          </div>

          <div v-else class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
              <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                <tr>
                  <th scope="col" class="px-5 py-3 lg:px-6">Request</th>
                  <th scope="col" class="px-5 py-3">Pemohon</th>
                  <th scope="col" class="px-5 py-3">Status</th>
                  <th scope="col" class="px-5 py-3">Aset</th>
                  <th scope="col" class="px-5 py-3 text-right lg:px-6">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200">
                <tr
                  v-for="row in actionItems"
                  :key="row.id"
                  class="transition-colors hover:bg-slate-50"
                >
                  <td class="px-5 py-4 lg:px-6">
                    <p class="font-mono text-xs font-semibold text-slate-950">{{ row.request_number }}</p>
                    <p class="mt-1 line-clamp-1 text-xs text-slate-500">{{ row.client_name }}</p>
                  </td>
                  <td class="min-w-[160px] px-5 py-4 font-medium text-slate-800">{{ row.requester_name }}</td>
                  <td class="whitespace-nowrap px-5 py-4">
                    <span
                      class="inline-flex items-center rounded-md border px-2 py-1 text-xs font-medium"
                      :class="statusTone(row.status_value)"
                    >
                      {{ row.status_label }}
                    </span>
                  </td>
                  <td class="whitespace-nowrap px-5 py-4 tabular-nums text-slate-600">{{ row.assets_count }}</td>
                  <td class="whitespace-nowrap px-5 py-4 text-right lg:px-6">
                    <Link
                      :href="row.show_url"
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
              <h2 class="text-lg font-semibold text-slate-950">Menunggu Pembayaran</h2>
              <p class="mt-1 text-sm text-slate-500">{{ waitingPaymentCount }} kontrak siap diproses.</p>
            </div>

            <div class="p-5">
              <div v-if="!paymentQueue.length" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500">
                Tidak ada antrean pembayaran.
              </div>

              <div v-else class="space-y-3">
                <Link
                  v-for="item in paymentQueue"
                  :key="item.id"
                  :href="item.show_url"
                  class="block rounded-xl border border-slate-200 px-4 py-3 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/15 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
                >
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <p class="font-mono text-xs font-semibold text-slate-950">{{ item.request_number }}</p>
                      <p class="mt-1 line-clamp-1 text-sm text-slate-600">{{ item.requester_name }}</p>
                    </div>
                    <span class="rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">
                      {{ item.offer_validity_days ? `${item.offer_validity_days} hari` : 'Belum diisi' }}
                    </span>
                  </div>
                  <div class="mt-3 flex items-center justify-between gap-3 text-xs text-slate-500">
                    <span class="font-semibold tabular-nums text-slate-800">{{ formatCurrency(item.fee_total) }}</span>
                    <span>{{ shortDate(item.updated_at) }}</span>
                  </div>
                </Link>
              </div>

              <Link
                :href="adminPaymentsHref"
                class="mt-4 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/15 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
              >
                Buka Modul Pembayaran
                <ArrowRight class="size-4" />
              </Link>
            </div>
          </section>

          <section class="rounded-[18px] border border-slate-200 bg-white shadow-xs">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="text-lg font-semibold text-slate-950">Aktivitas Hari Ini</h2>
            </div>
            <div class="grid divide-y divide-slate-200">
              <div
                v-for="item in dailyStats"
                :key="item.key"
                class="flex items-center justify-between gap-4 px-5 py-4"
              >
                <div>
                  <p class="text-sm font-medium text-slate-900">{{ item.label }}</p>
                  <p class="mt-1 text-xs text-slate-500">{{ item.description }}</p>
                </div>
                <p class="text-2xl font-semibold tabular-nums text-slate-950">{{ item.value }}</p>
              </div>
              <div v-if="!dailyStats.length" class="px-5 py-4 text-sm text-slate-500">
                Belum ada aktivitas hari ini.
              </div>
            </div>
          </section>
        </aside>
      </section>

      <section v-if="isSuperAdmin && superAdminWidgets" class="grid gap-5 xl:grid-cols-12">
        <section class="rounded-[18px] border border-slate-200 bg-white shadow-xs xl:col-span-5">
          <div class="border-b border-slate-200 px-5 py-4">
            <div class="flex items-center gap-2">
              <ShieldAlert class="size-4 text-rose-700" />
              <h2 class="text-lg font-semibold text-slate-950">Super Admin Watchlist</h2>
            </div>
            <p class="mt-1 text-sm text-slate-500">
              {{ permissionSummary.roles_with_sensitive_access ?? 0 }} role memiliki akses sensitif.
            </p>
          </div>
          <div class="space-y-3 p-5">
            <Link
              v-for="item in exceptionQueue"
              :key="item.key"
              :href="item.url"
              class="block rounded-xl border px-4 py-3 transition-colors hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/15 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
              :class="toneClass(item.tone)"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-sm font-semibold text-slate-950">{{ item.label }}</p>
                  <p class="mt-1 text-xs text-slate-600">{{ item.description }}</p>
                </div>
                <p class="text-2xl font-semibold tabular-nums text-slate-950">{{ item.value }}</p>
              </div>
            </Link>
            <div v-if="!exceptionQueue.length" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500">
              Tidak ada exception queue aktif.
            </div>
          </div>
        </section>

        <section class="rounded-[18px] border border-slate-200 bg-white shadow-xs xl:col-span-7">
          <div class="border-b border-slate-200 px-5 py-4">
            <div class="flex items-center gap-2">
              <UserCog class="size-4 text-slate-700" />
              <h2 class="text-lg font-semibold text-slate-950">System Snapshot</h2>
            </div>
            <p class="mt-1 text-sm text-slate-500">Kesehatan sistem, referensi, dan perubahan sensitif terbaru.</p>
          </div>

          <div class="grid gap-0 divide-y divide-slate-200 lg:grid-cols-2 lg:divide-x lg:divide-y-0">
            <div class="p-5">
              <h3 class="text-sm font-semibold text-slate-950">Health Indicator</h3>
              <div class="mt-3 space-y-3">
                <Link
                  v-for="item in systemHealth.slice(0, 4)"
                  :key="item.key"
                  :href="item.url"
                  class="flex items-start justify-between gap-3 rounded-xl border border-slate-200 px-3 py-3 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/15 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
                >
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-900">{{ item.label }}</p>
                    <p class="mt-1 line-clamp-1 text-xs text-slate-500">{{ item.description }}</p>
                  </div>
                  <p class="text-xl font-semibold tabular-nums" :class="textToneClass(item.tone)">{{ item.value }}</p>
                </Link>
                <div v-if="!systemHealth.length" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-500">
                  Belum ada indikator sistem.
                </div>
              </div>
            </div>

            <div class="p-5">
              <h3 class="text-sm font-semibold text-slate-950">Referensi & Perubahan</h3>
              <div class="mt-3 space-y-3">
                <Link
                  v-for="item in referenceItems.slice(0, 3)"
                  :key="item.key"
                  :href="item.url"
                  class="flex items-start justify-between gap-3 rounded-xl border border-slate-200 px-3 py-3 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/15 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
                >
                  <div>
                    <p class="text-sm font-medium text-slate-900">{{ item.label }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ item.status_label }}</p>
                  </div>
                  <p class="text-xl font-semibold tabular-nums text-slate-950">{{ item.value }}</p>
                </Link>
                <div v-if="sensitiveChanges.length" class="rounded-xl border border-slate-200 px-3 py-3">
                  <p class="text-xs font-semibold text-slate-500">Perubahan terakhir</p>
                  <p class="mt-1 line-clamp-1 text-sm font-medium text-slate-950">{{ sensitiveChanges[0].title }}</p>
                  <p class="mt-1 text-xs text-slate-500">{{ shortDate(sensitiveChanges[0].changed_at) }}</p>
                </div>
              </div>
            </div>
          </div>
        </section>
      </section>
    </div>
  </AdminLayout>
</template>
